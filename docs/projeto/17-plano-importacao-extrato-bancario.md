# 17 — Plano de Importação de Extrato Bancário

**Versão:** 0.1  
**Data:** 2026-07-15

---

## 1. Objetivo

Definir como a importação de extrato bancário deve funcionar no sistema para registrar pagamentos, débitos PIX, créditos PIX e demais movimentos financeiros sem gerar lançamentos incorretos ou duplicados.

O objetivo principal não é apenas “importar despesas”, mas reduzir o trabalho manual de conciliação e acelerar a baixa de contas já cadastradas no contas a pagar e no contas a receber.

---

## 2. Princípio de Produto

Importação de extrato deve ser tratada como **conciliação bancária assistida**, e não como geração cega de despesas e receitas.

Isso significa que cada transação importada pode resultar em um dos seguintes desfechos:

1. baixa automática de um lançamento já existente;
2. sugestão de vínculo manual com lançamento existente;
3. criação assistida de nova despesa;
4. criação assistida de novo recebimento;
5. classificação como movimento interno ou não operacional.

---

## 3. Problema que a Funcionalidade Resolve

Sem a importação de extrato, o operador precisa:

1. consultar o internet banking;
2. localizar pagamentos e recebimentos manualmente;
3. baixar contas uma a uma;
4. descobrir movimentos sem cadastro prévio;
5. revisar possíveis duplicidades de forma manual.

Com a importação de extrato, o sistema passa a centralizar esse processo e reduz o esforço operacional de conferência.

---

## 4. Escopo Funcional

### 4.1 Entrada

- Upload de arquivo OFX.
- Suporte a OFX 1.x e 2.x.
- Leitura dos campos mínimos: `FITID`, `DTPOSTED`, `TRNAMT`, `NAME`, `MEMO`.

### 4.2 Saída

- Lista de transações importadas.
- Identificação de transações conciliadas automaticamente.
- Identificação de transações pendentes de revisão.
- Ação para criar lançamento manual a partir da transação.
- Ação para vincular manualmente a lançamento existente.

### 4.3 Fora de Escopo Inicial

- Integração bancária via API.
- Emissão real de PIX, boleto ou TED.
- Captura automática de saldo bancário em tempo real.
- Conciliação entre múltiplas contas bancárias internas com espelhamento contábil completo.

---

## 5. Tipos de Movimentação Esperados

Cada transação do extrato deve ser tratada por natureza operacional.

| Tipo identificado no extrato | Interpretação no sistema | Ação padrão sugerida |
| --- | --- | --- |
| Crédito PIX de cliente | Entrada operacional | Tentar baixa em conta a receber |
| Pagamento PIX a fornecedor | Saída operacional | Tentar baixa em conta a pagar |
| TED/DOC/Boleto pago | Saída operacional | Tentar baixa em conta a pagar |
| Recebimento de boleto | Entrada operacional | Tentar baixa em conta a receber |
| Tarifa bancária | Despesa financeira | Criar despesa manual assistida |
| IOF / juros / encargos | Despesa financeira | Criar despesa manual assistida |
| Estorno | Movimento de ajuste | Revisão manual obrigatória |
| Transferência entre contas próprias | Movimento interno | Não criar receita/despesa |
| Aporte / retirada de sócio | Movimento patrimonial | Revisão manual obrigatória |

---

## 6. Fluxo Operacional Proposto

### 6.1 Etapa 1 — Upload e Parse

1. O operador acessa o módulo de conciliação.
2. O sistema valida permissão do módulo `conciliacao`.
3. O operador envia um arquivo OFX.
4. O sistema faz parse do arquivo e extrai as transações.
5. O sistema descarta o arquivo após o processamento.

### 6.2 Etapa 2 — Idempotência

1. Para cada transação importada, o sistema verifica se o `FITID` já existe para a empresa ativa.
2. Se já existir, a transação é ignorada e marcada como duplicada.
3. A importação continua normalmente para as demais transações.

### 6.3 Etapa 3 — Matching Automático

1. O sistema separa créditos e débitos.
2. Para créditos, busca contas a receber compatíveis.
3. Para débitos, busca parcelas de contas a pagar compatíveis.
4. O critério inicial de match é:
   - valor exato;
   - data dentro da janela de ±3 dias;
   - mesma empresa ativa.
5. Quando houver apenas uma correspondência válida, o sistema marca como `conciliado_auto`.

### 6.4 Etapa 4 — Revisão Manual

Se não houver match automático, o operador deve poder:

1. vincular a uma conta existente;
2. criar uma nova despesa com base na transação;
3. criar um novo recebimento com base na transação;
4. classificar como transferência interna;
5. classificar como tarifa, juros, estorno ou ajuste;
6. ignorar temporariamente para revisão posterior.

### 6.5 Etapa 5 — Efeito Financeiro

