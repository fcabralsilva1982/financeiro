# 07 — Backlog MVP

**Versão:** 1.0.0  
**Data:** 2026-06-04

---

## Legenda

| Campo | Valores |
|-------|---------|
| Tipo | Epic / Historia / Tarefa Técnica |
| Prioridade | Alta / Média / Baixa |
| Estimativa | P = até 2h · M = até 1 dia · G = até 3 dias · GG = mais de 3 dias |
| Status | Pendente / Em andamento / Concluído |

---

## Marco M1 — Base Sólida: Usuários com Persistência Real

| ID | Tipo | Descrição | Prioridade | Est. | Status | Deps | Requisitos |
|----|------|-----------|-----------|------|--------|------|-----------|
| B-001 | Tarefa Técnica | Criar migration SQL para garantir schema atualizado: `usuario_termos`, campos `criado_por_usuario_id` em tabelas financeiras, `updated_at` em todas as tabelas | Alta | M | Pendente | — | RF-006, RNF-018 |
| B-002 | Historia | Implementar CRUD real de usuários: criar, editar, bloquear, desbloquear persistindo em `usuarios` e `usuario_empresa` (substituir mock atual) | Alta | G | Pendente | B-001 | RF-006, RF-007, RF-008, RF-010 |
| B-003 | Historia | Implementar troca de empresa ativa via menu sem novo login; recarregar permissões na sessão | Alta | M | Pendente | B-002 | RF-002, RN-008 |
| B-004 | Historia | Implementar alteração de senha pelo próprio usuário (verificar senha atual + validar nova) | Média | P | Pendente | B-002 | RF-005 |
| B-005 | Historia | Implementar tela de aceite de termo de uso no primeiro login; registrar aceite em `usuario_termos` | Média | M | Pendente | B-001 | RF-047, RN-020 |
| B-006 | Tarefa Técnica | Atualizar `PERMISSION_MAP` no config para incluir módulos novos: `contas_pagar`, `dashboard`, `relatorios`, `conciliacao`, `importacao`, `categorias` | Alta | P | Pendente | — | RF-003, RN-002 |
| B-007 | Tarefa Técnica | Criar tela de gestão de perfis com permissões para os novos módulos | Média | M | Pendente | B-006 | RF-003 |

---

## Marco M2 — Contas a Pagar

| ID | Tipo | Descrição | Prioridade | Est. | Status | Deps | Requisitos |
|----|------|-----------|-----------|------|--------|------|-----------|
| B-010 | Tarefa Técnica | Criar tabelas: `contas_pagar`, `contas_pagar_parcelas`, `contas_pagar_historico`, `categorias` (migration SQL) | Alta | M | Pendente | B-001 | RF-017, RF-018, RF-045 |
| B-011 | Historia | Implementar CRUD de categorias financeiras por empresa (nome, tipo: receita/despesa) | Média | M | Pendente | B-010 | RF-045 |
| B-012 | Historia | Implementar listagem de Contas a Pagar com filtros: status, período, fornecedor, categoria, busca | Alta | M | Pendente | B-010 | RF-022 |
| B-013 | Historia | Implementar formulário de criação/edição de Conta a Pagar: fornecedor, descrição, valor, datas, categoria, forma de pagamento, recorrência | Alta | G | Pendente | B-010, B-011 | RF-017, RF-021 |
| B-014 | Historia | Implementar parcelamento de Conta a Pagar: geração de N parcelas com validação de somatório | Alta | M | Pendente | B-013 | RF-018, RN-005 |
| B-015 | Historia | Implementar fluxo de aprovação: botão "Enviar para Aprovação", botão "Aprovar" (com verificação de segregação de funções), log do aprovador | Alta | M | Pendente | B-013 | RF-019, RN-004, RN-010 |
| B-016 | Historia | Implementar baixa de parcela de Conta a Pagar: data pagamento, valor pago, forma de pagamento, upload de comprovante | Alta | G | Pendente | B-015 | RF-020, RN-006, RN-013 |
| B-017 | Historia | Implementar cancelamento de Conta a Pagar com validação de status permitidos | Alta | P | Pendente | B-013 | RN-004 |
| B-018 | Tarefa Técnica | Implementar job `jobs/atualizar_status.php`: atualizar parcelas vencidas (CR e CP) e contas-mãe | Alta | M | Pendente | B-010 | RF-014, RN-003, RN-004 |
| B-019 | Historia | Implementar contas recorrentes: ao baixar última parcela de conta CP recorrente, gerar próxima ocorrência em `rascunho` | Baixa | M | Pendente | B-016 | RF-021, RN-012 |
| B-020 | Tarefa Técnica | Criar controller de download de comprovante autenticado (sem URL direta ao arquivo) | Alta | P | Pendente | B-016 | RNF-008, RN-013 |

---

## Marco M3 — Dashboard

