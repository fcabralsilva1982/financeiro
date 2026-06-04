# 09 — Critérios de Aceite

**Versão:** 1.0.0  
**Data:** 2026-06-04

---

## Como usar este documento

Para cada funcionalidade, os cenários são descritos como:
- **Cenário Feliz (CF):** fluxo normal, dados válidos, resultado esperado
- **Cenário de Erro (CE):** dados inválidos, permissões negadas, estados inesperados
- **Critério de Homologação (CH):** condição mínima para considerar a funcionalidade aprovada

---

## 1. Autenticação (RF-001 a RF-004)

### CF-001 — Login com credenciais válidas
- **Dado** um usuário com status `ativo` e senha correta  
- **Quando** submete o formulário de login  
- **Então** é criada uma sessão; usuário é redirecionado ao seletor de empresa (se > 1) ou ao dashboard (se 1 empresa)

### CE-001 — Login com senha incorreta
- **Dado** um e-mail válido com senha errada  
- **Quando** submete o formulário  
- **Então** nenhuma sessão é criada; mensagem genérica é exibida ("E-mail ou senha inválidos"); evento `warning` é gravado em `log_acoes` com IP

### CE-002 — Login de usuário bloqueado
- **Dado** um usuário com status `bloqueado`  
- **Quando** submete login com credenciais corretas  
- **Então** acesso negado; mensagem: "Sua conta está bloqueada. Entre em contato com o administrador"

### CE-003 — Acesso sem permissão
- **Dado** usuário logado sem permissão `contas_pagar/criar`  
- **Quando** acessa a URL `?c=contas_pagar&a=new`  
- **Então** retorna HTTP 403; log `warning` registrado

**CH-AUTH:** Login funcionando; usuário bloqueado não entra; acesso sem permissão retorna 403 com log

---

## 2. Usuários (RF-006 a RF-010)

### CF-002 — Criar usuário com vínculo a empresa
- **Dado** admin preenchendo formulário com nome, e-mail único, senha e selecionando empresa + perfil  
- **Quando** submete  
- **Então** registro criado em `usuarios` e `usuario_empresa`; log com `dados_novos` registrado

### CE-004 — Criar usuário com e-mail duplicado
- **Dado** e-mail já cadastrado  
- **Quando** tenta criar  
- **Então** erro: "Este e-mail já está em uso"; nenhum registro gravado

### CE-005 — Alterar senha com senha atual errada
- **Dado** usuário logado informando senha atual incorreta  
- **Quando** submete troca de senha  
- **Então** senha não alterada; mensagem de erro genérica; nenhum detalhe de hash revelado

**CH-USR:** CRUD completo funcionando com persistência real; e-mail único validado; vínculo empresa-perfil salvo

---

## 3. Contas a Receber (RF-011 a RF-016)

### CF-003 — Criar conta a receber com 3 parcelas
- **Dado** operador selecionando cliente ativo, valor R$ 900, vencimento em 3 parcelas de R$ 300  
- **Quando** salva  
- **Então** conta criada em `rascunho`; 3 parcelas em `contas_receber_parcelas` com status `pendente`; código CR-XXXX único gerado

### CE-006 — Parcelas com soma diferente do valor total
- **Dado** valor total R$ 900, parcelas somando R$ 850  
- **Quando** tenta salvar  
- **Então** erro de validação: "A soma das parcelas (R$ 850,00) é diferente do valor total (R$ 900,00)"; nenhum registro gravado

### CF-004 — Registrar baixa de parcela
- **Dado** parcela com status `pendente`, valor R$ 300  
- **Quando** operador registra pagamento de R$ 300 com data hoje  
- **Então** parcela atualizada para `paga`; `data_pagamento` e `valor_pago` gravados; se última parcela, conta-mãe atualizada para `paga`