Depois da confirmação da conciliação:

1. se houver vínculo com conta a receber, o sistema executa a baixa financeira do título;
2. se houver vínculo com conta a pagar, o sistema executa a baixa da parcela;
3. se houver criação manual, o lançamento já nasce conciliado à transação de origem;
4. todo evento deve gerar histórico rastreável.

---

## 7. Relação com os Módulos Já Existentes

### 7.1 Contas a Receber

- Transações de crédito devem procurar contas em aberto.
- Quando confirmadas, devem aproveitar a rotina de baixa já existente.
- A forma de recebimento deve ser preenchida com base na origem identificada, como `pix`, `boleto`, `transferencia` ou `outro`.

### 7.2 Contas a Pagar

- Transações de débito devem procurar parcelas pendentes ou vencidas.
- Quando confirmadas, devem aproveitar a rotina de baixa de parcelas já existente.
- A forma de pagamento deve ser derivada da leitura do extrato quando possível.

### 7.3 Categorias

- Quando a transação não tiver lançamento prévio, a criação assistida deve exigir categoria.
- Tarifas, juros e encargos devem ter categorias específicas para não se misturarem com despesas operacionais comuns.

---

## 8. Regras de Negócio Adicionais

### RN-EX-001 — Extrato não cria lançamento automaticamente sem confirmação

Toda transação sem match automático confiável deve depender de confirmação humana antes de criar nova despesa ou novo recebimento.

### RN-EX-002 — Transferência entre contas próprias não vira despesa nem receita

Quando a transação representar transferência interna, o sistema deve permitir classificá-la como movimento interno e impedir que ela altere indicadores operacionais de receita e despesa.

### RN-EX-003 — Tarifas bancárias não devem baixar contas existentes por valor coincidente isolado

Mesmo com valor e data compatíveis, descrições tipicamente bancárias como tarifa, pacote, anuidade, juros ou IOF devem reduzir a confiança do matching automático.

### RN-EX-004 — Uma transação bancária concilia apenas um lançamento financeiro por vez

Na primeira versão, cada transação importada deve se vincular a somente um lançamento do sistema. Casos de rateio ou baixa agrupada ficam para evolução futura.

### RN-EX-005 — Toda baixa originada de extrato deve registrar origem

O histórico da conta ou parcela deve indicar que a baixa foi realizada via conciliação de extrato, incluindo identificador da importação e `FITID`.

---

## 9. Sugestão de Estrutura de Dados

### 9.1 Tabela `conciliacao_bancaria`

Cabeçalho da importação.

Campos sugeridos:

- `id`
- `empresa_id`
- `usuario_id`
- `nome_arquivo`
- `data_importacao`
- `quantidade_transacoes`
- `quantidade_conciliadas_auto`
- `quantidade_pendentes`
- `created_at`
- `updated_at`

### 9.2 Tabela `conciliacao_transacoes`

Transações individuais do extrato.

Campos sugeridos:

- `id`
- `conciliacao_bancaria_id`
- `empresa_id`
- `fitid`
- `data_movimento`
- `valor`
- `tipo_movimento` (`credito`, `debito`)
- `descricao_banco`
- `memo`
- `status_conciliacao` (`pendente`, `conciliado_auto`, `conciliado_manual`, `ignorado`, `movimento_interno`)
- `origem_match` (`conta_receber`, `conta_pagar`, `novo_recebimento`, `nova_despesa`, `nenhum`)
- `conta_receber_id`
- `conta_pagar_parcela_id`
- `confianca_match`
- `acao_confirmada_por_usuario_id`
- `acao_confirmada_em`
- `created_at`
- `updated_at`

---

## 10. Critérios de Matching Automático

### 10.1 Regra Inicial

O algoritmo inicial deve usar os seguintes critérios mínimos:

1. valor igual ao valor da parcela ou do título;
2. data do extrato dentro de ±3 dias da data de pagamento esperada ou vencimento;
3. status elegível para baixa;
4. empresa ativa igual em ambos os lados.

### 10.2 Fatores que Aumentam Confiança

1. descrição contém número do documento ou código do lançamento;
2. nome do cliente ou fornecedor aparece no `NAME` ou `MEMO`;
3. existe apenas um candidato compatível.

### 10.3 Fatores que Reduzem Confiança

1. múltiplos candidatos com mesmo valor;
2. descrição genérica, como `PIX ENVIADO` ou `TRANSFERENCIA`;
3. transação com texto típico de tarifa bancária;
4. valor recorrente muito comum, como mensalidades idênticas em massa.

### 10.4 Regra de Segurança

Se houver mais de um candidato plausível, o sistema não deve baixar automaticamente.

---

## 11. Criação Assistida de Lançamentos

Quando não existir lançamento prévio, a transação pode originar um novo cadastro assistido.

### 11.1 Nova Despesa

