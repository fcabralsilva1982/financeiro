<?php
declare(strict_types=1);

namespace Tests\Integration;

use Tests\DatabaseTestCase;

/**
 * Testes de integridade do banco de dados.
 * Verifica constraints, cascade, unique keys e regras de negócio a nível de schema.
 */
class DatabaseIntegrityTest extends DatabaseTestCase
{
    // =========================================================
    // UNIQUE CONSTRAINTS
    // =========================================================

    public function test_empresa_cnpj_unico(): void
    {
        $this->insertEmpresa(['codigo' => 'EMP-0001', 'cnpj' => '12345678901234']);

        $this->expectException(\PDOException::class);
        $this->insertEmpresa(['codigo' => 'EMP-0002', 'cnpj' => '12345678901234']);
    }

    public function test_empresa_cpf_unico(): void
    {
        $this->insertEmpresa([
            'codigo' => 'EMP-0001',
            'tipo_pessoa' => 'PF',
            'cnpj' => null,
            'cpf' => '123.456.789-01',
        ]);

        $this->expectException(\PDOException::class);
        $this->insertEmpresa([
            'codigo' => 'EMP-0002',
            'tipo_pessoa' => 'PF',
            'cnpj' => null,
            'cpf' => '123.456.789-01',
        ]);
    }

    public function test_empresa_pf_sem_cnpj_permitida(): void
    {
        $empresa = $this->insertEmpresa([
            'codigo' => 'EMP-0001',
            'tipo_pessoa' => 'PF',
            'cnpj' => null,
            'cpf' => '987.654.321-00',
        ]);

        $stmt = $this->db()->prepare('SELECT tipo_pessoa, cnpj, cpf FROM empresas WHERE id = :id');
        $stmt->execute([':id' => $empresa['id']]);
        $row = $stmt->fetch();

        $this->assertSame('PF', $row['tipo_pessoa']);
        $this->assertNull($row['cnpj']);
        $this->assertSame('987.654.321-00', $row['cpf']);
    }

    public function test_empresa_codigo_unico(): void
    {
        $this->insertEmpresa(['codigo' => 'EMP-0001', 'cnpj' => '11111111111111']);

        $this->expectException(\PDOException::class);
        $this->insertEmpresa(['codigo' => 'EMP-0001', 'cnpj' => '22222222222222']);
    }

    public function test_perfil_nome_unico(): void
    {
        $this->insertPerfil(['codigo' => 'PRF-0001', 'nome' => 'Admin']);

        $this->expectException(\PDOException::class);
        $this->insertPerfil(['codigo' => 'PRF-0002', 'nome' => 'Admin']);
    }

    public function test_usuario_email_unico(): void
    {
        $empresa = $this->insertEmpresa(['codigo' => 'EMP-0001', 'cnpj' => '11111111111111']);
        $perfil = $this->insertPerfil(['codigo' => 'PRF-0001', 'nome' => 'Admin']);

        $this->insertUsuario($empresa['id'], $perfil['id'], ['codigo' => 'USR-0001', 'email' => 'same@test.com']);

        $this->expectException(\PDOException::class);
        $this->insertUsuario($empresa['id'], $perfil['id'], ['codigo' => 'USR-0002', 'email' => 'same@test.com']);
    }

