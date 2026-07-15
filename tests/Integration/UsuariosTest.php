<?php
declare(strict_types=1);

namespace Tests\Integration;

use Tests\DatabaseTestCase;

class UsuariosTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!function_exists('buscar_empresas_para_select')) {
            global $action;
            $action = '__noop__';
            require_once __DIR__ . '/../../controllers/usuarios.php';
        }

        $this->loginAsMaster();
    }

    public function test_empresa_pf_aparece_no_select_de_usuarios_com_label_visivel(): void
    {
        $empresa = $this->insertEmpresa([
            'codigo' => 'EMP-0005',
            'tipo_pessoa' => 'PF',
            'cnpj' => null,
            'cpf' => '216.136.478-24',
            'razao_social' => 'Flavio Cabral',
            'nome_fantasia' => '',
        ]);

        $empresas = buscar_empresas_para_select();
        $porId = array_column($empresas, null, 'id');

        $this->assertArrayHasKey($empresa['id'], $porId);
        $this->assertSame('Flavio Cabral (EMP-0005 / PF)', empresa_label_usuario($porId[$empresa['id']]));
    }

    public function test_validar_usuario_aceita_empresa_pf_ativa(): void
    {
        $empresa = $this->insertEmpresa([
            'codigo' => 'EMP-0005',
            'tipo_pessoa' => 'PF',
            'cnpj' => null,
            'cpf' => '216.136.478-24',
            'razao_social' => 'Flavio Cabral',
            'nome_fantasia' => '',
        ]);
        $perfil = $this->insertPerfil(['codigo' => 'PRF-0001', 'nome' => 'Administrador']);

        $erros = validar_usuario([
            'nome' => 'Usuario PF',
            'email' => 'usuario.pf@test.com',
            'empresa_id' => $empresa['id'],
            'perfil_id' => $perfil['id'],
            'senha' => 'Senha@123',
            'confirmar_senha' => 'Senha@123',
        ]);

        $this->assertEmpty($erros);
    }
}