### CE-007 — Baixa com data futura
- **Dado** operador informando data de pagamento amanhã  
- **Quando** tenta salvar  
- **Então** erro: "Data de pagamento não pode ser no futuro"

### CE-008 — Cancelar conta já paga
- **Dado** conta com status `paga`  
- **Quando** tenta cancelar  
- **Então** erro: "Contas pagas não podem ser canceladas"

**CH-CR:** Ciclo completo criação → parcelamento → baixa → status `paga` funcionando; validações de parcela aplicadas

---

## 4. Contas a Pagar (RF-017 a RF-022)

### CF-005 — Criar conta a pagar e aprovar
- **Dado** operador criando conta com fornecedor ativo, valor R$ 1.200, 2 parcelas  
- **Quando** envia para aprovação e o aprovador (outro usuário) aprova  
- **Então** status muda para `aprovada`; log com aprovador gravado; parcelas ficam disponíveis para baixa

### CE-009 — Aprovação pelo próprio criador (sem permissão especial)
- **Dado** usuário sem `contas_pagar/aprovar_proprio` tentando aprovar conta que criou  
- **Quando** clica em Aprovar  
- **Então** erro: "Você não pode aprovar uma conta que você mesmo criou"; log `warning` gravado

### CF-006 — Baixa com upload de comprovante
- **Dado** parcela de CP com status `pendente` em conta `aprovada`  
- **Quando** operador registra pagamento e faz upload de PDF (3 MB)  
- **Então** parcela atualizada para `paga`; arquivo salvo em `uploads/comprovantes/{empresa_id}/`; acesso ao arquivo somente via controller autenticado

### CE-010 — Upload de arquivo executável
- **Dado** tentativa de upload de arquivo `.php` como comprovante  
- **Quando** submete  
- **Então** erro: "Tipo de arquivo não permitido"; nenhum arquivo salvo

### CE-011 — Baixa em conta não aprovada
- **Dado** conta com status `pendente_aprovacao`  
- **Quando** operador tenta registrar baixa  
- **Então** erro: "Esta conta ainda não foi aprovada para pagamento"

**CH-CP:** Ciclo completo criação → aprovação → baixa com comprovante funcionando; segregação de funções aplicada

---

## 5. Dashboard (RF-023 a RF-026)

### CF-007 — Dashboard carrega com dados corretos
- **Dado** empresa com 10 contas a receber (5 pagas, 3 pendentes, 2 vencidas) e 5 contas a pagar  
- **Quando** usuário acessa o dashboard  
- **Então** card "A Receber" mostra soma das pendentes do mês; card "Inadimplência" mostra soma das vencidas; carregamento em < 1.200ms

### CF-008 — Gráfico de fluxo de caixa
- **Dado** empresa com lançamentos nos últimos 6 meses  
- **Quando** acessa dashboard  
- **Então** gráfico exibe 6 barras (uma por mês); meses sem movimento exibem R$ 0,00; não lança erro de divisão por zero

**CH-DASH:** Todos os 4 KPIs exibidos com valores corretos; gráfico renderizado sem erro; tempo de carregamento medido e dentro do limite

---

## 6. Régua de Cobrança (RF-030 a RF-033)

### CF-009 — Disparo de e-mail por régua
- **Dado** regra configurada com `dias_antes = 3`, canal `email`; parcela com vencimento daqui a 3 dias  
- **Quando** job `jobs/regua_cobranca.php` executa  
- **Então** e-mail enviado; evento registrado em `contas_receber_historico` com canal `email` e status `enviado`

### CE-012 — Disparo para cliente com opt-out
- **Dado** cliente com `opt_out_lembretes = 1`  
- **Quando** job executa e a regra bate  
- **Então** e-mail NÃO é enviado; evento registrado em histórico com status `opt_out`

### CE-013 — Disparo duplicado no mesmo dia
- **Dado** job executado duas vezes no mesmo dia (acidentalmente)  
- **Quando** segunda execução  
- **Então** nenhum disparo duplicado (idempotência por verificação no histórico)

