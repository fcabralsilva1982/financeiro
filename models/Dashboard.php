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
                    COALESCE(SUM(CASE WHEN status = 'vencida' THEN valor_total ELSE 0 END), 0) AS inadimplencia
                FROM contas_receber
                WHERE empresa_id = :empresa_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':empresa_id' => $empresaId]);
            $receber = $stmt->fetch() ?: ['total_receber_aberto' => 0, 'inadimplencia' => 0];

        $sql = "SELECT
                        COALESCE(SUM(CASE WHEN status IN ('rascunho','pendente_aprovacao','aprovada','parcialmente_paga','vencida') THEN valor_total ELSE 0 END), 0) AS total_pagar_aberto
                FROM contas_pagar
                WHERE empresa_id = :empresa_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':empresa_id' => $empresaId]);
            $pagar = $stmt->fetch() ?: ['total_pagar_aberto' => 0];

            $totalReceber = (float) ($receber['total_receber_aberto'] ?? 0);
            $totalPagar = (float) ($pagar['total_pagar_aberto'] ?? 0);
        $inadimplencia = (float) ($receber['inadimplencia'] ?? 0);

        return [
                'total_receber_aberto' => $totalReceber,
                'total_pagar_aberto' => $totalPagar,
            'saldo_previsto' => $totalReceber - $totalPagar,
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
}
