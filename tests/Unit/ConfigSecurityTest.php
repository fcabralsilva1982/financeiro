<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class ConfigSecurityTest extends TestCase
{
    private function runConfigInIsolatedProcess(array $env): array
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'cfg-sec-');
        if ($tempFile === false) {
            $this->fail('Nao foi possivel criar arquivo temporario para teste de configuracao.');
        }

        $configPath = realpath(__DIR__ . '/../../config/config.php');
        $envExport = var_export($env, true);

        $script = "<?php\n"
            . "declare(strict_types=1);\n"
            . "\$_SERVER['HTTP_HOST'] = 'localhost';\n"
            . "\$_SERVER['HTTPS'] = '';\n"
            . "\$_SERVER['REQUEST_URI'] = '/financeiro/index.php';\n"
            . "\$_SERVER['REQUEST_METHOD'] = 'GET';\n"
            . "\$_SERVER['REMOTE_ADDR'] = '127.0.0.1';\n"
            . "\$_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';\n"
            . "\$_ENV = array_merge(\$_ENV, {$envExport});\n"
            . "try {\n"
            . "    require " . var_export($configPath, true) . ";\n"
            . "    echo 'OK';\n"
            . '} catch (Throwable $e) {' . "\n"
            . "    echo 'ERR:' . " . '$e->getMessage();' . "\n"
            . "    exit(2);\n"
            . "}\n";

        file_put_contents($tempFile, $script);

        $output = [];
        $exitCode = 0;
        $cmd = escapeshellarg((string) PHP_BINARY) . ' ' . escapeshellarg($tempFile) . ' 2>&1';
        exec($cmd, $output, $exitCode);

        @unlink($tempFile);

        return [
            'exitCode' => $exitCode,
            'output' => trim(implode("\n", $output)),
        ];
    }

    public function test_producao_falha_quando_app_secret_e_padrao(): void
    {
        $result = $this->runConfigInIsolatedProcess([
            'APP_DEBUG' => 'false',
            'APP_SECRET' => 'change-me',
            'DB_PASS' => 'senha-db-segura',
        ]);

        $this->assertSame(2, $result['exitCode']);
        $this->assertStringContainsString('APP_SECRET inseguro para producao', $result['output']);
    }

    public function test_producao_falha_quando_db_pass_ausente(): void
    {
        $result = $this->runConfigInIsolatedProcess([
            'APP_DEBUG' => 'false',
            'APP_SECRET' => str_repeat('a', 32),
            'DB_PASS' => '',
        ]);

        $this->assertSame(2, $result['exitCode']);
        $this->assertStringContainsString('DB_PASS inseguro para producao', $result['output']);
    }

    public function test_producao_falha_quando_smtp_habilitado_sem_senha(): void
    {
        $result = $this->runConfigInIsolatedProcess([
            'APP_DEBUG' => 'false',
            'APP_SECRET' => str_repeat('b', 32),
            'DB_PASS' => 'senha-db-segura',
            'SMTP_HOST' => 'smtp.exemplo.com',
            'SMTP_PASS' => '',
        ]);

        $this->assertSame(2, $result['exitCode']);
        $this->assertStringContainsString('SMTP_PASS inseguro para producao', $result['output']);
    }

    public function test_producao_falha_quando_api_token_for_obrigatorio_e_ausente(): void
    {
        $result = $this->runConfigInIsolatedProcess([
            'APP_DEBUG' => 'false',
            'APP_SECRET' => str_repeat('c', 32),
            'DB_PASS' => 'senha-db-segura',
            'API_TOKEN_REQUIRED' => 'true',
            'API_TOKEN' => '',
        ]);

        $this->assertSame(2, $result['exitCode']);
        $this->assertStringContainsString('API_TOKEN inseguro para producao', $result['output']);
    }

    public function test_producao_inicializa_quando_requisitos_estao_ok(): void
    {
        $result = $this->runConfigInIsolatedProcess([
            'APP_DEBUG' => 'false',
            'APP_SECRET' => str_repeat('d', 32),
            'DB_PASS' => 'senha-db-segura',
            'SMTP_HOST' => 'smtp.exemplo.com',
            'SMTP_PASS' => 'senha-smtp-segura',
            'API_TOKEN_REQUIRED' => 'true',
            'API_TOKEN' => 'token-seguro',
        ]);

        $this->assertSame(0, $result['exitCode'], $result['output']);
        $this->assertStringContainsString('OK', $result['output']);
    }
}
