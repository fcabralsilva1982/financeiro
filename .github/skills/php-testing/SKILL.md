---
name: php-testing
description: 'Escrever, revisar ou executar testes em projetos PHP. Use para: criar testes unitários com PHPUnit, testes de integração com PDO/SQLite, mocks de dependências, data providers, cobertura de código, testar autenticação, validações, repositórios e regras de negócio.'
argument-hint: 'Descreva o que testar (ex: "teste unitário para AuthService", "testar repositório PDO de usuários")'
---

# PHP Testing — PHPUnit

## Estrutura

```
tests/
├── Unit/           classe/função isolada (sem I/O real)
├── Integration/    com banco real ou SQLite :memory:
├── Feature/        fluxo completo via HTTP
└── Fixtures/       dados estáticos reutilizáveis
```

## Configuração mínima (`phpunit.xml`)

```xml
<phpunit bootstrap="vendor/autoload.php" colors="true">
    <testsuites>
        <testsuite name="Unit"><directory>tests/Unit</directory></testsuite>
        <testsuite name="Integration"><directory>tests/Integration</directory></testsuite>
    </testsuites>
    <source><include><directory>src</directory></include></source>
</phpunit>
```

## Padrões obrigatórios

- Nome: `test_<contexto>_<ação>_<resultado_esperado>()`
- Um assert principal por teste
- `setUp()` para estado compartilhado; `tearDown()` para limpeza
- `@dataProvider` para múltiplos cenários do mesmo comportamento
- Nunca usar banco de produção; usar SQLite `:memory:` ou banco de teste isolado
- Mocks apenas para dependências externas (I/O, e-mail, APIs)

## Exemplo — Unitário com mock

```php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

class AuthServiceTest extends TestCase
{
    private UserRepository&MockObject $users;
    private AuthService $auth;

    protected function setUp(): void
    {
        $this->users = $this->createMock(UserRepository::class);
        $this->auth  = new AuthService($this->users);
    }

    public function test_login_valido_retorna_true(): void
    {
        $this->users->method('findByEmail')
            ->willReturn(['id' => 1, 'senha' => password_hash('secret', PASSWORD_BCRYPT)]);
        $this->assertTrue($this->auth->login('user@example.com', 'secret'));
    }

    public function test_senha_incorreta_retorna_false(): void
    {
        $this->users->method('findByEmail')
            ->willReturn(['id' => 1, 'senha' => password_hash('correct', PASSWORD_BCRYPT)]);
        $this->assertFalse($this->auth->login('user@example.com', 'wrong'));
    }
}
```

## Exemplo — Integração com PDO/SQLite

```php
declare(strict_types=1);
use PHPUnit\Framework\TestCase;

class UserRepositoryTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->exec("CREATE TABLE usuarios (
            id INTEGER PRIMARY KEY,
            email TEXT UNIQUE NOT NULL,
            senha TEXT NOT NULL
        )");
    }

    public function test_inserir_e_buscar_usuario(): void
    {
        $repo = new UserRepository($this->pdo);
        $repo->inserir('teste@exemplo.com', password_hash('123', PASSWORD_BCRYPT));
        $usuario = $repo->findByEmail('teste@exemplo.com');
        $this->assertSame('teste@exemplo.com', $usuario['email']);
    }
}
```

## Exemplo — Data Provider

```php
/** @dataProvider provedorEmailsInvalidos */
public function test_email_invalido_lanca_excecao(string $email): void
{
    $this->expectException(InvalidArgumentException::class);
    validar_email($email);
}

public static function provedorEmailsInvalidos(): array
{
    return [[''], ['nao-e-email'], ['@dominio.com'], ['usuario@']];
}
```

## Comandos

```bash
./vendor/bin/phpunit                          # todos os testes
./vendor/bin/phpunit --testsuite Unit         # só unitários
./vendor/bin/phpunit --filter test_login      # por nome
./vendor/bin/phpunit --coverage-html coverage/ # cobertura HTML
```
