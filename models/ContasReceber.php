<?php
require_once __DIR__ . '/../helpers/database.php';
/**
 * Model — Contas a Receber
 * Métodos estáticos para CRUD
 */
class ContasReceber {
    public static function listar($filtros) {
        $pdo = db();
        $sql = "SELECT cr.*, c.razao_social AS cliente_nome FROM contas_receber cr
                JOIN clientes c ON c.id = cr.cliente_id
                WHERE cr.empresa_id = :empresa_id";
        $params = [':empresa_id' => $filtros['empresa_id']];
        if (!empty($filtros['search'])) {
            $sql .= " AND (cr.codigo LIKE :search OR cr.descricao LIKE :search OR c.razao_social LIKE :search)";
            $params[':search'] = '%' . $filtros['search'] . '%';
        }
        if (!empty($filtros['status'])) {
            $sql .= " AND cr.status = :status";
            $params[':status'] = $filtros['status'];
        }
        if (!empty($filtros['cliente_id'])) {
            $sql .= " AND cr.cliente_id = :cliente_id";
            $params[':cliente_id'] = $filtros['cliente_id'];
        }
        if (!empty($filtros['periodo_inicio'])) {
            $sql .= " AND cr.data_vencimento >= :periodo_inicio";
            $params[':periodo_inicio'] = $filtros['periodo_inicio'];
        }
        if (!empty($filtros['periodo_fim'])) {
            $sql .= " AND cr.data_vencimento <= :periodo_fim";
            $params[':periodo_fim'] = $filtros['periodo_fim'];
        }
        $sql .= " ORDER BY cr.data_vencimento DESC, cr.codigo DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function buscar($id, $empresaId) {
        $pdo = db();
        $sql = "SELECT * FROM contas_receber WHERE id = :id AND empresa_id = :empresa_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id, ':empresa_id' => $empresaId]);
        return $stmt->fetch();
    }

    public static function criar($dados) {
        $pdo = db();
        $dados['id'] = generate_uuid();
        $sql = "INSERT INTO contas_receber (id, empresa_id, cliente_id, codigo, descricao, valor_total, data_emissao, data_vencimento, forma_cobranca, status, observacoes, data_pagamento, valor_pago, forma_recebimento, observacoes_baixa)
                VALUES (:id, :empresa_id, :cliente_id, :codigo, :descricao, :valor_total, :data_emissao, :data_vencimento, :forma_cobranca, :status, :observacoes, :data_pagamento, :valor_pago, :forma_recebimento, :observacoes_baixa)";
        $params = [
            ':id' => $dados['id'],
            ':empresa_id' => $dados['empresa_id'],
            ':cliente_id' => $dados['cliente_id'],
            ':codigo' => $dados['codigo'] ?? gerar_codigo_conta(),
            ':descricao' => $dados['descricao'],
            ':valor_total' => normalize_money_br($dados['valor_total'] ?? 0),
            ':data_emissao' => $dados['data_emissao'],
            ':data_vencimento' => $dados['data_vencimento'],
            ':forma_cobranca' => $dados['forma_cobranca'],
            ':status' => $dados['status'] ?? 'emitida',
            ':observacoes' => $dados['observacoes'] ?? null,
            ':data_pagamento' => null,
            ':valor_pago' => null,
            ':forma_recebimento' => null,
            ':observacoes_baixa' => null,
        ];
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $dados['id'];
    }

