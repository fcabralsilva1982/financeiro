# 16 — Backlog de Execução: PF e Profissional Liberal

**Versão:** 1.0.0  
**Data:** 2026-07-14  
**Objetivo:** habilitar uso do sistema por pessoa física e profissional liberal sem CNPJ, mantendo compatibilidade com tenants PJ já existentes.

---

## 1. Visão de Execução

Este backlog foi estruturado para entrega incremental com baixo risco:

1. Preparar banco e compatibilidade de dados.
2. Ajustar validações e regras de negócio no backend.
3. Ajustar formulários e experiência no frontend.
4. Cobrir regressão com testes automatizados.
5. Atualizar documentação e homologar.

---

## 2. Critérios de Pronto (Definition of Done)

- [x] Cadastro de tenant aceita PF e PJ com validação correta por tipo.
- [x] Fluxos de login, alternância de empresa e permissões seguem íntegros na suíte automatizada.
- [x] Nenhum vazamento cross-tenant é introduzido nos fluxos alterados.
- [x] Testes de integração de empresas e integridade de banco passam.
- [x] Regressões críticas de contas a pagar e receber não ocorrem na suíte automatizada.
- [x] Documentação funcional e técnica atualizada.

---

## 3. Backlog Priorizado

| ID | Tipo | Descrição | Prioridade | Estimativa | Dependências | Critério de Aceite |
|---|---|---|---|---|---|---|
| BL-PF-001 | Tarefa Técnica | Criar migration para adicionar `tipo_pessoa` (PF/PJ) em `empresas` com default PJ | Alta | P | Nenhuma | Coluna criada e registros atuais migrados para PJ |
| BL-PF-002 | Tarefa Técnica | Criar migration para tornar `cnpj` opcional e adicionar `cpf` opcional em `empresas` | Alta | M | BL-PF-001 | Schema suporta PF sem CNPJ sem quebrar dados atuais |
| BL-PF-003 | Tarefa Técnica | Ajustar índices únicos para documento fiscal da empresa (CNPJ/CPF) com nulos permitidos | Alta | M | BL-PF-002 | Integridade preservada sem duplicidade indevida |
| BL-PF-004 | História | Ajustar validação no controller de empresas para documento obrigatório por tipo (CPF para PF, CNPJ para PJ) | Alta | M | BL-PF-002 | Erro correto por tipo e gravação bem-sucedida quando válido |
| BL-PF-005 | História | Ajustar formulário de empresas para seleção de tipo e campos condicionais (CPF/CNPJ, IE somente PJ) | Alta | M | BL-PF-004 | Tela exibe campos corretos e impede combinação inválida |
| BL-PF-006 | Tarefa Técnica | Atualizar buscas/listagem de empresas para considerar CPF ou CNPJ conforme tipo | Média | P | BL-PF-005 | Filtro textual encontra PF e PJ de forma consistente |
| BL-PF-007 | Tarefa Técnica | Revisar logs de auditoria para registrar documento correto por tipo sem mascaramento incorreto | Média | P | BL-PF-004 | Eventos de criação/edição armazenam dados corretos |
| BL-PF-008 | Tarefa Técnica | Atualizar testes de schema em `DatabaseTestCase` para nova estrutura de `empresas` | Alta | M | BL-PF-003 | Suite de testes monta schema sem falha |
| BL-PF-009 | Teste | Atualizar testes de integridade para novas regras de unicidade e obrigatoriedade por tipo | Alta | M | BL-PF-008 | Testes de integridade passam cobrindo PF e PJ |
| BL-PF-010 | Teste | Atualizar testes do módulo empresas para cenários PF e PJ (create/edit/validação) | Alta | M | BL-PF-009 | Casos felizes e de erro validados para ambos os tipos |
| BL-PF-011 | Teste | Executar regressão de módulos financeiros críticos (CP/CR/autenticação) | Alta | M | BL-PF-010 | Nenhuma regressão funcional crítica |
| BL-PF-012 | Documentação | Atualizar visão geral, regras de negócio e critérios de aceite para contemplar PF sem PJ | Média | P | BL-PF-010 | Documentos revisados e consistentes com implementação |
| BL-PF-013 | Operação | Planejar rollout e checklist de go-live com estratégia de rollback | Média | P | BL-PF-011 | Plano aprovado com validação de contingência |

---

## 4. Checklist de Acompanhamento

## Fase A — Banco e Migração

