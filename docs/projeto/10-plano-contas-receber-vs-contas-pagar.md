# Plano de Ajuste do Fluxo de Contas a Receber

**Versão:** 0.1
**Data:** 2026-07-12

## 1. Objetivo

Corrigir a percepção conceitual de que uma conta a receber nasce como "rascunho" logo após o cadastro. O fluxo de contas a receber deve refletir a criação de um título já emitido, enquanto contas a pagar continua usando rascunho como estado inicial por depender de revisão/aprovação.

## 2. Diagnóstico Atual

Hoje o status de rascunho está sendo aplicado em mais de uma camada do fluxo de contas a receber:

- O formulário envia o status padrão como rascunho.
- O model grava rascunho quando o campo não é enviado.
- O schema também usa rascunho como default da tabela.

Ao mesmo tempo, a base de contas a pagar já evidencia um ciclo mais aderente ao seu domínio:

- criação em rascunho;
- avanço para aprovação;
- cancelamento e baixa com transições explícitas.

Para contas a receber, esse mesmo ciclo não existe de forma consistente. O resultado é um cadastro que aparenta ficar "incompleto" após salvar, mesmo quando a intenção do usuário era gerar uma cobrança válida.

## 3. Hipótese de Regra de Negócio

Contas a receber deve nascer como título emitido ou equivalente operacional, e não como rascunho. O estado de rascunho só faria sentido se houver um fluxo explícito de pré-emissão, com ação separada para publicar a cobrança.

## 4. Fluxo Alvo

### 4.1 Contas a Receber

1. Usuário cadastra a cobrança.
2. Sistema valida os campos obrigatórios.
3. Sistema grava a conta com status inicial compatível com emissão, como "emitida".
4. Se houver necessidade futura, ações adicionais podem mover a conta para "enviada", "visualizada", "paga", "vencida" ou "cancelada".

### 4.2 Contas a Pagar

1. Usuário cadastra a obrigação.
2. Sistema grava em rascunho.
3. Fluxo segue para aprovação, cancelamento ou baixa.
4. O rascunho é mantido como estado natural de preparação do título.

## 5. Ajustes Planejados

### 5.1 Contas a Receber

- Remover o default de rascunho do fluxo de criação.
- Definir um status inicial claro para novos cadastros.
- Garantir que o form, o model e o schema apontem para a mesma regra.
- Avaliar se o status deve ser fixado no back-end ou se haverá ação explícita de "salvar como rascunho" no futuro.

### 5.2 Contas a Pagar

- Manter o fluxo atual como referência de aderência ao domínio.
- Revisar se a nomenclatura e as transições estão claras para o usuário final.
- Verificar se mensagens, labels e histórico acompanham o ciclo real da conta.

## 6. Pontos de Atenção Técnicos

- Sincronizar o default entre controller, model, view e banco.
- Não quebrar filtros e relatórios que já consideram o status atual.
- Conferir impacto em migrações existentes e em bases já preenchidas.
- Definir se contas já cadastradas em rascunho devem ser migradas manualmente ou apenas respeitadas como legado.

## 7. Critérios de Aceite

- Novo cadastro de conta a receber não aparece como rascunho por padrão.
- O fluxo de contas a pagar permanece inalterado.
- O usuário entende claramente a diferença entre criação de cobrança e preparação de obrigação.
- O status exibido na listagem é coerente com a etapa real do título.

## 8. Próximos Passos Sugeridos

1. Ajustar a regra de status inicial de contas a receber.
2. Atualizar schema, model e formulário para a mesma convenção.
3. Criar testes cobrindo o status inicial de cadastro.
4. Revisar textos da interface para refletir o novo comportamento.