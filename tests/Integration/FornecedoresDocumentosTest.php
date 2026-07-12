<?php
declare(strict_types=1);

namespace Tests\Integration;

use Tests\DatabaseTestCase;

class FornecedoresDocumentosTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureFornecedoresDocumentosTables();
        $this->loginAsMaster();
    }

    private function ensureFornecedoresDocumentosTables(): void
    {
        $this->db()->exec('DROP TABLE IF EXISTS fornecedor_documentos');
        $this->db()->exec('DROP TABLE IF EXISTS fornecedores');

        $this->db()->exec(
            "CREATE TABLE IF NOT EXISTS fornecedores (
                id CHAR(36) NOT NULL,
                codigo VARCHAR(20) NOT NULL,
                empresa_id CHAR(36) NOT NULL,
                tipo_pessoa ENUM('PF','PJ') NOT NULL DEFAULT 'PJ',
                cpf_cnpj VARCHAR(18) NOT NULL,
                razao_social VARCHAR(255) NOT NULL,
                status ENUM('ativo','bloqueado') NOT NULL DEFAULT 'ativo',
                PRIMARY KEY (id),
                UNIQUE KEY uk_fornecedores_codigo (codigo),
                UNIQUE KEY uk_fornecedores_cpf_cnpj_empresa (cpf_cnpj, empresa_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->db()->exec(
            "CREATE TABLE IF NOT EXISTS fornecedor_documentos (
                id CHAR(36) NOT NULL,
                fornecedor_id CHAR(36) NOT NULL,
                empresa_id CHAR(36) NOT NULL,
                tipo_documento ENUM('rg','cpf','cnh','comprovante_residencia','cartao_cnpj','contrato_social','inscricao_estadual','procuracao','foto_frente','foto_verso','outro') NOT NULL DEFAULT 'outro',
                nome_original VARCHAR(255) NOT NULL,
                arquivo_path VARCHAR(500) NOT NULL,
                mime_type VARCHAR(100) NOT NULL,
                tamanho_bytes BIGINT NOT NULL,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_fornecedor_documentos_fornecedor (fornecedor_id),
                CONSTRAINT fk_test_fornecedor_documentos_fornecedor
                    FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function test_persiste_documento_do_fornecedor(): void
    {
        $empresa = $this->insertEmpresa([
            'codigo' => 'EMP-FORDOC',
            'cnpj' => '66999999000144',
        ]);

        $fornecedorId = generate_uuid();
        $this->db()->prepare(
            "INSERT INTO fornecedores (id, codigo, empresa_id, tipo_pessoa, cpf_cnpj, razao_social, status)
             VALUES (:id, :codigo, :empresa_id, 'PJ', :cpf_cnpj, :razao_social, 'ativo')"
        )->execute([
            ':id' => $fornecedorId,
            ':codigo' => 'FOR-9001',
            ':empresa_id' => $empresa['id'],
            ':cpf_cnpj' => '66.999.999/0001-44',
            ':razao_social' => 'Fornecedor Documento LTDA',
        ]);

        $documentoId = generate_uuid();
        $this->db()->prepare(
            "INSERT INTO fornecedor_documentos (id, fornecedor_id, empresa_id, tipo_documento, nome_original, arquivo_path, mime_type, tamanho_bytes)
             VALUES (:id, :fornecedor_id, :empresa_id, :tipo_documento, :nome_original, :arquivo_path, :mime_type, :tamanho_bytes)"
        )->execute([
            ':id' => $documentoId,
            ':fornecedor_id' => $fornecedorId,
            ':empresa_id' => $empresa['id'],
            ':tipo_documento' => 'cartao_cnpj',
            ':nome_original' => 'cnpj.pdf',
            ':arquivo_path' => 'fornecedor_documentos/for_teste_cnpj.pdf',
            ':mime_type' => 'application/pdf',
            ':tamanho_bytes' => 1024,
        ]);

        $stmt = $this->db()->prepare('SELECT tipo_documento, nome_original, arquivo_path FROM fornecedor_documentos WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $documentoId]);
        $documento = $stmt->fetch();

        $this->assertNotFalse($documento);
        $this->assertSame('cartao_cnpj', $documento['tipo_documento']);
        $this->assertSame('cnpj.pdf', $documento['nome_original']);
        $this->assertSame('fornecedor_documentos/for_teste_cnpj.pdf', $documento['arquivo_path']);
    }

    public function test_exclusao_do_fornecedor_remove_documentos(): void
    {
        $empresa = $this->insertEmpresa([
            'codigo' => 'EMP-FORD2',
            'cnpj' => '55999999000133',
        ]);

        $fornecedorId = generate_uuid();
        $this->db()->prepare(
            "INSERT INTO fornecedores (id, codigo, empresa_id, tipo_pessoa, cpf_cnpj, razao_social, status)
             VALUES (:id, :codigo, :empresa_id, 'PJ', :cpf_cnpj, :razao_social, 'ativo')"
        )->execute([
            ':id' => $fornecedorId,
            ':codigo' => 'FOR-9002',
            ':empresa_id' => $empresa['id'],
            ':cpf_cnpj' => '55.999.999/0001-33',
            ':razao_social' => 'Fornecedor Cascade LTDA',
        ]);

        $this->db()->prepare(
            "INSERT INTO fornecedor_documentos (id, fornecedor_id, empresa_id, tipo_documento, nome_original, arquivo_path, mime_type, tamanho_bytes)
             VALUES (:id, :fornecedor_id, :empresa_id, 'rg', :nome_original, :arquivo_path, :mime_type, :tamanho_bytes)"
        )->execute([
            ':id' => generate_uuid(),
            ':fornecedor_id' => $fornecedorId,
            ':empresa_id' => $empresa['id'],
            ':nome_original' => 'rg.png',
            ':arquivo_path' => 'fornecedor_documentos/for_teste_rg.png',
            ':mime_type' => 'image/png',
            ':tamanho_bytes' => 2048,
        ]);

        $this->db()->prepare('DELETE FROM fornecedores WHERE id = :id')->execute([':id' => $fornecedorId]);

        $stmt = $this->db()->prepare('SELECT COUNT(*) FROM fornecedor_documentos WHERE fornecedor_id = :fornecedor_id');
        $stmt->execute([':fornecedor_id' => $fornecedorId]);
        $this->assertSame(0, (int) $stmt->fetchColumn());
    }
}
