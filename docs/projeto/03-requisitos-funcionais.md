# 03 — Requisitos Funcionais

**Versão:** 1.0.0  
**Data:** 2026-06-04

---

## Legenda de Prioridade

| Prioridade | Significado |
|-----------|-------------|
| Alta | Bloqueia o MVP se ausente |
| Média | Necessário antes da entrega final |
| Baixa | Desejável, pode entrar após MVP |

---

## Épico 1 — Autenticação e Controle de Acesso

| ID | Descrição | Prioridade | Origem | Critério de Aceite |
|----|-----------|-----------|--------|--------------------|
| RF-001 | O sistema deve autenticar usuários com e-mail e senha (hash bcrypt). Tentativas com credenciais inválidas devem ser logadas com IP e user-agent. | Alta | Entrevista / LGPD | Login com credenciais válidas cria sessão; 3 tentativas inválidas consecutivas são logadas como `warning` em `log_acoes` |
| RF-002 | Após autenticação, o usuário deve selecionar a empresa ativa caso tenha acesso a mais de uma. O superadmin acessa todas. | Alta | Entrevista | Seletor de empresa exibido quando `usuario_empresa.count > 1`; empresa ativa salva na sessão |
| RF-003 | Toda operação deve verificar se o usuário possui permissão (`modulo` + `acao`) no perfil vinculado à empresa ativa. | Alta | Regra de negócio | Acesso a ação sem permissão retorna HTTP 403 e registra log `warning` |
| RF-004 | O sistema deve permitir logout explícito, destruindo a sessão e registrando o evento no log. | Alta | Segurança | Sessão destruída; log de `auth/logout` criado com IP |
| RF-005 | Usuário deve poder alterar sua própria senha após informar a senha atual. A nova senha deve ter ≥ 8 caracteres, incluir letras e números. | Média | Boas práticas | Senha atualizada com novo hash; log registrado; senha antiga inválida retorna erro sem revelar detalhes |

---

## Épico 2 — Gestão de Usuários

| ID | Descrição | Prioridade | Origem | Critério de Aceite |
|----|-----------|-----------|--------|--------------------|
| RF-006 | Admin deve criar usuários com nome, e-mail, senha temporária e vínculo a pelo menos uma empresa com um perfil. | Alta | Marco M1 | Usuário criado persiste em `usuarios` e `usuario_empresa`; e-mail único validado no banco |
| RF-007 | Admin deve editar dados de um usuário (nome, telefone, celular) e alterar seu perfil por empresa. | Alta | Marco M1 | Alterações persistem no banco; log de edição registrado com `dados_anteriores` e `dados_novos` |
| RF-008 | Admin deve poder bloquear e desbloquear usuários. Usuário bloqueado não pode autenticar. | Alta | Segurança | Status `bloqueado` impede login; log registrado |
| RF-009 | Admin deve poder excluir usuário que não possui lançamentos financeiros vinculados. | Média | Integridade | Exclusão negada se existirem contas a receber/pagar com `usuario_id`; soft-delete não aplicável aqui |
| RF-010 | Admin deve poder vincular um usuário a múltiplas empresas com perfis distintos por empresa. | Alta | Multiempresa | Registro em `usuario_empresa` para cada par usuário-empresa; perfil diferente permitido por empresa |

---

## Épico 3 — Contas a Receber

