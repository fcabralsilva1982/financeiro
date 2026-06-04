# 05 — Regras de Negócio

**Versão:** 1.0.0  
**Data:** 2026-06-04

---

## RN-001 — Isolamento de Dados por Empresa (Multitenancy)

**Contexto:** Todo dado financeiro (contas, clientes, fornecedores, categorias, regras de cobrança) pertence a exatamente uma empresa.

**Regra:** Toda query que acessa tabelas com coluna `empresa_id` deve incluir `WHERE empresa_id = :empresa_id_sessao`. A empresa ativa é determinada pela sessão do usuário, não por parâmetro de URL ou body.

**Exceções:** O superadmin pode consultar dados de qualquer empresa desde que a empresa seja passada explicitamente como filtro de relatório, nunca como by-pass de segurança.

**Impacto em dados/processo:** Qualquer ausência deste filtro caracteriza vazamento cross-tenant — vulnerabilidade crítica de segurança.

---

## RN-002 — Controle de Permissões Granular

**Contexto:** Cada usuário possui um perfil por empresa. O perfil define quais ações (`visualizar`, `criar`, `editar`, `bloquear`, `excluir`, `aprovar`) o usuário pode executar em cada módulo.

**Regra:** Antes de qualquer ação (inclusive leitura de dados sensíveis), o sistema verifica em `perfil_permissoes` se existe registro com `perfil_id` do usuário na empresa ativa, `modulo` = módulo da ação e `acao` = ação solicitada. Ausência de registro nega o acesso.

**Exceções:** O superadmin não está vinculado a perfis e tem acesso irrestrito a todos os módulos de todas as empresas.

**Impacto em dados/processo:** Ações negadas devem retornar HTTP 403 e registrar evento de nível `warning` em `log_acoes`.

---

## RN-003 — Fluxo de Status de Contas a Receber

**Contexto:** Uma conta a receber passa por estados bem definidos ao longo de seu ciclo de vida.

**Regra:** Transições válidas:

```
rascunho → emitida → enviada → visualizada → paga
                                            → vencida (automático, se não paga após vencimento)
rascunho → cancelada
emitida  → cancelada
```

- `rascunho → emitida`: ação manual do operador
- `emitida → enviada`: ao registrar envio do e-mail/WhatsApp da régua ou manualmente
- `enviada → visualizada`: ao registrar evento de visualização (webhook futuro)
- `qualquer → vencida`: automático, via job diário, quando `data_vencimento < CURDATE()` e status não for `paga` ou `cancelada`
- `qualquer → cancelada`: somente se status for `rascunho` ou `emitida`; contas `pagas` não podem ser canceladas

**Exceções:** Nenhuma.

**Impacto em dados/processo:** Transições inválidas devem retornar erro HTTP 422 com mensagem clara. O status de cada parcela é independente do status da conta-mãe.

---

## RN-004 — Fluxo de Status de Contas a Pagar

**Contexto:** Contas a pagar têm um fluxo similar ao de contas a receber, com a adição da etapa de aprovação obrigatória antes do pagamento.

**Regra:** Transições válidas:

```
rascunho → pendente_aprovacao → aprovada → paga
                                          → vencida (automático)
rascunho → cancelada
pendente_aprovacao → cancelada
aprovada → cancelada (somente por admin)
```

- `rascunho → pendente_aprovacao`: ação manual do operador ao "enviar para aprovação"
- `pendente_aprovacao → aprovada`: ação do usuário com permissão `contas_pagar/aprovar`; o aprovador não pode ser o mesmo que criou a conta
- `aprovada → paga`: ao registrar baixa de todas as parcelas
- `qualquer → vencida`: automático, via job diário

**Exceções:** O admin pode aprovar contas que ele próprio criou (permissão explícita de admin).

**Impacto em dados/processo:** A aprovação deve registrar em log: aprovador, data e `dados_anteriores` com status `pendente_aprovacao`.

---

## RN-005 — Parcelamento e Integridade de Valor

**Contexto:** Tanto contas a receber quanto a pagar podem ser parceladas.

