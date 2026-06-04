---
name: sql-database
description: 'Modelagem, migrations, queries e otimização de banco de dados relacional MySQL/MariaDB. Use para: criar ou revisar schema, escrever migrations, otimizar queries lentas, definir índices, relacionamentos, constraints, normalização, backup e boas práticas de PDO em PHP.'
argument-hint: 'Descreva o que precisa (ex: "modelar tabela de pedidos", "otimizar query de relatório", "criar migration de usuários")'
---

# SQL — MySQL / MariaDB

## Convenções de nomenclatura

- Tabelas: `snake_case` no plural (`usuarios`, `pedidos`, `itens_pedido`)
- Colunas: `snake_case` (`criado_em`, `atualizado_em`, `usuario_id`)
- PKs: `id` (AUTO_INCREMENT); FKs: `<tabela_singular>_id` (`usuario_id`)
- Timestamps padrão: `criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP`, `atualizado_em DATETIME ... ON UPDATE CURRENT_TIMESTAMP`
- Índices: `idx_<tabela>_<coluna>` (`idx_usuarios_email`)

## Criação de tabela — padrão

```sql
CREATE TABLE usuarios (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome        VARCHAR(100) NOT NULL,
    email       VARCHAR(255) NOT NULL,
    senha       VARCHAR(255) NOT NULL,
    role        ENUM('admin','usuario') NOT NULL DEFAULT 'usuario',
    ativo       TINYINT(1) NOT NULL DEFAULT 1,
    criado_em   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY  idx_usuarios_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Migrations

- Um arquivo por migration: `YYYY_MM_DD_HHMMSS_descricao.sql`
- Sempre incluir `UP` e `DOWN` (rollback)
- Nunca alterar migration já executada em produção — criar nova
- Controlar execução em tabela `migrations (arquivo, executado_em)`

```sql
-- UP
ALTER TABLE usuarios ADD COLUMN telefone VARCHAR(20) NULL AFTER email;
CREATE INDEX idx_usuarios_telefone ON usuarios (telefone);

-- DOWN
DROP INDEX idx_usuarios_telefone ON usuarios;
ALTER TABLE usuarios DROP COLUMN telefone;
```

## Índices — quando criar

| Situação | Tipo |
|----------|------|
| Coluna em `WHERE` com alta seletividade | `INDEX` simples |
| Coluna usada em `JOIN` | `INDEX` na FK |
| Busca por múltiplas colunas juntas | `INDEX` composto (ordem importa) |
| Coluna com unicidade de negócio | `UNIQUE KEY` |
| Busca full-text | `FULLTEXT` |

- Evitar índice em colunas com baixa cardinalidade (`ativo`, `role`)
- Índice composto: colocar coluna mais seletiva primeiro
- `EXPLAIN SELECT ...` antes de criar índice em produção

## Queries seguras com PDO

```php
// Listagem com filtro e paginação
$stmt = $pdo->prepare("
    SELECT id, nome, email, criado_em
    FROM usuarios
    WHERE ativo = 1 AND role = :role
    ORDER BY criado_em DESC
    LIMIT :limite OFFSET :offset
");
$stmt->bindValue(':role',   $role,   PDO::PARAM_STR);
$stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
```

## Transações

```php
try {
    $pdo->beginTransaction();
    // múltiplos writes
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
```

## Normalização mínima

- **1NF:** sem grupos repetidos; cada célula tem valor atômico
- **2NF:** sem dependência parcial de PK composta
- **3NF:** sem dependência transitiva (coluna depende apenas da PK)
- Desnormalizar **somente** com justificativa de performance documentada

## Relacionamentos

```sql
-- FK com comportamento explícito
FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
    ON DELETE RESTRICT   -- nunca deletar silenciosamente
    ON UPDATE CASCADE
```

- Preferir `ON DELETE RESTRICT` (força integridade); usar `CASCADE` com cautela
- Soft delete: coluna `deletado_em DATETIME NULL`; nunca deletar dados de negócio

## Performance

- `EXPLAIN` toda query de relatório antes de ir para produção
- Evitar `SELECT *`; listar colunas necessárias
- Evitar funções em colunas indexadas no `WHERE`: `WHERE YEAR(criado_em) = 2024` → lento; usar range
- Paginação com `LIMIT/OFFSET` tem degradação em tabelas grandes — usar keyset pagination para grandes volumes
- `COUNT(*)` é eficiente; `COUNT(coluna)` ignora NULLs — escolher conscientemente

## Backup

- Dump diário: `mysqldump --single-transaction --routines --triggers`
- Testar restore mensalmente em ambiente isolado
- Retenção: 7 dias diários + 4 semanas mensais
- Nunca usar banco de produção para testes ou desenvolvimento