| ID | Descrição | Prioridade | Origem | Critério de Aceite |
|----|-----------|-----------|--------|--------------------|
| RF-011 | Operador deve criar uma conta a receber vinculada a um cliente ativo da empresa, com descrição, valor total, data de emissão e data de vencimento. | Alta | Código existente | Registro criado em `contas_receber` com status `rascunho`; código CR-XXXX gerado automaticamente |
| RF-012 | Ao criar ou editar uma conta a receber, o operador deve poder parcelá-la em N parcelas com datas e valores individuais editáveis. A soma das parcelas deve ser igual ao valor total. | Alta | Schema existente | Parcelas persistidas em `contas_receber_parcelas`; validação bloqueia criação se somatório diverge do valor total |
| RF-013 | Operador deve registrar a baixa de uma parcela informando data de pagamento, valor pago e forma de pagamento. Pagamento parcial deve ser registrado com valor pago < valor da parcela. | Alta | Regra de negócio | Parcela atualizada para `paga` (ou `pendente` se parcial); `data_pagamento` e `valor_pago` gravados; evento registrado em `contas_receber_historico` |
| RF-014 | O sistema deve atualizar automaticamente o status da conta a receber para `vencida` quando a data de vencimento for ultrapassada e não houver pagamento registrado. | Alta | Regra de negócio | Job/verificação diária atualiza status; não requer interação manual |
| RF-015 | Operador deve poder cancelar uma conta a receber no status `rascunho` ou `emitida`. Contas pagas não podem ser canceladas. | Alta | Regra de negócio | Status alterado para `cancelada`; parcelas também canceladas; log registrado |
| RF-016 | Listagem de contas a receber deve permitir filtrar por: status, período de vencimento, cliente e busca textual (descrição/código). | Média | Usabilidade | Filtros combinados retornam somente registros da empresa ativa; resultado paginado (50 por página) |

---

## Épico 4 — Contas a Pagar

| ID | Descrição | Prioridade | Origem | Critério de Aceite |
|----|-----------|-----------|--------|--------------------|
| RF-017 | Operador deve criar uma conta a pagar vinculada a um fornecedor ativo da empresa, com descrição, valor total, data de emissão, data de vencimento, categoria/centro de custo e forma de pagamento. | Alta | Entrevista | Registro criado em `contas_pagar` com status `rascunho`; código CP-XXXX gerado automaticamente |
| RF-018 | Conta a pagar deve suportar parcelamento idêntico ao de contas a receber: N parcelas com datas e valores individuais editáveis; soma igual ao valor total. | Alta | Entrevista | Parcelas persistidas em `contas_pagar_parcelas`; validação de somatório aplicada |
| RF-019 | Conta a pagar deve passar por fluxo de aprovação antes de ser paga: status `pendente_aprovacao` → `aprovada` → `paga`. Usuário com permissão `contas_pagar/aprovar` autoriza o pagamento. | Alta | Entrevista | Transição para `aprovada` só é possível por usuário com permissão; log registrado com aprovador |
| RF-020 | Operador deve registrar a baixa de uma parcela de conta a pagar informando data de pagamento, valor pago, forma de pagamento e podendo anexar comprovante (PDF/JPG/PNG, máx. 5 MB). | Alta | Entrevista | Parcela atualizada para `paga`; comprovante salvo em `public/uploads/comprovantes/{empresa_id}/`; log registrado |
| RF-021 | Conta a pagar deve suportar recorrência: ao marcar como recorrente (mensal ou anual), o sistema deve gerar automaticamente a próxima ocorrência após a baixa. | Baixa | Entrevista | Nova conta gerada com mesmos dados, data de vencimento incrementada; flag `recorrente` = true na nova conta |
| RF-022 | Listagem de contas a pagar deve permitir filtrar por: status, período de vencimento, fornecedor, categoria e busca textual. | Média | Usabilidade | Filtros combinados retornam somente registros da empresa ativa; paginado (50 por página) |

---

## Épico 5 — Dashboard

| ID | Descrição | Prioridade | Origem | Critério de Aceite |
|----|-----------|-----------|--------|--------------------|
| RF-023 | Dashboard deve exibir para a empresa ativa: total a receber no mês corrente, total a pagar no mês corrente, saldo previsto (receber − pagar) e total em atraso (inadimplência). | Alta | Entrevista | Valores calculados em tempo real via query agregada; carregamento em < 800ms |
| RF-024 | Dashboard deve exibir gráfico de barras com fluxo de caixa mensal dos últimos 6 meses (entradas x saídas por mês). | Alta | Entrevista | Gráfico renderizado no front-end com dados da API interna; meses sem movimento exibem valor zero |
| RF-025 | Dashboard deve listar contas vencendo nos próximos 7 dias (a receber e a pagar), com link direto para o registro. | Alta | Entrevista | Lista ordenada por vencimento; máximo de 10 itens exibidos; link aponta para edição do registro |
| RF-026 | Dashboard deve exibir os 5 clientes com maior saldo devedor em aberto e os 5 fornecedores com maior valor a pagar. | Média | Entrevista | Rankings calculados via query com GROUP BY; clientes/fornecedores inativos excluídos |

