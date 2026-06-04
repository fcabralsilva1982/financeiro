# 04 — Requisitos Não Funcionais

**Versão:** 1.0.0  
**Data:** 2026-06-04

---

## Legenda de Categorias

| Categoria | Sigla |
|-----------|-------|
| Performance | PERF |
| Segurança | SEG |
| Disponibilidade | DISP |
| Observabilidade | OBS |
| Escalabilidade | ESC |
| Manutenibilidade | MAN |
| Conformidade | CONF |

---

## Requisitos

| ID | Categoria | Requisito | Meta Objetiva | Forma de Validação |
|----|-----------|-----------|--------------|-------------------|
| RNF-001 | PERF | Listagens (contas a receber, a pagar, clientes, fornecedores) com até 1.000 registros devem carregar dentro do limite de tempo estabelecido. | Tempo de resposta p95 < 800ms | Teste de carga com Apache Bench: 50 usuários simultâneos, 100 requisições cada |
| RNF-002 | PERF | Dashboard com todos os KPIs calculados deve carregar dentro do limite de tempo. | Tempo de resposta p95 < 1.200ms | Medição com DevTools Network tab em ambiente de staging com 10.000 registros no banco |
| RNF-003 | PERF | Importação de CSV com até 5.000 linhas deve ser processada sem timeout. | Conclusão em < 120 segundos | Teste com arquivo de 5.000 linhas; `max_execution_time` configurado em 300s |
| RNF-004 | PERF | Consultas ao banco de dados em tabelas financeiras devem usar índices; nenhuma query deve fazer full table scan em tabelas com > 10.000 registros. | Zero full table scans em produção | EXPLAIN nas queries críticas; alertas via slow query log (> 500ms) |
| RNF-005 | SEG | Todas as queries ao banco devem usar PDO com prepared statements parametrizados; interpolação direta de variáveis em SQL é proibida. | Zero ocorrências de SQL injection | Code review; testes de OWASP ZAP nos endpoints |
| RNF-006 | SEG | Todas as requisições que modificam estado (POST, formulários) devem validar token CSRF via `hash_equals`. | 100% das rotas POST protegidas | Revisão manual de todos os controllers; tentativa de POST sem token deve retornar 403 |
| RNF-007 | SEG | Senhas de usuários devem ser armazenadas exclusivamente com hash bcrypt (custo ≥ 12). Senhas nunca são logadas ou expostas em respostas. | 100% das senhas em bcrypt custo ≥ 12 | Inspeção do schema e do código de `password_hash`; nenhum log deve conter o campo `senha` |
| RNF-008 | SEG | Upload de arquivos (logos, comprovantes, CSVs) deve validar extensão e MIME type no servidor; arquivos enviados não devem ser acessíveis diretamente via URL pública sem autenticação. | Nenhum arquivo executável aceito | Teste de upload de `.php` deve retornar erro; comprovantes servidos via controller autenticado |
| RNF-009 | SEG | Headers de segurança HTTP devem estar presentes em todas as respostas: `Content-Security-Policy`, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`. | 100% das respostas com headers corretos | Verificação com securityheaders.com ou `curl -I` |
| RNF-010 | SEG | Sessões devem expirar após 2 horas de inatividade. Cookie de sessão deve ter flags `HttpOnly`, `SameSite=Strict` e `Secure` (em produção HTTPS). | Sessão inativa por 2h invalida automaticamente | Teste manual: aguardar 2h sem atividade e verificar redirecionamento para login |
| RNF-011 | DISP | O sistema deve funcionar em servidor único (VPS) com uptime ≥ 99,5% mensal, excluindo janelas de manutenção programada. | Uptime ≥ 99,5% | Monitoramento com UptimeRobot ou similar; alertas por e-mail em queda |
| RNF-012 | DISP | O banco de dados deve ter backup automático diário com retenção de 30 dias. Restore deve ser testado mensalmente. | Backup diário; restore testado 1x/mês | Script de backup configurado no cron; log de execução do backup armazenado |
| RNF-013 | OBS | Todos os erros PHP (exceptions não tratadas, erros fatais) devem ser capturados e gravados em arquivo de log com stack trace. Em produção, o usuário vê página genérica de erro. | 100% dos erros gravados em log | Forçar exception e verificar arquivo de log; usuário não deve ver stack trace em produção |
| RNF-014 | OBS | Ações críticas (criar/editar/excluir lançamentos financeiros, login/logout, aprovação de contas) devem ser registradas na tabela `log_acoes` com snapshot de dados anteriores e novos. | 100% das ações críticas logadas | Verificação unitária de cada ação crítica; `dados_anteriores` e `dados_novos` preenchidos nos logs de edição |
| RNF-015 | OBS | Disparos da régua de cobrança devem registrar em `contas_receber_historico`: canal, data, status (sucesso/falha) e resposta da API externa. | 100% dos disparos registrados | Verificação no banco após cada disparo; inclusive falhas de envio |
| RNF-016 | ESC | A arquitetura de multitenancy deve garantir isolamento completo de dados: queries em tabelas financeiras sempre filtradas por `empresa_id` da sessão. | Zero vazamento cross-tenant | Teste de penetração: usuário do tenant A não deve acessar dados do tenant B via manipulação de parâmetros |
| RNF-017 | ESC | O sistema deve suportar até 100 empresas ativas simultâneas e 500 usuários, com volumes de até 50.000 lançamentos/mês, sem degradação além dos limites de RNF-001. | Limites de RNF-001 mantidos sob carga descrita | Teste de carga com banco populado com 12 meses de dados no volume máximo |
| RNF-018 | MAN | O código PHP deve seguir PSR-12 de formatação. Funções devem ter responsabilidade única, máximo de 50 linhas. Nenhuma query deve estar embutida em views. | Sem violações PSR-12 críticas | PHP_CodeSniffer no CI; nenhuma query em arquivos de `views/` |
| RNF-019 | MAN | `declare(strict_types=1)` deve estar presente em todos os arquivos PHP. Parâmetros e retornos de funções devem ter tipos declarados. | 100% dos novos arquivos com strict_types | Grep automático no CI |
| RNF-020 | CONF | O sistema deve tratar dados pessoais (nome, e-mail, CPF, telefone) de acordo com a LGPD: finalidade definida, consentimento registrado para comunicações, opção de opt-out e exclusão/anonimização a pedido. | Conformidade LGPD verificada | Checklist LGPD revisado; campos `opt_out_lembretes` e aceite de termo implementados e auditáveis |
| RNF-021 | CONF | Comunicações por e-mail devem incluir link de descadastramento (unsubscribe) que atualiza `opt_out_lembretes = 1` sem necessidade de login. | 100% dos e-mails com link unsubscribe | Teste de envio: verificar presença do link; clicar no link deve atualizar o banco |
