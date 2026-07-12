# 12 - Plano de Hardening de Seguranca

## Objetivo
Implementar as correcoes de seguranca priorizadas para reduzir risco de vazamento de dados, invasao e fraude operacional, com rastreabilidade de andamento, testes e atualizacao de documentacao.

## Escopo
Este plano cobre:
- Correcao de acoes sensiveis expostas por GET
- Cobertura de CSRF em operacoes de alteracao
- Endurecimento de autorizacao (RBAC)
- Hardening de sessao, headers e segredos
- Atualizacao de dependencias vulneraveis
- Validacao por testes automatizados e revisao documental

## Regras de execucao
- Nenhuma tarefa sera considerada concluida sem teste automatizado.
- Nenhuma mudanca sera considerada pronta sem atualizar documentacao afetada.
- Todo ajuste de seguranca deve ter evidencias de validacao (teste, log ou checklist tecnico).

## Status geral
- [x] Fase 1 - Correcoes criticas de fluxo (GET/POST + CSRF)
- [x] Fase 2 - Autorizacao e antifraude
- [ ] Fase 3 - Hardening de plataforma e supply chain
- [ ] Fase 4 - Fechamento, validacao final e documentacao

---

## Fase 1 - Correcoes criticas de fluxo (prioridade maxima)

### SEC-001 Migrar acoes sensiveis de GET para POST
- [x] Implementar
- [x] Testes automatizados para guardas de requisicao (POST/CSRF)
- [x] Atualizar views para uso de formulario POST com csrf_field()
- [x] Atualizar documentacao funcional dos modulos impactados

Escopo minimo desta tarefa:
- auth: switch_empresa
- categorias: delete
- clientes: block, unblock, delete, delete_documento
- fornecedores: block, unblock, delete, delete_documento
- empresas: block, unblock, delete
- perfis: block, unblock, delete
- usuarios: block, unblock, delete
- contas_receber: save (quando alteracao), delete
- contas_pagar: enviar_aprovacao, aprovar, cancelar (se aplicavel por acao de estado)

### SEC-002 Exigir CSRF em 100% das operacoes de alteracao
- [x] Implementar validacao de token em todos os endpoints de escrita da Fase 1
- [x] Criar testes de rejeicao sem token e token invalido (helpers)
- [x] Padronizar resposta e flash para erro de CSRF
- [x] Atualizar documentacao tecnica de seguranca de formularios

### SEC-003 Bloquear metodo HTTP invalido
- [x] Implementar retorno 405 para metodos nao permitidos em acoes sensiveis
- [x] Adicionar testes automatizados para o guard de metodo HTTP
- [x] Atualizar guia de rotas/controladores

Evidencias da Fase 1:
- Novo guard central em helpers/functions.php: has_valid_csrf_token() e enforce_post_request()
- Roteamento atualizado para aceitar page/action via POST em index.php
- Views e modais convertidos para POST + csrf_field() nas acoes sensiveis
- Testes unitarios adicionados em tests/Unit/FunctionsTest.php
- Execucao de validacao: phpunit unitario passou; suite completa possui falhas preexistentes fora do escopo da Fase 1

---

## Fase 2 - Autorizacao e antifraude

### SEC-004 Expandir RBAC para todos os modulos criticos
- [x] Implementar verificacoes can_user(modulo, acao) por operacao sensivel
- [x] Criar matriz de permissoes por perfil
- [x] Criar testes de autorizacao (permitido/negado)
- [x] Atualizar documentacao de perfis e permissoes

### SEC-005 Endurecer trilha antifraude
- [x] Registrar eventos sensiveis com contexto minimo (usuario, empresa, ip, recurso)
- [x] Criar regra de alerta para padrao suspeito (ex.: muitas exclusoes)
- [x] Cobrir com testes de logs de auditoria
- [x] Atualizar documentacao operacional de monitoramento

### SEC-006 Corrigir confianca de IP de cliente
- [x] Ajustar get_client_ip para confiar em proxy apenas quando configurado
- [x] Definir variavel/env para lista de proxies confiaveis
- [x] Criar testes unitarios para cenarios com e sem proxy confiavel
- [x] Atualizar documentacao de deploy/rede reversa

Evidencias da Fase 2:
- RBAC central por rota sensivel implementado em index.php com resolve_sensitive_route_permission() e can_user().
- Matriz de modulos expandida em config/config.php e views/perfis/form.php (clientes, fornecedores, logs).
- Trilha antifraude implementada em helpers/functions.php com registrar_evento_antifraude() e verificar_alerta_antifraude().
- Hardening de IP por proxy confiavel implementado em helpers/logger.php com TRUST_PROXY_HEADERS e TRUSTED_PROXIES.
- Testes adicionados: tests/Integration/AuthorizationFraudTest.php e tests/Integration/ProxyTrustIpTest.php.
- Testes atualizados: tests/Unit/FunctionsTest.php e tests/bootstrap.php.
- Execucao de validacao da fase: phpunit focado da Fase 2 passou (51 testes, 85 assertions).