    public function test_usuario_empresa_vinculo_unico(): void
    {
        $empresa = $this->insertEmpresa(['codigo' => 'EMP-0001', 'cnpj' => '11111111111111']);
        $perfil = $this->insertPerfil(['codigo' => 'PRF-0001', 'nome' => 'Admin']);
        $usuario = $this->insertUsuario($empresa['id'], $perfil['id'], ['codigo' => 'USR-0001', 'email' => 'u@t.com']);

        // Tentar vincular o mesmo usuário à mesma empresa novamente
        $this->expectException(\PDOException::class);
        $this->db()->prepare("
            INSERT INTO usuario_empresa (id, usuario_id, empresa_id, perfil_id)
            VALUES (:id, :uid, :eid, :pid)
        ")->execute([
            ':id'  => generate_uuid(),
            ':uid' => $usuario['id'],
            ':eid' => $empresa['id'],
            ':pid' => $perfil['id'],
        ]);
    }

    // =========================================================
    // CASCADE DELETE
    // =========================================================

    public function test_deletar_empresa_remove_contatos_em_cascade(): void
    {
        $empresa = $this->insertEmpresa(['codigo' => 'EMP-0001', 'cnpj' => '11111111111111']);

        // Inserir contato
        $this->db()->prepare("
            INSERT INTO empresa_contatos (id, empresa_id, nome) VALUES (:id, :eid, :nome)
        ")->execute([':id' => generate_uuid(), ':eid' => $empresa['id'], ':nome' => 'Contato']);

        // Verificar que existe
        $stmt = $this->db()->prepare("SELECT COUNT(*) FROM empresa_contatos WHERE empresa_id = :eid");
        $stmt->execute([':eid' => $empresa['id']]);
        $this->assertSame(1, (int) $stmt->fetchColumn());

        // Deletar empresa
        $this->db()->prepare("DELETE FROM empresas WHERE id = :id")->execute([':id' => $empresa['id']]);

        // Contatos devem ter sido removidos em cascade
        $stmt->execute([':eid' => $empresa['id']]);
        $this->assertSame(0, (int) $stmt->fetchColumn());
    }

    public function test_deletar_perfil_remove_permissoes_em_cascade(): void
    {
        $perfil = $this->insertPerfil(['codigo' => 'PRF-0001', 'nome' => 'Admin']);

        // Inserir permissão
        $this->db()->prepare("
            INSERT INTO perfil_permissoes (id, perfil_id, modulo, acao) VALUES (:id, :pid, :mod, :acao)
        ")->execute([
            ':id'   => generate_uuid(),
            ':pid'  => $perfil['id'],
            ':mod'  => 'empresas',
            ':acao' => 'visualizar',
        ]);

        // Deletar perfil
        $this->db()->prepare("DELETE FROM perfis WHERE id = :id")->execute([':id' => $perfil['id']]);

        // Permissões devem ter sido removidas
        $stmt = $this->db()->prepare("SELECT COUNT(*) FROM perfil_permissoes WHERE perfil_id = :pid");
        $stmt->execute([':pid' => $perfil['id']]);
        $this->assertSame(0, (int) $stmt->fetchColumn());
    }

    // =========================================================
    // RESTRICT DELETE (proteção)
    // =========================================================

    public function test_deletar_perfil_em_uso_falha_restrict(): void
    {
        $empresa = $this->insertEmpresa(['codigo' => 'EMP-0001', 'cnpj' => '11111111111111']);
        $perfil = $this->insertPerfil(['codigo' => 'PRF-0001', 'nome' => 'Admin']);

        // Vincular um usuário a este perfil
        $this->insertUsuario($empresa['id'], $perfil['id'], ['codigo' => 'USR-0001', 'email' => 'u@t.com']);

        // Tentar deletar o perfil deve falhar (FK RESTRICT em usuario_empresa)
        $this->expectException(\PDOException::class);
        $this->db()->prepare("DELETE FROM perfis WHERE id = :id")->execute([':id' => $perfil['id']]);
    }

    // =========================================================
    // ENUM CONSTRAINTS
    // =========================================================

    public function test_empresa_status_invalido_falha(): void
    {
        $this->expectException(\PDOException::class);
        $this->db()->prepare("
            INSERT INTO empresas (id, codigo, cnpj, razao_social, status)
            VALUES (:id, :cod, :cnpj, :razao, 'invalido')
        ")->execute([
            ':id'    => generate_uuid(),
            ':cod'   => 'EMP-9999',
            ':cnpj'  => '99999999999999',
            ':razao' => 'Teste',
        ]);
    }

    // =========================================================
    // NOT NULL CONSTRAINTS
    // =========================================================

    public function test_empresa_sem_razao_social_falha(): void
    {
        $this->expectException(\PDOException::class);
        $this->db()->prepare("
            INSERT INTO empresas (id, codigo, cnpj) VALUES (:id, :cod, :cnpj)
        ")->execute([
            ':id'   => generate_uuid(),
            ':cod'  => 'EMP-9999',
            ':cnpj' => '99999999999999',
        ]);
    }
}
