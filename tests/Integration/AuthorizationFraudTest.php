<?php
declare(strict_types=1);

namespace Tests\Integration;

use Tests\DatabaseTestCase;

class AuthorizationFraudTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->loginAsRegular();

        if (!function_exists('resolve_sensitive_route_permission')) {
            require_once __DIR__ . '/../../helpers/functions.php';
        }
    }

    public function test_rbac_central_bloqueia_acao_sem_permissao(): void
    {
        $this->assertFalse(can_user('usuarios', 'excluir'));

        $perm = resolve_sensitive_route_permission('usuarios', 'delete', ['id' => 'x']);
        $this->assertSame('usuarios', $perm['modulo']);
        $this->assertSame('excluir', $perm['acao']);

        if (!can_user($perm['modulo'], $perm['acao'])) {
            registrar_evento_antifraude('acesso_negado', $perm['modulo'], $perm['acao'], 'x');
        }

        $result = buscar_logs(['modulo' => 'antifraude', 'acao' => 'acesso_negado']);
        $this->assertGreaterThanOrEqual(1, (int) $result['total']);
    }

    public function test_antifraude_alerta_padrao_suspeito(): void
    {
        $this->loginAsMaster();

        for ($i = 0; $i < 3; $i++) {
            registrar_evento_antifraude('acao_sensivel', 'usuarios', 'excluir', 'u-' . $i);
        }

        verificar_alerta_antifraude('usuarios', 'excluir', 900, 3);

        $result = buscar_logs(['modulo' => 'antifraude', 'acao' => 'alerta_padrao_suspeito']);
        $this->assertGreaterThanOrEqual(1, (int) $result['total']);
    }
}
