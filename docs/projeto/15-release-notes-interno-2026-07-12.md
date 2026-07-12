# Release Notes Interno - Hardening de Seguranca

Data: 2026-07-12
Ambiente alvo: interno (pre-producao/producao)
Status: concluido

## Resumo executivo

Foi concluido o ciclo completo de hardening de seguranca (SEC-001 a SEC-012), cobrindo correcoes de fluxo, controles de autorizacao, protecoes contra XSS/supply chain, validacao de segredos em producao, regressao automatizada e fechamento documental.

## Principais entregas

- Endpoints sensiveis migrados para POST com CSRF e bloqueio de metodo invalido (405).
- RBAC centralizado por rota sensivel e trilha antifraude reforcada.
- Confianca de IP via proxy condicionada a proxies explicitamente confiaveis.
- CSP com nonce por requisicao e remocao de unsafe-inline em script-src.
- SRI aplicado em assets CDN (Bootstrap, Bootstrap Icons e Chart.js).
- Segredos obrigatorios em producao:
  - APP_SECRET (minimo 32 e nao padrao)
  - DB_PASS obrigatorio
  - SMTP_PASS obrigatorio quando SMTP habilitado
  - API_TOKEN obrigatorio quando API_TOKEN_REQUIRED=true
- PHPUnit atualizado para 9.6.35.
- Politica de governanca de dependencias e gate de CI documentada.

## Qualidade e validacao

- Composer audit: sem vulnerabilidades encontradas.
- Testes focados de seguranca: OK (51 testes, 85 assertions).
- Regressao completa: OK (157 testes, 291 assertions).
- Estabilidade de testes reforcada com ajustes de FK e determinismo no cenario de rate-limit.

## Impacto esperado

- Reducao de risco de CSRF, abuso de metodo HTTP e exposicao por autorizacao inconsistente.
- Reducao de risco de XSS e supply chain em frontend.
- Menor chance de incidente por configuracao insegura de segredos em producao.
- Maior confiabilidade da regressao automatizada para futuras releases.

## Riscos residuais e controles

- Risco residual principal: regressao em ciclos futuros sem disciplina de pipeline.
- Controle recomendado: manter gate obrigatorio com composer audit + phpunit em toda release.

## Checklist de publicacao interna

- [x] Validacao tecnica concluida.
- [x] Documentacao de seguranca atualizada.
- [x] Changelog de seguranca publicado.
- [x] Plano de hardening encerrado.

## Referencias

- docs/projeto/11-configuracao-env-externo.md
- docs/projeto/12-plano-hardening-seguranca.md
- docs/projeto/13-governanca-dependencias-ci.md
- docs/projeto/14-changelog-seguranca.md
