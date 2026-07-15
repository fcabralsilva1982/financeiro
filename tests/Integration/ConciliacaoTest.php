<?php
declare(strict_types=1);

namespace Tests\Integration;

use Conciliacao;
use Tests\DatabaseTestCase;

class ConciliacaoTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists('Conciliacao')) {
            require_once __DIR__ . '/../../models/Conciliacao.php';
        }

        $this->ensureConciliacaoTables();
        $this->loginAsMaster();
    }

    private function ensureConciliacaoTables(): void
    {
        $this->db()->exec('SET FOREIGN_KEY_CHECKS = 0');
        $this->db()->exec('DROP TABLE IF EXISTS conciliacao_transacoes');
        $this->db()->exec('DROP TABLE IF EXISTS conciliacao_bancaria');
        $this->db()->exec('DROP TABLE IF EXISTS contas_pagar_parcelas');
        $this->db()->exec('DROP TABLE IF EXISTS contas_pagar');
        $this->db()->exec('DROP TABLE IF EXISTS contas_receber');
        $this->db()->exec('SET FOREIGN_KEY_CHECKS = 1');

        $this->db()->exec(
            "CREATE TABLE IF NOT EXISTS contas_receber (
                id CHAR(36) NOT NULL,
                empresa_id CHAR(36) NOT NULL,
                cliente_id CHAR(36) NULL,
                codigo VARCHAR(20) NOT NULL,
                descricao VARCHAR(255) NOT NULL,
                valor_total DECIMAL(15,2) NOT NULL,
                data_emissao DATE NOT NULL,
                data_vencimento DATE NOT NULL,
                forma_cobranca VARCHAR(50) NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'emitida',
                PRIMARY KEY (id),
                UNIQUE KEY uk_contas_receber_codigo (codigo),
                CONSTRAINT fk_test_cr_empresa
                    FOREIGN KEY (empresa_id) REFERENCES empresas(id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->db()->exec(
            "CREATE TABLE IF NOT EXISTS contas_pagar (
                id CHAR(36) NOT NULL,
                empresa_id CHAR(36) NOT NULL,
                fornecedor_id CHAR(36) NULL,
                codigo VARCHAR(20) NOT NULL,
                descricao VARCHAR(255) NOT NULL,
                valor_total DECIMAL(15,2) NOT NULL,
                data_emissao DATE NOT NULL,
                data_vencimento DATE NOT NULL,
                forma_pagamento VARCHAR(50) NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'rascunho',
                PRIMARY KEY (id),
                UNIQUE KEY uk_contas_pagar_empresa_codigo (empresa_id, codigo),
                CONSTRAINT fk_test_cp_empresa
                    FOREIGN KEY (empresa_id) REFERENCES empresas(id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->db()->exec(
            "CREATE TABLE IF NOT EXISTS contas_pagar_parcelas (
                id CHAR(36) NOT NULL,
                conta_pagar_id CHAR(36) NOT NULL,
                numero_parcela INT NOT NULL,
                valor DECIMAL(15,2) NOT NULL,
                data_vencimento DATE NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pendente',
                PRIMARY KEY (id),
                CONSTRAINT fk_test_cpp_conta
                    FOREIGN KEY (conta_pagar_id) REFERENCES contas_pagar(id)
                    ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->db()->exec(
            "CREATE TABLE IF NOT EXISTS conciliacao_bancaria (
                id CHAR(36) NOT NULL,
                empresa_id CHAR(36) NOT NULL,
                usuario_id CHAR(36) NULL,
                nome_arquivo VARCHAR(255) NOT NULL,
                data_importacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                quantidade_transacoes INT NOT NULL DEFAULT 0,
                quantidade_duplicadas INT NOT NULL DEFAULT 0,
                quantidade_conciliadas_auto INT NOT NULL DEFAULT 0,
                quantidade_pendentes INT NOT NULL DEFAULT 0,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                CONSTRAINT fk_test_cb_empresa
                    FOREIGN KEY (empresa_id) REFERENCES empresas(id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_test_cb_usuario
                    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
                    ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->db()->exec(
            "CREATE TABLE IF NOT EXISTS conciliacao_transacoes (
                id CHAR(36) NOT NULL,
                conciliacao_bancaria_id CHAR(36) NOT NULL,
                empresa_id CHAR(36) NOT NULL,
                fitid VARCHAR(100) NOT NULL,
                data_movimento DATE NOT NULL,
                valor DECIMAL(15,2) NOT NULL,
                tipo_movimento ENUM('credito','debito') NOT NULL,
                descricao_banco VARCHAR(255) NULL,
                memo TEXT NULL,
                status_conciliacao ENUM('pendente','conciliado_auto','conciliado_manual','ignorado','movimento_interno') NOT NULL DEFAULT 'pendente',
                origem_match ENUM('conta_receber','conta_pagar','novo_recebimento','nova_despesa','movimento_interno','nenhum') NOT NULL DEFAULT 'nenhum',
                conta_receber_id CHAR(36) NULL,
                conta_pagar_parcela_id CHAR(36) NULL,
                confianca_match TINYINT UNSIGNED NULL,
                acao_confirmada_por_usuario_id CHAR(36) NULL,
                acao_confirmada_em DATETIME NULL,
                criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY uk_ct_empresa_fitid (empresa_id, fitid),
                CONSTRAINT fk_test_ct_importacao
                    FOREIGN KEY (conciliacao_bancaria_id) REFERENCES conciliacao_bancaria(id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_test_ct_empresa
                    FOREIGN KEY (empresa_id) REFERENCES empresas(id)
                    ON DELETE CASCADE ON UPDATE CASCADE,
                CONSTRAINT fk_test_ct_conta_receber
                    FOREIGN KEY (conta_receber_id) REFERENCES contas_receber(id)
                    ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT fk_test_ct_parcela_pagar
                    FOREIGN KEY (conta_pagar_parcela_id) REFERENCES contas_pagar_parcelas(id)
                    ON DELETE SET NULL ON UPDATE CASCADE,
                CONSTRAINT fk_test_ct_confirmado_por
                    FOREIGN KEY (acao_confirmada_por_usuario_id) REFERENCES usuarios(id)
                    ON DELETE SET NULL ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function test_criar_importacao_ignora_fitid_duplicado_no_mesmo_arquivo(): void
    {
        $empresa = $this->insertEmpresa([
            'codigo' => 'EMP-3001',
            'cnpj' => '32345678000177',
        ]);
        $perfil = $this->insertPerfil(['nome' => 'Administrador Conciliação']);
        $usuario = $this->insertUsuario($empresa['id'], $perfil['id']);

        $importacao = Conciliacao::criarImportacao($empresa['id'], $usuario['id'], 'extrato_julho.ofx', [
            [
                'fitid' => 'FIT-001',
                'data_movimento' => '2026-07-15',
                'valor' => 100.50,
                'tipo_movimento' => 'credito',
                'descricao_banco' => 'PIX RECEBIDO',
                'memo' => 'Cliente A',
            ],
            [
                'fitid' => 'FIT-001',
                'data_movimento' => '2026-07-15',
                'valor' => 100.50,
                'tipo_movimento' => 'credito',
                'descricao_banco' => 'PIX RECEBIDO',
                'memo' => 'Cliente A duplicado',
            ],
            [
                'fitid' => 'FIT-002',
                'data_movimento' => '2026-07-16',
                'valor' => 80.00,
                'tipo_movimento' => 'debito',
                'descricao_banco' => 'TARIFA',
                'memo' => 'Pacote',
            ],
        ]);

        $this->assertSame(2, (int) $importacao['quantidade_transacoes']);
        $this->assertSame(1, (int) $importacao['quantidade_duplicadas']);
        $this->assertSame(2, (int) $importacao['quantidade_pendentes']);

        $stmt = $this->db()->prepare('SELECT COUNT(*) FROM conciliacao_transacoes WHERE conciliacao_bancaria_id = :id');
        $stmt->execute([':id' => $importacao['id']]);
        $this->assertSame(2, (int) $stmt->fetchColumn());
    }

    public function test_criar_importacao_ignora_fitid_ja_importado_anteriormente(): void
    {
        $empresa = $this->insertEmpresa([
            'codigo' => 'EMP-3002',
            'cnpj' => '42345678000166',
        ]);
        $perfil = $this->insertPerfil(['nome' => 'Administrador Conciliação 2']);
        $usuario = $this->insertUsuario($empresa['id'], $perfil['id']);

        Conciliacao::criarImportacao($empresa['id'], $usuario['id'], 'extrato_1.ofx', [
            [
                'fitid' => 'FIT-ANTIGO',
                'data_movimento' => '2026-07-10',
                'valor' => 45.00,
                'tipo_movimento' => 'debito',
                'descricao_banco' => 'TARIFA',
                'memo' => 'Tarifa 1',
            ],
        ]);

        $importacao = Conciliacao::criarImportacao($empresa['id'], $usuario['id'], 'extrato_2.ofx', [
            [
                'fitid' => 'FIT-ANTIGO',
                'data_movimento' => '2026-07-11',
                'valor' => 45.00,
                'tipo_movimento' => 'debito',
                'descricao_banco' => 'TARIFA',
                'memo' => 'Tarifa duplicada',
            ],
            [
                'fitid' => 'FIT-NOVO',
                'data_movimento' => '2026-07-12',
                'valor' => 500.00,
                'tipo_movimento' => 'credito',
                'descricao_banco' => 'PIX RECEBIDO',
                'memo' => 'Cliente B',
            ],
        ]);

        $this->assertSame(1, (int) $importacao['quantidade_transacoes']);
        $this->assertSame(1, (int) $importacao['quantidade_duplicadas']);
        $this->assertSame(1, (int) $importacao['quantidade_pendentes']);
    }

    public function test_marcar_movimento_interno_atualiza_resumo_da_importacao(): void
    {
        $empresa = $this->insertEmpresa([
            'codigo' => 'EMP-3003',
            'cnpj' => '52345678000155',
        ]);
        $perfil = $this->insertPerfil(['nome' => 'Administrador Conciliação 3']);
        $usuario = $this->insertUsuario($empresa['id'], $perfil['id']);

        $importacao = Conciliacao::criarImportacao($empresa['id'], $usuario['id'], 'extrato_3.ofx', [
            [
                'fitid' => 'FIT-INT-001',
                'data_movimento' => '2026-07-20',
                'valor' => 700.00,
                'tipo_movimento' => 'debito',
                'descricao_banco' => 'TRANSFERENCIA',
                'memo' => 'Entre contas',
            ],
        ]);

        $transacao = Conciliacao::listarTransacoes((string) $importacao['id'], $empresa['id'])[0];
        Conciliacao::marcarMovimentoInterno((string) $transacao['id'], $empresa['id'], $usuario['id']);

        $importacaoAtualizada = Conciliacao::buscarImportacao((string) $importacao['id'], $empresa['id']);
        $transacaoAtualizada = Conciliacao::listarTransacoes((string) $importacao['id'], $empresa['id'])[0];

        $this->assertNotNull($importacaoAtualizada);
        $this->assertSame(0, (int) $importacaoAtualizada['quantidade_pendentes']);
        $this->assertSame('movimento_interno', $transacaoAtualizada['status_conciliacao']);
        $this->assertSame('movimento_interno', $transacaoAtualizada['origem_match']);
    }
}