---

## Fase 3 - Hardening de plataforma e supply chain

### SEC-007 Endurecer CSP e reduzir superficie XSS
- [ ] Remover "unsafe-inline" quando possivel
- [ ] Adotar nonce/hash para scripts inline necessarios
- [ ] Criar testes de cabecalhos de seguranca
- [ ] Atualizar documentacao de frontend e assets externos

### SEC-008 Tornar segredos obrigatorios em producao
- [ ] Falhar inicializacao se APP_SECRET estiver em valor padrao
- [ ] Revisar chaves obrigatorias de ambiente
- [ ] Criar testes de inicializacao/configuracao
- [ ] Atualizar docs de configuracao (.env e ambiente externo)

### SEC-009 Corrigir vulnerabilidade de dependencia (phpunit)
- [ ] Atualizar phpunit para versao sem CVE reportada
- [ ] Executar suite de testes completa
- [ ] Reexecutar composer audit sem findings criticos
- [ ] Atualizar documentacao de desenvolvimento e CI

### SEC-010 SRI em recursos CDN (quando aplicavel)
- [ ] Adicionar integrity e crossorigin nos assets externos suportados
- [ ] Validar carregamento em ambiente local e homologacao
- [ ] Atualizar documentacao de frontend/seguranca de supply chain

---

## Fase 4 - Fechamento e validacao final

### SEC-011 Regressao de seguranca automatizada
- [ ] Expandir tests/Integration/SecurityTest.php com novos cenarios
- [ ] Garantir cobertura de CSRF, metodo HTTP, RBAC e headers
- [ ] Rodar run-tests.bat com sucesso
- [ ] Registrar evidencias de execucao

### SEC-012 Revisao de documentacao final
- [ ] Atualizar docs/projeto afetados pelo hardening
- [ ] Atualizar guias tecnicos (configuracao, permissao, operacao)
- [ ] Revisar coerencia entre codigo e documentacao
- [ ] Publicar changelog de seguranca

---

## Checklist de acompanhamento detalhado

Use este quadro para status continuo (marcar data e responsavel).

| ID | Item | Prioridade | Status | Responsavel | Data inicio | Data fim | Evidencia |
|---|---|---|---|---|---|---|---|
| SEC-001 | GET -> POST em acoes sensiveis | Critica | Concluido |  | 2026-07-12 | 2026-07-12 | Controllers e views da fase 1 migrados para POST |
| SEC-002 | CSRF 100% escrita | Critica | Concluido |  | 2026-07-12 | 2026-07-12 | Validacao CSRF aplicada nas acoes sensiveis da fase 1 |
| SEC-003 | Metodo HTTP invalido = 405 | Critica | Concluido |  | 2026-07-12 | 2026-07-12 | Guard enforce_post_request + testes unitarios |
| SEC-004 | RBAC por acao sensivel | Alta | Concluido |  | 2026-07-12 | 2026-07-12 | Guard central de permissao por rota sensivel no index.php |
| SEC-005 | Antifraude e auditoria | Alta | Concluido |  | 2026-07-12 | 2026-07-12 | Eventos antifraude + alerta de padrao suspeito no helper |
| SEC-006 | IP confiavel por proxy | Alta | Concluido |  | 2026-07-12 | 2026-07-12 | get_client_ip com TRUST_PROXY_HEADERS/TRUSTED_PROXIES + testes |
| SEC-007 | CSP sem unsafe-inline | Alta | Nao iniciado |  |  |  |  |
| SEC-008 | Segredos obrigatorios | Alta | Nao iniciado |  |  |  |  |
| SEC-009 | Atualizar phpunit/CVE | Alta | Nao iniciado |  |  |  |  |
| SEC-010 | SRI para CDN | Media | Nao iniciado |  |  |  |  |
| SEC-011 | Regressao de seguranca | Critica | Nao iniciado |  |  |  |  |
| SEC-012 | Atualizacao final de docs | Critica | Nao iniciado |  |  |  |  |

---

## Criterios de pronto (Definition of Done)
Uma tarefa SEC-XXX so pode ser marcada como concluida quando:
- Implementacao finalizada e revisada
- Testes automatizados criados/atualizados e passando
- Documentacao correspondente atualizada
- Evidencia registrada no quadro de acompanhamento

## Comandos de validacao sugeridos
```bat
composer audit --no-interaction
run-tests.bat
```

## Observacoes
- Priorizar entregas pequenas e incrementais por controller para reduzir risco de regressao.
- Em cada PR: incluir o ID SEC-XXX no titulo e no corpo para rastreabilidade.
