<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/database.php';
require_once __DIR__ . '/../helpers/functions.php';

final class Conciliacao
{
    private const STATUS_PENDENTE = 'pendente';
    private const STATUS_CONCILIADO_AUTO = 'conciliado_auto';
    private const STATUS_CONCILIADO_MANUAL = 'conciliado_manual';
    private const STATUS_IGNORADO = 'ignorado';
    private const STATUS_MOVIMENTO_INTERNO = 'movimento_interno';

    public static function listarImportacoes(string $empresaId): array
    {
        $pdo = db();
        $stmt = $pdo->prepare(
            "SELECT cb.*, u.nome AS usuario_nome
             FROM conciliacao_bancaria cb
             LEFT JOIN usuarios u ON u.id = cb.usuario_id
             WHERE cb.empresa_id = :empresa_id
             ORDER BY cb.data_importacao DESC, cb.criado_em DESC"
        );
        $stmt->execute([':empresa_id' => $empresaId]);
        return $stmt->fetchAll();
    }

    public static function buscarImportacao(string $id, string $empresaId): ?array
    {
        $pdo = db();
        $stmt = $pdo->prepare(
            "SELECT cb.*, u.nome AS usuario_nome
             FROM conciliacao_bancaria cb
             LEFT JOIN usuarios u ON u.id = cb.usuario_id
             WHERE cb.id = :id AND cb.empresa_id = :empresa_id
             LIMIT 1"
        );
        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function listarTransacoes(string $importacaoId, string $empresaId, array $filtros = []): array
    {
        $pdo = db();
        $sql = "SELECT ct.*, cr.codigo AS conta_receber_codigo,
                       cpp.numero_parcela,
                       cp.codigo AS conta_pagar_codigo
                FROM conciliacao_transacoes ct
                LEFT JOIN contas_receber cr ON cr.id = ct.conta_receber_id
                LEFT JOIN contas_pagar_parcelas cpp ON cpp.id = ct.conta_pagar_parcela_id
                LEFT JOIN contas_pagar cp ON cp.id = cpp.conta_pagar_id
                WHERE ct.conciliacao_bancaria_id = :importacao_id
                  AND ct.empresa_id = :empresa_id";
        $params = [
            ':importacao_id' => $importacaoId,
            ':empresa_id' => $empresaId,
        ];

        if (!empty($filtros['status_conciliacao'])) {
            $sql .= ' AND ct.status_conciliacao = :status_conciliacao';
            $params[':status_conciliacao'] = (string) $filtros['status_conciliacao'];
        }

        if (!empty($filtros['tipo_movimento'])) {
            $sql .= ' AND ct.tipo_movimento = :tipo_movimento';
            $params[':tipo_movimento'] = (string) $filtros['tipo_movimento'];
        }

        if (!empty($filtros['busca'])) {
            $sql .= ' AND (
                ct.fitid LIKE :busca OR
                ct.descricao_banco LIKE :busca OR
                ct.memo LIKE :busca OR
                cr.codigo LIKE :busca OR
                cp.codigo LIKE :busca
            )';
            $params[':busca'] = '%' . $filtros['busca'] . '%';
        }

        $sql .= ' ORDER BY ct.data_movimento DESC, ct.valor DESC, ct.criado_em DESC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function listarPendentes(string $empresaId, int $limite = 100): array
    {
        $pdo = db();
        $stmt = $pdo->prepare(
            "SELECT ct.*, cb.nome_arquivo
             FROM conciliacao_transacoes ct
             INNER JOIN conciliacao_bancaria cb ON cb.id = ct.conciliacao_bancaria_id
             WHERE ct.empresa_id = :empresa_id
               AND ct.status_conciliacao = :status
             ORDER BY ct.data_movimento DESC, ct.criado_em DESC
             LIMIT :limite"
        );
        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_STR);
        $stmt->bindValue(':status', self::STATUS_PENDENTE, PDO::PARAM_STR);
        $stmt->bindValue(':limite', max(1, $limite), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function listarContasReceberElegiveis(string $empresaId): array
    {
        $pdo = db();
        $stmt = $pdo->prepare(
            "SELECT cr.id, cr.codigo, cr.descricao, cr.valor_total, cr.data_vencimento,
                    c.razao_social AS cliente_nome
             FROM contas_receber cr
             LEFT JOIN clientes c ON c.id = cr.cliente_id
             WHERE cr.empresa_id = :empresa_id
               AND cr.status IN ('emitida','enviada','visualizada','vencida')
             ORDER BY cr.data_vencimento ASC, cr.codigo ASC"
        );
        $stmt->execute([':empresa_id' => $empresaId]);
        return $stmt->fetchAll();
    }

    public static function listarParcelasPagarElegiveis(string $empresaId): array
    {
        $pdo = db();
        $stmt = $pdo->prepare(
            "SELECT cpp.id, cpp.numero_parcela, cpp.valor, cpp.data_vencimento,
                    cp.codigo AS conta_codigo, cp.descricao, f.razao_social AS fornecedor_nome
             FROM contas_pagar_parcelas cpp
             INNER JOIN contas_pagar cp ON cp.id = cpp.conta_pagar_id
             LEFT JOIN fornecedores f ON f.id = cp.fornecedor_id
             WHERE cp.empresa_id = :empresa_id
               AND cpp.status IN ('pendente','vencida')
               AND cp.status IN ('aprovada','parcialmente_paga','vencida')
             ORDER BY cpp.data_vencimento ASC, cp.codigo ASC, cpp.numero_parcela ASC"
        );
        $stmt->execute([':empresa_id' => $empresaId]);
        return $stmt->fetchAll();
    }

    public static function fitidJaImportado(string $empresaId, string $fitid): bool
    {
        $fitidNormalizado = trim($fitid);
        if ($fitidNormalizado === '') {
            return false;
        }

        return self::fitidJaImportadoNaConexao(db(), $empresaId, $fitidNormalizado);
    }

    public static function criarImportacao(string $empresaId, string $usuarioId, string $nomeArquivo, array $transacoes): array
    {
        $nomeArquivoNormalizado = trim($nomeArquivo);
        if ($nomeArquivoNormalizado === '') {
            throw new RuntimeException('Nome do arquivo de importacao e obrigatorio.');
        }

        $pdo = db();
        $pdo->beginTransaction();

        try {
            $importacaoId = generate_uuid();
            $stmtImportacao = $pdo->prepare(
                "INSERT INTO conciliacao_bancaria (
                    id, empresa_id, usuario_id, nome_arquivo,
                    quantidade_transacoes, quantidade_duplicadas,
                    quantidade_conciliadas_auto, quantidade_pendentes
                 ) VALUES (
                    :id, :empresa_id, :usuario_id, :nome_arquivo,
                    0, 0, 0, 0
                 )"
            );
            $stmtImportacao->execute([
                ':id' => $importacaoId,
                ':empresa_id' => $empresaId,
                ':usuario_id' => $usuarioId !== '' ? $usuarioId : null,
                ':nome_arquivo' => $nomeArquivoNormalizado,
            ]);

            $stmtTransacao = $pdo->prepare(
                "INSERT INTO conciliacao_transacoes (
                    id, conciliacao_bancaria_id, empresa_id, fitid, data_movimento,
                    valor, tipo_movimento, descricao_banco, memo,
                    status_conciliacao, origem_match
                 ) VALUES (
                    :id, :conciliacao_bancaria_id, :empresa_id, :fitid, :data_movimento,
                    :valor, :tipo_movimento, :descricao_banco, :memo,
                    :status_conciliacao, 'nenhum'
                 )"
            );

            $fitidsNoArquivo = [];
            $duplicadas = 0;

            foreach ($transacoes as $transacao) {
                $fitid = trim((string) ($transacao['fitid'] ?? ''));
                if ($fitid === '') {
                    throw new RuntimeException('Transacao sem FITID valido na importacao.');
                }

                if (isset($fitidsNoArquivo[$fitid]) || self::fitidJaImportadoNaConexao($pdo, $empresaId, $fitid)) {
                    $duplicadas++;
                    continue;
                }

                [$valor, $tipoMovimento] = self::normalizarValorETipo($transacao);
                $dataMovimento = self::normalizarDataMovimento($transacao['data_movimento'] ?? $transacao['dtposted'] ?? '');
                $descricaoBanco = self::normalizarTexto($transacao['descricao_banco'] ?? $transacao['name'] ?? '');
                $memo = self::normalizarTexto($transacao['memo'] ?? '');

                $stmtTransacao->execute([
                    ':id' => generate_uuid(),
                    ':conciliacao_bancaria_id' => $importacaoId,
                    ':empresa_id' => $empresaId,
                    ':fitid' => $fitid,
                    ':data_movimento' => $dataMovimento,
                    ':valor' => $valor,
                    ':tipo_movimento' => $tipoMovimento,
                    ':descricao_banco' => $descricaoBanco,
                    ':memo' => $memo,
                    ':status_conciliacao' => self::STATUS_PENDENTE,
                ]);

                $fitidsNoArquivo[$fitid] = true;
            }

            self::atualizarResumoImportacao($pdo, $importacaoId, $empresaId, $duplicadas);
            $pdo->commit();

            $importacao = self::buscarImportacao($importacaoId, $empresaId);
            if (!$importacao) {
                throw new RuntimeException('Importacao criada, mas nao foi encontrada para retorno.');
            }

            return $importacao;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function marcarConciliadoContaReceber(
        string $transacaoId,
        string $empresaId,
        string $contaReceberId,
        string $usuarioId,
        bool $automatico = false,
        int $confiancaMatch = 100
    ): void {
        if (!self::contaReceberExiste($contaReceberId, $empresaId)) {
            throw new RuntimeException('Conta a receber nao encontrada para conciliacao.');
        }

        self::atualizarStatusTransacao(
            $transacaoId,
            $empresaId,
            [
                'status_conciliacao' => $automatico ? self::STATUS_CONCILIADO_AUTO : self::STATUS_CONCILIADO_MANUAL,
                'origem_match' => 'conta_receber',
                'conta_receber_id' => $contaReceberId,
                'conta_pagar_parcela_id' => null,
                'confianca_match' => self::normalizarConfianca($confiancaMatch),
                'acao_confirmada_por_usuario_id' => $usuarioId,
            ]
        );
    }

    public static function marcarConciliadoContaPagarParcela(
        string $transacaoId,
        string $empresaId,
        string $parcelaId,
        string $usuarioId,
        bool $automatico = false,
        int $confiancaMatch = 100
    ): void {
        if (!self::parcelaContaPagarExiste($parcelaId, $empresaId)) {
            throw new RuntimeException('Parcela de conta a pagar nao encontrada para conciliacao.');
        }

        self::atualizarStatusTransacao(
            $transacaoId,
            $empresaId,
            [
                'status_conciliacao' => $automatico ? self::STATUS_CONCILIADO_AUTO : self::STATUS_CONCILIADO_MANUAL,
                'origem_match' => 'conta_pagar',
                'conta_receber_id' => null,
                'conta_pagar_parcela_id' => $parcelaId,
                'confianca_match' => self::normalizarConfianca($confiancaMatch),
                'acao_confirmada_por_usuario_id' => $usuarioId,
            ]
        );
    }

    public static function marcarMovimentoInterno(string $transacaoId, string $empresaId, string $usuarioId): void
    {
        self::atualizarStatusTransacao(
            $transacaoId,
            $empresaId,
            [
                'status_conciliacao' => self::STATUS_MOVIMENTO_INTERNO,
                'origem_match' => 'movimento_interno',
                'conta_receber_id' => null,
                'conta_pagar_parcela_id' => null,
                'confianca_match' => null,
                'acao_confirmada_por_usuario_id' => $usuarioId,
            ]
        );
    }

    public static function marcarIgnorado(string $transacaoId, string $empresaId, string $usuarioId): void
    {
        self::atualizarStatusTransacao(
            $transacaoId,
            $empresaId,
            [
                'status_conciliacao' => self::STATUS_IGNORADO,
                'origem_match' => 'nenhum',
                'conta_receber_id' => null,
                'conta_pagar_parcela_id' => null,
                'confianca_match' => null,
                'acao_confirmada_por_usuario_id' => $usuarioId,
            ]
        );
    }

    public static function executarMatchingAutomatico(string $importacaoId, string $empresaId, string $usuarioId): array
    {
        $transacoes = self::listarTransacoes($importacaoId, $empresaId, [
            'status_conciliacao' => self::STATUS_PENDENTE,
        ]);

        $concilidadas = 0;
        $ignoradasPorAmbiguidade = 0;

        foreach ($transacoes as $transacao) {
            $transacaoId = (string) ($transacao['id'] ?? '');
            if ($transacaoId === '') {
                continue;
            }

            if ((string) ($transacao['tipo_movimento'] ?? '') === 'credito') {
                $candidatos = self::buscarCandidatosContaReceber($empresaId, $transacao);
                if (count($candidatos) === 1) {
                    self::marcarConciliadoContaReceber(
                        $transacaoId,
                        $empresaId,
                        (string) $candidatos[0]['id'],
                        $usuarioId,
                        true,
                        100
                    );
                    $concilidadas++;
                } else {
                    $ignoradasPorAmbiguidade++;
                }
                continue;
            }

            if ((string) ($transacao['tipo_movimento'] ?? '') === 'debito') {
                if (self::descricaoPareceTarifaOuEncargo($transacao)) {
                    $ignoradasPorAmbiguidade++;
                    continue;
                }

                $candidatos = self::buscarCandidatosParcelaPagar($empresaId, $transacao);
                if (count($candidatos) === 1) {
                    self::marcarConciliadoContaPagarParcela(
                        $transacaoId,
                        $empresaId,
                        (string) $candidatos[0]['id'],
                        $usuarioId,
                        true,
                        100
                    );
                    $concilidadas++;
                } else {
                    $ignoradasPorAmbiguidade++;
                }
            }
        }

        return [
            'conciliadas_auto' => $concilidadas,
            'pendentes_ou_ambiguas' => $ignoradasPorAmbiguidade,
        ];
    }

    private static function atualizarStatusTransacao(string $transacaoId, string $empresaId, array $dados): void
    {
        $pdo = db();
        $transacao = self::buscarTransacao($transacaoId, $empresaId);
        if (!$transacao) {
            throw new RuntimeException('Transacao de conciliacao nao encontrada.');
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                "UPDATE conciliacao_transacoes
                 SET status_conciliacao = :status_conciliacao,
                     origem_match = :origem_match,
                     conta_receber_id = :conta_receber_id,
                     conta_pagar_parcela_id = :conta_pagar_parcela_id,
                     confianca_match = :confianca_match,
                     acao_confirmada_por_usuario_id = :acao_confirmada_por_usuario_id,
                     acao_confirmada_em = NOW()
                 WHERE id = :id AND empresa_id = :empresa_id"
            );
            $stmt->execute([
                ':status_conciliacao' => $dados['status_conciliacao'],
                ':origem_match' => $dados['origem_match'],
                ':conta_receber_id' => $dados['conta_receber_id'],
                ':conta_pagar_parcela_id' => $dados['conta_pagar_parcela_id'],
                ':confianca_match' => $dados['confianca_match'],
                ':acao_confirmada_por_usuario_id' => $dados['acao_confirmada_por_usuario_id'] !== '' ? $dados['acao_confirmada_por_usuario_id'] : null,
                ':id' => $transacaoId,
                ':empresa_id' => $empresaId,
            ]);

            self::atualizarResumoImportacao($pdo, (string) $transacao['conciliacao_bancaria_id'], $empresaId);
            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function buscarTransacao(string $transacaoId, string $empresaId): ?array
    {
        $pdo = db();
        $stmt = $pdo->prepare(
            "SELECT *
             FROM conciliacao_transacoes
             WHERE id = :id AND empresa_id = :empresa_id
             LIMIT 1"
        );
        $stmt->execute([
            ':id' => $transacaoId,
            ':empresa_id' => $empresaId,
        ]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private static function atualizarResumoImportacao(PDO $pdo, string $importacaoId, string $empresaId, ?int $duplicadas = null): void
    {
        $stmt = $pdo->prepare(
            "SELECT
                    COUNT(*) AS quantidade_transacoes,
                    SUM(CASE WHEN status_conciliacao = 'conciliado_auto' THEN 1 ELSE 0 END) AS quantidade_conciliadas_auto,
                    SUM(CASE WHEN status_conciliacao = 'pendente' THEN 1 ELSE 0 END) AS quantidade_pendentes
             FROM conciliacao_transacoes
             WHERE conciliacao_bancaria_id = :importacao_id
               AND empresa_id = :empresa_id"
        );
        $stmt->execute([
            ':importacao_id' => $importacaoId,
            ':empresa_id' => $empresaId,
        ]);
        $resumo = $stmt->fetch() ?: [
            'quantidade_transacoes' => 0,
            'quantidade_conciliadas_auto' => 0,
            'quantidade_pendentes' => 0,
        ];

        $sql = "UPDATE conciliacao_bancaria
                SET quantidade_transacoes = :quantidade_transacoes,
                    quantidade_conciliadas_auto = :quantidade_conciliadas_auto,
                    quantidade_pendentes = :quantidade_pendentes";
        $params = [
            ':quantidade_transacoes' => (int) ($resumo['quantidade_transacoes'] ?? 0),
            ':quantidade_conciliadas_auto' => (int) ($resumo['quantidade_conciliadas_auto'] ?? 0),
            ':quantidade_pendentes' => (int) ($resumo['quantidade_pendentes'] ?? 0),
            ':id' => $importacaoId,
            ':empresa_id' => $empresaId,
        ];

        if ($duplicadas !== null) {
            $sql .= ', quantidade_duplicadas = :quantidade_duplicadas';
            $params[':quantidade_duplicadas'] = $duplicadas;
        }

        $sql .= ' WHERE id = :id AND empresa_id = :empresa_id';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    private static function buscarCandidatosContaReceber(string $empresaId, array $transacao): array
    {
        $pdo = db();
        $stmt = $pdo->prepare(
            "SELECT cr.id
             FROM contas_receber cr
             WHERE cr.empresa_id = :empresa_id
               AND cr.status IN ('emitida','enviada','visualizada','vencida')
               AND cr.valor_total = :valor
               AND ABS(DATEDIFF(cr.data_vencimento, :data_movimento)) <= 3
             ORDER BY cr.data_vencimento ASC, cr.codigo ASC
             LIMIT 3"
        );
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':valor' => (float) ($transacao['valor'] ?? 0),
            ':data_movimento' => (string) ($transacao['data_movimento'] ?? ''),
        ]);
        return $stmt->fetchAll();
    }

    private static function buscarCandidatosParcelaPagar(string $empresaId, array $transacao): array
    {
        $pdo = db();
        $stmt = $pdo->prepare(
            "SELECT cpp.id
             FROM contas_pagar_parcelas cpp
             INNER JOIN contas_pagar cp ON cp.id = cpp.conta_pagar_id
             WHERE cp.empresa_id = :empresa_id
               AND cpp.status IN ('pendente','vencida')
               AND cp.status IN ('aprovada','parcialmente_paga','vencida')
               AND cpp.valor = :valor
               AND ABS(DATEDIFF(cpp.data_vencimento, :data_movimento)) <= 3
             ORDER BY cpp.data_vencimento ASC, cpp.numero_parcela ASC
             LIMIT 3"
        );
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':valor' => (float) ($transacao['valor'] ?? 0),
            ':data_movimento' => (string) ($transacao['data_movimento'] ?? ''),
        ]);
        return $stmt->fetchAll();
    }

    private static function descricaoPareceTarifaOuEncargo(array $transacao): bool
    {
        $descricao = strtoupper(trim((string) ($transacao['descricao_banco'] ?? '')));
        $memo = strtoupper(trim((string) ($transacao['memo'] ?? '')));
        $texto = $descricao . ' ' . $memo;
        if ($texto === ' ') {
            return false;
        }

        $palavras = ['TARIFA', 'PACOTE', 'MENSALIDADE', 'ANUIDADE', 'JUROS', 'IOF', 'ENCARGO'];
        foreach ($palavras as $palavra) {
            if (str_contains($texto, $palavra)) {
                return true;
            }
        }

        return false;
    }

    private static function fitidJaImportadoNaConexao(PDO $pdo, string $empresaId, string $fitid): bool
    {
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM conciliacao_transacoes WHERE empresa_id = :empresa_id AND fitid = :fitid'
        );
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':fitid' => $fitid,
        ]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private static function contaReceberExiste(string $contaReceberId, string $empresaId): bool
    {
        $stmt = db()->prepare(
            'SELECT COUNT(*) FROM contas_receber WHERE id = :id AND empresa_id = :empresa_id'
        );
        $stmt->execute([
            ':id' => $contaReceberId,
            ':empresa_id' => $empresaId,
        ]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private static function parcelaContaPagarExiste(string $parcelaId, string $empresaId): bool
    {
        $stmt = db()->prepare(
            "SELECT COUNT(*)
             FROM contas_pagar_parcelas cpp
             INNER JOIN contas_pagar cp ON cp.id = cpp.conta_pagar_id
             WHERE cpp.id = :id AND cp.empresa_id = :empresa_id"
        );
        $stmt->execute([
            ':id' => $parcelaId,
            ':empresa_id' => $empresaId,
        ]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private static function normalizarValorETipo(array $transacao): array
    {
        $valorOriginal = normalize_money_br($transacao['valor'] ?? $transacao['trnamt'] ?? 0);
        $tipoMovimento = trim((string) ($transacao['tipo_movimento'] ?? ''));

        if ($tipoMovimento === '') {
            $tipoMovimento = $valorOriginal < 0 ? 'debito' : 'credito';
        }

        if (!in_array($tipoMovimento, ['credito', 'debito'], true)) {
            throw new RuntimeException('Tipo de movimento invalido na importacao de extrato.');
        }

        return [abs(round($valorOriginal, 2)), $tipoMovimento];
    }

    private static function normalizarDataMovimento(mixed $data): string
    {
        if ($data instanceof DateTimeInterface) {
            return $data->format('Y-m-d');
        }

        $texto = trim((string) $data);
        if ($texto === '') {
            throw new RuntimeException('Data de movimento obrigatoria na importacao.');
        }

        if (preg_match('/^(\d{4})(\d{2})(\d{2})/', $texto, $matches) === 1) {
            return $matches[1] . '-' . $matches[2] . '-' . $matches[3];
        }

        $date = date_create($texto);
        if ($date === false) {
            throw new RuntimeException('Data de movimento invalida na importacao.');
        }

        return $date->format('Y-m-d');
    }

    private static function normalizarTexto(mixed $valor): ?string
    {
        $texto = trim((string) $valor);
        return $texto !== '' ? $texto : null;
    }

    private static function normalizarConfianca(int $confiancaMatch): int
    {
        return max(0, min(100, $confiancaMatch));
    }
}
