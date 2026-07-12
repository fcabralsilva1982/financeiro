# 02 — Escopo MVP

**Versão:** 1.0.0  
**Data:** 2026-06-04

---

## 1. Definição de MVP

O MVP é o conjunto mínimo de funcionalidades que entrega valor real para o usuário: controle completo do ciclo financeiro (receber e pagar), visibilidade consolidada via dashboard, conciliação com extrato bancário e cobrança automatizada.

---

## 2. Funcionalidades MVP

### 2.1 Módulos já implementados (completar/ajustar)

| Módulo 					| Status atual 	| O que falta 													|
|--------					|-------------	|-------------													|
| Empresas (multiempresa) 	| ✅ Completo 	| -																|
| Perfis e Permissões 		| ✅ Completo 	| Adicionar permissões para módulos novos						|
| Clientes 					| ✅ Completo 	| — 															|
| Fornecedores 				| ✅ Completo 	| — 															|
| Logs de Auditoria 		| ✅ Completo 	| — 															|
| Usuários 					| ⚠️ Mock 		| Persistência real no banco, gestão de senha, vínculo empresa	|
| Contas a Receber 			| ⚠️ Básico 	| Parcelas, histórico, régua de cobrança ativa, baixa manual 	|
| Autenticação 				| ✅ Completo 	| — 															|

### 2.2 Módulos a construir

| Módulo 							| Prioridade 	| Descrição resumida |
|--------							|-----------	|-------------------|
| **Contas a Pagar** 				| Alta 			| Cadastro de contas com fornecedor, parcelamento, aprovação e baixa de pagamento |
| **Dashboard** 					| Alta 			| Painel por empresa: totais, saldo previsto, inadimplência, gráfico mensal |
| **Fluxo de Caixa** 				| Alta 			| Visão de entradas x saídas por período, com projeção |
| **Régua de Cobrança** 			| Alta 			| Disparos automáticos por e-mail (SMTP) e WhatsApp (API externa) |
| **Relatórios** 					| Média 		| Inadimplência, a vencer, pagas, por período e por cliente/fornecedor |
| **Importação CSV/Excel** 			| Média 		| Importação de lançamentos e clientes/fornecedores em lote |
| **Conciliação Bancária** 			| Média 		| Importação de OFX e matching com lançamentos do sistema |
| **Centro de Custo / Categoria** 	| Média 		| Categorização de lançamentos para relatórios gerenciais |
| **Contas Recorrentes** 			| Baixa 		| Geração automática de contas a pagar com repetição mensal/anual |

---

## 3. Fora do Escopo (MVP)

Os itens abaixo **não serão implementados no MVP** e devem ser tratados como backlog futuro:

- Emissão de boleto bancário real (requer integração com banco/gateway)
- Geração de QR Code PIX real (requer integração com banco/PSP)
- Cobrança via cartão de crédito (requer gateway: Asaas, Stripe, etc.)
- Emissão de NF-e (nota fiscal de produtos)
- Emissão de NFS-e (nota fiscal de serviços)
- Módulo de folha de pagamento
- Contabilidade fiscal / livros contábeis
- Integração em tempo real via Open Finance (requer homologação no BCB)
- App mobile nativo
- Portal do cliente (área do cliente para ver e pagar faturas)
- Onboarding / self-service de cadastro de empresa no SaaS

---

## 4. Marcos de Entrega

Cada marco representa uma entrega funcional completa e testável, sem data definida (equipe de 1 desenvolvedor, entrega incremental).

| Marco | Funcionalidades | Critério de conclusão |
|-------|----------------|----------------------|
| **M1 — Base sólida** | Usuários com persistência real, permissões atualizadas | CRUD de usuários persistindo no banco, vínculos empresa-perfil funcionando |
| **M2 — Contas a Pagar** | Módulo CP completo: cadastro, parcelamento, aprovação, baixa, upload comprovante | Ciclo completo de uma conta a pagar aprovada e paga |
| **M3 — Dashboard** | Painel por empresa com todos os KPIs e gráfico de fluxo de caixa | Dashboard carrega em < 800ms com dados reais |
| **M4 — Régua de Cobrança** | Disparos automáticos de e-mail e WhatsApp conforme configuração | Lembrete disparado automaticamente no dia configurado |
| **M5 — Relatórios** | Relatórios de inadimplência, a vencer e pagas com filtros | Exportação em PDF/Excel funcionando |
| **M6 — Importação** | CSV de clientes, fornecedores e lançamentos | Importação com validação e relatório de erros |
| **M7 — Conciliação** | Importação OFX e matching automático/manual | Conciliação de extrato com lançamentos, taxa de match > 70% automático |

---

## 5. Suposições de Prazo

- Sem data limite definida; entregas são incrementais por marco
- Cada marco deve ser validado pelo responsável antes de avançar
- Refatorações fora do escopo do marco atual serão tratadas como débito técnico e registradas no backlog
