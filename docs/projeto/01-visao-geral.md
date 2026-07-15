# 01 — Visão Geral

**Versão:** 1.0.0  
**Data:** 2026-06-04  
**Responsável:** Equipe de Desenvolvimento

---

## 1. Contexto

O sistema é uma plataforma SaaS de gestão financeira multiempresa voltada para empresas que precisam controlar seus recebimentos e pagamentos de forma centralizada. Cada empresa-cliente do SaaS opera em seu próprio tenant isolado, com usuários, dados e configurações independentes.

O sistema é desenvolvido em PHP 8 com MySQL, hospedado em VPS/servidor próprio, seguindo arquitetura mini-MVC procedural com PDO, autenticação por sessão, tokens CSRF e logs de auditoria granulares.

---

## 2. Problema

Empresas de pequeno e médio porte têm dificuldade para acompanhar contas a receber e a pagar quando operam com múltiplas unidades ou CNPJs. O controle em planilhas dispersas gera:

- Perda de visibilidade sobre inadimplência e vencimentos
- Retrabalho na reconciliação manual com extratos bancários
- Falta de rastreabilidade sobre quem aprovou ou baixou um pagamento
- Ausência de uma régua de cobrança automática e rastreável
- Impossibilidade de consolidar o fluxo de caixa entre empresas do mesmo grupo

---

## 3. Objetivos de Negócio

| ID | Objetivo | Métrica | Meta |
|----|----------|---------|------|
| OBJ-01 | Reduzir inadimplência via cobrança automatizada | % de parcelas pagas dentro do prazo | ≥ 85% em 3 meses |
| OBJ-02 | Eliminar controle por planilha | Adoção: % de lançamentos feitos no sistema | 100% em 60 dias após implantação |
| OBJ-03 | Dar visibilidade financeira consolidada | Tempo para obter saldo previsto do mês | < 30 segundos |
| OBJ-04 | Reduzir tempo de reconciliação bancária | Horas mensais gastas em conciliação | Redução de ≥ 60% |
| OBJ-05 | Suportar crescimento SaaS | Capacidade de tenants ativos simultâneos | 100 empresas / 500 usuários |

---

## 4. Stakeholders

| Papel | Responsabilidade |
|-------|-----------------|
| **Superadmin** | Gerencia todos os tenants, configurações globais da plataforma |
| **Admin da Empresa** | Gerencia usuários, configurações e dados do seu tenant |
| **Gerente Financeiro** | Cria e aprova contas, visualiza relatórios e dashboard |
| **Operador Financeiro** | Registra lançamentos, faz baixas, importa extratos |
| **Visualizador** | Acesso somente leitura a relatórios e listagens |
| **Desenvolvedor** | Mantém e evolui o sistema (1 pessoa) |

---

## 5. Métricas de Sucesso

| Métrica | Forma de Medição | Meta |
|---------|-----------------|------|
| Cobertura de lançamentos no sistema | Lançamentos importados / total de transações bancárias | ≥ 95% |
| Tempo de resposta de listagem | Tempo de carregamento de listas com até 1.000 registros | p95 < 800ms |
| Taxa de envio da régua de cobrança | E-mails/WhatsApp disparados / total esperado no dia | ≥ 98% |
| Incidentes de segurança | Tentativas de acesso indevido bloqueadas e logadas | 100% logadas |
| Satisfação do usuário | NPS após 90 dias | ≥ 50 |

---

## 6. Restrições e Premissas Iniciais

- **Stack fixo:** PHP 8+, MySQL/MariaDB, Apache/Nginx, sem framework externo
- **Hospedagem:** VPS ou servidor próprio (sem cloud gerenciado no MVP)
- **Equipe:** 1 desenvolvedor
- **Sem data limite:** entregas incrementais por funcionalidade completa
- **LGPD:** sistema trata dados pessoais (CPF, e-mail, telefone) — conformidade obrigatória
- **Integrações de pagamento real** (boleto, PIX, cartão) ficam fora do MVP; o MVP usa apenas registro manual de baixa

---

## 7. Atualização 2026-07-14 — Tenants PF e PJ

O cadastro de tenants passa a aceitar pessoa jurídica e pessoa física/profissional liberal. Tenants PJ continuam usando CNPJ obrigatório; tenants PF usam CPF obrigatório e podem operar sem CNPJ e sem inscrição estadual.

A compatibilidade com tenants PJ existentes é mantida por default `PJ` na coluna `empresas.tipo_pessoa`, preservando isolamento multiempresa, login, alternância de empresa e permissões.
