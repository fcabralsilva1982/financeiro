<?php
declare(strict_types=1);

namespace Tests\Integration;

use Tests\DatabaseTestCase;

class ClientesDocumentosTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->ensureClientesDocumentosTables();
        $this->loginAsMaster();
    }

    private function ensureClientesDocumentosTables(): void
    {
        $this->db()->exec('DROP TABLE IF EXISTS cliente_documentos');
        $this->db()->exec('DROP TABLE IF EXISTS clientes');

        $this->db()->exec(
            "CREATE TABLE IF NOT EXISTS clientes (
                id CHAR(36) NOT NULL,
                codigo VARCHAR(20) NOT NULL,
                empresa_id CHAR(36) NOT NULL,
                tipo_pessoa ENUM('PF','PJ') NOT NULL DEFAULT 'PJ',
                cpf_cnpj VARCHAR(18) NOT NULL,
                razao_social VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL,
                status ENUM('ativo','bloqueado') NOT NULL DEFAULT 'ativo',
                PRIMARY KEY (id),
                UNIQUE KEY uk_clientes_codigo (codigo),
                UNIQUE KEY uk_clientes_cpf_cnpj_empresa (cpf_cnpj, empresa_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->db()->exec(
            "CREATE TABLE IF NOT EXISTS cliente_documentos (
                id CHAR(36) NOT NULL,
                cliente_id CHAR(36) NOT NULL,
                empresa_id CHAR(36) NOT NULL,
                tipo_documento ENUM('rg','cpf','cnh','comprovante_residencia','cartao_cnpj','contrato_social','inscricao_estadual','procuracao','foto_frente','foto_verso','outro') NOT NULL DEFAULT 'outro',
                nome_original VARCHAR(255) NOT NULL,
                arquivo_path VARCHAR(500) NOT NULL,
                mime_type VARCHAR(100) NOT NULL,
                tamanho_bytes BIGINT NOT NULL,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_cliente_documentos_cliente (cliente_id),
                KEY idx_cliente_documentos_empresa (empresa_id),
                CONSTRAINT fk_test_cliente_documentos_cliente
                    FOREIGN KEY (cliente_id) REFERENCES clientes(id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function test_persiste_documento_do_cliente(): void
    {
        $empresa = $this->insertEmpresa([
            'codigo' => 'EMP-CLDOC',
            'cnpj' => '88999999000155',
        ]);

        $clienteId = generate_uuid();
        $this->db()->prepare(
            "INSERT INTO clientes (id, codigo, empresa_id, tipo_pessoa, cpf_cnpj, razao_social, email, status)
             VALUES (:id, :codigo, :empresa_id, 'PJ', :cpf_cnpj, :razao_social, :email, 'ativo')"
        )->execute([
            ':id' => $clienteId,
            ':codigo' => 'CLI-9001',
            ':empresa_id' => $empresa['id'],
            ':cpf_cnpj' => '88.999.999/0001-55',
            ':razao_social' => 'Cliente Documento LTDA',
            ':email' => 'doc@cliente.com',
        ]);

        $documentoId = generate_uuid();
        $this->db()->prepare(
            "INSERT INTO cliente_documentos (id, cliente_id, empresa_id, tipo_documento, nome_original, arquivo_path, mime_type, tamanho_bytes)
             VALUES (:id, :cliente_id, :empresa_id, :tipo_documento, :nome_original, :arquivo_path, :mime_type, :tamanho_bytes)"
        )->execute([
            ':id' => $documentoId,
            ':cliente_id' => $clienteId,
            ':empresa_id' => $empresa['id'],
            ':tipo_documento' => 'cpf',
            ':nome_original' => 'cpf.pdf',
            ':arquivo_path' => 'cliente_documentos/cli_teste_cpf.pdf',
            ':mime_type' => 'application/pdf',
            ':tamanho_bytes' => 1024,
        ]);

        $stmt = $this->db()->prepare('SELECT tipo_documento, nome_original, arquivo_path FROM cliente_documentos WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $documentoId]);
        $documento = $stmt->fetch();

        $this->assertNotFalse($documento);
        $this->assertSame('cpf', $documento['tipo_documento']);
        $this->assertSame('cpf.pdf', $documento['nome_original']);
        $this->assertSame('cliente_documentos/cli_teste_cpf.pdf', $documento['arquivo_path']);
    }

    public function test_exclusao_do_cliente_remove_documentos(): void
    {
        $empresa = $this->insertEmpresa([
            'codigo' => 'EMP-CLD2',
            'cnpj' => '77999999000166',
        ]);

        $clienteId = generate_uuid();
        $this->db()->prepare(
            "INSERT INTO clientes (id, codigo, empresa_id, tipo_pessoa, cpf_cnpj, razao_social, email, status)
             VALUES (:id, :codigo, :empresa_id, 'PJ', :cpf_cnpj, :razao_social, :email, 'ativo')"
        )->execute([
            ':id' => $clienteId,
            ':codigo' => 'CLI-9002',
            ':empresa_id' => $empresa['id'],
            ':cpf_cnpj' => '77.999.999/0001-66',
            ':razao_social' => 'Cliente Cascade LTDA',
            ':email' => 'cascade@cliente.com',
        ]);

        $this->db()->prepare(
            "INSERT INTO cliente_documentos (id, cliente_id, empresa_id, tipo_documento, nome_original, arquivo_path, mime_type, tamanho_bytes)
             VALUES (:id, :cliente_id, :empresa_id, 'rg', :nome_original, :arquivo_path, :mime_type, :tamanho_bytes)"
        )->execute([
            ':id' => generate_uuid(),
            ':cliente_id' => $clienteId,
            ':empresa_id' => $empresa['id'],
            ':nome_original' => 'rg.png',
            ':arquivo_path' => 'cliente_documentos/cli_teste_rg.png',
            ':mime_type' => 'image/png',
            ':tamanho_bytes' => 2048,
        ]);

        $this->db()->prepare('DELETE FROM clientes WHERE id = :id')->execute([':id' => $clienteId]);

        $stmt = $this->db()->prepare('SELECT COUNT(*) FROM cliente_documentos WHERE cliente_id = :cliente_id');
        $stmt->execute([':cliente_id' => $clienteId]);
        $this->assertSame(0, (int) $stmt->fetchColumn());
    }
}
