<?php
declare(strict_types=1);

namespace Tests\Integration;

use Tests\DatabaseTestCase;

/**
 * Testes de segurança da aplicação.
 * Verifica proteções contra XSS, CSRF, brute force e isolamento de tenants.
 */
class SecurityTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('buscar_usuario_por_email')) {
            global $action;
            $action = '__noop__';
            require_once __DIR__ . '/../../controllers/auth.php';
        }
    }

    // =========================================================
    // Proteção XSS via e()
    // =========================================================

    public function test_csp_header_define_nonce_e_bloqueia_unsafe_inline_em_scripts(): void
    {
        $this->assertNotEmpty(CSP_HEADER_POLICY, 'Politica CSP deve estar definida');
        $this->assertMatchesRegularExpression('/script-src\\s+[^;]*\'nonce-[^\']+\'/', CSP_HEADER_POLICY);

        preg_match('/script-src\\s+([^;]+)/', CSP_HEADER_POLICY, $matches);
        $scriptSrc = $matches[1] ?? '';

        $this->assertStringNotContainsString("'unsafe-inline'", $scriptSrc);
        $this->assertStringContainsString("'self'", $scriptSrc);
    }

    public function test_csp_header_define_frame_ancestors_none(): void
    {
        $this->assertStringContainsString("frame-ancestors 'none'", CSP_HEADER_POLICY);
    }

    public function test_xss_em_escape_html(): void
    {
        $inputs = [
            '<script>alert("XSS")</script>',
            '<img src=x onerror=alert(1)>',
            '"><svg onload=alert(1)>',
            "javascript:alert('XSS')",
        ];

        foreach ($inputs as $input) {
            $escaped = e($input);
            $this->assertStringNotContainsString('<script', $escaped, "XSS não deve passar: $input");
            $this->assertStringNotContainsString('<img', $escaped);
            $this->assertStringNotContainsString('<svg', $escaped);
        }
    }

    // =========================================================
    // Proteção CSRF
    // =========================================================

    public function test_csrf_token_formato_seguro(): void
    {
        unset($_SESSION['csrf_token']);
        csrf_field();

        $token = $_SESSION['csrf_token'];
        $this->assertSame(64, strlen($token), 'Token CSRF deve ter 64 caracteres (32 bytes hex)');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token, 'Token deve ser hexadecimal');
    }

    public function test_csrf_tokens_sao_diferentes_por_sessao(): void
    {
        unset($_SESSION['csrf_token']);
        csrf_field();
        $token1 = $_SESSION['csrf_token'];

        // Simular nova sessão
        unset($_SESSION['csrf_token']);
        csrf_field();
        $token2 = $_SESSION['csrf_token'];

        $this->assertNotSame($token1, $token2, 'Tokens CSRF de sessões diferentes devem ser distintos');
    }

    // =========================================================
    // Senhas — bcrypt
    // =========================================================

    public function test_senha_hash_nunca_e_plaintext(): void
    {
        $senhas = ['123456', 'password', 'Senh@F0rte!2024', ''];

        foreach ($senhas as $senha) {
            if ($senha === '') continue; // password_hash não aceita vazio
            $hash = hash_senha($senha);
            $this->assertNotSame($senha, $hash);
            $this->assertStringStartsWith('$2y$', $hash);
        }
    }

    public function test_hashes_diferentes_para_mesma_senha(): void
    {
        $hash1 = hash_senha('MesmaSenha123');
        $hash2 = hash_senha('MesmaSenha123');

        // bcrypt gera salt diferente a cada chamada
        $this->assertNotSame($hash1, $hash2, 'Dois hashes da mesma senha devem ser diferentes (salt)');
        // Mas ambos devem verificar com a senha original
        $this->assertTrue(verificar_senha('MesmaSenha123', $hash1));
        $this->assertTrue(verificar_senha('MesmaSenha123', $hash2));
    }

    // =========================================================
    // Rate limiting no login
    // =========================================================

    public function test_rate_limiting_conta_tentativas(): void
    {
        $this->loginAsMaster();

        // Simular 5 falhas de login via log
        for ($i = 0; $i < 5; $i++) {
            registrar_log('auth', 'login_falha', 'Falha: attacker@evil.com', 'warning');
        }

        $count = contar_tentativas_recentes('127.0.0.1', 'attacker@evil.com', 900);
        $this->assertGreaterThanOrEqual(5, $count, 'Deve contar pelo menos 5 tentativas recentes');
    }

    // =========================================================
    // Isolamento de Tenant
    // =========================================================

    public function test_tenant_master_acessa_todas_empresas(): void
    {
        $_SESSION['user'] = ['empresa_id' => MASTER_EMPRESA_ID];
        $this->assertTrue(is_master_empresa());
        $this->assertTrue(can_see_all_empresas());
        $this->assertTrue(can_manage_empresas());
    }

    public function test_tenant_regular_nao_gerencia_empresas(): void
    {
        $_SESSION['user'] = ['empresa_id' => 'empresa-regular'];
        $this->assertFalse(is_master_empresa());
        $this->assertFalse(can_see_all_empresas());
        $this->assertFalse(can_manage_empresas());
    }

    public function test_tenant_filter_isola_dados(): void
    {
        $_SESSION['user'] = ['empresa_id' => 'empresa-a'];

        $records = [
            ['empresa_id' => 'empresa-a', 'dado' => 'visível'],
            ['empresa_id' => 'empresa-b', 'dado' => 'oculto'],
            ['empresa_id' => 'empresa-a', 'dado' => 'visível2'],
            ['empresa_id' => 'empresa-c', 'dado' => 'oculto2'],
        ];

        $filtered = array_values(filter_by_empresa($records));
        $this->assertCount(2, $filtered);

        foreach ($filtered as $r) {
            $this->assertSame('empresa-a', $r['empresa_id']);
        }
    }

    public function test_logo_local_usa_endpoint_controlado(): void
    {
        $url = montar_logo_empresa('logos/minha-logo.png', 'Empresa X', 'empresa-x');
        $this->assertStringContainsString('page=empresas', $url);
        $this->assertStringContainsString('action=logo', $url);
        $this->assertStringContainsString('id=empresa-x', $url);
    }

    public function test_logo_http_externa_mantem_url_original(): void
    {
        $logo = 'https://cdn.exemplo.com/logo.png';
        $result = montar_logo_empresa($logo, 'Empresa X', 'empresa-x');
        $this->assertSame($logo, $result);
    }

    public function test_upload_rate_limit_excedido_por_ip(): void
    {
        $this->loginAsMaster();
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_REAL_IP'], $_SERVER['HTTP_CLIENT_IP']);

        for ($i = 0; $i < 3; $i++) {
            registrar_log('upload', 'tentativa_logo', 'Teste de rate limit', 'info');
        }

        $this->assertTrue(upload_rate_limit_exceeded('logo', '127.0.0.1', 900, 3));
    }

    public function test_upload_rate_limit_nao_excedido_abaixo_do_limite(): void
    {
        $this->loginAsMaster();
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        unset($_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_X_REAL_IP'], $_SERVER['HTTP_CLIENT_IP']);

        for ($i = 0; $i < 2; $i++) {
            registrar_log('upload', 'tentativa_logo', 'Teste de rate limit', 'info');
        }

        $this->assertFalse(upload_rate_limit_exceeded('logo', '127.0.0.1', 900, 3));
    }

    // =========================================================
    // SQL Injection — Prepared Statements
    // =========================================================

    public function test_busca_com_sql_injection_nao_funciona(): void
    {
        // Esses dados não devem causar SQL injection porque usamos prepared statements
        $empresa = $this->insertEmpresa([
            'codigo'       => 'EMP-0001',
            'cnpj'         => '11111111111111',
            'razao_social' => "Empresa Legítima",
        ]);

        if (!function_exists('buscar_empresas')) {
            require_once __DIR__ . '/../../controllers/empresas.php';
        }

        // Tentar SQL injection no campo de busca
        $malicious = "' OR 1=1 --";
        $result = buscar_empresas(['busca' => $malicious]);

        // Não deve retornar todos os registros, apenas os que realmente contêm a string
        $this->assertSame(0, $result['total'], 'SQL injection não deve retornar resultados inesperados');
    }

    // =========================================================
    // Sessão — Timeout
    // =========================================================

    public function test_sessao_controle_atividade(): void
    {
        $_SESSION['user'] = ['id' => '123', 'empresa_id' => 'emp'];
        $_SESSION['ultima_atividade'] = time();

        $this->assertTrue(is_logged_in());
        $this->assertNotNull(current_user());
    }

    public function test_sessao_sem_usuario(): void
    {
        unset($_SESSION['user']);
        $this->assertFalse(is_logged_in());
        $this->assertNull(current_user());
    }
}
