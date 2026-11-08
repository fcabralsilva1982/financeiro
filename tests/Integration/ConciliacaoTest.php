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

    public function test_matching_automatico_concilia_credito_com_conta_receber_unica(): void
    {
        $empresa = $this->insertEmpresa([
            'codigo' => 'EMP-3004',
            'cnpj' => '62345678000144',
        ]);
        $perfil = $this->insertPerfil(['nome' => 'Administrador Conciliação 4']);
        $usuario = $this->insertUsuario($empresa['id'], $perfil['id']);

        $contaReceberId = generate_uuid();
        $this->db()->prepare(
            "INSERT INTO contas_receber (
                id, empresa_id, cliente_id, codigo, descricao, valor_total,
                data_emissao, data_vencimento, forma_cobranca, status
            ) VALUES (
                :id, :empresa_id, :cliente_id, :codigo, :descricao, :valor_total,
                :data_emissao, :data_vencimento, :forma_cobranca, :status
            )"
        )->execute([
            ':id' => $contaReceberId,
            ':empresa_id' => $empresa['id'],
            ':cliente_id' => generate_uuid(),
            ':codigo' => 'CR-3004',
            ':descricao' => 'Receita de serviço mensal',
            ':valor_total' => 250.00,
            ':data_emissao' => '2026-07-10',
            ':data_vencimento' => '2026-07-15',
            ':forma_cobranca' => 'pix',
            ':status' => 'emitida',
        ]);

        $importacao = Conciliacao::criarImportacao($empresa['id'], $usuario['id'], 'extrato_credito.ofx', [
            [
                'fitid' => 'FIT-MATCH-CR-001',
                'data_movimento' => '2026-07-16',
                'valor' => 250.00,
                'tipo_movimento' => 'credito',
                'descricao_banco' => 'PIX RECEBIDO CLIENTE',
                'memo' => 'Serviço mensal',
            ],
        ]);

        $resultado = Conciliacao::executarMatchingAutomatico((string) $importacao['id'], $empresa['id'], $usuario['id']);
        $transacao = Conciliacao::listarTransacoes((string) $importacao['id'], $empresa['id'])[0];
        $importacaoAtualizada = Conciliacao::buscarImportacao((string) $importacao['id'], $empresa['id']);

        $this->assertSame(1, (int) $resultado['conciliadas_auto']);
        $this->assertSame('conciliado_auto', $transacao['status_conciliacao']);
        $this->assertSame('conta_receber', $transacao['origem_match']);
        $this->assertSame($contaReceberId, $transacao['conta_receber_id']);
        $this->assertNotNull($importacaoAtualizada);
        $this->assertSame(1, (int) $importacaoAtualizada['quantidade_conciliadas_auto']);
        $this->assertSame(0, (int) $importacaoAtualizada['quantidade_pendentes']);
    }

    public function test_matching_automatico_concilia_debito_com_parcela_conta_pagar_unica(): void
    {
        $empresa = $this->insertEmpresa([
            'codigo' => 'EMP-3005',
            'cnpj' => '72345678000133',
        ]);
        $perfil = $this->insertPerfil(['nome' => 'Administrador Conciliação 5']);
        $usuario = $this->insertUsuario($empresa['id'], $perfil['id']);

        $contaPagarId = generate_uuid();
        $this->db()->prepare(
            "INSERT INTO contas_pagar (
                id, empresa_id, fornecedor_id, codigo, descricao, valor_total,
                data_emissao, data_vencimento, forma_pagamento, status
            ) VALUES (
                :id, :empresa_id, :fornecedor_id, :codigo, :descricao, :valor_total,
                :data_emissao, :data_vencimento, :forma_pagamento, :status
            )"
        )->execute([
            ':id' => $contaPagarId,
            ':empresa_id' => $empresa['id'],
            ':fornecedor_id' => generate_uuid(),
            ':codigo' => 'CP-3005',
            ':descricao' => 'Pagamento fornecedor de insumos',
            ':valor_total' => 480.00,
            ':data_emissao' => '2026-07-10',
            ':data_vencimento' => '2026-07-15',
            ':forma_pagamento' => 'pix',
            ':status' => 'aprovada',
        ]);

        $parcelaId = generate_uuid();
        $this->db()->prepare(
            "INSERT INTO contas_pagar_parcelas (
                id, conta_pagar_id, numero_parcela, valor, data_vencimento, status
            ) VALUES (
                :id, :conta_pagar_id, :numero_parcela, :valor, :data_vencimento, :status
            )"
        )->execute([
            ':id' => $parcelaId,
            ':conta_pagar_id' => $contaPagarId,
            ':numero_parcela' => 1,
            ':valor' => 480.00,
            ':data_vencimento' => '2026-07-15',
            ':status' => 'pendente',
        ]);

        $importacao = Conciliacao::criarImportacao($empresa['id'], $usuario['id'], 'extrato_debito.ofx', [
            [
                'fitid' => 'FIT-MATCH-CP-001',
                'data_movimento' => '2026-07-16',
                'valor' => 480.00,
                'tipo_movimento' => 'debito',
                'descricao_banco' => 'PIX ENVIADO FORNECEDOR',
                'memo' => 'Insumos',
            ],
        ]);

        $resultado = Conciliacao::executarMatchingAutomatico((string) $importacao['id'], $empresa['id'], $usuario['id']);
        $transacao = Conciliacao::listarTransacoes((string) $importacao['id'], $empresa['id'])[0];

        $this->assertSame(1, (int) $resultado['conciliadas_auto']);
        $this->assertSame('conciliado_auto', $transacao['status_conciliacao']);
        $this->assertSame('conta_pagar', $transacao['origem_match']);
        $this->assertSame($parcelaId, $transacao['conta_pagar_parcela_id']);
    }

    public function test_matching_automatico_nao_concilia_quando_ha_ambiguidade(): void
    {
        $empresa = $this->insertEmpresa([
            'codigo' => 'EMP-3006',
            'cnpj' => '82345678000122',
        ]);
        $perfil = $this->insertPerfil(['nome' => 'Administrador Conciliação 6']);
        $usuario = $this->insertUsuario($empresa['id'], $perfil['id']);

        for ($i = 1; $i <= 2; $i++) {
            $this->db()->prepare(
                "INSERT INTO contas_receber (
                    id, empresa_id, cliente_id, codigo, descricao, valor_total,
                    data_emissao, data_vencimento, forma_cobranca, status
                ) VALUES (
                    :id, :empresa_id, :cliente_id, :codigo, :descricao, :valor_total,
                    :data_emissao, :data_vencimento, :forma_cobranca, :status
                )"
            )->execute([
                ':id' => generate_uuid(),
                ':empresa_id' => $empresa['id'],
                ':cliente_id' => generate_uuid(),
                ':codigo' => 'CR-3006-' . $i,
                ':descricao' => 'Receita recorrente ' . $i,
                ':valor_total' => 100.00,
                ':data_emissao' => '2026-07-10',
                ':data_vencimento' => '2026-07-15',
                ':forma_cobranca' => 'pix',
                ':status' => 'emitida',
            ]);
        }

        $importacao = Conciliacao::criarImportacao($empresa['id'], $usuario['id'], 'extrato_ambiguo.ofx', [
            [
                'fitid' => 'FIT-AMB-001',
                'data_movimento' => '2026-07-15',
                'valor' => 100.00,
                'tipo_movimento' => 'credito',
                'descricao_banco' => 'PIX RECEBIDO',
                'memo' => 'Recebimento sem identificador',
            ],
        ]);

        $resultado = Conciliacao::executarMatchingAutomatico((string) $importacao['id'], $empresa['id'], $usuario['id']);
        $transacao = Conciliacao::listarTransacoes((string) $importacao['id'], $empresa['id'])[0];

        $this->assertSame(0, (int) $resultado['conciliadas_auto']);
        $this->assertSame('pendente', $transacao['status_conciliacao']);
        $this->assertNull($transacao['conta_receber_id']);
    }
}