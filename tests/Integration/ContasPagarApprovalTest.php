<?php
declare(strict_types=1);

namespace Tests\Integration;

use ContasPagar;
use Tests\DatabaseTestCase;

class ContasPagarApprovalTest extends DatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!class_exists('ContasPagar')) {
            require_once __DIR__ . '/../../models/ContasPagar.php';
        }

        $this->rebuildApprovalTables();
    }

    private function rebuildApprovalTables(): void
    {
        $pdo = $this->db();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        $pdo->exec('DROP TABLE IF EXISTS fornecedor_documentos');
        $pdo->exec('DROP TABLE IF EXISTS contas_pagar_historico');
        $pdo->exec('DROP TABLE IF EXISTS contas_pagar_parcelas');
        $pdo->exec('DROP TABLE IF EXISTS contas_pagar');
        $pdo->exec('DROP TABLE IF EXISTS categorias');
        $pdo->exec('DROP TABLE IF EXISTS fornecedores');
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');

        $pdo->exec("CREATE TABLE fornecedores (
            id CHAR(36) NOT NULL,
            codigo VARCHAR(20) NOT NULL,
            empresa_id CHAR(36) NOT NULL,
            razao_social VARCHAR(255) NOT NULL,
            status ENUM('ativo','bloqueado') NOT NULL DEFAULT 'ativo',
            PRIMARY KEY (id),
            UNIQUE KEY uk_fornecedores_codigo (codigo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE categorias (
            id CHAR(36) NOT NULL,
            empresa_id CHAR(36) NOT NULL,
            codigo VARCHAR(20) NOT NULL,
            nome VARCHAR(120) NOT NULL,
            tipo ENUM('receita','despesa') NOT NULL,
            status ENUM('ativo','bloqueado') NOT NULL DEFAULT 'ativo',
            PRIMARY KEY (id),
            UNIQUE KEY uk_categorias_codigo (codigo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE contas_pagar (
            id CHAR(36) NOT NULL,
            empresa_id CHAR(36) NOT NULL,
            fornecedor_id CHAR(36) NOT NULL,
            categoria_id CHAR(36) NULL,
            codigo VARCHAR(20) NOT NULL,
            descricao VARCHAR(255) NOT NULL,
            valor_total DECIMAL(15,2) NOT NULL,
            data_emissao DATE NOT NULL,
            data_vencimento DATE NOT NULL,
            forma_pagamento ENUM('boleto','pix','ted','doc','cartao','dinheiro','outro') NOT NULL,
            status ENUM('rascunho','pendente_aprovacao','aprovada','parcialmente_paga','paga','vencida','cancelada') NOT NULL DEFAULT 'rascunho',
            recorrente TINYINT(1) NOT NULL DEFAULT 0,
            periodicidade ENUM('semanal','quinzenal','mensal','bimestral','trimestral','semestral','anual') NULL,
            observacoes TEXT NULL,
            criado_por_usuario_id CHAR(36) NULL,
            aprovado_por_usuario_id CHAR(36) NULL,
            aprovado_em DATETIME NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uk_contas_pagar_codigo (codigo)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE contas_pagar_parcelas (
            id CHAR(36) NOT NULL,
            conta_pagar_id CHAR(36) NOT NULL,
            numero_parcela INT NOT NULL,
            valor DECIMAL(15,2) NOT NULL,
            data_vencimento DATE NOT NULL,
            data_pagamento DATE NULL,
            valor_pago DECIMAL(15,2) NULL,
            status ENUM('pendente','paga','vencida','cancelada') NOT NULL DEFAULT 'pendente',
            forma_pagamento ENUM('boleto','pix','ted','doc','cartao','dinheiro','outro') NULL,
            comprovante_path VARCHAR(500) NULL,
            observacoes VARCHAR(255) NULL,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE contas_pagar_historico (
            id CHAR(36) NOT NULL,
            conta_pagar_id CHAR(36) NOT NULL,
            parcela_id CHAR(36) NULL,
            evento VARCHAR(80) NOT NULL,
            descricao VARCHAR(255) NOT NULL,
            usuario_id CHAR(36) NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private function prepararContaParaAprovacao(bool $permitirAprovacao, bool $mesmoUsuarioCriaEaprova = false): array
    {
        $pdo = $this->db();

        $empresaId = $this->insertEmpresa([
            'codigo' => 'EMP-9001',
            'cnpj' => '99999999999999',
            'razao_social' => 'Empresa Gerente LTDA',
            'nome_fantasia' => 'Gerente',
            'email' => 'gerente@empresa.com',
        ])['id'];

        $perfilId = $this->insertPerfil([
            'codigo' => 'PRF-9001',
            'nome' => 'Gerente',
            'descricao' => 'Perfil gerente',
        ])['id'];

        $usuarioId = $this->insertUsuario($empresaId, $perfilId, [
            'codigo' => 'USR-9001',
            'nome' => 'Maria Santos',
            'email' => 'maria@empresa.com',
        ])['id'];

        $fornecedorId = generate_uuid();
        $categoriaId = generate_uuid();

        if ($permitirAprovacao) {
            $pdo->prepare('INSERT INTO perfil_permissoes (id, perfil_id, modulo, acao) VALUES (:id, :perfil_id, "contas_pagar", "aprovar")')
                ->execute([
                    ':id' => generate_uuid(),
                    ':perfil_id' => $perfilId,
                ]);
        }

        $pdo->prepare('INSERT INTO fornecedores (id, codigo, empresa_id, razao_social, status) VALUES (:id, :codigo, :empresa_id, :razao_social, "ativo")')
            ->execute([
                ':id' => $fornecedorId,
                ':codigo' => 'FOR-9001',
                ':empresa_id' => $empresaId,
                ':razao_social' => 'Fornecedor Teste LTDA',
            ]);

        $pdo->prepare('INSERT INTO categorias (id, empresa_id, codigo, nome, tipo, status) VALUES (:id, :empresa_id, :codigo, :nome, "despesa", "ativo")')
            ->execute([
                ':id' => $categoriaId,
                ':empresa_id' => $empresaId,
                ':codigo' => 'CAT-9001',
                ':nome' => 'Serviços',
            ]);

        $contaId = generate_uuid();
        $pdo->prepare('INSERT INTO contas_pagar (id, empresa_id, fornecedor_id, categoria_id, codigo, descricao, valor_total, data_emissao, data_vencimento, forma_pagamento, status, recorrente, observacoes, criado_por_usuario_id) VALUES (:id, :empresa_id, :fornecedor_id, :categoria_id, :codigo, :descricao, :valor_total, :data_emissao, :data_vencimento, :forma_pagamento, "pendente_aprovacao", 0, :observacoes, :criado_por_usuario_id)')
            ->execute([
                ':id' => $contaId,
                ':empresa_id' => $empresaId,
                ':fornecedor_id' => $fornecedorId,
                ':categoria_id' => $categoriaId,
                ':codigo' => 'CP-9001',
                ':descricao' => 'Conta para aprovação',
                ':valor_total' => '1200.00',
                ':data_emissao' => '2026-07-12',
                ':data_vencimento' => '2026-07-20',
                ':forma_pagamento' => 'pix',
                ':observacoes' => 'Teste',
                ':criado_por_usuario_id' => $mesmoUsuarioCriaEaprova ? $usuarioId : generate_uuid(),
            ]);

        $_SESSION['user'] = [
            'id' => $usuarioId,
            'codigo' => 'USR-9001',
            'nome' => 'Maria Santos',
            'email' => 'maria@empresa.com',
            'empresa_id' => $empresaId,
            'empresa_codigo' => 'EMP-9001',
            'empresa_nome' => 'Empresa Gerente LTDA',
            'empresa_logo' => '',
            'perfil_id' => $perfilId,
            'perfil_nome' => 'Gerente',
        ];

        return [
            'contaId' => $contaId,
            'empresaId' => $empresaId,
            'usuarioId' => $usuarioId,
            'perfilId' => $perfilId,
        ];
    }

    public function test_gerente_com_permissao_pode_aprovar_conta(): void
    {
        $dados = $this->prepararContaParaAprovacao(true);

        $this->assertTrue(can_user('contas_pagar', 'aprovar'));

        ContasPagar::aprovar($dados['contaId'], $dados['empresaId'], $dados['usuarioId']);

        $stmt = $this->db()->prepare('SELECT status, aprovado_por_usuario_id FROM contas_pagar WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $dados['contaId']]);
        $conta = $stmt->fetch();

        $this->assertSame('aprovada', $conta['status']);
        $this->assertSame($dados['usuarioId'], $conta['aprovado_por_usuario_id']);
    }

    public function test_gerente_com_permissao_pode_autoaprovar_conta(): void
    {
        $dados = $this->prepararContaParaAprovacao(true, true);

        $this->assertTrue(can_user('contas_pagar', 'aprovar'));

        ContasPagar::aprovar($dados['contaId'], $dados['empresaId'], $dados['usuarioId']);

        $stmt = $this->db()->prepare('SELECT status, aprovado_por_usuario_id FROM contas_pagar WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $dados['contaId']]);
        $conta = $stmt->fetch();

        $this->assertSame('aprovada', $conta['status']);
        $this->assertSame($dados['usuarioId'], $conta['aprovado_por_usuario_id']);
    }

    public function test_gerente_sem_permissao_nao_pode_aprovar(): void
    {
        $dados = $this->prepararContaParaAprovacao(false);

        $this->assertFalse(can_user('contas_pagar', 'aprovar'));
    }
}