---

## Épico 6 — Fluxo de Caixa

| ID | Descrição | Prioridade | Origem | Critério de Aceite |
|----|-----------|-----------|--------|--------------------|
| RF-027 | Sistema deve oferecer tela de fluxo de caixa com visão de entradas (contas a receber pagas) e saídas (contas a pagar pagas) por período selecionável (dia, semana, mês). | Alta | Entrevista | Período padrão = mês corrente; filtros de período retornam somente registros da empresa ativa |
| RF-028 | Fluxo de caixa deve exibir projeção: saldo atual + entradas previstas − saídas previstas no período. | Alta | Entrevista | Projeção usa parcelas com status `pendente` e data de vencimento dentro do período |
| RF-029 | Fluxo de caixa deve permitir exportação dos dados em formato CSV. | Média | Entrevista | Arquivo CSV gerado no servidor e enviado ao browser; nome do arquivo inclui período e empresa |

---

## Épico 7 — Régua de Cobrança

| ID | Descrição | Prioridade | Origem | Critério de Aceite |
|----|-----------|-----------|--------|--------------------|
| RF-030 | Admin deve configurar a régua de cobrança da empresa: regras com `dias_antes` ou `dias_depois` do vencimento, canal (e-mail ou WhatsApp) e mensagem padrão. | Alta | Entrevista | Regras salvas em `contas_receber_regua`; pelo menos uma regra ativa ativa a régua |
| RF-031 | Sistema deve disparar lembretes de cobrança por e-mail (SMTP) nas datas configuradas pela régua, respeitando o campo `opt_out_lembretes` do cliente. | Alta | Entrevista | E-mail enviado via PHPMailer; evento registrado em `contas_receber_historico`; clientes com `opt_out_lembretes = 1` não recebem |
| RF-032 | Sistema deve disparar lembretes de cobrança por WhatsApp via API externa (Z-API / WPP Connect / Twilio) nas datas configuradas pela régua, respeitando o `opt_out_lembretes`. | Alta | Entrevista | Mensagem enviada via HTTP POST para API configurada; resposta da API registrada no histórico; falha de envio logada como `warning` |
| RF-033 | Admin deve poder visualizar o histórico completo de disparos da régua por conta a receber (canal, data, status do envio). | Média | Rastreabilidade | Histórico exibido na tela de detalhe da conta; eventos de `contas_receber_historico` filtrados por `conta_receber_id` |

---

## Épico 8 — Relatórios

| ID | Descrição | Prioridade | Origem | Critério de Aceite |
|----|-----------|-----------|--------|--------------------|
| RF-034 | Relatório de Inadimplência: lista parcelas vencidas sem pagamento, agrupadas por cliente, com valor total em atraso e dias de atraso. | Média | Entrevista | Filtrável por período de vencimento; exportável em CSV e PDF |
| RF-035 | Relatório de Contas a Vencer: lista parcelas com vencimento nos próximos N dias (configurável), separadas por a receber e a pagar. | Média | Entrevista | N padrão = 30 dias; exportável em CSV |
| RF-036 | Relatório de Contas Pagas: lista parcelas pagas em um período, com data de pagamento e forma de pagamento, separadas por a receber e a pagar. | Média | Entrevista | Filtrável por período de pagamento, tipo (CR/CP) e cliente/fornecedor; exportável em CSV |
| RF-037 | Relatório de Fluxo de Caixa mensal: tabela com entradas, saídas e saldo por mês nos últimos 12 meses. | Média | Entrevista | Exportável em CSV; meses sem movimento incluídos com valores zerados |