**Regra:**
1. A soma dos valores das parcelas deve ser igual ao `valor_total` da conta. Diferença de até R$ 0,01 é tolerada por arredondamento (o sistema ajusta automaticamente na última parcela).
2. Número mínimo de parcelas: 1. Número máximo: 60.
3. Cada parcela deve ter data de vencimento ≥ data de emissão da conta.
4. Parcelas não podem ter valor ≤ R$ 0,00.

**Exceções:** Nenhuma — validação é bloqueante.

**Impacto em dados/processo:** Criar conta com parcelas inválidas retorna HTTP 422; nenhum registro é gravado (transação revertida).

---

## RN-006 — Baixa (Registro de Pagamento)

**Contexto:** A baixa registra que uma parcela foi paga.

**Regra:**
1. Somente parcelas com status `pendente` ou `vencida` podem receber baixa.
2. Pagamento parcial é permitido: `valor_pago` < `valor da parcela`. Nesse caso, o status permanece `pendente` e o saldo devedor é calculado como `valor − valor_pago`.
3. `data_pagamento` deve ser ≤ data atual (`CURDATE()`); não é permitido registrar pagamento no futuro.
4. Ao quitar a última parcela pendente de uma conta, o sistema atualiza o status da conta-mãe para `paga`.
5. Baixa em conta a pagar exige que a conta esteja com status `aprovada`.

**Exceções:** Admin pode registrar baixa em conta a pagar `pendente_aprovacao` em casos de urgência, registrando justificativa obrigatória no campo `observacoes`.

**Impacto em dados/processo:** Evento de baixa registrado em `contas_receber_historico` (ou tabela equivalente para CP).

---

## RN-007 — Régua de Cobrança Automática

**Contexto:** O sistema dispara notificações automáticas para clientes com base em regras configuradas por empresa.

**Regra:**
1. Um job diário (cron) avalia todas as parcelas com status `pendente` ou `vencida` da empresa.
2. Para cada parcela, verifica se existe regra na `contas_receber_regua` onde `CURDATE() = data_vencimento − dias_antes` ou `CURDATE() = data_vencimento + dias_depois`.
3. Se a regra bater, verifica se já existe evento com mesmo `conta_receber_id`, `parcela_id` e `evento` no mesmo dia em `contas_receber_historico`. Se sim, pula (idempotência).
4. Se o cliente tem `opt_out_lembretes = 1`, o disparo é cancelado e registrado como `opt_out` no histórico.
5. Falha no envio (timeout, erro de API) não deve parar o processamento das demais parcelas; a falha é logada individualmente.

**Exceções:** Regras com `ativo = 0` são ignoradas pelo job.

**Impacto em dados/processo:** Todo disparo (sucesso ou falha) é registrado em `contas_receber_historico` com canal, data e descrição do resultado.

---

## RN-008 — Multiempresa: Alternância de Empresa Ativa

**Contexto:** Um usuário pode estar vinculado a múltiplas empresas com perfis distintos.

**Regra:**
1. Ao fazer login, se o usuário tem acesso a apenas uma empresa, ela é definida automaticamente como empresa ativa.
2. Se tem acesso a mais de uma, é exibida tela de seleção de empresa.
3. O usuário pode trocar a empresa ativa a qualquer momento via menu, sem necessidade de novo login.
4. Ao trocar de empresa, todas as permissões são recarregadas com base no perfil do usuário na nova empresa ativa.
5. Dados exibidos em qualquer tela devem ser somente da empresa ativa na sessão.

**Exceções:** Superadmin pode filtrar dados por empresa em telas de relatório sem necessidade de trocar a empresa ativa.

**Impacto em dados/processo:** Troca de empresa deve limpar caches de permissão da sessão e registrar evento de `auth/empresa_trocada` no log.

---

## RN-009 — Geração de Códigos Amigáveis

**Contexto:** Cada registro principal possui um código amigável (CR-XXXX, CP-XXXX, CLI-XXXX, etc.) para identificação humana.

