---
name: code-review
description: 'Revisar código de projetos web PHP. Use para: checklist de revisão, identificar problemas de segurança, legibilidade, performance, cobertura de testes, padrões PSR-12, SQL seguro, tratamento de erros, duplicação de código e débito técnico.'
argument-hint: 'Cole o código ou descreva o que revisar (ex: "revisar este controller", "checar segurança deste formulário")'
---

# Code Review — PHP Web

Atue como revisor experiente. Seja direto, construtivo e priorize por impacto. Para cada problema encontrado: **localizar** (arquivo/linha), **classificar** (Bloqueante/Importante/Sugestão) e **propor** solução concreta.

> Para análise aprofundada de vulnerabilidades (XSS, CSRF, injeção, headers, LGPD), carregar também a skill [web-security](../web-security/SKILL.md).

## Classificação

| Nível | Critério | Ação |
|-------|----------|------|
| 🔴 **Bloqueante** | Segurança, bug crítico, dado perdido | Não aprovar; corrigir antes do merge |
| 🟡 **Importante** | Lógica incorreta, sem teste, viola padrão | Solicitar correção |
| 🟢 **Sugestão** | Legibilidade, refactor, performance menor | Comentar; autor decide |

---

## Checklist de segurança (Bloqueante se falhar)

- [ ] Toda entrada de `GET/POST/COOKIE` validada antes do uso
- [ ] Saída HTML escapada com `htmlspecialchars(ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8')`
- [ ] PDO + prepared statements em 100% das queries (sem concatenação)
- [ ] CSRF token presente e validado em todo POST que altera estado
- [ ] Senhas com `password_hash` (bcrypt ≥ 12); nunca MD5/SHA1
- [ ] Sessão regenerada após login (`session_regenerate_id(true)`)
- [ ] Redirects apenas para whitelist de paths internos
- [ ] Upload: MIME real, nome gerado, fora do webroot
- [ ] Sem `eval`, `exec`, `system`, `extract($_POST)`, `$$var`
- [ ] Sem credencial ou chave hardcoded
- [ ] Sem `@` suprimindo erros

## Checklist de qualidade (Importante se falhar)

- [ ] Funções com responsabilidade única e nome descritivo
- [ ] Sem lógica de negócio misturada com HTML
- [ ] Sem código morto ou comentado sem explicação
- [ ] Tratamento de exceção adequado (sem `catch` vazio ou genérico demais)
- [ ] Queries de listagem com `LIMIT` explícito
- [ ] Sem `SELECT *`; colunas listadas explicitamente
- [ ] Testes existem para o comportamento alterado
- [ ] Constantes nomeadas em maiúsculo (sem magic numbers/strings)
- [ ] `declare(strict_types=1)` presente
- [ ] Tipagem de parâmetros e retorno declarada

## Checklist de manutenibilidade (Sugestão)

- [ ] Comentários explicam o **porquê**, não o **o quê**
- [ ] Sem duplicação de lógica (DRY)
- [ ] Nomes de variáveis autoexplicativos
- [ ] Arquivo não ultrapassa ~200 linhas (considerar extração)
- [ ] Função não ultrapassa ~30 linhas
- [ ] Dependências injetadas (não instanciadas dentro da função)

---

## Padrões de problemas comuns

### 🔴 SQL por concatenação
```php
// ❌
$sql = "SELECT * FROM usuarios WHERE email = '$email'";

// ✅
$stmt = $pdo->prepare("SELECT id, nome FROM usuarios WHERE email = :email LIMIT 1");
$stmt->execute([':email' => $email]);
```

### 🔴 Saída sem escape
```php
// ❌
echo "Olá, " . $_GET['nome'];

// ✅
echo "Olá, " . htmlspecialchars($_GET['nome'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
```

### 🔴 Redirect aberto
```php
// ❌
header("Location: " . $_GET['redirect']);

// ✅
$permitidos = ['/dashboard', '/perfil'];
$dest = in_array($_GET['redirect'] ?? '', $permitidos, true) ? $_GET['redirect'] : '/dashboard';
header("Location: $dest");
```

### 🟡 Catch silencioso
```php
// ❌
try { $pdo->exec($sql); } catch (Exception $e) {}

// ✅
try { $pdo->exec($sql); } catch (PDOException $e) {
    logar_erro($e->getMessage());
    throw new RuntimeException('Erro ao executar operação.', 0, $e);
}
```

### 🟡 Lógica no template
```php
// ❌ (em lista.php)
<?php $usuarios = $pdo->query("SELECT * FROM usuarios")->fetchAll(); ?>

// ✅ — processar no controller, passar dados prontos para a view
```

---

## Formato do feedback

```
**[BLOQUEANTE] Injeção SQL — arquivo: modulos/usuarios/model.php, linha 42**
A query concatena `$_POST['email']` diretamente na string SQL.
→ Substituir por prepared statement com `:email` como parâmetro.
```

---

## Relatório de revisão

Ao concluir a análise, **sempre** gerar um relatório e salvá-lo em `docs/seguranca/`.

### Nome do arquivo

```
docs/seguranca/review-YYYY-MM-DD-<escopo>.md
```

Exemplos: `review-2026-06-04-auth.md`, `review-2026-06-04-modulo-usuarios.md`

### Template do relatório

```markdown
# Relatório de Code Review — <escopo>

**Data:** YYYY-MM-DD
**Arquivos analisados:** lista dos arquivos revisados
**Revisor:** GitHub Copilot

---

## Resumo executivo

| Nível | Quantidade |
|-------|-----------|
| 🔴 Bloqueante | N |
| 🟡 Importante | N |
| 🟢 Sugestão | N |

**Resultado:** APROVADO / REPROVADO (reprovado se houver qualquer Bloqueante)

---

## 🔴 Bloqueantes

### [B1] Título do problema
- **Arquivo:** caminho/arquivo.php, linha X
- **Evidência:** trecho do código problemático
- **Impacto:** o que pode ser explorado ou quebrado
- **Correção:** solução concreta com código corrigido

---

## 🟡 Importantes

### [I1] Título do problema
- **Arquivo:** caminho/arquivo.php, linha X
- **Evidência:** trecho do código
- **Correção:** solução sugerida

---

## 🟢 Sugestões

- **[S1]** arquivo.php linha X — descrição breve da melhoria

---

## Checklist de segurança

- [ ] Prepared statements em 100% das queries
- [ ] Saída HTML escapada por contexto
- [ ] CSRF validado em POSTs de alteração de estado
- [ ] Sessão segura (flags, timeout, regeneração)
- [ ] Sem credenciais hardcoded
- [ ] Redirects com whitelist

---

## Próximos passos

1. Corrigir todos os Bloqueantes antes do merge
2. Abrir issues para os Importantes: #XX
3. Sugestões: avaliar na próxima sprint
```