Campos sugeridos pré-preenchidos:

- descrição com base no `NAME` e `MEMO`;
- valor total igual ao valor do débito;
- data de emissão igual à data do movimento;
- data de vencimento igual à data do movimento;
- forma de pagamento compatível com a leitura do extrato;
- observação informando que o lançamento foi criado a partir de extrato.

O operador ainda deve informar ou confirmar:

- fornecedor;
- categoria;
- natureza real da despesa;
- se o lançamento deve nascer já baixado.

### 11.2 Novo Recebimento

Campos sugeridos pré-preenchidos:

- descrição com base no `NAME` e `MEMO`;
- valor total igual ao valor do crédito;
- data de emissão igual à data do movimento;
- data de vencimento igual à data do movimento;
- forma de cobrança ou recebimento compatível;
- observação informando origem no extrato.

O operador ainda deve informar ou confirmar:

- cliente;
- categoria, quando aplicável;
- se é receita operacional, aporte ou ajuste;
- se o lançamento deve nascer já quitado.

---

## 12. Fluxo de Interface Sugerido

### Tela 1 — Importação

- campo de upload OFX;
- resumo do arquivo processado;
- quantidade de transações novas e duplicadas.

### Tela 2 — Resultado do Parse

- tabela com data, valor, descrição, tipo e status de match;
- destaque visual para `conciliado_auto`, `pendente` e `duplicado`.

### Tela 3 — Revisão Manual

- filtro por pendentes;
- ação de vincular com conta existente;
- ação de criar nova despesa;
- ação de criar novo recebimento;
- ação de marcar como movimento interno;
- ação de ignorar.

### Tela 4 — Resumo Final

- total importado;
- total conciliado automaticamente;
- total conciliado manualmente;
- total classificado como movimento interno;
- total ainda pendente.

---

## 13. Auditoria e Rastreabilidade

Toda ação relevante deve ficar registrada:

1. quem importou o arquivo;
2. quando importou;
3. quantas transações foram lidas;
4. quais `FITID` foram ignorados por duplicidade;
5. quais transações geraram baixa automática;
6. quais foram conciliadas manualmente;
7. quais criaram novos lançamentos.

Essa rastreabilidade é necessária para revisão financeira e investigação de divergências.

---

## 14. Riscos e Cuidados

### 14.1 Risco de Falso Positivo

Baixar uma conta errada por coincidência de valor e data distorce o financeiro. Por isso, o matching automático deve ser conservador.

### 14.2 Risco de Duplicidade

Importar o mesmo OFX duas vezes sem controle de `FITID` gera duplicidade de conciliação e possivelmente duplicidade de baixa.

### 14.3 Risco de Classificação Indevida

Transferência entre contas próprias, estornos e aportes não podem ser tratados como receita ou despesa operacional.

### 14.4 Risco de Misturar Extrato com Cadastro Mestre

O extrato não deve criar automaticamente clientes, fornecedores ou categorias sem validação humana.

---

## 15. Sequência Recomendada de Implementação

### Fase 1

1. criar tabelas de importação e transações;
2. implementar parser OFX com idempotência por `FITID`;
3. exibir lista de transações importadas sem baixa automática.

### Fase 2

1. implementar matching automático com valor exato e data ±3 dias;
2. permitir vínculo manual com contas existentes;
3. registrar histórico de conciliação.

### Fase 3

1. permitir criação assistida de despesa e recebimento a partir do extrato;
2. permitir classificação como movimento interno;
3. refinar score de confiança do matching.

### Fase 4

1. suportar casos especiais, como agrupamentos e rateios;
2. adicionar métricas operacionais de conciliação;
3. evoluir para múltiplas contas bancárias, se o produto exigir.

---

## 16. Critérios de Aceite Propostos

1. O sistema importa OFX válido e exibe as transações para a empresa ativa.
2. Transações com `FITID` já importado não são duplicadas.
3. O sistema diferencia crédito de débito e busca o tipo de lançamento compatível.
4. Match automático só ocorre quando houver alta confiança.
5. O operador consegue conciliar manualmente uma transação pendente.
6. O operador consegue criar despesa ou recebimento a partir de uma transação não conciliada.
7. Transferências internas podem ser marcadas sem gerar distorção em receita e despesa.
8. Toda baixa originada por extrato deixa trilha de auditoria.

---

## 17. Decisão Recomendada

Para este projeto, a melhor abordagem é:

1. manter a importação de extrato dentro do módulo `conciliacao`;
2. reutilizar as rotinas atuais de baixa de contas a pagar e contas a receber;
3. impedir criação automática irrestrita de despesas e receitas;
4. tratar o extrato como fonte de evidência financeira, não como cadastro mestre.

Essa abordagem preserva a integridade do financeiro, reduz trabalho operacional e evita que o módulo de importação introduza ruído contábil no sistema.
