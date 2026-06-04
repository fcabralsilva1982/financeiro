---
name: git-workflow
description: 'Convenções de Git para projetos web. Use para: escrever mensagens de commit, nomear branches, fazer PR/code review, versionamento semântico, gitignore, estratégia de branching, resolução de conflitos e boas práticas de histórico limpo.'
argument-hint: 'Descreva o que precisa (ex: "mensagem de commit para nova tela de login", "nomear branch para correção de bug")'
---

# Git Workflow

## Estrutura de branches

```
main          produção (protegida; só via PR)
develop       integração contínua
feature/      nova funcionalidade
fix/          correção de bug
hotfix/       correção urgente em produção
chore/        tarefas técnicas (deps, config, refactor)
```

- `main` e `develop` são permanentes; demais são temporárias
- Nunca commitar diretamente em `main`
- Hotfix parte de `main` e é mergeado em `main` + `develop`

## Nomenclatura de branches

```
feature/login-com-google
feature/modulo-relatorios
fix/csrf-token-expirado
fix/upload-mime-validation
hotfix/sql-injection-busca
chore/atualizar-phpmailer
chore/configurar-github-actions
```

- Kebab-case; sem espaços; descritivo e curto
- Prefixo obrigatório (`feature/`, `fix/`, `hotfix/`, `chore/`)

## Mensagens de commit — Conventional Commits

```
<tipo>(<escopo>): <descrição curta no imperativo>

[corpo opcional — explica O QUÊ e POR QUÊ, não o como]

[rodapé opcional — refs, breaking changes]
```

### Tipos

| Tipo | Quando usar |
|------|-------------|
| `feat` | Nova funcionalidade |
| `fix` | Correção de bug |
| `security` | Correção de vulnerabilidade |
| `refactor` | Refatoração sem mudança de comportamento |
| `test` | Adição ou correção de testes |
| `chore` | Tarefas técnicas (deps, build, config) |
| `docs` | Documentação |
| `style` | Formatação (sem lógica alterada) |
| `perf` | Melhoria de performance |

### Exemplos

```
feat(auth): adicionar rate limiting no endpoint de login

Bloqueia por 15 min após 5 tentativas falhas por IP+conta.
Registra eventos na tabela tentativas_login.

Refs #42
```

```
fix(upload): validar MIME real com finfo_file

Substituído mime_content_type() que dependia de extensão.
Corrige bypass via renomeação de arquivo .php para .jpg.

Security: CWE-434
```

```
security(csrf): regenerar token após uso único

Token anterior era reutilizável na mesma sessão.
Agora é invalidado após validação bem-sucedida.
```

## Fluxo de trabalho

```bash
# 1. Criar branch a partir de develop
git checkout develop && git pull
git checkout -b feature/nome-da-feature

# 2. Commits atômicos e descritivos
git add -p                        # adicionar por partes (evitar commits gigantes)
git commit -m "feat(modulo): descrição"

# 3. Manter branch atualizada
git fetch origin
git rebase origin/develop         # preferir rebase a merge para branches de feature

# 4. Abrir PR
# - título: mesmo padrão de commit
# - descrição: o quê, por quê, como testar
# - ao menos 1 aprovação antes de merge
```

## .gitignore mínimo para projetos PHP

```gitignore
# Dependências
/vendor/

# Ambiente
.env
.env.*
!.env.example

# Uploads e logs (dados, não código)
/uploads/
/logs/
*.log

# IDE
.idea/
.vscode/
*.swp

# Build/cache
/cache/
/tmp/
*.cache

# OS
.DS_Store
Thumbs.db
```

## Versionamento semântico (SemVer)

```
MAJOR.MINOR.PATCH  →  2.1.4
```

- **MAJOR:** quebra compatibilidade com versão anterior
- **MINOR:** nova funcionalidade retrocompatível
- **PATCH:** correção de bug retrocompatível

```bash
git tag -a v1.2.0 -m "feat: módulo de relatórios"
git push origin v1.2.0
```

## Boas práticas

- Commits atômicos: uma mudança lógica por commit
- Nunca commitar: `.env`, `/vendor`, senhas, chaves de API, arquivos gerados
- `git rebase -i` para limpar histórico antes de abrir PR (squash de WIP commits)
- `git stash` para mudanças temporárias; nomear stashes (`git stash push -m "wip: login"`)
- Revisar diff antes de commitar: `git diff --staged`
