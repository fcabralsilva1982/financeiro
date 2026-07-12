# 13 - Governanca de Dependencias e CI de Seguranca

## Objetivo
Padronizar o controle de versoes de dependencias e o gate de seguranca na esteira de CI para reduzir risco de supply chain.

## Escopo
- Politica de atualizacao de dependencias PHP
- Execucao obrigatoria de auditoria de vulnerabilidades
- Evidencias minimas para aprovar mudancas em dependencias

## Politica de dependencias
- Versao minima atual do PHPUnit: `9.6.35`.
- Em `composer.json`, manter faixa segura para PHPUnit: `^9.6.33` ou superior.
- Atualizacoes de dependencias devem ser feitas com lockfile versionado (`composer.lock`).
- Toda atualizacao de dependencia deve registrar:
  - pacote alterado
  - versao anterior e nova versao
  - resultado do `composer audit`

## Regras de CI (obrigatorias)
- Pipeline deve executar, no minimo:
  1. `composer install --no-interaction --prefer-dist`
  2. `composer audit --no-interaction`
  3. `vendor/bin/phpunit`
- O job deve falhar se `composer audit` retornar vulnerabilidade.
- O job deve falhar se a bateria de testes falhar.

## Gate de aprovacao para PR
- PR so pode ser aprovado quando:
  - `composer audit` sem findings criticos
  - testes relevantes executados com evidencia no PR
  - arquivo `composer.lock` consistente com `composer.json`
- Se houver falhas legadas fora do escopo da mudanca, o PR deve:
  - documentar quais falhas sao legadas
  - anexar execucao focada comprovando o escopo alterado

## Comandos de referencia
```bat
composer audit --no-interaction
vendor\bin\phpunit.bat
```

## Evidencia atual (2026-07-12)
- PHPUnit atualizado para `9.6.35`.
- `composer audit --no-interaction`: sem vulnerabilidades encontradas.
- Execucao focada de seguranca: OK (51 testes, 85 assertions).
