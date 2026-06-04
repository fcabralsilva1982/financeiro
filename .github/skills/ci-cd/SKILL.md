---
name: ci-cd
description: 'Configurar ou revisar pipeline de CI/CD para projetos PHP com GitHub Actions. Use para: rodar testes automatizados, análise estática, auditoria de dependências, lint, deploy automatizado, variáveis de ambiente seguras, ambientes de staging e produção.'
argument-hint: 'Descreva o que precisa (ex: "pipeline de testes PHP", "deploy automático no push para main", "auditoria de dependências no CI")'
---

# CI/CD — GitHub Actions + PHP

## Estrutura de workflows

```
.github/
└── workflows/
    ├── ci.yml       # roda em todo PR e push: testes, lint, audit
    └── deploy.yml   # roda só em push na main: deploy para produção
```

## `ci.yml` — Pipeline de qualidade

```yaml
name: CI

on:
  push:
    branches: [main, develop]
  pull_request:
    branches: [main, develop]

jobs:
  testes:
    name: Testes e Qualidade
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: app_test
        ports: ["3306:3306"]
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Instalar PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: "8.3"
          extensions: pdo, pdo_mysql, mbstring, intl
          coverage: xdebug

      - name: Cache Composer
        uses: actions/cache@v4
        with:
          path: vendor
          key: composer-${{ hashFiles('composer.lock') }}

      - name: Instalar dependências
        run: composer install --no-interaction --prefer-dist

      - name: Auditoria de segurança
        run: composer audit

      - name: Análise estática (PHPStan)
        run: ./vendor/bin/phpstan analyse --level=8

      - name: Testes
        env:
          DB_HOST: 127.0.0.1
          DB_NAME: app_test
          DB_USER: root
          DB_PASS: root
        run: ./vendor/bin/phpunit --coverage-clover coverage.xml

      - name: Upload de cobertura
        uses: codecov/codecov-action@v4
        with:
          files: coverage.xml
```

## `deploy.yml` — Deploy para produção

```yaml
name: Deploy

on:
  push:
    branches: [main]

jobs:
  deploy:
    name: Deploy Produção
    runs-on: ubuntu-latest
    environment: producao   # requer aprovação manual configurada no repo

    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Instalar PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: "8.3"

      - name: Instalar dependências (sem dev)
        run: composer install --no-dev --optimize-autoloader --no-interaction

      - name: Auditoria de segurança
        run: composer audit

      - name: Deploy via SSH
        uses: appleboy/ssh-action@v1
        with:
          host:     ${{ secrets.PROD_HOST }}
          username: ${{ secrets.PROD_USER }}
          key:      ${{ secrets.PROD_SSH_KEY }}
          script: |
            cd /var/www/app
            git pull origin main
            composer install --no-dev --optimize-autoloader --no-interaction
            php artisan migrate --force 2>/dev/null || true
```

## Variáveis de ambiente e segredos

- Nunca colocar segredos em `env:` no YAML em claro — usar **GitHub Secrets**
- Configurar em: Repo → Settings → Secrets and variables → Actions

| Secret | Descrição |
|--------|-----------|
| `PROD_HOST` | IP ou hostname do servidor |
| `PROD_USER` | Usuário SSH de deploy |
| `PROD_SSH_KEY` | Chave privada SSH (sem passphrase) |
| `SMTP_PASS` | Senha do SMTP de produção |

- Usar **Environments** (`producao`, `staging`) para exigir aprovação manual antes do deploy
- Rotacionar chaves de deploy a cada 90 dias

## Boas práticas

- Fixar versões de actions: `actions/checkout@v4` (nunca `@main` ou `@latest`)
- Cache de dependências (`actions/cache`) para acelerar builds
- Separar jobs: testes e lint em paralelo; deploy só após aprovação
- Falhar rápido: `composer audit` antes dos testes (não instalar dependências vulneráveis)
- Artefatos de cobertura: fazer upload para Codecov ou similar
- Branch protection: exigir CI verde antes de merge em `main`

## Branch protection (configurar no GitHub)

```
main:
  - Require status checks to pass: CI / Testes e Qualidade
  - Require pull request reviews: 1 aprovação
  - Dismiss stale reviews on new commits
  - Require branches to be up to date
  - Restrict who can push: apenas admins
```

## Checklist antes de fazer push para main

- [ ] Testes passando localmente (`./vendor/bin/phpunit`)
- [ ] `composer audit` sem vulnerabilidades críticas/altas
- [ ] Sem segredos no código (`git diff` revisado)
- [ ] `.env` no `.gitignore` e não trackeado
- [ ] PR revisado e aprovado
- [ ] Migrations testadas em staging antes de produção
