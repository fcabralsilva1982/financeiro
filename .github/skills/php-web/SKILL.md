---
name: php-web
description: 'Gerar, revisar ou auditar código PHP para sistema web estruturados (mini MVC procedural, MySQL/MariaDB). Use para: criar arquivos PHP seguros, revisar segurança, gerar estrutura de projeto, implementar autenticação, CSRF, upload seguro, PDO, e-mail com PHPMailer, logs de auditoria. Inclui checklist de auditoria de segurança.'
argument-hint: 'Descreva o que precisa gerar ou revisar (ex: "tela de login com rate limiting")'
---

# PHP — Sistema Web Estruturado

PHP procedural (mini MVC sem framework), MySQL/MariaDB, auditado por analista de segurança.

## Estrutura

```
projeto/
├── index.php / .htaccess
├── core/       router, controller, view, db
├── config/     config.php
├── modulos/    {modulo}/controller, model, routes, views/
└── public/     assets/ (único dir exposto)
/uploads/ e /logs/ fora do webroot
```

## Código

- PSR-12, `declare(strict_types=1)`, funções curtas e descritivas
- Um arquivo por responsabilidade; separar lógica de HTML
- Constantes em maiúsculo (`DB_HOST`, `BASE_URL`)
- PHP 8.1+: tipagem forte, `enum`, `readonly`, `match`, `?->`, named args, PHPDoc PHPStan/Psalm
- Composição > herança; injeção de dependência; exceções explícitas (nunca suprimir com `@`)
- Comentários entonados, explicando o "porquê" e não o "o quê"; evitar comentários óbvios; manter código autoexplicativo
- Conversar e ensinar sobre o que está sendo feito enquanto codifica
- Referenciar número de linha e nome do arquivo ao mencionar código

## Banco de dados

- PDO + prepared statements em 100% das queries; DSN com `charset=utf8mb4`
- `ERRMODE_EXCEPTION`, `FETCH_ASSOC`; sem `SELECT *`; `LIMIT` obrigatório em listagens
- Transações para múltiplos writes; erros de banco apenas em log interno

## Segurança

**Entrada/saída:** validar tipo, formato e tamanho de todo `GET/POST/COOKIE`; escapar por contexto: HTML → `htmlspecialchars($v, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8')`, SQL → prepared statements; não confiar em `HTTP_*`.

**Autenticação/sessão:** `password_hash` (bcrypt custo ≥ 12); `session_regenerate_id(true)` após login; cookies `HttpOnly, Secure, SameSite=Strict`; timeout 30 min; mensagem genérica "Credenciais inválidas".

**Rate limiting:** bloquear por IP+conta após 5 falhas (login, reset, POST público); bloqueio temporário de 15 min.

**CSRF:** token por sessão (`bin2hex(random_bytes(32))`), validar via `hash_equals` em todo POST que altera estado.

**Tokens de link:** `random_bytes`; armazenar `hash('sha256', $token)`; TTL ≤ 1h; uso único; invalidar anteriores.

**Redirect:** apenas whitelist de paths internos.

**Upload:** MIME real via `finfo_file`; nome aleatório; fora do webroot; registrar metadados em banco.

**Controle de acesso:** verificar no início de cada script protegido; negar por padrão; separar área admin por `role`.

**Headers:** `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `CSP: default-src 'self'`, `HSTS`.

**CORS:** política explícita; nunca `*` em endpoint autenticado.

**.htaccess:** `Options -Indexes`; bloquear `.env,.log,.sql,.json,.lock,.md`; bloquear `/config`,`/logs`,`/uploads`.

## E-mail

PHPMailer; credenciais SMTP no `.env`; validar com `filter_var`; escapar HTML dinâmico; rate limit em envios sensíveis.

## Logs

`display_errors=Off`, `log_errors=On`; nunca expor stack trace/SQL ao usuário; log técnico separado do log de auditoria (logins, ações admin, bloqueios, uploads, mudanças de permissão).

## Dependências e ambiente

- Composer; commitar `composer.lock`; `composer audit` antes de deploy
- Timezone `America/Sao_Paulo`; `mb_internal_encoding('UTF-8')`
- `.env` fora do webroot e no `.gitignore`; commitar só `.env.example`

## Nunca gerar

`eval/exec/system/passthru` com input do usuário · `extract($_POST)` · SQL concatenado · segredos hardcoded · `@` para suprimir erro · `md5/sha1` para senha/token · redirect sem whitelist · mensagem que enumere usuários

## Checklist de auditoria

- [ ] Prepared statements + `LIMIT` em listagens
- [ ] Validação de entrada e escape de saída por contexto
- [ ] CSRF em todo POST de alteração de estado
- [ ] Sessão segura (flags, timeout, regeneração)
- [ ] Rate limiting em login, reset e POSTs públicos
- [ ] Tokens com hash + TTL ≤ 1h + uso único
- [ ] Headers de segurança e CORS restrito
- [ ] Upload: MIME real, nome aleatório, fora do webroot
- [ ] Logs técnico e auditoria separados
- [ ] `.env` fora do repo e do webroot
- [ ] `composer audit` limpo