    public static function atualizar($id, $dados) {
        $pdo = db();
        $sql = "UPDATE contas_receber SET cliente_id = :cliente_id, descricao = :descricao, valor_total = :valor_total, data_emissao = :data_emissao, data_vencimento = :data_vencimento, forma_cobranca = :forma_cobranca, status = :status, observacoes = :observacoes WHERE id = :id AND empresa_id = :empresa_id";
        $params = [
            ':id' => $id,
            ':empresa_id' => $dados['empresa_id'],
            ':cliente_id' => $dados['cliente_id'],
            ':descricao' => $dados['descricao'],
            ':valor_total' => normalize_money_br($dados['valor_total'] ?? 0),
            ':data_emissao' => $dados['data_emissao'],
            ':data_vencimento' => $dados['data_vencimento'],
            ':forma_cobranca' => $dados['forma_cobranca'],
            ':status' => $dados['status'],
            ':observacoes' => $dados['observacoes'] ?? null
        ];
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    public static function baixar(string $id, string $empresaId, array $dados, string $usuarioId): void
    {
        $pdo = db();
        $conta = self::buscar($id, $empresaId);
        if (!$conta) {
            throw new RuntimeException('Conta nao encontrada.');
        }

        if (in_array((string) $conta['status'], ['paga', 'cancelada'], true)) {
            throw new RuntimeException('Conta em status que nao permite baixa.');
        }

        $dataPagamento = trim((string) ($dados['data_pagamento'] ?? ''));
        $valorPago = normalize_money_br($dados['valor_pago'] ?? 0);
        if ($dataPagamento === '' || $valorPago <= 0) {
            throw new RuntimeException('Data e valor pago sao obrigatorios para baixa.');
        }

        $formaRecebimento = trim((string) ($dados['forma_recebimento'] ?? ''));
        $formasValidas = ['boleto', 'pix', 'cartao', 'dinheiro', 'transferencia', 'outro'];
        if (!in_array($formaRecebimento, $formasValidas, true)) {
            throw new RuntimeException('Forma de recebimento invalida.');
        }

        $observacoesBaixa = trim((string) ($dados['observacoes_baixa'] ?? ''));
        $comprovantePath = $dados['comprovante_path'] ?? null;

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "UPDATE contas_receber
                 SET status = 'paga',
                     data_pagamento = :data_pagamento,
                     valor_pago = :valor_pago,
                     forma_recebimento = :forma_recebimento,
                     comprovante_path = :comprovante_path,
                     observacoes_baixa = :observacoes_baixa
                 WHERE id = :id AND empresa_id = :empresa_id"
            );
            $stmt->execute([
                ':id' => $id,
                ':empresa_id' => $empresaId,
                ':data_pagamento' => $dataPagamento,
                ':valor_pago' => $valorPago,
                ':forma_recebimento' => $formaRecebimento,
                ':comprovante_path' => $comprovantePath,
                ':observacoes_baixa' => $observacoesBaixa !== '' ? $observacoesBaixa : null,
            ]);

            $stmtHistorico = $pdo->prepare(
                "INSERT INTO contas_receber_historico (id, conta_receber_id, parcela_id, evento, descricao, canal, data_evento, usuario_id)
                 VALUES (:id, :conta_receber_id, NULL, 'baixa', :descricao, 'sistema', NOW(), :usuario_id)"
            );
            $stmtHistorico->execute([
                ':id' => generate_uuid(),
                ':conta_receber_id' => $id,
                ':descricao' => 'Conta baixada como paga',
                ':usuario_id' => $usuarioId,
            ]);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function excluir($id, $empresaId) {
        $pdo = db();
        $sql = "DELETE FROM contas_receber WHERE id = :id AND empresa_id = :empresa_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $id, ':empresa_id' => $empresaId]);
    }

    public static function validar($dados) {
        $erros = [];
        if (empty($dados['cliente_id'])) {
            $erros[] = __('contas_receber.erro_cliente_obrigatorio');
        }
        if (empty($dados['valor_total'])) {
            $erros[] = __('contas_receber.erro_valor_obrigatorio');
        }
        if (empty($dados['data_vencimento'])) {
            $erros[] = __('contas_receber.erro_vencimento_obrigatorio');
        }
        return $erros;
    }
}

function gerar_codigo_conta() {
    $pdo = db();
    $sql = "SELECT codigo FROM contas_receber WHERE codigo LIKE 'CR-%' ORDER BY codigo DESC LIMIT 1";
    $stmt = $pdo->query($sql);
    $ultimo = (string) ($stmt->fetchColumn() ?: '');

    $num = 1;
    if ($ultimo !== '' && preg_match('/CR-(\d+)/', $ultimo, $m) === 1) {
        $num = ((int) $m[1]) + 1;
    }

    return 'CR-' . str_pad((string) $num, 4, '0', STR_PAD_LEFT);
}

function get_clientes_empresa($empresaId) {
    $pdo = db();
    $sql = "SELECT id, razao_social FROM clientes WHERE empresa_id = :empresa_id AND status = 'ativo' ORDER BY razao_social";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':empresa_id' => $empresaId]);
    return $stmt->fetchAll();
}
