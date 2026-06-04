<?php
declare(strict_types=1);

namespace Tests\Integration;

use Tests\DatabaseTestCase;

/**
 * Testes de integração para o módulo Perfis de Acesso.
 * Testa funções do controller que acessam o banco de dados.
 */
class PerfisTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Carregar funções do controller perfis (evitar redeclaração)
        if (!function_exists('buscar_perfis')) {
            global $action;
            $action = '__noop__';
            require_once __DIR__ . '/../../controllers/perfis.php';
        }

        $this->loginAsMaster();
    }

    // =========================================================
    // buscar_perfis()
    // =========================================================

    public function test_buscar_perfis_lista_vazia(): void
    {
        $result = buscar_perfis();
        $this->assertSame(0, $result['total']);
        $this->assertEmpty($result['perfis']);
    }

    public function test_buscar_perfis_retorna_registros(): void
    {
        $this->insertPerfil(['codigo' => 'PRF-0001', 'nome' => 'Admin']);
        $this->insertPerfil(['codigo' => 'PRF-0002', 'nome' => 'Operador']);

        $result = buscar_perfis();
        $this->assertSame(2, $result['total']);
        $this->assertCount(2, $result['perfis']);
    }

    public function test_buscar_perfis_filtro_busca(): void
    {
        $this->insertPerfil(['codigo' => 'PRF-0001', 'nome' => 'Administrador']);
        $this->insertPerfil(['codigo' => 'PRF-0002', 'nome' => 'Operador']);

        $result = buscar_perfis(['busca' => 'Admin']);
        $this->assertSame(1, $result['total']);
    }

    public function test_buscar_perfis_filtro_status(): void
    {
        $this->insertPerfil(['codigo' => 'PRF-0001', 'nome' => 'Ativo', 'status' => 'ativo']);
        $this->insertPerfil(['codigo' => 'PRF-0002', 'nome' => 'Bloqueado', 'status' => 'bloqueado']);

        $ativos = buscar_perfis(['status' => 'ativo']);
        $this->assertSame(1, $ativos['total']);
    }

    public function test_buscar_perfis_paginacao(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->insertPerfil([
                'codigo' => sprintf('PRF-%04d', $i),
                'nome'   => "Perfil Teste $i",
            ]);
        }

        $page1 = buscar_perfis([], 1, 10);
        $this->assertSame(20, $page1['total']);
        $this->assertCount(10, $page1['perfis']);
    }

    public function test_buscar_perfis_inclui_contagem_usuarios(): void
    {
        $perfil = $this->insertPerfil(['codigo' => 'PRF-0001', 'nome' => 'Admin']);
        $empresa = $this->insertEmpresa(['codigo' => 'EMP-0001', 'cnpj' => '11111111111111']);

        // Inserir 2 usuários no mesmo perfil
        $this->insertUsuario($empresa['id'], $perfil['id'], ['codigo' => 'USR-0001', 'email' => 'u1@t.com']);
        $this->insertUsuario($empresa['id'], $perfil['id'], ['codigo' => 'USR-0002', 'email' => 'u2@t.com']);

        $result = buscar_perfis();
        $this->assertSame(2, (int) $result['perfis'][0]['num_usuarios']);
    }

    // =========================================================
    // buscar_perfil_por_id()
    // =========================================================

    public function test_buscar_perfil_por_id_existente(): void
    {
        $perfil = $this->insertPerfil(['codigo' => 'PRF-0001', 'nome' => 'Admin']);
        $found = buscar_perfil_por_id($perfil['id']);

        $this->assertNotNull($found);
        $this->assertSame($perfil['id'], $found['id']);
        $this->assertArrayHasKey('permissoes', $found);
    }

    public function test_buscar_perfil_por_id_inexistente(): void
    {
        $this->assertNull(buscar_perfil_por_id('id-nao-existe'));
    }

    public function test_buscar_perfil_inclui_permissoes(): void
    {
        $perfil = $this->insertPerfil(['codigo' => 'PRF-0001', 'nome' => 'Admin']);

        // Inserir permissões manualmente
        $this->db()->prepare("
            INSERT INTO perfil_permissoes (id, perfil_id, modulo, acao)
            VALUES (:id, :pid, :mod, :acao)
        ")->execute([
            ':id'   => generate_uuid(),
            ':pid'  => $perfil['id'],
            ':mod'  => 'empresas',
            ':acao' => 'visualizar',
        ]);

        $found = buscar_perfil_por_id($perfil['id']);
        $this->assertContains('empresas.visualizar', $found['permissoes']);
    }

    // =========================================================
    // gerar_proximo_codigo_perfil()
    // =========================================================

    public function test_gerar_proximo_codigo_perfil_primeiro(): void
    {
        $codigo = gerar_proximo_codigo_perfil();
        $this->assertSame('PRF-0001', $codigo);
    }

    public function test_gerar_proximo_codigo_perfil_sequencial(): void
    {
        $this->insertPerfil(['codigo' => 'PRF-0003', 'nome' => 'Existente']);
        $codigo = gerar_proximo_codigo_perfil();
        $this->assertSame('PRF-0004', $codigo);
    }

    // =========================================================
    // validar_perfil()
    // =========================================================

    public function test_validar_perfil_nome_obrigatorio(): void
    {
        $erros = validar_perfil(['nome' => '']);
        $this->assertArrayHasKey('nome', $erros);
    }

    public function test_validar_perfil_nome_duplicado(): void
    {
        $this->insertPerfil(['codigo' => 'PRF-0001', 'nome' => 'Admin']);

        $erros = validar_perfil(['nome' => 'Admin']);
        $this->assertArrayHasKey('nome', $erros);
    }

    public function test_validar_perfil_nome_duplicado_ignora_proprio_edicao(): void
    {
        $perfil = $this->insertPerfil(['codigo' => 'PRF-0001', 'nome' => 'Admin']);

        $erros = validar_perfil(['id' => $perfil['id'], 'nome' => 'Admin'], true);
        $this->assertArrayNotHasKey('nome', $erros);
    }

    public function test_validar_perfil_dados_validos(): void
    {
        $erros = validar_perfil(['nome' => 'Novo Perfil']);
        $this->assertEmpty($erros);
    }

    // =========================================================
    // salvar_permissoes()
    // =========================================================

    public function test_salvar_permissoes_insere(): void
    {
        $perfil = $this->insertPerfil(['codigo' => 'PRF-0001', 'nome' => 'Admin']);

        salvar_permissoes($this->db(), $perfil['id'], [
            'empresas.visualizar',
            'empresas.criar',
            'usuarios.visualizar',
        ]);

        $stmt = $this->db()->prepare("SELECT COUNT(*) FROM perfil_permissoes WHERE perfil_id = :pid");
        $stmt->execute([':pid' => $perfil['id']]);
        $this->assertSame(3, (int) $stmt->fetchColumn());
    }

    public function test_salvar_permissoes_substitui_existentes(): void
    {
        $perfil = $this->insertPerfil(['codigo' => 'PRF-0001', 'nome' => 'Admin']);

        // Primeira vez: 3 permissões
        salvar_permissoes($this->db(), $perfil['id'], [
            'empresas.visualizar',
            'empresas.criar',
            'empresas.editar',
        ]);

        // Segunda vez: 1 permissão (deve substituir)
        salvar_permissoes($this->db(), $perfil['id'], [
            'usuarios.visualizar',
        ]);

        $stmt = $this->db()->prepare("SELECT COUNT(*) FROM perfil_permissoes WHERE perfil_id = :pid");
        $stmt->execute([':pid' => $perfil['id']]);
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    public function test_salvar_permissoes_ignora_modulos_futuros(): void
    {
        $perfil = $this->insertPerfil(['codigo' => 'PRF-0001', 'nome' => 'Admin']);

        salvar_permissoes($this->db(), $perfil['id'], [
            'empresas.visualizar',
            'financeiro.visualizar',  // módulo futuro — deve ser ignorado
            'relatorios.exportar',    // módulo futuro — deve ser ignorado
        ]);

        $stmt = $this->db()->prepare("SELECT COUNT(*) FROM perfil_permissoes WHERE perfil_id = :pid");
        $stmt->execute([':pid' => $perfil['id']]);
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }
}
