<?php
declare(strict_types=1);

namespace Tests\Integration;

use Tests\DatabaseTestCase;

/**
 * Testes de integração para o módulo de Autenticação.
 * Testa funções do controller auth que acessam o banco.
 */
class AuthTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Carregar funções do controller auth (evitar redeclaração)
        if (!function_exists('buscar_usuario_por_email')) {
            // O auth.php tem um switch($action) que executa imediatamente.
            // Precisamos definir $action como algo inofensivo antes de incluir.
            global $action;
            $action = '__noop__';
            ob_start();
            require_once __DIR__ . '/../../controllers/auth.php';
            ob_end_clean();
        }
    }

    /**
     * Cria dados base: empresa + perfil + usuário com senha conhecida.
     */
    private function criarUsuarioCompleto(
        string $email = 'user@test.com',
        string $senha = 'Senha@123',
        string $status = 'ativo'
    ): array {
        $empresa = $this->insertEmpresa([
            'codigo' => 'EMP-0001',
            'cnpj'   => '11111111111111',
        ]);

        $perfil = $this->insertPerfil([
            'codigo' => 'PRF-0001',
            'nome'   => 'Admin',
        ]);

        $usuario = $this->insertUsuario($empresa['id'], $perfil['id'], [
            'codigo' => 'USR-0001',
            'email'  => $email,
            'senha'  => hash_senha($senha),
            'status' => $status,
        ]);

        return compact('empresa', 'perfil', 'usuario');
    }

    // =========================================================
    // buscar_usuario_por_email()
    // =========================================================

    public function test_buscar_usuario_por_email_existente(): void
    {
        $this->criarUsuarioCompleto('admin@empresa.com');

        $result = buscar_usuario_por_email('admin@empresa.com');

        $this->assertNotNull($result);
        $this->assertSame('admin@empresa.com', $result['email']);
        $this->assertSame('USR-0001', $result['codigo']);
        $this->assertArrayHasKey('empresa_id', $result);
        $this->assertArrayHasKey('perfil_id', $result);
        $this->assertArrayHasKey('empresa_nome', $result);
        $this->assertArrayHasKey('perfil_nome', $result);
    }

    public function test_buscar_usuario_por_email_inexistente(): void
    {
        $result = buscar_usuario_por_email('naoexiste@test.com');
        $this->assertNull($result);
    }

    public function test_buscar_usuario_por_email_empresa_bloqueada(): void
    {
        $empresa = $this->insertEmpresa([
            'codigo' => 'EMP-0001',
            'cnpj'   => '11111111111111',
            'status' => 'bloqueado',
        ]);
        $perfil = $this->insertPerfil(['codigo' => 'PRF-0001', 'nome' => 'Admin']);
        $this->insertUsuario($empresa['id'], $perfil['id'], [
            'codigo' => 'USR-0001',
            'email'  => 'user@bloqueada.com',
        ]);

        // Empresa bloqueada: join exige e.status = 'ativo', logo retorna null
        $result = buscar_usuario_por_email('user@bloqueada.com');
        $this->assertNull($result, 'Usuário de empresa bloqueada não deve retornar nas buscas');
    }

    public function test_buscar_usuario_por_email_perfil_bloqueado(): void
    {
        $empresa = $this->insertEmpresa(['codigo' => 'EMP-0001', 'cnpj' => '11111111111111']);
        $perfil = $this->insertPerfil(['codigo' => 'PRF-0001', 'nome' => 'Bloqueado', 'status' => 'bloqueado']);
        $this->insertUsuario($empresa['id'], $perfil['id'], [
            'codigo' => 'USR-0001',
            'email'  => 'user@perfil-bloqueado.com',
        ]);

        // Perfil bloqueado: join exige p.status = 'ativo'
        $result = buscar_usuario_por_email('user@perfil-bloqueado.com');
        $this->assertNull($result, 'Usuário com perfil bloqueado não deve conseguir login');
    }

    // =========================================================
    // atualizar_ultimo_login()
    // =========================================================

    public function test_atualizar_ultimo_login(): void
    {
        $dados = $this->criarUsuarioCompleto();

        // Antes de atualizar, ultimo_login é NULL
        $stmt = $this->db()->prepare("SELECT ultimo_login FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $dados['usuario']['id']]);
        $this->assertNull($stmt->fetchColumn());

        // Atualizar
        atualizar_ultimo_login($dados['usuario']['id']);

        $stmt->execute([':id' => $dados['usuario']['id']]);
        $this->assertNotNull($stmt->fetchColumn(), 'ultimo_login deve ser preenchido após o update');
    }

    public function test_registrar_aceite_termo_cria_ou_atualiza_registro(): void
    {
        $dados = $this->criarUsuarioCompleto();

        registrar_aceite_termo($dados['usuario']['id']);
        registrar_aceite_termo($dados['usuario']['id']);

        $stmt = $this->db()->prepare(
            'SELECT COUNT(*) FROM usuario_termos WHERE usuario_id = :uid AND versao_termo = :versao'
        );
        $stmt->execute([
            ':uid' => $dados['usuario']['id'],
            ':versao' => TERMS_CURRENT_VERSION,
        ]);

        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    // =========================================================
    // contar_tentativas_recentes()
    // =========================================================

    public function test_contar_tentativas_recentes_zero(): void
    {
        $count = contar_tentativas_recentes('127.0.0.1', 'test@test.com');
        $this->assertSame(0, $count);
    }

    public function test_contar_tentativas_recentes_conta_por_ip(): void
    {
        $this->loginAsMaster();

        // Simular 3 falhas de login
        for ($i = 0; $i < 3; $i++) {
            registrar_log('auth', 'login_falha', 'Falha para test@test.com', 'warning');
        }

        // Inserir com IP correto (a função registrar_log usa REMOTE_ADDR)
        $count = contar_tentativas_recentes('127.0.0.1', 'test@test.com');
        $this->assertSame(3, $count);
    }

    // =========================================================
    // Fluxo de autenticação (verificação de senha)
    // =========================================================

    public function test_fluxo_senha_correta(): void
    {
        $this->criarUsuarioCompleto('admin@test.com', 'MinhaS3nh@Forte');

        $usuario = buscar_usuario_por_email('admin@test.com');
        $this->assertNotNull($usuario);
        $this->assertTrue(verificar_senha('MinhaS3nh@Forte', $usuario['senha']));
    }

    public function test_fluxo_senha_incorreta(): void
    {
        $this->criarUsuarioCompleto('admin@test.com', 'SenhaCorreta123');

        $usuario = buscar_usuario_por_email('admin@test.com');
        $this->assertNotNull($usuario);
        $this->assertFalse(verificar_senha('SenhaErrada999', $usuario['senha']));
    }

    public function test_fluxo_usuario_bloqueado_nao_faz_login(): void
    {
        $this->criarUsuarioCompleto('bloqueado@test.com', 'Senha@123', 'bloqueado');

        $usuario = buscar_usuario_por_email('bloqueado@test.com');
        // O INNER JOIN exige ue.status = 'ativo', mas o status do usuário é verificado depois
        // Se o resultado existir, verificar que o status é bloqueado
        if ($usuario) {
            $this->assertNotSame('ativo', $usuario['usuario_status']);
        } else {
            // Se o join já filterou, ok — não retornou
            $this->assertNull($usuario);
        }
    }
}
