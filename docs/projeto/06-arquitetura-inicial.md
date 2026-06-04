# 06 — Arquitetura Inicial

**Versão:** 1.0.0  
**Data:** 2026-06-04

---

## 1. Contexto Técnico

O sistema é uma aplicação web PHP 8 hospedada em servidor próprio (VPS ou on-premises com Laragon em desenvolvimento). Não utiliza frameworks externos — segue padrão mini-MVC procedural com PDO para acesso a dados, sessões nativas PHP para autenticação e tokens CSRF para proteção de formulários.

**Ambiente de desenvolvimento:** Laragon (Apache + PHP 8 + MySQL/MariaDB)  
**Ambiente de produção:** VPS Linux (Apache ou Nginx + PHP-FPM + MySQL)

---

## 2. Estrutura de Diretórios

```
financeiro/
├── index.php                  # Front controller — roteamento central
├── config/
│   └── config.php             # Configurações: BD, sessão, constantes, leitura de .env
├── controllers/               # Lógica de negócio por módulo
│   ├── auth.php
│   ├── clientes.php
│   ├── contas_receber.php
│   ├── contas_pagar.php       # A criar
│   ├── dashboard.php          # A criar
│   ├── relatorios.php         # A criar
│   ├── conciliacao.php        # A criar
│   ├── importacao.php         # A criar
│   ├── categorias.php         # A criar
│   ├── empresas.php
│   ├── fornecedores.php
│   ├── logs.php
│   ├── perfis.php
│   └── usuarios.php
├── models/                    # Acesso a dados (classes estáticas com PDO)
│   ├── ContasReceber.php
│   ├── ContasPagar.php        # A criar
│   ├── Dashboard.php          # A criar
│   └── Conciliacao.php        # A criar
├── views/                     # Templates PHP (sem lógica de negócio, sem queries)
│   ├── layout/
│   │   ├── header.php
│   │   ├── sidebar.php
│   │   └── footer.php
│   ├── auth/
│   ├── clientes/
│   ├── contas_receber/
│   ├── contas_pagar/          # A criar
│   ├── dashboard/             # A criar
│   ├── relatorios/            # A criar
│   ├── conciliacao/           # A criar
│   └── importacao/            # A criar
├── helpers/
│   ├── database.php           # Singleton PDO
│   ├── functions.php          # Funções utilitárias (CSRF, sessão, validações)
│   └── logger.php             # Wrapper para log_acoes
├── jobs/                      # Scripts de CLI/cron — A criar
│   ├── atualizar_status.php   # Atualiza contas vencidas
│   └── regua_cobranca.php     # Disparos automáticos da régua
├── public/
│   ├── css/style.css
│   ├── js/app.js
│   └── uploads/
│       ├── logos/
│       └── comprovantes/      # A criar (isolado por empresa_id/ano/mes)
├── lang/
│   ├── pt-br.php
│   └── en.php
├── database/
│   └── schema.sql
└── tests/
    ├── Unit/
    └── Integration/
```

---

## 3. Componentes Principais

### 3.1 Front Controller (`index.php`)

Ponto de entrada único. Lê os parâmetros `c` (controller) e `a` (action) da URL, valida a sessão, verifica permissão e inclui o controller correspondente.

```
GET /financeiro/?c=contas_receber&a=index
GET /financeiro/?c=contas_pagar&a=new
POST /financeiro/?c=contas_pagar&a=save
```

### 3.2 Roteamento de Permissões

Cada combinação `(controller, action)` mapeia para `(modulo, acao)` em `perfil_permissoes`. O mapeamento é definido em `config/config.php` na constante `PERMISSION_MAP`.

### 3.3 Models (Acesso a Dados)

Classes PHP estáticas que encapsulam todas as queries PDO. Nenhuma query é executada em controllers ou views.

Padrão de método:
```php
public static function listar(array $filtros): array
public static function buscar(string $id, string $empresaId): ?array
public static function criar(array $dados): string  // retorna UUID
public static function atualizar(string $id, array $dados): void
public static function excluir(string $id, string $empresaId): void
public static function validar(array $dados): array  // retorna erros
```

### 3.4 Jobs (Cron)

Scripts PHP de linha de comando executados via cron no servidor.

| Job | Frequência | Responsabilidade |
|-----|-----------|-----------------|
| `jobs/atualizar_status.php` | Diário, 00:05 | Atualiza parcelas vencidas; atualiza contas-mãe para `vencida` |
| `jobs/regua_cobranca.php` | Diário, 08:00 | Avalia régua por empresa; dispara e-mails e WhatsApp |

Configuração de cron (exemplo):
```
5 0 * * * php /var/www/financeiro/jobs/atualizar_status.php >> /var/log/financeiro/atualizar_status.log 2>&1
0 8 * * * php /var/www/financeiro/jobs/regua_cobranca.php >> /var/log/financeiro/regua_cobranca.log 2>&1
```

---

## 4. Banco de Dados

**SGBD:** MySQL 8 / MariaDB 10.6+  
**Charset:** utf8mb4 / utf8mb4_unicode_ci  
**Schema:** `financeiro_erp`

### 4.1 Tabelas existentes

