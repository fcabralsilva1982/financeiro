# Preparação da avaliação Strix — financeiro

Data: 2026-09-10. Estado: preparação concluída; pentest não executado.

## Base da avaliação

Workspace: `C:/laragon/www/financeiro`, branch `main`, HEAD `937331fcef651518f6f1179e441791e71296ac44`. Há alterações locais em controllers/conciliacao.php, models/Conciliacao.php, views/conciliacao/index.php, tests/Integration/ConciliacaoTest.php e traduções en/pt-br, além de arquivos novos de skills/agente. O alvo proposto é uma cópia do estado de trabalho, incluindo essas alterações, com manifesto e hashes registrados antes da execução. Não avaliar somente HEAD e concluir sobre a conciliação atual.

Foram lidas as skills application-security-testing, penetration-testing-with-strix, find-security-vulnerabilities-in-code e web-app-penetration-testing, além do agente Strix Security. Esta preparação não instalou ferramentas, iniciou scans, acessou o banco, executou testes ou enviou o código ao serviço Strix.

## Pré-requisitos verificados

| Item | Resultado |
| --- | --- |
| Skills e agente | Presentes no projeto; nove referências no agente |
| CLI Strix no Windows | Não encontrada no PATH; versão e flags instaladas não verificáveis |
| Docker no Windows | Não encontrado no PATH nem no caminho padrão do Docker Desktop; daemon não confirmado |
| STRIX_LLM e LLM_API_KEY | Ausentes no ambiente do processo PowerShell; não foram procuradas chaves em outros arquivos |
| Cloud | STRIX_API_TOKEN ausente no processo e arquivo padrão de autenticação Windows ausente; conta/créditos não verificados |
| WSL | Ubuntu: Docker em /usr/bin/docker e daemon respondendo, versão 29.6.2. Strix não encontrado por command -v. STRIX_LLM, LLM_API_KEY e STRIX_API_TOKEN ausentes na sessão verificada |
| PHP CLI | 8.1.10; módulos PDO MySQL, mbstring, curl, fileinfo, openssl e XML presentes |
| Composer / PHPUnit | Composer no PATH e vendor/bin/phpunit presente; suíte não executada |
| Servidor local | Não foi observado listener Windows nas portas 80, 443, 3306, 8000 ou 8080 no momento da inspeção; outros hosts/portas não avaliados |
| Configuração da aplicação | config/config.php:50 referencia C:/laragon/financeiro_param/.env, existente; .env na raiz ausente. Conteúdo sensível não copiado |
| Banco de teste | tests/bootstrap.php declara financeiro_erp_test; existência, isolamento e conexão não confirmados |
| Contas de teste | Não provisionadas ou verificadas |
| API formal | Nenhum arquivo OpenAPI/Swagger/Postman encontrado pelo levantamento de nomes fora de vendor |

A suíte de integração cria banco e limpa tabelas (tests/DatabaseTestCase.php:21 e :64). Só executar depois de verificar o destino efetivo. O arquivo de configuração externo também é lido no bootstrap: a cópia de avaliação deve ter configuração própria, armazenamento próprio e nenhuma conexão com serviços reais.

## Escopo proposto

Aplicação PHP/MySQL com roteamento por `index.php?page=...&action=...`. O alvo é o código próprio e uma instância isolada; não há necessidade inicial de inventar uma API REST independente.

| Prioridade | Superfície real | Verificação proposta |
| --- | --- | --- |
| 1 | index.php:21, :31, :55; helpers/functions.php:519, :630, :667 | Autenticação, permissão por ação, empresa ativa e acesso direto às rotas |
| 1 | controllers/auth.php:176, :311, :321, :370 | Login, escolha/troca de empresa, sessão, CSRF e vínculos permitidos |
| 1 | clientes, fornecedores, usuarios, perfis, empresas, logs, dashboard | Leitura e escrita entre empresas, privilégios master e escopo dos filtros |
| 1 | controllers/contas_pagar.php:175, :201, :232, :257 | Envio/aprovação, cancelamento, baixa, segregação de funções e repetição de operações em títulos fictícios |
| 1 | controllers/contas_receber.php:139, :169, :191 | Alteração de valores, exclusão e baixa; objeto e empresa autorizados |
| 1 | controllers/conciliacao.php:63, :90; models/Conciliacao.php | Importação OFX, conciliar_receber, conciliar_pagar, movimento_interno, ignorar; duplicidade, vínculo e consistência de saldos |
| 2 | Documentos de clientes/fornecedores, comprovantes e logo | Autorização por objeto, MIME, tamanho, caminho de arquivo e download entre empresas |
| 2 | Controllers, models, views, helpers | SQL parametrizado, XSS contextual, CSRF, parâmetros manipulados e erros |
| 2 | .htaccess, config, scripts, jobs, dependências | Exposição de caminhos internos, diagnóstico, configuração e revisão complementar de dependências |