---

## Épico 9 — Importação de Dados

| ID | Descrição | Prioridade | Origem | Critério de Aceite |
|----|-----------|-----------|--------|--------------------|
| RF-038 | Sistema deve importar clientes a partir de arquivo CSV com campos mapeados (razão social, CPF/CNPJ, e-mail, telefone). | Média | Entrevista | Arquivo validado antes de importar; linhas com erro reportadas em lista; linhas válidas persistidas; duplicatas (por CPF/CNPJ) ignoradas com aviso |
| RF-039 | Sistema deve importar fornecedores a partir de arquivo CSV com os mesmos campos do cadastro manual. | Média | Entrevista | Idem RF-038 |
| RF-040 | Sistema deve importar lançamentos de contas a receber/pagar a partir de CSV, com colunas: cliente/fornecedor (código ou CNPJ), descrição, valor, vencimento, status. | Média | Entrevista | Máximo de 5.000 linhas por arquivo; lançamentos vinculados à empresa ativa; relatório de erros gerado ao final |

---

## Épico 10 — Conciliação Bancária

| ID | Descrição | Prioridade | Origem | Critério de Aceite |
|----|-----------|-----------|--------|--------------------|
| RF-041 | Operador deve importar extrato bancário no formato OFX. O sistema deve parsear as transações e exibir a lista de lançamentos do extrato. | Média | Entrevista | Arquivo OFX válido processado; transações exibidas com data, valor e descrição do banco |
| RF-042 | Sistema deve fazer matching automático entre transações do OFX e parcelas do sistema com base em: valor exato + data dentro de ±3 dias. | Média | Entrevista | Parcelas com match automático marcadas como `conciliado_auto`; percentual de match automático ≥ 70% nos dados de teste |
| RF-043 | Operador deve poder conciliar manualmente uma transação do extrato com uma parcela do sistema quando o match automático falhar. | Média | Entrevista | Interface de drag-or-select para vincular transação a parcela; vínculo salvo na tabela `conciliacao_bancaria` |
| RF-044 | Transações do extrato sem correspondência no sistema devem ser listadas como `não conciliadas` para revisão do operador. | Média | Entrevista | Lista de não conciliados exibida ao final do processo; operador pode criar lançamento manualmente a partir da transação |

---

## Épico 11 — Categorias e Centros de Custo

| ID | Descrição | Prioridade | Origem | Critério de Aceite |
|----|-----------|-----------|--------|--------------------|
| RF-045 | Admin deve cadastrar categorias financeiras da empresa (ex.: Salários, Aluguel, Vendas) para classificar contas a pagar e a receber. | Média | Entrevista | CRUD de categorias por empresa; categoria vinculada ao lançamento no momento da criação |
| RF-046 | Relatórios devem permitir filtrar e agrupar por categoria para análise gerencial. | Média | Entrevista | Filtro de categoria disponível em todos os relatórios; gráfico de pizza por categoria no dashboard (futuro) |

---

## Épico 12 — LGPD e Privacidade

| ID | Descrição | Prioridade | Origem | Critério de Aceite |
|----|-----------|-----------|--------|--------------------|
| RF-047 | Sistema deve exibir termo de uso e política de privacidade no primeiro acesso do usuário, exigindo aceite explícito. | Média | LGPD | Aceite registrado com data, IP e versão do termo; usuário não prossegue sem aceitar |
| RF-048 | Usuário deve poder solicitar exportação de seus dados pessoais (nome, e-mail, telefone) em formato JSON. | Média | LGPD | Arquivo JSON gerado e enviado por e-mail ao solicitante; ação registrada no log |
| RF-049 | Usuário deve poder solicitar exclusão de sua conta. Dados financeiros (lançamentos) são anonimizados; dados de identificação (nome, e-mail) são removidos. | Média | LGPD | Exclusão executa UPDATE em `usuarios` com dados anonimizados; log da solicitação retido por 5 anos |
