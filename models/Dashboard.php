<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/database.php';

final class Dashboard
{
    public static function kpis(string $empresaId): array
    {
        $pdo = db();

        $sql = "SELECT
                        COALESCE(SUM(CASE WHEN status IN ('emitida','enviada','visualizada','vencida') THEN valor_total ELSE 0 END), 0) AS total_receber_aberto,
                        COALESCE(SUM(CASE WHEN status = 'paga' THEN valor_total ELSE 0 END), 0) AS total_recebido,
                    COALESCE(SUM(CASE WHEN status = 'vencida' THEN valor_total ELSE 0 END), 0) AS inadimplencia
                FROM contas_receber
                WHERE empresa_id = :empresa_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':empresa_id' => $empresaId]);
            $receber = $stmt->fetch() ?: ['total_receber_aberto' => 0, 'total_recebido' => 0, 'inadimplencia' => 0];

        $sql = "SELECT
                        COALESCE(SUM(CASE WHEN status IN ('rascunho','pendente_aprovacao','aprovada','parcialmente_paga','vencida') THEN valor_total ELSE 0 END), 0) AS total_pagar_aberto,
                        COALESCE(SUM(CASE WHEN status = 'paga' THEN valor_total ELSE 0 END), 0) AS total_pago
                FROM contas_pagar
                WHERE empresa_id = :empresa_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':empresa_id' => $empresaId]);
            $pagar = $stmt->fetch() ?: ['total_pagar_aberto' => 0, 'total_pago' => 0];

            $totalReceber = (float) ($receber['total_receber_aberto'] ?? 0);
            $totalPagar   = (float) ($pagar['total_pagar_aberto'] ?? 0);
            $totalRecebido = (float) ($receber['total_recebido'] ?? 0);
            $totalPago     = (float) ($pagar['total_pago'] ?? 0);
        $inadimplencia = (float) ($receber['inadimplencia'] ?? 0);

        return [
                'total_receber_aberto' => $totalReceber,
                'total_pagar_aberto' => $totalPagar,
            'saldo_previsto' => $totalReceber - $totalPagar,
            // Posição de caixa efetiva: o que já foi recebido menos o que já foi pago
            'saldo_realizado' => $totalRecebido - $totalPago,
            'inadimplencia' => $inadimplencia,
        ];
    }

    public static function fluxoCaixaMensal(string $empresaId, int $meses = 6): array
    {
        $pdo = db();

        $inicio = (new DateTimeImmutable('first day of -' . max(0, $meses - 1) . ' month'))->format('Y-m-01');

        $sqlReceber = "SELECT DATE_FORMAT(data_vencimento, '%Y-%m') AS mes, COALESCE(SUM(valor_total), 0) AS total
                       FROM contas_receber
                       WHERE empresa_id = :empresa_id
                         AND data_vencimento >= :inicio
                         AND status IN ('paga','emitida','enviada','visualizada','vencida')
                       GROUP BY DATE_FORMAT(data_vencimento, '%Y-%m')";
        $stmt = $pdo->prepare($sqlReceber);
        $stmt->execute([':empresa_id' => $empresaId, ':inicio' => $inicio]);
        $receberRows = $stmt->fetchAll();

        $sqlPagar = "SELECT DATE_FORMAT(data_vencimento, '%Y-%m') AS mes, COALESCE(SUM(valor_total), 0) AS total
                     FROM contas_pagar
                     WHERE empresa_id = :empresa_id
                       AND data_vencimento >= :inicio
                       AND status IN ('paga','aprovada','parcialmente_paga','vencida')
                     GROUP BY DATE_FORMAT(data_vencimento, '%Y-%m')";
        $stmt = $pdo->prepare($sqlPagar);
        $stmt->execute([':empresa_id' => $empresaId, ':inicio' => $inicio]);
        $pagarRows = $stmt->fetchAll();

        $receberPorMes = [];
        foreach ($receberRows as $row) {
            $receberPorMes[(string) $row['mes']] = (float) $row['total'];
        }

        $pagarPorMes = [];
        foreach ($pagarRows as $row) {
            $pagarPorMes[(string) $row['mes']] = (float) $row['total'];
        }

        $saida = [];
        for ($i = $meses - 1; $i >= 0; $i--) {
            $ref = new DateTimeImmutable('first day of -' . $i . ' month');
            $chave = $ref->format('Y-m');
            $saida[] = [
                'mes' => $ref->format('m/Y'),
                'entradas' => $receberPorMes[$chave] ?? 0.0,
                'saidas' => $pagarPorMes[$chave] ?? 0.0,
            ];
        }

        return $saida;
    }

    public static function vencimentosProximos(string $empresaId, int $dias = 7): array
    {
        $pdo = db();
        $inicio = (new DateTimeImmutable('today'))->format('Y-m-d');
        $fim = (new DateTimeImmutable('today +' . $dias . ' day'))->format('Y-m-d');

        $sql = "SELECT
                    'receber' AS tipo,
                    cr.codigo,
                    cr.descricao,
                    c.razao_social AS contraparte,
                    cr.data_vencimento,
                    cr.valor_total,
                    cr.status
                FROM contas_receber cr
                INNER JOIN clientes c ON c.id = cr.cliente_id
                WHERE cr.empresa_id = :empresa_id
                  AND cr.data_vencimento BETWEEN :inicio AND :fim
                  AND cr.status IN ('rascunho','emitida','enviada','visualizada','vencida')

                UNION ALL

                SELECT
                    'pagar' AS tipo,
                    cp.codigo,
                    cp.descricao,
                    f.razao_social AS contraparte,
                    cp.data_vencimento,
                    cp.valor_total,
                    cp.status
                FROM contas_pagar cp
                INNER JOIN fornecedores f ON f.id = cp.fornecedor_id
                                WHERE cp.empresa_id = :empresa_id_cp
                                    AND cp.data_vencimento BETWEEN :inicio_cp AND :fim_cp
                  AND cp.status IN ('rascunho','pendente_aprovacao','aprovada','parcialmente_paga','vencida')

                ORDER BY data_vencimento ASC
                LIMIT 20";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':empresa_id' => $empresaId,
            ':inicio' => $inicio,
            ':fim' => $fim,
            ':empresa_id_cp' => $empresaId,
            ':inicio_cp' => $inicio,
            ':fim_cp' => $fim,
        ]);

        return $stmt->fetchAll();
    }

    public static function topClientesDevedores(string $empresaId, int $limite = 5): array
    {
        $pdo = db();

        $sql = "SELECT
                    c.id,
                    c.razao_social,
                    COALESCE(SUM(cr.valor_total), 0) AS valor_total,
                    COUNT(cr.id) AS qtd_contas
                FROM contas_receber cr
                INNER JOIN clientes c ON c.id = cr.cliente_id
                WHERE cr.empresa_id = :empresa_id
                  AND cr.status IN ('emitida','enviada','visualizada','vencida')
                GROUP BY c.id, c.razao_social
                ORDER BY valor_total DESC
                LIMIT :limite";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_STR);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function topFornecedores(string $empresaId, int $limite = 5): array
    {
        $pdo = db();

        $sql = "SELECT
                    f.id,
                    f.razao_social,
                    COALESCE(SUM(cp.valor_total), 0) AS valor_total,
                    COUNT(cp.id) AS qtd_contas
                FROM contas_pagar cp
                INNER JOIN fornecedores f ON f.id = cp.fornecedor_id
                WHERE cp.empresa_id = :empresa_id
                  AND cp.status IN ('aprovada','parcialmente_paga','vencida','pendente_aprovacao')
                GROUP BY f.id, f.razao_social
                ORDER BY valor_total DESC
                LIMIT :limite";

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':empresa_id', $empresaId, PDO::PARAM_STR);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * KPIs filtrados por mês/ano.
     *
     * Previsto: usa data_vencimento (o que estava programado para o mês).
     * Realizado (receber): usa data_pagamento — data em que o dinheiro entrou de fato.
     * Realizado (pagar): usa data_vencimento com status 'paga', pois contas_pagar não
     * possui coluna de data de pagamento.
     */
    public static function kpisMensal(string $empresaId, int $ano, int $mes): array
    {
        $pdo = db();
        $mesStr = sprintf('%04d-%02d', $ano, $mes);

        // Previsto a receber e já recebido (por vencimento) no mês
        $sqlReceber = "SELECT
            COALESCE(SUM(CASE WHEN status NOT IN ('cancelada') THEN valor_total ELSE 0 END), 0)                         AS previsto_receber,
            COALESCE(SUM(CASE WHEN status = 'paga' THEN COALESCE(valor_pago, valor_total) ELSE 0 END), 0)               AS recebido_por_venc
        FROM contas_receber
        WHERE empresa_id = :empresa_id
          AND DATE_FORMAT(data_vencimento, '%Y-%m') = :mes";

        // Efetivamente recebido: usa data_pagamento (data real da entrada)
        $sqlRecebidoReal = "SELECT
            COALESCE(SUM(COALESCE(valor_pago, valor_total)), 0) AS recebido_real
        FROM contas_receber
        WHERE empresa_id = :empresa_id
          AND status = 'paga'
          AND DATE_FORMAT(data_pagamento, '%Y-%m') = :mes";

        // Previsto a pagar e efetivamente pago no mês (usa data_vencimento como referência)
        $sqlPagar = "SELECT
            COALESCE(SUM(CASE WHEN status NOT IN ('cancelada') THEN valor_total ELSE 0 END), 0) AS previsto_pagar,
            COALESCE(SUM(CASE WHEN status = 'paga' THEN valor_total ELSE 0 END), 0)             AS pago_no_mes
        FROM contas_pagar
        WHERE empresa_id = :empresa_id
          AND DATE_FORMAT(data_vencimento, '%Y-%m') = :mes";

        $stmt = $pdo->prepare($sqlReceber);
        $stmt->execute([':empresa_id' => $empresaId, ':mes' => $mesStr]);
        $receber = $stmt->fetch() ?: ['previsto_receber' => 0, 'recebido_por_venc' => 0];

        $stmt = $pdo->prepare($sqlRecebidoReal);
        $stmt->execute([':empresa_id' => $empresaId, ':mes' => $mesStr]);
        $recebidoReal = $stmt->fetch() ?: ['recebido_real' => 0];

        $stmt = $pdo->prepare($sqlPagar);
        $stmt->execute([':empresa_id' => $empresaId, ':mes' => $mesStr]);
        $pagar = $stmt->fetch() ?: ['previsto_pagar' => 0, 'pago_no_mes' => 0];

        $prevReceber  = (float) ($receber['previsto_receber'] ?? 0);
        $prevPagar    = (float) ($pagar['previsto_pagar'] ?? 0);
        $recebidoReal = (float) ($recebidoReal['recebido_real'] ?? 0);
        $pagoNoMes    = (float) ($pagar['pago_no_mes'] ?? 0);

        return [
            'previsto_receber'    => $prevReceber,
            'previsto_pagar'      => $prevPagar,
            // Diferença entre o que estava previsto para entrar e sair no mês
            'resultado_previsto'  => $prevReceber - $prevPagar,
            // Dinheiro que efetivamente entrou na conta (data_pagamento no mês)
            'recebido_real'       => $recebidoReal,
            // Contas pagas com vencimento no mês (melhor aproximação sem data_pagamento em CP)
            'pago_no_mes'         => $pagoNoMes,
            // Diferença entre o que foi recebido e o que foi pago no mês
            'resultado_realizado' => $recebidoReal - $pagoNoMes,
        ];
    }
}
