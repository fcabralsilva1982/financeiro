<?php
declare(strict_types=1);

namespace Tests\Integration;

use Tests\DatabaseTestCase;

/**
 * Testes de integração para o sistema de Logs (auditoria).
 * Testa registrar_log(), buscar_logs() e os filtros multi-tenancy.
 */
class LogsTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsMaster();
    }

    // =========================================================
    // registrar_log()
    // =========================================================

    public function test_registrar_log_basico(): void
    {
        $result = registrar_log('auth', 'login', 'Usuário fez login');
        $this->assertTrue($result, 'registrar_log deve retornar true');

        $stmt = $this->db()->query("SELECT COUNT(*) FROM log_acoes");
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    public function test_registrar_log_com_dados_antes_depois(): void
    {
        $antes = ['status' => 'ativo'];
        $depois = ['status' => 'bloqueado'];

        registrar_log('empresas', 'bloquear', 'Empresa bloqueada', 'warning', 'reg-123', 'EMP-0001', $antes, $depois);

        $stmt = $this->db()->query("SELECT dados_anteriores, dados_novos FROM log_acoes LIMIT 1");
        $row = $stmt->fetch();

        $this->assertNotNull($row['dados_anteriores']);
        $this->assertNotNull($row['dados_novos']);

        $dadosAnt = json_decode($row['dados_anteriores'], true);
        $dadosNov = json_decode($row['dados_novos'], true);

        $this->assertSame('ativo', $dadosAnt['status']);
        $this->assertSame('bloqueado', $dadosNov['status']);
    }

    public function test_registrar_log_captura_ip_e_user_agent(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.50';
        $_SERVER['HTTP_USER_AGENT'] = 'TestBrowser/1.0';

        registrar_log('auth', 'login', 'Test log');

        $stmt = $this->db()->query("SELECT ip, user_agent FROM log_acoes LIMIT 1");
        $row = $stmt->fetch();

        $this->assertSame('192.168.1.50', $row['ip']);
        $this->assertSame('TestBrowser/1.0', $row['user_agent']);
    }

    // =========================================================
    // log_info(), log_warning(), log_error(), log_critical()
    // =========================================================

    public function test_atalhos_de_nivel(): void
    {
        log_info('auth', 'login', 'Info test');
        log_warning('auth', 'brute', 'Warning test');
        log_error('auth', 'fail', 'Error test');
        log_critical('auth', 'crash', 'Critical test');

        $stmt = $this->db()->query("SELECT nivel FROM log_acoes ORDER BY criado_em");
        $niveis = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        sort($niveis);
        $expected = ['critical', 'error', 'info', 'warning'];
        $this->assertSame($expected, $niveis);
    }

    // =========================================================
    // buscar_logs()
    // =========================================================

    public function test_buscar_logs_lista_vazia(): void
    {
        $result = buscar_logs();
        $this->assertSame(0, $result['total']);
        $this->assertEmpty($result['logs']);
    }

    public function test_buscar_logs_retorna_registros(): void
    {
        log_info('auth', 'login', 'Log 1');
        log_info('auth', 'logout', 'Log 2');

        $result = buscar_logs();
        $this->assertSame(2, $result['total']);
        $this->assertCount(2, $result['logs']);
    }

    public function test_buscar_logs_filtro_modulo(): void
    {
        log_info('auth', 'login', 'Auth log');
        log_info('empresas', 'criar', 'Empresa log');

        $result = buscar_logs(['modulo' => 'auth']);
        $this->assertSame(1, $result['total']);
        $this->assertSame('auth', $result['logs'][0]['modulo']);
    }

    public function test_buscar_logs_filtro_nivel(): void
    {
        log_info('auth', 'login', 'Info');
        log_error('auth', 'fail', 'Error');

        $result = buscar_logs(['nivel' => 'error']);
        $this->assertSame(1, $result['total']);
        $this->assertSame('error', $result['logs'][0]['nivel']);
    }

    public function test_buscar_logs_filtro_acao(): void
    {
        log_info('auth', 'login', 'Login');
        log_info('auth', 'logout', 'Logout');

        $result = buscar_logs(['acao' => 'login']);
        $this->assertSame(1, $result['total']);
    }

    public function test_buscar_logs_paginacao(): void
    {
        for ($i = 0; $i < 10; $i++) {
            log_info('auth', 'test', "Log $i");
        }

        $page1 = buscar_logs([], 5, 0);
        $this->assertSame(10, $page1['total']);
        $this->assertCount(5, $page1['logs']);

        $page2 = buscar_logs([], 5, 5);
        $this->assertCount(5, $page2['logs']);
    }

    public function test_buscar_logs_limite_maximo_200(): void
    {
        // buscar_logs limita internamente a 200 registros
        $result = buscar_logs([], 999, 0);
        // Não deve lançar erro, apenas limitar
        $this->assertIsArray($result);
    }

    // =========================================================
    // Multi-tenancy em logs
    // =========================================================

    public function test_buscar_logs_master_ve_todos(): void
    {
        $this->loginAsMaster();

        // Inserir logs de empresas diferentes diretamente
        $this->db()->prepare("
            INSERT INTO log_acoes (id, empresa_id, empresa_codigo, modulo, acao, descricao, ip, metodo_http)
            VALUES (:id, :eid, :ec, :mod, :acao, :desc, '127.0.0.1', 'GET')
        ")->execute([
            ':id'   => generate_uuid(),
            ':eid'  => 'aaaa1111-aaaa-4aaa-aaaa-aaaaaaaaaaaa',
            ':ec'   => 'EMP-0001',
            ':mod'  => 'auth',
            ':acao' => 'login',
            ':desc' => 'Log empresa A',
        ]);

        $this->db()->prepare("
            INSERT INTO log_acoes (id, empresa_id, empresa_codigo, modulo, acao, descricao, ip, metodo_http)
            VALUES (:id, :eid, :ec, :mod, :acao, :desc, '127.0.0.1', 'GET')
        ")->execute([
            ':id'   => generate_uuid(),
            ':eid'  => 'bbbb2222-bbbb-4bbb-bbbb-bbbbbbbbbbbb',
            ':ec'   => 'EMP-0002',
            ':mod'  => 'auth',
            ':acao' => 'login',
            ':desc' => 'Log empresa B',
        ]);

        $result = buscar_logs();
        $this->assertSame(2, $result['total'], 'Master deve ver logs de todas as empresas');
    }

    public function test_buscar_logs_regular_ve_apenas_sua_empresa(): void
    {
        $this->loginAsRegular();
        $empresaId = $_SESSION['user']['empresa_id'];

        // Log da empresa do usuário
        $this->db()->prepare("
            INSERT INTO log_acoes (id, empresa_id, modulo, acao, descricao, ip, metodo_http)
            VALUES (:id, :eid, 'auth', 'login', 'Meu log', '127.0.0.1', 'GET')
        ")->execute([':id' => generate_uuid(), ':eid' => $empresaId]);

        // Log de outra empresa
        $this->db()->prepare("
            INSERT INTO log_acoes (id, empresa_id, modulo, acao, descricao, ip, metodo_http)
            VALUES (:id, :eid, 'auth', 'login', 'Outro log', '127.0.0.1', 'GET')
        ")->execute([':id' => generate_uuid(), ':eid' => 'eeee9999-eeee-4eee-eeee-eeeeeeeeeeee']);

        $result = buscar_logs();
        $this->assertSame(1, $result['total'], 'Usuário regular só deve ver logs da sua empresa');
    }
}
