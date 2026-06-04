<?php
declare(strict_types=1);

namespace Tests\Integration;

use Tests\DatabaseTestCase;

/**
 * Testes de integração para o módulo Empresas.
 * Testa funções do controller que acessam o banco de dados.
 */
class EmpresasTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Carregar funções do controller empresas
        if (!function_exists('buscar_empresas')) {
            global $action;
            $action = '__noop__';
            require_once __DIR__ . '/../../controllers/empresas.php';
        }

        $this->loginAsMaster();
    }

    // =========================================================
    // buscar_empresas()
    // =========================================================

    public function test_buscar_empresas_lista_vazia(): void
    {
        $result = buscar_empresas();
        $this->assertSame(0, $result['total']);
        $this->assertEmpty($result['empresas']);
    }

    public function test_buscar_empresas_retorna_registros(): void
    {
        $this->insertEmpresa(['codigo' => 'EMP-0001', 'cnpj' => '11111111111111', 'razao_social' => 'Empresa Alpha']);
        $this->insertEmpresa(['codigo' => 'EMP-0002', 'cnpj' => '22222222222222', 'razao_social' => 'Empresa Beta']);

        $result = buscar_empresas();
        $this->assertSame(2, $result['total']);
        $this->assertCount(2, $result['empresas']);
    }

    public function test_buscar_empresas_filtro_busca(): void
    {
        $this->insertEmpresa(['codigo' => 'EMP-0001', 'cnpj' => '11111111111111', 'razao_social' => 'Alpha Ltda']);
        $this->insertEmpresa(['codigo' => 'EMP-0002', 'cnpj' => '22222222222222', 'razao_social' => 'Beta SA']);

        $result = buscar_empresas(['busca' => 'Alpha']);
        $this->assertSame(1, $result['total']);
        $this->assertSame('Alpha Ltda', $result['empresas'][0]['razao_social']);
    }

    public function test_buscar_empresas_filtro_status(): void
    {
        $this->insertEmpresa(['codigo' => 'EMP-0001', 'cnpj' => '11111111111111', 'status' => 'ativo']);
        $this->insertEmpresa(['codigo' => 'EMP-0002', 'cnpj' => '22222222222222', 'status' => 'bloqueado']);

        $ativos = buscar_empresas(['status' => 'ativo']);
        $this->assertSame(1, $ativos['total']);

        $bloqueados = buscar_empresas(['status' => 'bloqueado']);
        $this->assertSame(1, $bloqueados['total']);
    }

    public function test_buscar_empresas_paginacao(): void
    {
        for ($i = 1; $i <= 20; $i++) {
            $this->insertEmpresa([
                'codigo' => sprintf('EMP-%04d', $i),
                'cnpj'   => str_pad((string) $i, 14, '0', STR_PAD_LEFT),
            ]);
        }

        $page1 = buscar_empresas([], 1, 10);
        $this->assertSame(20, $page1['total']);
        $this->assertCount(10, $page1['empresas']);

        $page2 = buscar_empresas([], 2, 10);
        $this->assertCount(10, $page2['empresas']);

        // IDs da página 1 e 2 não devem se repetir
        $ids1 = array_column($page1['empresas'], 'id');
        $ids2 = array_column($page2['empresas'], 'id');
        $this->assertEmpty(array_intersect($ids1, $ids2));
    }

    // =========================================================
    // buscar_empresa_por_id()
    // =========================================================

    public function test_buscar_empresa_por_id_existente(): void
    {
        $emp = $this->insertEmpresa(['codigo' => 'EMP-0001', 'cnpj' => '11111111111111']);
        $found = buscar_empresa_por_id($emp['id']);

        $this->assertNotNull($found);
        $this->assertSame($emp['id'], $found['id']);
        $this->assertSame('EMP-0001', $found['codigo']);
        $this->assertArrayHasKey('contatos', $found);
    }

    public function test_buscar_empresa_por_id_inexistente(): void
    {
        $found = buscar_empresa_por_id('id-que-nao-existe');
        $this->assertNull($found);
    }

    public function test_buscar_empresa_inclui_contatos(): void
    {
        $emp = $this->insertEmpresa(['codigo' => 'EMP-0001', 'cnpj' => '11111111111111']);

        // Inserir contato manualmente
        $this->db()->prepare("
            INSERT INTO empresa_contatos (id, empresa_id, nome, email, cargo)
            VALUES (:id, :eid, :nome, :email, :cargo)
        ")->execute([
            ':id'    => generate_uuid(),
            ':eid'   => $emp['id'],
            ':nome'  => 'João Silva',
            ':email' => 'joao@teste.com',
            ':cargo' => 'Gerente',
        ]);

        $found = buscar_empresa_por_id($emp['id']);
        $this->assertCount(1, $found['contatos']);
        $this->assertSame('João Silva', $found['contatos'][0]['nome']);
    }

    // =========================================================
    // gerar_proximo_codigo_empresa()
    // =========================================================

    public function test_gerar_proximo_codigo_primeiro(): void
    {
        // Tabela vazia: deve gerar EMP-0001
        $codigo = gerar_proximo_codigo_empresa();
        $this->assertSame('EMP-0001', $codigo);
    }

    public function test_gerar_proximo_codigo_sequencial(): void
    {
        $this->insertEmpresa(['codigo' => 'EMP-0005', 'cnpj' => '55555555555555']);
        $codigo = gerar_proximo_codigo_empresa();
        $this->assertSame('EMP-0006', $codigo);
    }

    // =========================================================
    // validar_empresa()
    // =========================================================

    public function test_validar_empresa_cnpj_obrigatorio(): void
    {
        $erros = validar_empresa(['cnpj' => '', 'razao_social' => 'Teste']);
        $this->assertArrayHasKey('cnpj', $erros);
    }

    public function test_validar_empresa_razao_social_obrigatoria(): void
    {
        $erros = validar_empresa(['cnpj' => '12345678901234', 'razao_social' => '']);
        $this->assertArrayHasKey('razao_social', $erros);
    }

    public function test_validar_empresa_cnpj_duplicado(): void
    {
        $this->insertEmpresa(['codigo' => 'EMP-0001', 'cnpj' => '12345678901234']);

        $erros = validar_empresa(['cnpj' => '12345678901234', 'razao_social' => 'Outra']);
        $this->assertArrayHasKey('cnpj', $erros);
    }

    public function test_validar_empresa_cnpj_duplicado_ignora_proprio_id_em_edicao(): void
    {
        $emp = $this->insertEmpresa(['codigo' => 'EMP-0001', 'cnpj' => '12345678901234']);

        // Na edição, deve ignorar o próprio registro
        $erros = validar_empresa(['id' => $emp['id'], 'cnpj' => '12345678901234', 'razao_social' => 'Renomeada'], true);
        $this->assertArrayNotHasKey('cnpj', $erros);
    }

    public function test_validar_empresa_dados_validos(): void
    {
        $erros = validar_empresa(['cnpj' => '99999999999999', 'razao_social' => 'Empresa Válida']);
        $this->assertEmpty($erros);
    }

    // =========================================================
    // salvar_contatos()
    // =========================================================

    public function test_salvar_contatos_insere_novos(): void
    {
        $emp = $this->insertEmpresa(['codigo' => 'EMP-0001', 'cnpj' => '11111111111111']);

        salvar_contatos($this->db(), $emp['id'], [
            ['nome' => 'Maria', 'email' => 'maria@t.com', 'telefone' => '', 'cargo' => 'Dir.'],
            ['nome' => 'Pedro', 'email' => 'pedro@t.com', 'telefone' => '', 'cargo' => 'Ger.'],
        ]);

        $stmt = $this->db()->prepare("SELECT COUNT(*) FROM empresa_contatos WHERE empresa_id = :eid");
        $stmt->execute([':eid' => $emp['id']]);
        $this->assertSame(2, (int) $stmt->fetchColumn());
    }

    public function test_salvar_contatos_substitui_existentes(): void
    {
        $emp = $this->insertEmpresa(['codigo' => 'EMP-0001', 'cnpj' => '11111111111111']);

        // Inserir 3 contatos
        salvar_contatos($this->db(), $emp['id'], [
            ['nome' => 'A', 'email' => '', 'telefone' => '', 'cargo' => ''],
            ['nome' => 'B', 'email' => '', 'telefone' => '', 'cargo' => ''],
            ['nome' => 'C', 'email' => '', 'telefone' => '', 'cargo' => ''],
        ]);

        // Substituir por 1 contato
        salvar_contatos($this->db(), $emp['id'], [
            ['nome' => 'Novo', 'email' => '', 'telefone' => '', 'cargo' => ''],
        ]);

        $stmt = $this->db()->prepare("SELECT COUNT(*) FROM empresa_contatos WHERE empresa_id = :eid");
        $stmt->execute([':eid' => $emp['id']]);
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }

    public function test_salvar_contatos_ignora_nome_vazio(): void
    {
        $emp = $this->insertEmpresa(['codigo' => 'EMP-0001', 'cnpj' => '11111111111111']);

        salvar_contatos($this->db(), $emp['id'], [
            ['nome' => '', 'email' => 'vazio@t.com', 'telefone' => '', 'cargo' => ''],
            ['nome' => 'Válido', 'email' => '', 'telefone' => '', 'cargo' => ''],
        ]);

        $stmt = $this->db()->prepare("SELECT COUNT(*) FROM empresa_contatos WHERE empresa_id = :eid");
        $stmt->execute([':eid' => $emp['id']]);
        $this->assertSame(1, (int) $stmt->fetchColumn());
    }
}
