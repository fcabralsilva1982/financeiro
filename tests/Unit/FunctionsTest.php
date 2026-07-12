<?php
declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Testes unitários para helpers/functions.php
 * Testa funções puras que não dependem de banco de dados.
 */
class FunctionsTest extends TestCase
{
    // =========================================================
    // generate_uuid()
    // =========================================================

    public function test_generate_uuid_formato_valido(): void
    {
        $uuid = generate_uuid();

        // UUID v4: 8-4-4-4-12 caracteres hex com hifens
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid,
            'UUID deve estar no formato v4 válido'
        );
    }

    public function test_generate_uuid_unicidade(): void
    {
        $uuids = [];
        for ($i = 0; $i < 100; $i++) {
            $uuids[] = generate_uuid();
        }

        $this->assertCount(
            100,
            array_unique($uuids),
            '100 UUIDs gerados devem ser todos únicos'
        );
    }

    // =========================================================
    // generate_codigo()
    // =========================================================

    public function test_generate_codigo_formato(): void
    {
        $this->assertSame('EMP-0001', generate_codigo('EMP', '1'));
        $this->assertSame('USR-0042', generate_codigo('USR', '42'));
        $this->assertSame('PRF-1234', generate_codigo('PRF', '1234'));
    }

    public function test_generate_codigo_prefixo_uppercase(): void
    {
        $this->assertSame('EMP-0001', generate_codigo('emp', '1'));
        $this->assertSame('USR-0010', generate_codigo('usr', '10'));
    }

    public function test_generate_codigo_padding(): void
    {
        $this->assertSame('EMP-0001', generate_codigo('EMP', '1'));
        $this->assertSame('EMP-0099', generate_codigo('EMP', '99'));
        $this->assertSame('EMP-9999', generate_codigo('EMP', '9999'));
    }

    // =========================================================
    // hash_senha() / verificar_senha() / senha_precisa_rehash()
    // =========================================================

    public function test_hash_senha_gera_hash_bcrypt(): void
    {
        $hash = hash_senha('MinhaS3nh@!');

        $this->assertStringStartsWith('$2y$', $hash, 'Hash deve ser bcrypt ($2y$)');
        $this->assertNotEquals('MinhaS3nh@!', $hash, 'Hash não pode ser igual à senha');
    }

    public function test_verificar_senha_correta(): void
    {
        $hash = hash_senha('Senha@Forte123');

        $this->assertTrue(
            verificar_senha('Senha@Forte123', $hash),
            'Senha correta deve retornar true'
        );
    }

    public function test_verificar_senha_incorreta(): void
    {
        $hash = hash_senha('Senha@Forte123');

        $this->assertFalse(
            verificar_senha('SenhaErrada', $hash),
            'Senha incorreta deve retornar false'
        );
    }

    public function test_verificar_senha_vazia(): void
    {
        $hash = hash_senha('Senha@123');

        $this->assertFalse(
            verificar_senha('', $hash),
            'Senha vazia deve retornar false'
        );
    }

    public function test_senha_precisa_rehash_com_custo_atual(): void
    {
        $hash = hash_senha('Teste123');

        $this->assertFalse(
            senha_precisa_rehash($hash),
            'Hash recém-gerado não precisa de rehash'
        );
    }

    // =========================================================
    // url()
    // =========================================================

    public function test_url_sem_parametros(): void
    {
        $result = url();
        $this->assertStringContains('/index.php', $result);
    }

    public function test_url_com_pagina(): void
    {
        $result = url('empresas');
        $this->assertStringContains('page=empresas', $result);
    }

    public function test_url_com_pagina_e_acao(): void
    {
        $result = url('empresas', 'create');
        $this->assertStringContains('page=empresas', $result);
        $this->assertStringContains('action=create', $result);
    }

    public function test_url_com_parametros_extras(): void
    {
        $result = url('empresas', 'edit', ['id' => '123']);
        $this->assertStringContains('id=123', $result);
    }

    public function test_url_escapa_html(): void
    {
        // url() usa htmlspecialchars — o & vira &amp;
        $result = url('empresas', 'edit');
        $this->assertStringContains('&amp;', $result);
    }

    // =========================================================
    // e() — Escape HTML
    // =========================================================

    public function test_e_escapa_xss(): void
    {
        $this->assertSame(
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            e('<script>alert(1)</script>')
        );
    }

    public function test_e_escapa_aspas(): void
    {
        $input = 'Olá "mundo" & \'amigos\'';
        $result = e($input);
        $this->assertStringNotContainsString('"', $result);
        $this->assertStringContainsString('&quot;', $result);
        $this->assertStringContainsString('&#039;', $result);
    }

    public function test_e_com_null(): void
    {
        $this->assertSame('', e(null));
    }

    public function test_e_com_numero(): void
    {
        $this->assertSame('42', e(42));
        $this->assertSame('3.14', e(3.14));
    }

    // =========================================================
    // __() — Tradução
    // =========================================================

    public function test_traducao_chave_existente(): void
    {
        $result = __('menu.dashboard');
        $this->assertSame('Dashboard', $result);
    }

    public function test_traducao_chave_inexistente_retorna_chave(): void
    {
        $result = __('chave.que.nao.existe');
        $this->assertSame('chave.que.nao.existe', $result);
    }

    public function test_traducao_chave_inexistente_com_default(): void
    {
        $result = __('chave.inexistente', 'valor padrão');
        $this->assertSame('valor padrão', $result);
    }

    // =========================================================
    // selected_menu()
    // =========================================================

    public function test_selected_menu_ativo(): void
    {
        $_GET['page'] = 'empresas';
        $this->assertSame('active', selected_menu('empresas'));
    }

    public function test_selected_menu_inativo(): void
    {
        $_GET['page'] = 'empresas';
        $this->assertSame('', selected_menu('usuarios'));
    }

    public function test_selected_menu_sem_page(): void
    {
        unset($_GET['page']);
        $this->assertSame('', selected_menu('empresas'));
    }

    // =========================================================
    // csrf_field()
    // =========================================================

    public function test_csrf_field_gera_input_hidden(): void
    {
        unset($_SESSION['csrf_token']);
        $html = csrf_field();

        $this->assertStringContainsString('<input type="hidden"', $html);
        $this->assertStringContainsString('name="csrf_token"', $html);
        $this->assertStringContainsString('value="', $html);
    }

    public function test_csrf_field_mantem_token_na_sessao(): void
    {
        unset($_SESSION['csrf_token']);
        csrf_field();

        $this->assertNotEmpty($_SESSION['csrf_token']);
        $this->assertSame(64, strlen($_SESSION['csrf_token']), 'Token CSRF deve ter 64 chars (32 bytes hex)');
    }

    public function test_csrf_field_reutiliza_token_existente(): void
    {
        $_SESSION['csrf_token'] = 'token-fixo-para-teste';
        $html = csrf_field();

        $this->assertStringContainsString('token-fixo-para-teste', $html);
    }

    // =========================================================
    // flash_message() / set_flash()
    // =========================================================

    public function test_set_flash_e_flash_message(): void
    {
        set_flash('success', 'Operação realizada!');

        $html = flash_message();
        $this->assertStringContainsString('alert-success', $html);
        $this->assertStringContainsString('Operação realizada!', $html);
    }

    public function test_flash_message_limpa_apos_exibir(): void
    {
        set_flash('danger', 'Erro!');
        flash_message();

        // Segunda chamada deve retornar vazio
        $this->assertSame('', flash_message());
    }

    public function test_flash_message_escapa_html(): void
    {
        set_flash('info', '<script>xss</script>');
        $html = flash_message();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    // =========================================================
    // Multi-tenancy helpers
    // =========================================================

    public function test_is_master_empresa(): void
    {
        $_SESSION['user'] = ['empresa_id' => MASTER_EMPRESA_ID];
        $this->assertTrue(is_master_empresa());
    }

    public function test_is_not_master_empresa(): void
    {
        $_SESSION['user'] = ['empresa_id' => 'outra-empresa-qualquer'];
        $this->assertFalse(is_master_empresa());
    }

    public function test_current_user_logado(): void
    {
        $_SESSION['user'] = ['id' => '123', 'nome' => 'Teste'];
        $user = current_user();
        $this->assertSame('123', $user['id']);
    }

    public function test_current_user_nao_logado(): void
    {
        unset($_SESSION['user']);
        $this->assertNull(current_user());
    }

    public function test_is_logged_in(): void
    {
        $_SESSION['user'] = ['id' => '123'];
        $this->assertTrue(is_logged_in());

        unset($_SESSION['user']);
        $this->assertFalse(is_logged_in());
    }

    public function test_filter_by_empresa_master_ve_tudo(): void
    {
        $_SESSION['user'] = ['empresa_id' => MASTER_EMPRESA_ID];

        $records = [
            ['empresa_id' => 'empresa-a', 'nome' => 'A'],
            ['empresa_id' => 'empresa-b', 'nome' => 'B'],
        ];

        $result = filter_by_empresa($records);
        $this->assertCount(2, $result, 'Master deve ver todos os registros');
    }

    public function test_filter_by_empresa_regular_ve_apenas_sua(): void
    {
        $_SESSION['user'] = ['empresa_id' => 'empresa-a'];

        $records = [
            ['empresa_id' => 'empresa-a', 'nome' => 'A'],
            ['empresa_id' => 'empresa-b', 'nome' => 'B'],
            ['empresa_id' => 'empresa-a', 'nome' => 'C'],
        ];

        $result = array_values(filter_by_empresa($records));
        $this->assertCount(2, $result, 'Usuário regular só vê registros da sua empresa');
        $this->assertSame('A', $result[0]['nome']);
        $this->assertSame('C', $result[1]['nome']);
    }

    // =========================================================
    // get_client_ip()
    // =========================================================

    public function test_get_client_ip_remote_addr(): void
    {
        // Limpar headers de proxy
        unset(
            $_SERVER['HTTP_X_FORWARDED_FOR'],
            $_SERVER['HTTP_X_REAL_IP'],
            $_SERVER['HTTP_CLIENT_IP']
        );
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';

        $this->assertSame('192.168.1.100', get_client_ip());
    }

    public function test_get_client_ip_x_forwarded_for(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.1, 10.0.0.2';
        $this->assertSame('10.0.0.1', get_client_ip());
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    }


    // =========================================================
    // Upload storage helpers
    // =========================================================

    public function test_upload_storage_root_usa_env_externo(): void
    {
        $esperado = rtrim(str_replace('\\', '/', (string) UPLOAD_STORAGE_PATH), '/');
        $this->assertSame($esperado, upload_storage_root());
        $this->assertStringContainsString('financeiro_storage_test', upload_storage_root());
    }

    public function test_normalize_logo_relative_path_aceita_atual_e_legado(): void
    {
        $this->assertSame('logos/teste.png', normalize_logo_relative_path('logos/teste.png'));
        $this->assertSame('logos/teste.png', normalize_logo_relative_path('uploads/logos/teste.png'));
        $this->assertNull(normalize_logo_relative_path('../etc/passwd'));
    }

    public function test_normalize_comprovante_relative_path_aceita_atual_e_legado(): void
    {
        $this->assertSame('comprovantes/a.pdf', normalize_comprovante_relative_path('comprovantes/a.pdf'));
        $this->assertSame('comprovantes/a.pdf', normalize_comprovante_relative_path('uploads/comprovantes/a.pdf'));
        $this->assertNull(normalize_comprovante_relative_path('uploads/logo/a.pdf'));
    }

    public function test_empresa_logo_url_aponta_para_endpoint_controlado(): void
    {
        $url = empresa_logo_url('empresa-123');
        $this->assertStringContainsString('page=empresas', $url);
        $this->assertStringContainsString('action=logo', $url);
        $this->assertStringContainsString('id=empresa-123', $url);
    }

    // =========================================================
    // Helper para assertStringContains (PHP 8.1 compat)
    // =========================================================

    private function assertStringContains(string $needle, string $haystack, string $msg = ''): void
    {
        $this->assertStringContainsString($needle, $haystack, $msg);
    }
}
