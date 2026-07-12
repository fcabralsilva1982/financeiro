<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/database.php';
require_once __DIR__ . '/../helpers/functions.php';

final class ContasPagar
{
    public static function listar(string $empresaId, array $filtros = []): array
    {
        $pdo = db();
        $sql = "SELECT cp.id, cp.codigo, cp.descricao, cp.valor_total, cp.data_vencimento, cp.status,
                       cp.recorrente, cp.periodicidade,
                       f.razao_social AS fornecedor_nome,
                       c.nome AS categoria_nome,
                       SUM(CASE WHEN cpp.status = 'paga' THEN 1 ELSE 0 END) AS parcelas_pagas,
                       COUNT(cpp.id) AS parcelas_total
                FROM contas_pagar cp
                INNER JOIN fornecedores f ON f.id = cp.fornecedor_id
                LEFT JOIN categorias c ON c.id = cp.categoria_id
                LEFT JOIN contas_pagar_parcelas cpp ON cpp.conta_pagar_id = cp.id
                WHERE cp.empresa_id = :empresa_id";
        $params = [':empresa_id' => $empresaId];

        if (!empty($filtros['status'])) {
            $sql .= ' AND cp.status = :status';
            $params[':status'] = $filtros['status'];
        }

        if (!empty($filtros['fornecedor_id'])) {
            $sql .= ' AND cp.fornecedor_id = :fornecedor_id';
            $params[':fornecedor_id'] = $filtros['fornecedor_id'];
        }

        if (!empty($filtros['categoria_id'])) {
            $sql .= ' AND cp.categoria_id = :categoria_id';
            $params[':categoria_id'] = $filtros['categoria_id'];
        }

        if (!empty($filtros['periodo_inicio'])) {
            $sql .= ' AND cp.data_vencimento >= :periodo_inicio';
            $params[':periodo_inicio'] = $filtros['periodo_inicio'];
        }

        if (!empty($filtros['periodo_fim'])) {
            $sql .= ' AND cp.data_vencimento <= :periodo_fim';
            $params[':periodo_fim'] = $filtros['periodo_fim'];
        }

        if (!empty($filtros['busca'])) {
            $sql .= ' AND (cp.codigo LIKE :busca OR cp.descricao LIKE :busca OR f.razao_social LIKE :busca)';
            $params[':busca'] = '%' . $filtros['busca'] . '%';
        }

        $sql .= ' GROUP BY cp.id, cp.codigo, cp.descricao, cp.valor_total, cp.data_vencimento, cp.status, cp.recorrente, cp.periodicidade, f.razao_social, c.nome';
        $sql .= ' ORDER BY cp.data_vencimento ASC, cp.codigo ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function buscar(string $id, string $empresaId): ?array
    {
        $pdo = db();
        $stmt = $pdo->prepare(
            "SELECT cp.*, f.razao_social AS fornecedor_nome, c.nome AS categoria_nome
             FROM contas_pagar cp
             INNER JOIN fornecedores f ON f.id = cp.fornecedor_id
             LEFT JOIN categorias c ON c.id = cp.categoria_id
             WHERE cp.id = :id AND cp.empresa_id = :empresa_id
             LIMIT 1"
        );
        $stmt->execute([':id' => $id, ':empresa_id' => $empresaId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function buscarParcela(string $parcelaId, string $empresaId): ?array
    {
        $pdo = db();
        $stmt = $pdo->prepare(
            "SELECT cpp.*, cp.id AS conta_pagar_id, cp.codigo AS conta_codigo, cp.status AS conta_status,
                    cp.recorrente, cp.periodicidade, cp.empresa_id
             FROM contas_pagar_parcelas cpp
             INNER JOIN contas_pagar cp ON cp.id = cpp.conta_pagar_id
             WHERE cpp.id = :id AND cp.empresa_id = :empresa_id
             LIMIT 1"
        );
        $stmt->execute([':id' => $parcelaId, ':empresa_id' => $empresaId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function listarParcelas(string $contaId, string $empresaId): array
    {
        $pdo = db();
        $stmt = $pdo->prepare(
            "SELECT cpp.*
             FROM contas_pagar_parcelas cpp
             INNER JOIN contas_pagar cp ON cp.id = cpp.conta_pagar_id
             WHERE cpp.conta_pagar_id = :conta_id AND cp.empresa_id = :empresa_id
             ORDER BY cpp.numero_parcela ASC"
        );
        $stmt->execute([':conta_id' => $contaId, ':empresa_id' => $empresaId]);
        return $stmt->fetchAll();
    }

    public static function listarFornecedoresAtivos(string $empresaId): array
    {
        $pdo = db();
        $stmt = $pdo->prepare(
            "SELECT id, codigo, razao_social
             FROM fornecedores
             WHERE empresa_id = :empresa_id AND status = 'ativo'
             ORDER BY razao_social ASC"
        );
        $stmt->execute([':empresa_id' => $empresaId]);
        return $stmt->fetchAll();
    }

    public static function listarCategoriasDespesa(string $empresaId): array
    {
        $pdo = db();
        $stmt = $pdo->prepare(
            "SELECT id, codigo, nome
             FROM categorias
             WHERE empresa_id = :empresa_id AND tipo = 'despesa' AND status = 'ativo'
             ORDER BY nome ASC"
        );
        $stmt->execute([':empresa_id' => $empresaId]);
        return $stmt->fetchAll();
    }

    public static function validar(array $dados): array
    {
        $erros = [];

        if (trim((string) ($dados['fornecedor_id'] ?? '')) === '') {
            $erros[] = 'Fornecedor e obrigatorio.';
        }

        if (trim((string) ($dados['descricao'] ?? '')) === '') {
            $erros[] = 'Descricao e obrigatoria.';
        }

        $valorTotal = normalize_money_br($dados['valor_total'] ?? 0);
        if ($valorTotal <= 0) {
            $erros[] = 'Valor total deve ser maior que zero.';
        }

        if (trim((string) ($dados['data_emissao'] ?? '')) === '') {
            $erros[] = 'Data de emissao e obrigatoria.';
        }

        if (trim((string) ($dados['data_vencimento'] ?? '')) === '') {
            $erros[] = 'Data de vencimento e obrigatoria.';
        }

        $forma = (string) ($dados['forma_pagamento'] ?? '');
        $formasValidas = ['boleto', 'pix', 'ted', 'doc', 'cartao', 'dinheiro', 'outro'];
        if (!in_array($forma, $formasValidas, true)) {
            $erros[] = 'Forma de pagamento invalida.';
        }

        $qtdParcelas = max(1, (int) ($dados['qtd_parcelas'] ?? 1));
        if ($qtdParcelas > 120) {
            $erros[] = 'Quantidade de parcelas invalida.';
        }

        if ((string) ($dados['recorrente'] ?? '0') === '1') {
            $periodicidade = (string) ($dados['periodicidade'] ?? '');
            $validas = ['semanal', 'quinzenal', 'mensal', 'bimestral', 'trimestral', 'semestral', 'anual'];
            if (!in_array($periodicidade, $validas, true)) {
                $erros[] = 'Periodicidade obrigatoria para conta recorrente.';
            }
        }

        return $erros;
    }

    public static function criar(array $dados, string $empresaId, string $usuarioId): string
    {
        $pdo = db();
        $pdo->beginTransaction();

        try {
            $id = generate_uuid();
            $codigo = self::gerarCodigo($empresaId);
            $parcelas = self::gerarParcelas($dados);

            $stmt = $pdo->prepare(
                "INSERT INTO contas_pagar (
                    id, empresa_id, fornecedor_id, categoria_id, codigo, descricao,
                    valor_total, data_emissao, data_vencimento, forma_pagamento,
                    status, recorrente, periodicidade, observacoes, criado_por_usuario_id
                 ) VALUES (
                    :id, :empresa_id, :fornecedor_id, :categoria_id, :codigo, :descricao,
                    :valor_total, :data_emissao, :data_vencimento, :forma_pagamento,
                    'rascunho', :recorrente, :periodicidade, :observacoes, :criado_por
                 )"
            );

            $stmt->execute([
                ':id' => $id,
                ':empresa_id' => $empresaId,
                ':fornecedor_id' => $dados['fornecedor_id'],
                ':categoria_id' => $dados['categoria_id'] !== '' ? $dados['categoria_id'] : null,
                ':codigo' => $codigo,
                ':descricao' => trim((string) $dados['descricao']),
                ':valor_total' => normalize_money_br($dados['valor_total'] ?? 0),
                ':data_emissao' => $dados['data_emissao'],
                ':data_vencimento' => $dados['data_vencimento'],
                ':forma_pagamento' => $dados['forma_pagamento'],
                ':recorrente' => (string) ($dados['recorrente'] ?? '0') === '1' ? 1 : 0,
                ':periodicidade' => (string) ($dados['recorrente'] ?? '0') === '1' ? $dados['periodicidade'] : null,
                ':observacoes' => trim((string) ($dados['observacoes'] ?? '')) ?: null,
                ':criado_por' => $usuarioId,
            ]);

            self::salvarParcelas($pdo, $id, $dados['forma_pagamento'], $parcelas);
            self::registrarHistorico($pdo, $id, null, 'criada', 'Conta criada em rascunho', $usuarioId);

            $pdo->commit();
            return $id;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function atualizar(string $id, array $dados, string $empresaId, string $usuarioId): void
    {
        $pdo = db();
        $conta = self::buscar($id, $empresaId);
        if (!$conta) {
            throw new RuntimeException('Conta nao encontrada.');
        }

        if (in_array($conta['status'], ['paga', 'cancelada'], true)) {
            throw new RuntimeException('Conta em status que nao permite edicao.');
        }

        $pdo->beginTransaction();
        try {
            $parcelas = self::gerarParcelas($dados);

            $stmt = $pdo->prepare(
                "UPDATE contas_pagar
                 SET fornecedor_id = :fornecedor_id,
                     categoria_id = :categoria_id,
                     descricao = :descricao,
                     valor_total = :valor_total,
                     data_emissao = :data_emissao,
                     data_vencimento = :data_vencimento,
                     forma_pagamento = :forma_pagamento,
                     recorrente = :recorrente,
                     periodicidade = :periodicidade,
                     observacoes = :observacoes
                 WHERE id = :id AND empresa_id = :empresa_id"
            );

            $stmt->execute([
                ':id' => $id,
                ':empresa_id' => $empresaId,
                ':fornecedor_id' => $dados['fornecedor_id'],
                ':categoria_id' => $dados['categoria_id'] !== '' ? $dados['categoria_id'] : null,
                ':descricao' => trim((string) $dados['descricao']),
                ':valor_total' => normalize_money_br($dados['valor_total'] ?? 0),
                ':data_emissao' => $dados['data_emissao'],
                ':data_vencimento' => $dados['data_vencimento'],
                ':forma_pagamento' => $dados['forma_pagamento'],
                ':recorrente' => (string) ($dados['recorrente'] ?? '0') === '1' ? 1 : 0,
                ':periodicidade' => (string) ($dados['recorrente'] ?? '0') === '1' ? $dados['periodicidade'] : null,
                ':observacoes' => trim((string) ($dados['observacoes'] ?? '')) ?: null,
            ]);

            $pdo->prepare('DELETE FROM contas_pagar_parcelas WHERE conta_pagar_id = :conta_id')
                ->execute([':conta_id' => $id]);
            self::salvarParcelas($pdo, $id, $dados['forma_pagamento'], $parcelas);
            self::registrarHistorico($pdo, $id, null, 'editada', 'Conta editada', $usuarioId);

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function enviarParaAprovacao(string $id, string $empresaId, string $usuarioId): void
    {
        $pdo = db();
        $conta = self::buscar($id, $empresaId);
        if (!$conta || $conta['status'] !== 'rascunho') {
            throw new RuntimeException('Conta invalida para envio de aprovacao.');
        }

        $stmt = $pdo->prepare(
            "UPDATE contas_pagar
             SET status = 'pendente_aprovacao'
             WHERE id = :id AND empresa_id = :empresa_id"
        );
        $stmt->execute([':id' => $id, ':empresa_id' => $empresaId]);
        self::registrarHistorico($pdo, $id, null, 'enviada_aprovacao', 'Conta enviada para aprovacao', $usuarioId);
    }

    public static function aprovar(string $id, string $empresaId, string $usuarioId): void
    {
        $pdo = db();
        $conta = self::buscar($id, $empresaId);
        if (!$conta || $conta['status'] !== 'pendente_aprovacao') {
            throw new RuntimeException('Conta invalida para aprovacao.');
        }

        if (!can_user('contas_pagar', 'aprovar')) {
            throw new RuntimeException('Usuario sem permissao para aprovar conta.');
        }

        $stmt = $pdo->prepare(
            "UPDATE contas_pagar
             SET status = 'aprovada', aprovado_por_usuario_id = :usuario_id, aprovado_em = NOW()
             WHERE id = :id AND empresa_id = :empresa_id"
        );
        $stmt->execute([':id' => $id, ':empresa_id' => $empresaId, ':usuario_id' => $usuarioId]);
        self::registrarHistorico($pdo, $id, null, 'aprovada', 'Conta aprovada', $usuarioId);
    }

    public static function cancelar(string $id, string $empresaId, string $usuarioId): void
    {
        $pdo = db();
        $conta = self::buscar($id, $empresaId);
        if (!$conta) {
            throw new RuntimeException('Conta nao encontrada.');
        }

        $permitidos = ['rascunho', 'pendente_aprovacao', 'aprovada', 'vencida'];
        if (!in_array($conta['status'], $permitidos, true)) {
            throw new RuntimeException('Status nao permite cancelamento.');
        }

        $pdo->beginTransaction();
        try {
            $pdo->prepare("UPDATE contas_pagar SET status = 'cancelada' WHERE id = :id AND empresa_id = :empresa_id")
                ->execute([':id' => $id, ':empresa_id' => $empresaId]);
            $pdo->prepare("UPDATE contas_pagar_parcelas SET status = 'cancelada' WHERE conta_pagar_id = :conta_id AND status <> 'paga'")
                ->execute([':conta_id' => $id]);
            self::registrarHistorico($pdo, $id, null, 'cancelada', 'Conta cancelada', $usuarioId);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function baixarParcela(string $parcelaId, string $empresaId, array $dados, string $usuarioId): void
    {
        $pdo = db();
        $parcela = self::buscarParcela($parcelaId, $empresaId);
        if (!$parcela) {
            throw new RuntimeException('Parcela nao encontrada.');
        }

        if (!in_array($parcela['status'], ['pendente', 'vencida'], true)) {
            throw new RuntimeException('Parcela nao permite baixa.');
        }

        $dataPagamento = (string) ($dados['data_pagamento'] ?? '');
        $valorPago = normalize_money_br($dados['valor_pago'] ?? 0);
        if ($dataPagamento === '' || $valorPago <= 0) {
            throw new RuntimeException('Data e valor pago sao obrigatorios para baixa.');
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "UPDATE contas_pagar_parcelas
                 SET data_pagamento = :data_pagamento,
                     valor_pago = :valor_pago,
                     status = 'paga',
                     forma_pagamento = :forma_pagamento,
                     comprovante_path = :comprovante_path,
                     observacoes = :observacoes
                 WHERE id = :id"
            );
            $stmt->execute([
                ':id' => $parcelaId,
                ':data_pagamento' => $dataPagamento,
                ':valor_pago' => $valorPago,
                ':forma_pagamento' => (string) ($dados['forma_pagamento'] ?? $parcela['forma_pagamento'] ?? 'outro'),
                ':comprovante_path' => (string) ($dados['comprovante_path'] ?? '') !== '' ? $dados['comprovante_path'] : null,
                ':observacoes' => (string) ($dados['observacoes'] ?? '') !== '' ? trim((string) $dados['observacoes']) : null,
            ]);

            self::registrarHistorico($pdo, (string) $parcela['conta_pagar_id'], $parcelaId, 'parcela_paga', 'Parcela baixada como paga', $usuarioId);
            self::atualizarStatusContaPorParcelas($pdo, (string) $parcela['conta_pagar_id']);

            $contaAposBaixa = self::buscar((string) $parcela['conta_pagar_id'], $empresaId);
            if ($contaAposBaixa && $contaAposBaixa['status'] === 'paga' && (int) $contaAposBaixa['recorrente'] === 1) {
                self::gerarProximaRecorrencia($pdo, $contaAposBaixa, $usuarioId);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function buscarParcelaParaDownload(string $parcelaId, string $empresaId): ?array
    {
        $pdo = db();
        $stmt = $pdo->prepare(
            "SELECT cpp.id, cpp.comprovante_path, cp.codigo AS conta_codigo
             FROM contas_pagar_parcelas cpp
             INNER JOIN contas_pagar cp ON cp.id = cpp.conta_pagar_id
             WHERE cpp.id = :id AND cp.empresa_id = :empresa_id
             LIMIT 1"
        );
        $stmt->execute([':id' => $parcelaId, ':empresa_id' => $empresaId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function gerarCodigo(string $empresaId): string
    {
        $pdo = db();
        $stmt = $pdo->prepare(
            "SELECT codigo
             FROM contas_pagar
             WHERE empresa_id = :empresa_id AND codigo LIKE 'CP-%'
             ORDER BY codigo DESC
             LIMIT 1"
        );
        $stmt->execute([':empresa_id' => $empresaId]);
        $ultimo = (string) ($stmt->fetchColumn() ?: '');

        $seq = 1;
        if ($ultimo !== '' && preg_match('/CP-(\d+)/', $ultimo, $m) === 1) {
            $seq = (int) $m[1] + 1;
        }

        return generate_codigo('CP', $seq);
    }

    private static function gerarParcelas(array $dados): array
    {
        $qtd = max(1, (int) ($dados['qtd_parcelas'] ?? 1));
        $valorTotal = round(normalize_money_br($dados['valor_total'] ?? 0), 2);
        $dataBase = (string) ($dados['data_vencimento'] ?? date('Y-m-d'));

        $valorBase = round($valorTotal / $qtd, 2);
        $parcelas = [];
        $soma = 0.0;

        for ($i = 1; $i <= $qtd; $i++) {
            $valor = $i === $qtd ? round($valorTotal - $soma, 2) : $valorBase;
            $soma += $valor;
            $parcelas[] = [
                'numero_parcela' => $i,
                'valor' => $valor,
                'data_vencimento' => self::somarMeses($dataBase, $i - 1),
            ];
        }

        if (round($soma, 2) !== $valorTotal) {
            throw new RuntimeException('Falha ao distribuir parcelas: somatorio divergente.');
        }

        return $parcelas;
    }

    private static function salvarParcelas(PDO $pdo, string $contaId, string $formaPagamento, array $parcelas): void
    {
        $stmt = $pdo->prepare(
            "INSERT INTO contas_pagar_parcelas (
                id, conta_pagar_id, numero_parcela, valor, data_vencimento, status, forma_pagamento
            ) VALUES (
                :id, :conta_pagar_id, :numero_parcela, :valor, :data_vencimento, 'pendente', :forma_pagamento
            )"
        );

        foreach ($parcelas as $parcela) {
            $stmt->execute([
                ':id' => generate_uuid(),
                ':conta_pagar_id' => $contaId,
                ':numero_parcela' => $parcela['numero_parcela'],
                ':valor' => $parcela['valor'],
                ':data_vencimento' => $parcela['data_vencimento'],
                ':forma_pagamento' => $formaPagamento,
            ]);
        }
    }

    private static function atualizarStatusContaPorParcelas(PDO $pdo, string $contaId): void
    {
        $stmt = $pdo->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'paga' THEN 1 ELSE 0 END) AS pagas,
                SUM(CASE WHEN status IN ('pendente', 'vencida') THEN 1 ELSE 0 END) AS abertas
             FROM contas_pagar_parcelas
             WHERE conta_pagar_id = :conta_id"
        );
        $stmt->execute([':conta_id' => $contaId]);
        $resumo = $stmt->fetch() ?: ['total' => 0, 'pagas' => 0, 'abertas' => 0];

        $total = (int) ($resumo['total'] ?? 0);
        $pagas = (int) ($resumo['pagas'] ?? 0);

        $novoStatus = 'aprovada';
        if ($total > 0 && $pagas === $total) {
            $novoStatus = 'paga';
        } elseif ($pagas > 0) {
            $novoStatus = 'parcialmente_paga';
        }

        $pdo->prepare('UPDATE contas_pagar SET status = :status WHERE id = :id')
            ->execute([':status' => $novoStatus, ':id' => $contaId]);
    }

    private static function registrarHistorico(PDO $pdo, string $contaId, ?string $parcelaId, string $evento, string $descricao, string $usuarioId): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO contas_pagar_historico (id, conta_pagar_id, parcela_id, evento, descricao, usuario_id)
             VALUES (:id, :conta_pagar_id, :parcela_id, :evento, :descricao, :usuario_id)'
        );
        $stmt->execute([
            ':id' => generate_uuid(),
            ':conta_pagar_id' => $contaId,
            ':parcela_id' => $parcelaId,
            ':evento' => $evento,
            ':descricao' => $descricao,
            ':usuario_id' => $usuarioId,
        ]);
    }

    private static function gerarProximaRecorrencia(PDO $pdo, array $contaAtual, string $usuarioId): void
    {
        $periodicidade = (string) ($contaAtual['periodicidade'] ?? '');
        if ($periodicidade === '') {
            return;
        }

        $parcelasAtuais = self::listarParcelas((string) $contaAtual['id'], (string) $contaAtual['empresa_id']);
        if (empty($parcelasAtuais)) {
            return;
        }

        $novaContaId = generate_uuid();
        $novoCodigo = self::gerarCodigo((string) $contaAtual['empresa_id']);
        $novaEmissao = self::somarPeriodicidade((string) $contaAtual['data_emissao'], $periodicidade);
        $novoVencimento = self::somarPeriodicidade((string) $contaAtual['data_vencimento'], $periodicidade);

        $stmt = $pdo->prepare(
            "INSERT INTO contas_pagar (
                id, empresa_id, fornecedor_id, categoria_id, codigo, descricao,
                valor_total, data_emissao, data_vencimento, forma_pagamento,
                status, recorrente, periodicidade, observacoes, criado_por_usuario_id
             ) VALUES (
                :id, :empresa_id, :fornecedor_id, :categoria_id, :codigo, :descricao,
                :valor_total, :data_emissao, :data_vencimento, :forma_pagamento,
                'rascunho', 1, :periodicidade, :observacoes, :criado_por
             )"
        );

        $stmt->execute([
            ':id' => $novaContaId,
            ':empresa_id' => $contaAtual['empresa_id'],
            ':fornecedor_id' => $contaAtual['fornecedor_id'],
            ':categoria_id' => $contaAtual['categoria_id'] ?: null,
            ':codigo' => $novoCodigo,
            ':descricao' => $contaAtual['descricao'],
            ':valor_total' => $contaAtual['valor_total'],
            ':data_emissao' => $novaEmissao,
            ':data_vencimento' => $novoVencimento,
            ':forma_pagamento' => $contaAtual['forma_pagamento'],
            ':periodicidade' => $periodicidade,
            ':observacoes' => $contaAtual['observacoes'] ?: null,
            ':criado_por' => $usuarioId,
        ]);

        $stmtParcela = $pdo->prepare(
            "INSERT INTO contas_pagar_parcelas (
                id, conta_pagar_id, numero_parcela, valor, data_vencimento, status, forma_pagamento
             ) VALUES (
                :id, :conta_pagar_id, :numero_parcela, :valor, :data_vencimento, 'pendente', :forma_pagamento
             )"
        );

        foreach ($parcelasAtuais as $parcelaAtual) {
            $stmtParcela->execute([
                ':id' => generate_uuid(),
                ':conta_pagar_id' => $novaContaId,
                ':numero_parcela' => $parcelaAtual['numero_parcela'],
                ':valor' => $parcelaAtual['valor'],
                ':data_vencimento' => self::somarPeriodicidade((string) $parcelaAtual['data_vencimento'], $periodicidade),
                ':forma_pagamento' => $contaAtual['forma_pagamento'],
            ]);
        }

        self::registrarHistorico($pdo, $novaContaId, null, 'recorrencia_gerada', 'Nova ocorrencia gerada automaticamente', $usuarioId);
    }

    private static function somarMeses(string $data, int $meses): string
    {
        $dt = new DateTimeImmutable($data);
        return $dt->modify('+' . $meses . ' month')->format('Y-m-d');
    }

    private static function somarPeriodicidade(string $data, string $periodicidade): string
    {
        $mapa = [
            'semanal' => '+1 week',
            'quinzenal' => '+2 weeks',
            'mensal' => '+1 month',
            'bimestral' => '+2 months',
            'trimestral' => '+3 months',
            'semestral' => '+6 months',
            'anual' => '+1 year',
        ];

        $expressao = $mapa[$periodicidade] ?? '+1 month';
        $dt = new DateTimeImmutable($data);
        return $dt->modify($expressao)->format('Y-m-d');
    }
}