| Tabela | Propósito |
|--------|----------|
| `empresas` | Tenants do SaaS |
| `empresa_contatos` | Contatos da empresa |
| `perfis` | Perfis de acesso |
| `perfil_permissoes` | Permissões granulares por módulo/ação |
| `usuarios` | Usuários do sistema |
| `usuario_empresa` | Vínculo usuário ↔ empresa ↔ perfil |
| `log_acoes` | Auditoria de todas as ações críticas |
| `clientes` | Clientes por empresa (tenant) |
| `fornecedores` | Fornecedores por empresa (tenant) |
| `contas_receber` | Cabeçalho de contas a receber |
| `contas_receber_parcelas` | Parcelas de contas a receber |
| `contas_receber_historico` | Histórico de eventos da régua e baixas |
| `contas_receber_regua` | Configuração da régua de cobrança por empresa |

### 4.2 Tabelas a criar

| Tabela | Propósito |
|--------|----------|
| `contas_pagar` | Cabeçalho de contas a pagar |
| `contas_pagar_parcelas` | Parcelas de contas a pagar |
| `contas_pagar_historico` | Histórico de eventos (aprovação, baixa) |
| `categorias` | Categorias financeiras por empresa |
| `conciliacao_bancaria` | Importações OFX e vínculos com parcelas |
| `conciliacao_transacoes` | Transações individuais do extrato OFX |
| `usuario_termos` | Registro de aceite do termo de uso |

### 4.3 Padrão de chaves

- **PK:** `CHAR(36)` UUID gerado via `sprintf('%04x%04x-...', mt_rand(0, 0xffff), ...)`
- **Código amigável:** `VARCHAR(20)` com padrão `PREFIXO-XXXX` (ex.: CP-0001)
- **Tenant:** coluna `empresa_id CHAR(36)` em todas as tabelas de dados
- **Timestamps:** `created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP`, `updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP`

---

## 5. Fluxo Multiempresa (Sequência de Requisição)

```
Browser → index.php
           │
           ├─ Verifica sessão ativa → 401/redirect login
           │
           ├─ Verifica empresa_id na sessão → redirect seleção
           │
           ├─ Verifica permissão (modulo+acao) → 403
           │
           ├─ Inclui controller → valida CSRF (POST) → executa ação
           │                       │
           │                       ├─ Model (query com empresa_id obrigatório)
           │                       ├─ Logger (log_acoes)
           │                       └─ Flash message + redirect
           │
           └─ Inclui view → renderiza HTML
```

---

## 6. Integrações Externas

### 6.1 E-mail (SMTP) — PHPMailer

| Parâmetro | Config |
|-----------|--------|
| Biblioteca | `PHPMailer/PHPMailer` (Composer) |
| Configuração | `.env`: `MAIL_HOST`, `MAIL_PORT`, `MAIL_USER`, `MAIL_PASS`, `MAIL_FROM`, `MAIL_FROM_NAME` |
| Uso | Régua de cobrança, reset de senha, notificações |
| Tratamento de erro | Exception capturada; falha registrada no histórico; não interrompe o job |

### 6.2 WhatsApp — API Externa (configurável)

| Parâmetro | Config |
|-----------|--------|
| Provedor configurável | Z-API, WPP Connect, Twilio WhatsApp Business |
| Configuração | `.env` por empresa: `WA_API_URL`, `WA_API_TOKEN`, `WA_INSTANCE_ID` |
| Protocolo | HTTP POST com JSON (endpoint e payload específicos por provedor) |
| Tratamento de erro | Timeout de 10s; retry 1x; falha registrada no histórico |

### 6.3 OFX — Parser de Extrato Bancário

| Parâmetro | Config |
|-----------|--------|
| Biblioteca | Parser OFX nativo (sem dependência externa): leitura de XML/SGML do padrão OFX 1.x e 2.x |
| Campos lidos | `DTPOSTED`, `TRNAMT`, `NAME`, `MEMO`, `FITID` |
| Armazenamento | Apenas em banco após parse; arquivo OFX descartado |

---

## 7. Decisões Arquiteturais

| ID | Decisão | Alternativa considerada | Motivo |
|----|---------|------------------------|--------|
| DA-01 | Mini-MVC procedural sem framework | Laravel / Slim | Stack definida pelo cliente; sem overhead de framework; time de 1 dev |
| DA-02 | UUID v4 como PK (CHAR 36) | AUTO_INCREMENT INT | Evita sequenciais previsíveis; compatível com multitenancy e importação |
| DA-03 | Models como classes estáticas | Repository pattern com injeção | Consistência com código existente; nível de abstração adequado ao porte |
| DA-04 | Jobs PHP CLI via cron | Filas (Redis/RabbitMQ) | Sem infraestrutura de filas no VPS; volume não justifica |
| DA-05 | OFX parseado nativamente | Biblioteca Phynance/OFX | Zero dependência extra; formato OFX é bem documentado e estável |
| DA-06 | Isolamento de tenant via WHERE empresa_id | Schema separado por tenant | Operação mais simples; migrações únicas; volume não justifica schemas separados |
| DA-07 | Comprovantes acessíveis via controller | Diretório público | Impede acesso direto a arquivos sem autenticação (RNF-008) |
