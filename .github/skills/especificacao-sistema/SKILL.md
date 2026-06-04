---
name: especificacao-sistema
description: 'Especificar sistemas e programas do zero com documentação inicial e backlog de tarefas. Use para: levantar requisitos por entrevista guiada, definir escopo MVP, arquitetura inicial, riscos, critérios de aceite, plano de entregas e tarefas priorizadas.'
argument-hint: 'Descreva a ideia do projeto, público-alvo, prazo, equipe e restrições.'
---

# Especificação de Sistema

Esta skill transforma uma ideia em documentação de projeto + tarefas executáveis.

## Objetivo

- Produzir especificação completa do sistema
- Criar documentação inicial organizada
- Gerar backlog de tarefas priorizado
- Reduzir lacunas de escopo, risco e requisitos

## Regra obrigatória: entrevista antes de especificar

Antes de gerar qualquer documento final, conduzir entrevista guiada com o usuário.

### Como conduzir

1. Fazer perguntas em blocos curtos (4-8 perguntas por rodada)
2. Confirmar respostas com resumo objetivo ao final de cada rodada
3. Detectar lacunas e perguntar novamente até cobrir todos os tópicos obrigatórios
4. Só gerar documentação final após confirmação do usuário

## Roteiro de perguntas obrigatórias

### 1) Contexto e objetivo

- Qual problema o sistema resolve?
- Quem são os usuários (perfil principal e secundário)?
- Qual resultado de negócio é esperado?
- Como medir sucesso (métrica e meta)?

### 2) Escopo e entregas

- O que é MVP obrigatório?
- O que fica fora do MVP?
- Há data limite de entrega?
- Quais integrações externas são obrigatórias?

### 3) Regras de negócio

- Quais fluxos principais o sistema precisa suportar?
- Existem regras críticas (aprovação, cálculo, permissões)?
- Quais exceções precisam ser tratadas?
- Quais dados são obrigatórios por fluxo?

### 4) Requisitos não funcionais

- Qual volume esperado (usuários, requisições, dados)?
- Requisitos de performance (SLA, tempo de resposta)?
- Requisitos de disponibilidade e backup?
- Requisitos de segurança e conformidade (LGPD, auditoria)?

### 5) Restrições técnicas

- Stack obrigatória (linguagem, banco, infraestrutura)?
- Restrições de hospedagem (on-premises, cloud)?
- Limites de orçamento/equipe?
- Dependências com terceiros?

### 6) Operação e governança

- Quem aprova requisitos e mudanças?
- Como será o processo de homologação?
- Quem mantém o sistema após entrega?
- Quais riscos já conhecidos?

## Critério de completude da entrevista

Só considerar a descoberta concluída quando todos os itens abaixo estiverem preenchidos:

- Problema, público e objetivos
- Escopo MVP e fora de escopo
- Regras de negócio críticas
- Segurança/LGPD e privacidade
- Requisitos não funcionais
- Restrições técnicas e operacionais
- Dependências, riscos e premissas

## Entregáveis obrigatórios

Gerar e manter estes arquivos:

- `docs/projeto/01-visao-geral.md`
- `docs/projeto/02-escopo-mvp.md`
- `docs/projeto/03-requisitos-funcionais.md`
- `docs/projeto/04-requisitos-nao-funcionais.md`
- `docs/projeto/05-regras-de-negocio.md`
- `docs/projeto/06-arquitetura-inicial.md`
- `docs/projeto/07-backlog-mvp.md`
- `docs/projeto/08-riscos-premissas-dependencias.md`
- `docs/projeto/09-criterios-de-aceite.md`

## Estrutura mínima dos documentos

### 01-visao-geral.md

- Contexto
- Problema
- Objetivos de negócio
- Stakeholders
- Métricas de sucesso

### 02-escopo-mvp.md

- Funcionalidades MVP
- Fora de escopo
- Marcos de entrega
- Suposições de prazo

### 03-requisitos-funcionais.md

Para cada requisito:

- ID (`RF-001`)
- Descrição
- Prioridade (Alta, Media, Baixa)
- Origem (entrevista, regra legal, integração)
- Critério de aceite

### 04-requisitos-nao-funcionais.md

Para cada requisito:

- ID (`RNF-001`)
- Categoria (Performance, Segurança, Disponibilidade, Observabilidade, Escalabilidade)
- Meta objetiva (ex.: p95 < 400ms)
- Forma de validação

### 05-regras-de-negocio.md

Para cada regra:

- ID (`RN-001`)
- Contexto
- Regra
- Exceções
- Impacto em dados/processo

### 06-arquitetura-inicial.md

- Contexto técnico
- Componentes principais
- Fluxos entre componentes
- Integrações externas
- Decisões arquiteturais iniciais

### 07-backlog-mvp.md

Backlog em formato de tabela:

- ID
- Tipo (Epic, Historia, Tarefa Tecnica)
- Descrição
- Prioridade
- Estimativa (P/M/G ou horas)
- Dependências
- Critérios de aceite
- Definição de pronto

### 08-riscos-premissas-dependencias.md

- Riscos (probabilidade, impacto, mitigação)
- Premissas
- Dependências externas
- Plano de contingência

### 09-criterios-de-aceite.md

- Critérios por funcionalidade
- Cenários de teste (feliz/erro)
- Critérios mínimos para homologação

## Regras para geração das tarefas

- Quebrar por valor de negócio e ordem de entrega
- Evitar tarefas genéricas (cada item deve ser verificável)
- Sempre incluir tarefas de:
  - segurança
  - testes
  - documentação
  - observabilidade/logs
  - implantação
- Ligar cada tarefa a pelo menos um requisito (`RF-*`/`RNF-*`/`RN-*`)

## Qualidade da saída

- Linguagem clara e objetiva
- Sem ambiguidades (evitar termos vagos: "rápido", "bom", "simples")
- Requisitos testáveis e mensuráveis
- Rastreabilidade completa: requisito -> tarefa -> aceite

## Formato de interação recomendado

Durante a descoberta, usar este formato:

1. Perguntas da rodada
2. Resumo do que foi entendido
3. Lacunas identificadas
4. Próxima rodada de perguntas

Após confirmação do usuário:

1. Gerar documentos iniciais
2. Gerar backlog priorizado
3. Destacar riscos críticos e decisões pendentes
