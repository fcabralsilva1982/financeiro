<?php
declare(strict_types=1);

namespace Tests\Integration;

use ContasReceber;
use Tests\DatabaseTestCase;

class ContasReceberTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists('ContasReceber')) {
            require_once __DIR__ . '/../../models/ContasReceber.php';
        }

        $this->ensureClientesTable();
        $this->loginAsMaster();
    }

    private function ensureClientesTable(): void
    {
        $this->db()->exec('DROP TABLE IF EXISTS contas_receber_historico');
        $this->db()->exec('DROP TABLE IF EXISTS contas_receber');
        $this->db()->exec('DROP TABLE IF EXISTS clientes');

        $this->db()->exec(
            "CREATE TABLE IF NOT EXISTS clientes (
                id CHAR(36) NOT NULL,
                codigo VARCHAR(20) NOT NULL,
                empresa_id CHAR(36) NOT NULL,
                tipo_pessoa ENUM('PF','PJ') NOT NULL DEFAULT 'PJ',
                cpf_cnpj VARCHAR(18) NOT NULL,
                razao_social VARCHAR(255) NOT NULL,
                status ENUM('ativo','bloqueado') NOT NULL DEFAULT 'ativo',
                PRIMARY KEY (id),
                UNIQUE KEY uk_clientes_codigo (codigo),
                UNIQUE KEY uk_clientes_cpf_cnpj_empresa (cpf_cnpj, empresa_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->db()->exec(
            "CREATE TABLE IF NOT EXISTS contas_receber (
                id CHAR(36) NOT NULL,
                empresa_id CHAR(36) NOT NULL,
                cliente_id CHAR(36) NOT NULL,
                codigo VARCHAR(20) NOT NULL,
                descricao VARCHAR(255) NOT NULL,
                valor_total DECIMAL(15,2) NOT NULL,
                data_emissao DATE NOT NULL,
                data_vencimento DATE NOT NULL,
                forma_cobranca VARCHAR(50) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'emitida',
                data_pagamento DATE NULL,
                valor_pago DECIMAL(15,2) NULL,
                forma_recebimento VARCHAR(30) NULL,
                observacoes_baixa TEXT NULL,
                observacoes TEXT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uk_contas_receber_codigo (codigo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->db()->exec(
            "CREATE TABLE IF NOT EXISTS contas_receber_historico (
                id CHAR(36) NOT NULL,
                conta_receber_id CHAR(36) NOT NULL,
                parcela_id CHAR(36) NULL,
                evento VARCHAR(100) NOT NULL,
                descricao VARCHAR(255) NULL,
                canal ENUM('email','whatsapp','sistema','webhook','outro') NOT NULL,
                data_evento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                usuario_id CHAR(36) NULL,
                PRIMARY KEY (id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function test_criar_nova_conta_nasce_emitida(): void
    {
        $empresa = $this->insertEmpresa([
            'codigo' => 'EMP-1001',
            'cnpj' => '12345678000199',
        ]);

        $clienteId = generate_uuid();
        $codigoCliente = 'CLI-' . substr(str_replace('-', '', generate_uuid()), 0, 8);

        $this->db()->prepare(
            "INSERT INTO clientes (
                id, codigo, empresa_id, tipo_pessoa, cpf_cnpj, razao_social, status
            ) VALUES (
                :id, :codigo, :empresa_id, 'PJ', :cpf_cnpj, :razao_social, 'ativo'
            )"
        )->execute([
            ':id' => $clienteId,
            ':codigo' => $codigoCliente,
            ':empresa_id' => $empresa['id'],
            ':cpf_cnpj' => '12.345.678/0001-99',
            ':razao_social' => 'Cliente Teste LTDA',
        ]);

        $dados = [
            'empresa_id' => $empresa['id'],
            'cliente_id' => $clienteId,
            'descricao' => 'Serviço mensal de suporte',
            'valor_total' => '1500.00',
            'data_emissao' => '2026-07-12',
            'data_vencimento' => '2026-08-12',
            'forma_cobranca' => 'boleto',
            'observacoes' => 'Cadastro inicial',
        ];

        $contaId = ContasReceber::criar($dados);

        $stmt = $this->db()->prepare(
            'SELECT status, cliente_id, empresa_id FROM contas_receber WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $contaId]);
        $conta = $stmt->fetch();

        $this->assertNotFalse($conta);
        $this->assertSame('emitida', $conta['status']);
        $this->assertSame($clienteId, $conta['cliente_id']);
        $this->assertSame($empresa['id'], $conta['empresa_id']);
    }

    public function test_baixar_conta_marca_como_paga(): void
    {
        $empresa = $this->insertEmpresa([
            'codigo' => 'EMP-1002',
            'cnpj' => '22345678000188',
        ]);

        $clienteId = generate_uuid();
        $codigoCliente = 'CLI-' . substr(str_replace('-', '', generate_uuid()), 0, 8);
        $this->db()->prepare(
            "INSERT INTO clientes (id, codigo, empresa_id, tipo_pessoa, cpf_cnpj, razao_social, status)
             VALUES (:id, :codigo, :empresa_id, 'PJ', :cpf_cnpj, :razao_social, 'ativo')"
        )->execute([
            ':id' => $clienteId,
            ':codigo' => $codigoCliente,
            ':empresa_id' => $empresa['id'],
            ':cpf_cnpj' => '22.345.678/0001-88',
            ':razao_social' => 'Cliente Baixa LTDA',
        ]);

        $contaId = ContasReceber::criar([
            'empresa_id' => $empresa['id'],
            'cliente_id' => $clienteId,
            'descricao' => 'Servico pontual',
            'valor_total' => '250.00',
            'data_emissao' => '2026-07-12',
            'data_vencimento' => '2026-07-20',
            'forma_cobranca' => 'pix',
        ]);

        ContasReceber::baixar($contaId, $empresa['id'], [
            'data_pagamento' => '2026-07-13',
            'valor_pago' => '250.00',
            'forma_recebimento' => 'pix',
            'observacoes_baixa' => 'Pago via PIX',
        ], 'usuario-teste');

        $stmt = $this->db()->prepare('SELECT status, data_pagamento, valor_pago, forma_recebimento, observacoes_baixa FROM contas_receber WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $contaId]);
        $conta = $stmt->fetch();

        $this->assertNotFalse($conta);
        $this->assertSame('paga', $conta['status']);
        $this->assertSame('2026-07-13', $conta['data_pagamento']);
        $this->assertSame('250.00', $conta['valor_pago']);
        $this->assertSame('pix', $conta['forma_recebimento']);
        $this->assertSame('Pago via PIX', $conta['observacoes_baixa']);

        $stmtHistorico = $this->db()->prepare('SELECT COUNT(*) FROM contas_receber_historico WHERE conta_receber_id = :id AND evento = \'baixa\'');
        $stmtHistorico->execute([':id' => $contaId]);
        $this->assertSame(1, (int) $stmtHistorico->fetchColumn());
    }
}