### CE-014 — Falha na API de WhatsApp
- **Dado** API de WhatsApp retornando timeout  
- **Quando** job tenta enviar  
- **Então** falha registrada no histórico; job continua processando demais parcelas sem parar

**CH-REGUA:** Disparo correto no dia configurado; opt-out respeitado; idempotência verificada; falha de API não para o job

---

## 7. Relatórios (RF-034 a RF-037)

### CF-010 — Relatório de inadimplência
- **Dado** empresa com 5 clientes com parcelas vencidas  
- **Quando** acessa relatório de inadimplência sem filtros  
- **Então** todos os 5 clientes listados com valor total e dias de atraso corretos; nenhum cliente de outra empresa exibido

### CF-011 — Exportação CSV
- **Dado** relatório carregado com dados  
- **Quando** clica em "Exportar CSV"  
- **Então** arquivo `.csv` baixado com separador `;`; encoding UTF-8 com BOM; colunas correspondendo à visualização

**CH-REL:** Todos os 4 relatórios funcionando com filtros; exportação CSV gerada corretamente

---

## 8. Importação de Dados (RF-038 a RF-040)

### CF-012 — Importação de CSV de clientes
- **Dado** arquivo CSV com 100 clientes válidos e 5 com CNPJ duplicado  
- **Quando** faz upload  
- **Então** 100 clientes importados; 5 ignorados com aviso na tela; relatório de erros exibe linhas problemáticas

### CE-015 — CSV com mais de 5.000 linhas
- **Dado** arquivo CSV com 6.000 linhas  
- **Quando** faz upload  
- **Então** erro antes do processamento: "Arquivo excede o limite de 5.000 registros por importação"

**CH-IMP:** Importação de 1.000 registros válidos sem timeout; duplicatas não importadas com aviso

---

## 9. Conciliação Bancária (RF-041 a RF-044)

### CF-013 — Importação OFX e matching automático
- **Dado** arquivo OFX com 50 transações, 35 com valor e data compatíveis com parcelas do sistema  
- **Quando** importa  
- **Então** 35 transações marcadas como `conciliado_auto`; 15 listadas como `não conciliadas`; taxa de match ≥ 70%

### CE-016 — Importação do mesmo OFX duas vezes
- **Dado** arquivo OFX já importado anteriormente  
- **Quando** importa novamente  
- **Então** transações com FITIDs já existentes ignoradas; aviso: "X transações já foram importadas anteriormente"

**CH-CONC:** Parser OFX processa arquivos dos principais bancos brasileiros; matching automático ≥ 70% nos dados de teste; idempotência de FITID verificada

---

## 10. Critérios Mínimos para Homologação Geral

Para considerar o MVP pronto para produção, **todos** os itens abaixo devem estar verificados:

- [ ] **M1:** Usuários com persistência real funcionando (sem mock)
- [ ] **M2:** Ciclo completo de Contas a Pagar (criar → aprovar → pagar com comprovante)
- [ ] **M3:** Dashboard carregando em < 1.200ms com dados reais
- [ ] **M4:** Régua disparando e-mails corretamente; opt-out e idempotência verificados
- [ ] **M5:** 4 relatórios com exportação CSV funcionando
- [ ] **M6:** Importação CSV sem timeout para 1.000 registros
- [ ] **M7:** Parser OFX processando ao menos 2 bancos reais; matching automático ≥ 70%
- [ ] **SEG:** Zero SQL injection detectado pelo OWASP ZAP; todos os forms com CSRF; headers de segurança configurados
- [ ] **LGPD:** Aceite de termo implementado; exportação e exclusão de dados funcionando
- [ ] **BACKUP:** Backup automático configurado e restore testado com sucesso
- [ ] **CROSS-TENANT:** Teste de acesso indevido entre tenants bloqueado em 100% dos cenários testados
