# 11 - Configuracao Externa de Ambiente

## Objetivo
Centralizar parametros sensiveis e gerais em arquivo externo para evitar segredo no codigo-fonte.

## Local do arquivo
- `C:/laragon/financeiro_param/.env`

## Ordem de carregamento
1. Variaveis ja existentes no ambiente do servidor.
2. Arquivo externo `C:/laragon/financeiro_param/.env`.
3. Arquivo local do projeto `/.env` (fallback).

## Regra para credenciais de banco
- `DB_USER` e `DB_PASS` nao sao definidos como constantes em `config/config.php`.
- Essas credenciais sao lidas diretamente por `env('DB_USER')` e `env('DB_PASS')` nos pontos de conexao PDO.

## Parametros migrados
- Banco de dados: `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET`.
- Aplicacao: `APP_NAME`, `APP_VERSION`, `APP_DEBUG`, `APP_URL`, `APP_LANG`, `APP_TIMEZONE`.
- Seguranca: `BCRYPT_COST`, `SESSION_TIMEOUT`, `APP_SECRET`, `API_TOKEN`.
- E-mail: `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`, `SMTP_ENCRYPTION`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME`.
- API externa: `VIACEP_API_URL`.
- Uploads: `UPLOAD_STORAGE_PATH`, `UPLOAD_LOGO_MAX_BYTES`, `UPLOAD_COMPROVANTE_MAX_BYTES`.
- Uploads avançado: `UPLOAD_RATE_LIMIT_WINDOW_SECONDS`, `UPLOAD_RATE_LIMIT_MAX_ATTEMPTS`, `UPLOAD_AV_SCAN_ENABLED`, `UPLOAD_AV_SCAN_COMMAND`, `UPLOAD_AV_FAIL_OPEN`, `UPLOAD_QUARANTINE_PATH`.

## Observacoes
- Mantenha este arquivo fora do versionamento.
- Defina valores reais para os campos de segredo antes de producao.
- O diretório de uploads seguro deve existir fora do webroot: `C:/laragon/financeiro_storage`.
- Logos e comprovantes passam a ser gravados em storage externo e servidos por endpoints controlados.
- Quarentena e scan antivírus podem ser ativados por ambiente com `UPLOAD_AV_SCAN_ENABLED=true` e comando em `UPLOAD_AV_SCAN_COMMAND`.