- [x] BL-PF-001 concluído
- [x] BL-PF-002 concluído
- [x] BL-PF-003 concluído
- [ ] Scripts de migração testados em base de homologação
- [x] Plano de rollback documentado

## Fase B — Backend e Regras

- [x] BL-PF-004 concluído
- [x] BL-PF-006 concluído
- [x] BL-PF-007 concluído
- [x] Revisão de segurança concluída (sem bypass de tenant)

## Fase C — Frontend e UX

- [x] BL-PF-005 concluído
- [ ] Validação visual de formulário em desktop
- [ ] Validação visual de formulário em mobile
- [x] Mensagens de erro consistentes por tipo

## Fase D — Testes e Qualidade

- [x] BL-PF-008 concluído
- [x] BL-PF-009 concluído
- [x] BL-PF-010 concluído
- [x] BL-PF-011 concluído
- [x] Execução de testes sem falhas críticas

## Fase E — Documentação e Go-live

- [x] BL-PF-012 concluído
- [x] BL-PF-013 concluído
- [ ] Homologação funcional aprovada
- [ ] Publicação em produção autorizada

---

## 5. Quadro de Status (preencher ao longo da execução)

| ID | Responsável | Status | Data Início | Data Fim | Bloqueio |
|---|---|---|---|---|---|
| BL-PF-001 | Codex | Concluído | 2026-07-14 | 2026-07-14 |  |
| BL-PF-002 | Codex | Concluído | 2026-07-14 | 2026-07-14 |  |
| BL-PF-003 | Codex | Concluído | 2026-07-14 | 2026-07-14 |  |
| BL-PF-004 | Codex | Concluído | 2026-07-14 | 2026-07-14 |  |
| BL-PF-005 | Codex | Concluído | 2026-07-14 | 2026-07-14 | Validação visual manual pendente |
| BL-PF-006 | Codex | Concluído | 2026-07-14 | 2026-07-14 |  |
| BL-PF-007 | Codex | Concluído | 2026-07-14 | 2026-07-14 |  |
| BL-PF-008 | Codex | Concluído | 2026-07-14 | 2026-07-14 |  |
| BL-PF-009 | Codex | Concluído | 2026-07-14 | 2026-07-14 |  |
| BL-PF-010 | Codex | Concluído | 2026-07-14 | 2026-07-14 |  |
| BL-PF-011 | Codex | Concluído | 2026-07-14 | 2026-07-14 |  |
| BL-PF-012 | Codex | Concluído | 2026-07-14 | 2026-07-14 |  |
| BL-PF-013 | Codex | Concluído | 2026-07-14 | 2026-07-14 | Homologação e autorização de produção pendentes |

---

## 6. Sequência Recomendada de Execução

1. BL-PF-001 a BL-PF-003
2. BL-PF-004 e BL-PF-005
3. BL-PF-006 e BL-PF-007
4. BL-PF-008 a BL-PF-011
5. BL-PF-012 e BL-PF-013

---

## 7. Riscos e Mitigações

| Risco | Impacto | Mitigação |
|---|---|---|
| Regressão em validação de empresa PJ | Alto | Testes de regressão obrigatórios antes de merge |
| Índice de unicidade incompatível com dados atuais | Alto | Rodar migração em staging com snapshot real |
| Divergência de texto em telas e regras | Médio | Revisar i18n e critérios de aceite em conjunto |
| Falha de rollout | Alto | Checklist de rollback e janela de implantação controlada |

---

## 8. Plano de Rollout e Rollback

**Rollout**

1. Executar backup completo do banco antes da janela.
2. Validar duplicidade atual de `empresas.cnpj` em produção antes da migration.
3. Executar `php scripts/apply_m11_migration.php`.
4. Verificar colunas `tipo_pessoa` e `cpf` em `empresas`, índices `uk_empresas_cnpj` e `uk_empresas_cpf`, e registros atuais como `PJ`.
5. Criar tenant PJ de teste e tenant PF/profissional liberal de teste em homologação.
6. Executar suíte automatizada e smoke test de login, troca de empresa, empresas, contas a pagar e contas a receber.

**Rollback**

1. Se a migration falhar antes de uso em produção, restaurar backup do banco.
2. Se a aplicação precisar voltar sem perda de dados, manter colunas novas no banco e reverter apenas o deploy de código; tenants PF criados durante a janela devem ser bloqueados para não operar em versão antiga.
3. Se for exigido rollback completo de schema, exportar tenants PF criados após a migration, restaurar snapshot pré-migration e reimportar apenas após novo rollout.
