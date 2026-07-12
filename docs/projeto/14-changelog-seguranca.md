# 14 - Changelog de Seguranca

## 2026-07-12

### Escopo consolidado
- Fase 1: correcoes criticas de fluxo (GET para POST, CSRF e metodo HTTP).
- Fase 2: autorizacao centralizada (RBAC), antifraude e confianca de IP por proxy.
- Fase 3: hardening de plataforma e supply chain (CSP com nonce, SRI, segredos em producao, update de dependencia critica).
- Fase 4: regressao automatizada e fechamento documental.

### Mudancas implementadas
- Endpoints sensiveis migrados para POST com validacao CSRF e bloqueio de metodo invalido.
- Autorizacao por rota sensivel centralizada e trilha antifraude ampliada.
- Confianca de headers de proxy condicionada a lista explicita de proxies confiaveis.
- CSP atualizada para nonce por requisicao em scripts, com remocao de unsafe-inline para script-src.
- Assets CDN protegidos com integrity e crossorigin (SRI).
- Segredos obrigatorios em producao validados na inicializacao:
  - APP_SECRET (minimo 32, sem valor padrao)
  - DB_PASS obrigatorio
  - SMTP_PASS obrigatorio quando SMTP estiver habilitado
  - API_TOKEN obrigatorio quando API_TOKEN_REQUIRED=true
- PHPUnit atualizado para versao segura (9.6.35).
- Politica de governanca de dependencias e gate de CI registrada.

### Estabilizacao de testes
- Ajustada recriacao de tabelas em testes de contas para evitar erro de chave estrangeira durante teardown/setup.
- Ajustados testes de rate-limit para comportamento deterministico com reset de headers de proxy.

### Evidencias de validacao
- Composer audit: sem vulnerabilidades encontradas.
- Testes focados de seguranca: OK (51 testes, 85 assertions).
- Suite completa: OK (157 testes, 291 assertions).

### Documentos relacionados
- docs/projeto/11-configuracao-env-externo.md
- docs/projeto/12-plano-hardening-seguranca.md
- docs/projeto/13-governanca-dependencias-ci.md