| ID | Tipo | Descrição | Prioridade | Est. | Status | Deps | Requisitos |
|----|------|-----------|-----------|------|--------|------|-----------|
| B-030 | Tarefa Técnica | Criar model `Dashboard.php` com queries agregadas otimizadas (com índices): totais por status, inadimplência, vencimentos próximos, top clientes/fornecedores | Alta | G | Pendente | B-010 | RF-023, RF-024, RF-025, RF-026 |
| B-031 | Historia | Implementar view do Dashboard: cards com KPIs (a receber, a pagar, saldo previsto, inadimplência) | Alta | M | Pendente | B-030 | RF-023 |
| B-032 | Historia | Implementar gráfico de fluxo de caixa mensal (últimos 6 meses, barras: entradas vs saídas) via Chart.js | Alta | M | Pendente | B-030 | RF-024 |
| B-033 | Historia | Implementar lista de contas vencendo nos próximos 7 dias no dashboard, com links diretos | Alta | P | Pendente | B-030 | RF-025 |
| B-034 | Historia | Implementar ranking top 5 clientes devedores e top 5 fornecedores no dashboard | Média | P | Pendente | B-030 | RF-026 |

---

## Marco M4 — Régua de Cobrança

| ID | Tipo | Descrição | Prioridade | Est. | Status | Deps | Requisitos |
|----|------|-----------|-----------|------|--------|------|-----------|
| B-040 | Historia | Implementar tela de configuração da régua de cobrança: criar/editar/excluir regras (dias_antes/depois, canal, mensagem) | Alta | M | Pendente | — | RF-030 |
| B-041 | Tarefa Técnica | Implementar envio de e-mail via PHPMailer no job `jobs/regua_cobranca.php`: verificar opt_out, idempotência, registrar histórico | Alta | G | Pendente | B-040 | RF-031, RN-007 |
| B-042 | Tarefa Técnica | Implementar envio via WhatsApp API no job: configuração de credenciais por empresa no `.env`, payload configurável por provedor, timeout e retry | Alta | G | Pendente | B-040 | RF-032, RN-007 |
| B-043 | Historia | Implementar tela de histórico de disparos por conta a receber (canal, data, resultado) | Média | P | Pendente | B-041 | RF-033 |
| B-044 | Tarefa Técnica | Implementar link de unsubscribe nos e-mails (token único por cliente, sem login) que atualiza `opt_out_lembretes = 1` | Média | M | Pendente | B-041 | RNF-021, RF-020 |

---

## Marco M5 — Relatórios

| ID | Tipo | Descrição | Prioridade | Est. | Status | Deps | Requisitos |
|----|------|-----------|-----------|------|--------|------|-----------|
| B-050 | Historia | Implementar Relatório de Inadimplência: parcelas vencidas por cliente, valor total em atraso, dias de atraso; filtros por período | Média | M | Pendente | B-010 | RF-034 |
| B-051 | Historia | Implementar Relatório de Contas a Vencer: parcelas CR e CP, próximos N dias, filtráveis | Média | M | Pendente | B-010 | RF-035 |
| B-052 | Historia | Implementar Relatório de Contas Pagas: parcelas CR e CP pagas por período, com forma de pagamento | Média | M | Pendente | B-010 | RF-036 |
| B-053 | Historia | Implementar Relatório de Fluxo de Caixa: tabela mensal 12 meses, entradas x saídas x saldo | Média | M | Pendente | B-010 | RF-037 |
| B-054 | Tarefa Técnica | Implementar exportação CSV para todos os relatórios (geração no servidor, download direto) | Média | M | Pendente | B-050 | RF-029, RF-034-037 |
| B-055 | Tarefa Técnica | Implementar exportação PDF para relatório de inadimplência e fluxo de caixa (HTML → PDF via mPDF ou similar) | Média | G | Pendente | B-050 | RF-034, RF-037 |

---

## Marco M6 — Importação de Dados

| ID | Tipo | Descrição | Prioridade | Est. | Status | Deps | Requisitos |
|----|------|-----------|-----------|------|--------|------|-----------|
| B-060 | Historia | Implementar upload e parse de CSV de clientes: validação de campos obrigatórios, CPF/CNPJ único, relatório de erros | Média | G | Pendente | — | RF-038 |
| B-061 | Historia | Implementar upload e parse de CSV de fornecedores | Média | M | Pendente | B-060 | RF-039 |
| B-062 | Historia | Implementar upload e parse de CSV de lançamentos (CR e CP): vínculo com cliente/fornecedor por CNPJ ou código | Média | G | Pendente | B-060, B-010 | RF-040 |
| B-063 | Tarefa Técnica | Implementar processamento em lotes de no máximo 500 registros por vez para evitar timeout; exibir progresso ao usuário | Média | M | Pendente | B-060 | RNF-003 |

---

## Marco M7 — Conciliação Bancária

