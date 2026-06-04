# 08 — Riscos, Premissas e Dependências

**Versão:** 1.0.0  
**Data:** 2026-06-04

---

## 1. Riscos

| ID | Risco | Probabilidade | Impacto | Mitigação | Plano de Contingência |
|----|-------|--------------|---------|-----------|----------------------|
| R-01 | **Único desenvolvedor** — doença, burnout ou indisponibilidade paralisa o projeto por tempo indeterminado | Alta | Alto | Documentação contínua (este PRD); código comentado e testado; backlog priorizado permite retomar do ponto correto | Contratar freelancer temporário com acesso ao repositório e documentação |
| R-02 | **Vazamento cross-tenant** — bug de filtro `empresa_id` expõe dados de uma empresa a outra | Baixa | Crítico | RNF-016 e RN-001 como requisitos obrigatórios; testes de penetração cross-tenant antes de cada deploy | Rollback imediato; auditoria de logs; notificação às empresas afetadas conforme LGPD |
| R-03 | **API de WhatsApp indisponível ou bloqueada** — provedor externo pode suspender conta ou API | Média | Médio | Configuração do provedor em `.env` por empresa; sistema continua enviando e-mails mesmo sem WhatsApp | Trocar provedor sem alterar código (apenas `.env`); fallback para e-mail como canal único |
| R-04 | **Performance com alto volume** — 50.000 lançamentos/mês após 12 meses = 600.000+ linhas sem índices adequados | Média | Alto | Índices definidos no schema; queries revisadas com EXPLAIN; RNF-004 obrigatório | Adicionar índices compostos; implementar paginação via cursor; particionamento de tabelas históricas |
| R-05 | **Perda de dados por falha de backup** — backup não executado ou restore inválido | Baixa | Crítico | Script de backup diário automático (B-093); retenção de 30 dias; restore testado mensalmente (RNF-012) | Restaurar último backup válido; aceitar perda do dia se necessário; comunicar usuários afetados |
| R-06 | **Escopo crescente (scope creep)** — solicitações de novas funcionalidades fora do MVP durante o desenvolvimento | Alta | Médio | PRD com escopo explícito; "Fora do Escopo" documentado em 02-escopo-mvp.md; priorização formal pelo backlog | Registrar pedido no backlog como item futuro; não implementar durante o marco em andamento |
| R-07 | **Importação OFX com formato não padrão** — alguns bancos brasileiros geram OFX fora da especificação | Média | Médio | Parser tolerante a variações; testes com arquivos reais dos principais bancos (Itaú, Bradesco, BB, Sicoob) | Suporte manual ao formato do banco; opção de importação via CSV como fallback |
| R-08 | **Integração futura de pagamento real (boleto/PIX)** — APIs de gateways mudam frequentemente | Baixa | Médio | Módulo de pagamento desacoplado (fora do MVP); abstração por interface ao implementar | Isolar código de gateway em adapter; troca de provedor sem impacto no restante do sistema |
| R-09 | **Conformidade LGPD não atendida a tempo** — funcionalidades de privacidade atrasadas por depriorizacao | Média | Alto | RF-047 a RF-049 são requisitos de prioridade média no backlog; não podem ser indefinidamente adiados | Implementar em paralelo ao Marco M2; não lançar em produção com dados reais sem o aceite de termo implementado |
| R-10 | **Falha no envio da régua de cobrança** — job de cron não executa ou API externa cai silenciosamente | Média | Médio | Log de execução do cron; alertas por e-mail em falha do job; histórico de disparos permite auditoria | Disparar manualmente via interface admin; reprocessar parcelas não notificadas do dia |

---

## 2. Premissas

| ID | Premissa |
|----|---------|
| P-01 | O servidor de produção (VPS/on-premises) terá PHP 8.1+, MySQL 8+, suporte a cron jobs, extensões `pdo_mysql`, `mbstring`, `openssl`, `fileinfo` habilitadas |
| P-02 | O servidor terá acesso à internet para envio de e-mails via SMTP externo e chamadas à API de WhatsApp |
| P-03 | O domínio e o certificado SSL/TLS estarão configurados antes do deploy em produção (HTTPS obrigatório para cookies Secure) |
| P-04 | As credenciais SMTP (host, porta, usuário, senha) serão fornecidas pelo cliente antes do Marco M4 |
| P-05 | O cliente definirá o provedor de WhatsApp (Z-API, WPP Connect ou Twilio) antes do Marco M4 e fornecerá as credenciais |
| P-06 | O schema do banco (`database/schema.sql`) é a fonte de verdade; alterações estruturais são feitas via migration SQL versionada |
| P-07 | O sistema não terá self-service de cadastro de novas empresas no MVP; o admin do SaaS cria as empresas manualmente |
| P-08 | Não há SLA de atendimento a usuários finais no MVP; suporte é prestado diretamente pelo desenvolvedor |
| P-09 | Os dados iniciais de teste (seeds) existentes no schema.sql serão removidos antes do deploy em produção |
| P-10 | O cliente validará cada marco antes que o próximo seja iniciado |

---

## 3. Dependências Externas

| ID | Dependência | Tipo | Impacto se não disponível | Responsável |
|----|------------|------|--------------------------|-------------|
| DE-01 | Servidor SMTP externo (ex.: Brevo, Mailgun, Gmail SMTP) | Externa | Régua de cobrança por e-mail não funciona (Marco M4 bloqueado) | Cliente |
| DE-02 | Conta em provedor de WhatsApp API | Externa | Régua de cobrança via WhatsApp não funciona (canal degradado para e-mail) | Cliente |
| DE-03 | Certificado SSL no servidor de produção | Infraestrutura | Cookies Secure não funcionam; HTTPS indisponível | Cliente / DevOps |
| DE-04 | Acesso SSH ao servidor de produção para configuração do cron | Infraestrutura | Jobs de atualização e régua não executam automaticamente | Cliente / DevOps |
| DE-05 | Arquivos OFX reais dos bancos do cliente (para testes) | Funcional | Parser OFX não pode ser validado com dados reais (Marco M7) | Cliente |
| DE-06 | Composer (PHP) instalado no servidor de produção ou pacotes vendorizados | Técnica | Deploy não funciona sem dependências | Desenvolvedor |

---

## 4. Plano de Contingência Geral

Em caso de incidente crítico em produção (vazamento de dados, indisponibilidade > 2h):

1. **Isolar:** Derrubar a aplicação (`maintenance.php` ou regra no `.htaccess`) para bloquear acesso
2. **Diagnosticar:** Acessar `log_acoes` e logs de erro do PHP/Apache para identificar a causa
3. **Corrigir ou Restaurar:** Aplicar hotfix ou restaurar backup mais recente
4. **Comunicar:** Notificar empresas afetadas por e-mail dentro de 72h (exigência LGPD para incidentes com dados pessoais)
5. **Documentar:** Registrar o incidente com causa raiz, ações tomadas e medidas preventivas