Os itens acima são hipóteses de teste, não vulnerabilidades confirmadas. Na conciliação, conferir os nomes das ações atuais contra o mapeamento central de permissões. Conferir também a distinção entre pertencer à empresa master e ser superadmin, comparando implementação e regras RN-001/RN-002. A leitura inicial não demonstra exploração nem impacto.

## Dados e exclusões

Preparar empresas fictícias A e B; operador limitado em A; operador em B; aprovador em A; conta master; usuário com vínculo nas duas empresas. Semear títulos, parcelas, documentos e extratos sintéticos com proprietários conhecidos. Testar tanto acesso permitido quanto negado, mantendo identificadores para reprodução.

Excluídos: produção, dados pessoais reais, movimentação bancária efetiva, envio de mensagens, integrações de terceiros, testes de carga/negação de serviço, exclusões em massa e alterações no workspace original. Escritas ficam restritas aos registros sintéticos do ambiente descartável. CI, correções e retestes são fases posteriores, fora desta preparação.

## Modalidade e roteiro

Proposta: execução local pelo Ubuntu/WSL, aproveitando o Docker já funcional, condicionada à disponibilização da CLI e à escolha do provedor de IA. A execução local ainda pode enviar contexto ao provedor do modelo. Cloud é alternativa se o usuário preferir, após revisar arquivos a enviar, autenticação, conectividade do alvo e custos; não é fallback automático.

1. Resolver infraestrutura e selecionar modelo/provedor, URL de teste e orçamento.
2. Preparar snapshot sanitizado, configuração e banco exclusivos; confirmar conexão efetiva, contas e conectividade do scanner.
3. Rodar avaliação standard com código completo e instância de teste, foco nas prioridades 1; revisar cobertura antes de ampliar.
4. Conferir cada PoC e registrar impacto, evidências sanitizadas e cobertura ausente.

Proposta de orçamento inicial: `--max-budget 15` (USD), ainda não autorizado. Não é estimativa de conclusão: a documentação informa que a contabilização é aproximada e chamadas em andamento podem ultrapassar o limiar. Não retomar ou aumentar automaticamente o orçamento.

Modelo de comando para executar dentro do Ubuntu/WSL, deliberadamente pendente de caminhos Linux/URL reais e confirmação de `strix --help` na versão instalada:

```bash
strix -n -t '<COPIA_ISOLADA>' -t '<URL_HOMOLOGACAO_ACESSIVEL_PELO_SCANNER>' --scan-mode standard --scope-mode full --max-budget 15 --instruction-file '<ARQUIVO_PRIVADO_DE_ESCOPO_E_CONTAS>'
```

Não executar com placeholders. O arquivo privado deve limitar hosts e operações aos dados sintéticos descritos acima. Nenhum arquivo contendo credenciais deve entrar no repositório ou relatório.

## Critério de entrega do scan futuro

Relatório com alvo/snapshot, perfis usados, severidade, arquivo/linha ou endpoint, PoC conferida, impacto e correção sugerida. Separar achados reproduzidos de observações estáticas e áreas não testadas. Conferir run.json, status, custo e limites; ausência de achados ou saída zero não comprova cobertura completa. Manter artefatos em diretório privado e compartilhar somente versões sanitizadas.

## Pendências para execução

- Modalidade e provedor/modelo selecionados; CLI e infraestrutura funcionais.
- URL e banco de homologação isolados confirmados; contas sintéticas disponíveis.
- Escopo ativo e orçamento aceitos pelo usuário após revisar esta proposta.

Referências oficiais consultadas: [pré-requisitos](https://docs.strix.ai/quickstart), [CLI, escopo completo e limites de orçamento](https://docs.strix.ai/usage/cli). Os comandos foram conferidos na documentação, não em uma instalação local Strix.