| ID | Tipo | Descrição | Prioridade | Est. | Status | Deps | Requisitos |
|----|------|-----------|-----------|------|--------|------|-----------|
| B-070 | Tarefa Técnica | Criar tabelas: `conciliacao_bancaria`, `conciliacao_transacoes` (migration SQL) | Média | P | Pendente | B-001 | RF-041 |
| B-071 | Tarefa Técnica | Implementar parser OFX nativo (PHP puro): suporte a OFX 1.x (SGML) e OFX 2.x (XML); extrair FITID, DTPOSTED, TRNAMT, NAME/MEMO | Média | G | Pendente | B-070 | RF-041, RN-011 |
| B-072 | Historia | Implementar tela de importação OFX: upload, parse, exibição das transações antes de confirmar | Média | M | Pendente | B-071 | RF-041 |
| B-073 | Tarefa Técnica | Implementar algoritmo de matching automático: valor exato + data ±3 dias; marcar matches em `conciliacao_transacoes` | Média | G | Pendente | B-072 | RF-042 |
| B-074 | Historia | Implementar interface de conciliação manual: lista de transações não conciliadas com busca por valor/data para vínculo manual com parcela | Média | G | Pendente | B-073 | RF-043 |
| B-075 | Historia | Implementar lista de transações não conciliadas com opção de criar lançamento manual a partir da transação | Média | M | Pendente | B-074 | RF-044 |

---

## Tarefas Técnicas Transversais

| ID | Tipo | Descrição | Prioridade | Est. | Status | Deps | Requisitos |
|----|------|-----------|-----------|------|--------|------|-----------|
| B-090 | Tarefa Técnica | Configurar headers de segurança HTTP em todas as respostas (CSP, X-Frame-Options, etc.) via `.htaccess` ou middleware PHP | Alta | P | Pendente | — | RNF-009 |
| B-091 | Tarefa Técnica | Configurar `session.cookie_httponly = 1`, `session.cookie_samesite = Strict`, `session.gc_maxlifetime = 7200` no `config.php` | Alta | P | Pendente | — | RNF-010 |
| B-092 | Tarefa Técnica | Implementar slow query log no MySQL (> 500ms) e criar procedimento de revisão semanal | Média | P | Pendente | — | RNF-004 |
| B-093 | Tarefa Técnica | Configurar script de backup diário do banco com retenção de 30 dias; testar restore | Alta | M | Pendente | — | RNF-012 |
| B-094 | Tarefa Técnica | Configurar handler global de exceptions PHP: log com stack trace em arquivo; página genérica de erro para usuário | Alta | P | Pendente | — | RNF-013 |
| B-095 | Tarefa Técnica | Implementar endpoint de exportação de dados pessoais (LGPD): gerar JSON e enviar por e-mail | Média | M | Pendente | B-002 | RF-048 |
| B-096 | Tarefa Técnica | Implementar fluxo de exclusão/anonimização de conta de usuário (LGPD) | Média | M | Pendente | B-002 | RF-049 |
| B-097 | Tarefa Técnica | Adicionar `declare(strict_types=1)` e tipagem de parâmetros/retorno em todos os novos arquivos PHP | Alta | P | Pendente | — | RNF-019 |
| B-098 | Tarefa Técnica | Escrever testes de integração para os novos módulos (CP, Dashboard, Conciliação) seguindo padrão dos testes existentes | Alta | GG | Pendente | B-010 | RNF-016 |
| B-099 | Tarefa Técnica | Monitorar uptime com UptimeRobot (ou similar) e configurar alerta por e-mail em queda | Média | P | Pendente | — | RNF-011 |

---

## Detalhamento Operacional Inicial

Para reduzir ambiguidade durante a implementação, os itens do Marco M1 e dos pontos críticos do Marco M2 devem ser executados com a seguinte decomposição mínima.

### B-002 — CRUD real de usuários

- Ler usuários e vínculos a partir de `usuarios` e `usuario_empresa`
- Criar model próprio para listagem, busca, criação, edição e bloqueio
- Substituir o array mock no controller por consultas ao banco
- Validar e-mail único antes de persistir
- Persistir senha com `hash_senha()`
- Registrar logs de criação, edição, bloqueio e exclusão
- Garantir filtro por empresa ativa, com exceção do superadmin

### B-003 — Troca de empresa ativa

- Exibir seletor quando o usuário tiver mais de um vínculo ativo
- Salvar `empresa_id`, `empresa_codigo` e `empresa_nome` na sessão
- Recarregar perfil e permissões ao trocar a empresa
- Registrar evento em `log_acoes`

### B-005 — Aceite de termo de uso

- Criar estrutura para armazenar aceite por usuário
- Exibir termo no primeiro login ou quando houver nova versão
- Bloquear navegação até o aceite explícito
- Salvar data, IP e versão do termo

### B-013 a B-016 — Fluxo de Conta a Pagar

- Criar formulário com fornecedor, descrição, datas, categoria, valor e recorrência
- Validar somatório de parcelas antes de gravar
- Criar fluxo de aprovação com segregação de funções
- Permitir baixa manual com comprovante
- Atualizar status da conta-mãe quando a última parcela for quitada

### B-018 — Job de atualização de status

- Percorrer contas a receber e pagar com vencimento expirado
- Atualizar parcelas vencidas
- Atualizar status da conta-mãe quando necessário
- Registrar execução e falhas em log

### B-020 — Download autenticado de comprovantes

- Impedir acesso público direto aos arquivos
- Validar posse do arquivo pela empresa ativa
- Entregar o arquivo via controller autenticado
- Retornar erro seguro quando o arquivo não existir ou não pertencer ao tenant