**Regra:**
1. O código é gerado consultando `MAX(codigo)` da tabela no escopo da empresa, incrementando +1 e formatando com zero-padding de 4 dígitos (ex.: CR-0042).
2. A geração deve ocorrer dentro de uma transação para evitar duplicatas em concorrência.
3. Códigos nunca são reutilizados após exclusão.

**Exceções:** Nenhuma.

**Impacto em dados/processo:** Tabelas com `UNIQUE KEY` no campo `codigo` dentro do escopo da empresa garantem integridade.

---

## RN-010 — Aprovação de Conta a Pagar: Segregação de Funções

**Contexto:** Para evitar fraudes, quem cria uma conta a pagar não deve ser a mesma pessoa a aprová-la (exceto admin).

**Regra:**
1. Ao tentar aprovar uma conta a pagar, o sistema verifica se `criado_por_usuario_id` ≠ `usuario_id` da sessão.
2. Se forem iguais e o usuário não for admin (permissão `contas_pagar/aprovar_proprio`), a aprovação é negada com mensagem: "Você não pode aprovar uma conta que você mesmo criou."
3. A verificação é feita na camada de controller, não apenas na view.

**Exceções:** Perfil Admin possui permissão `contas_pagar/aprovar_proprio` que sobrepõe esta regra.

**Impacto em dados/processo:** Tentativa negada registrada em `log_acoes` com nível `warning`.

---

## RN-011 — Conciliação Bancária: Idempotência de Importação

**Contexto:** O mesmo arquivo OFX pode ser importado acidentalmente mais de uma vez.

**Regra:**
1. Cada transação do OFX possui um identificador único (`FITID`). O sistema armazena o `FITID` na tabela de conciliação.
2. Ao importar um OFX, o sistema ignora transações com `FITID` já existente para a empresa ativa, exibindo aviso ao operador.
3. O arquivo OFX importado é descartado após processamento; não é armazenado no servidor.

**Exceções:** Nenhuma.

**Impacto em dados/processo:** Prevents duplicatas de conciliação sem retornar erro ao usuário.

---

## RN-012 — Recorrência de Contas a Pagar

**Contexto:** Contas recorrentes (aluguel, assinaturas) precisam ser geradas automaticamente no próximo período.

**Regra:**
1. Ao registrar a baixa da última parcela de uma conta com `recorrente = true`, o sistema agenda a geração da próxima ocorrência.
2. A nova conta é gerada com os mesmos dados, exceto: nova data de emissão = data da baixa + 1 dia; nova data de vencimento calculada conforme periodicidade (`mensal`: +1 mês; `anual`: +1 ano).
3. A nova conta é criada com status `rascunho` para revisão antes de enviar para aprovação.
4. O campo `conta_origem_id` na nova conta referencia a conta anterior (rastreabilidade da cadeia de recorrências).

**Exceções:** Se a conta recorrente for cancelada, o ciclo é interrompido e nenhuma nova ocorrência é gerada.

**Impacto em dados/processo:** Geração da próxima ocorrência é registrada em log com referência à conta de origem.

---

## RN-013 — Upload de Comprovantes

**Contexto:** Comprovantes de pagamento (PDF, JPG, PNG) são anexados a parcelas de contas a pagar.

**Regra:**
1. Tamanho máximo por arquivo: 5 MB.
2. Extensões permitidas: `.pdf`, `.jpg`, `.jpeg`, `.png`.
3. MIME type deve ser validado no servidor (`application/pdf`, `image/jpeg`, `image/png`).
4. Nome do arquivo salvo deve ser sanitizado (substituir espaços e caracteres especiais) e prefixado com UUID para evitar colisão.
5. Arquivos salvos em `public/uploads/comprovantes/{empresa_id}/{ano}/{mes}/`.
6. Comprovante acessível somente via controller autenticado (`/financeiro/?c=contas_pagar&a=comprovante&id=...`) — nunca via URL direta.

**Exceções:** Nenhuma.

**Impacto em dados/processo:** Upload inválido retorna erro HTTP 422; nenhum arquivo é salvo em caso de erro.
