<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/database.php';
require_once __DIR__ . '/../helpers/functions.php';

$pdo = db();
$hoje = date('Y-m-d');

function tabela_existe(PDO $pdo, string $tabela): bool
{
    if (!preg_match('/^[a-z_]+$/', $tabela)) {
        return false;
    }

    $sql = 'SHOW TABLES LIKE ' . $pdo->quote($tabela);
    $stmt = $pdo->query($sql);
    return (bool) $stmt->fetchColumn();
}

$pdo->beginTransaction();

try {
    $crParcelasVencidas = 0;
    if (tabela_existe($pdo, 'contas_receber_parcelas')) {
        $stmtCr = $pdo->prepare(
            "UPDATE contas_receber_parcelas
             SET status = 'vencida'
             WHERE status = 'pendente' AND data_vencimento < :hoje"
        );
        $stmtCr->execute([':hoje' => $hoje]);
        $crParcelasVencidas = $stmtCr->rowCount();
    }

    $cpParcelasVencidas = 0;
    if (tabela_existe($pdo, 'contas_pagar_parcelas')) {
        $stmtCp = $pdo->prepare(
            "UPDATE contas_pagar_parcelas
             SET status = 'vencida'
             WHERE status = 'pendente' AND data_vencimento < :hoje"
        );
        $stmtCp->execute([':hoje' => $hoje]);
        $cpParcelasVencidas = $stmtCp->rowCount();
    }

    if (tabela_existe($pdo, 'contas_receber') && tabela_existe($pdo, 'contas_receber_parcelas')) {
        $pdo->exec(
            "UPDATE contas_receber cr
             INNER JOIN (
                SELECT conta_receber_id,
                       SUM(CASE WHEN status = 'vencida' THEN 1 ELSE 0 END) AS vencidas,
                       SUM(CASE WHEN status = 'paga' THEN 1 ELSE 0 END) AS pagas,
                       COUNT(*) AS total
                FROM contas_receber_parcelas
                GROUP BY conta_receber_id
             ) x ON x.conta_receber_id = cr.id
             SET cr.status = CASE
                WHEN x.pagas = x.total THEN 'paga'
                WHEN x.vencidas > 0 THEN 'vencida'
                WHEN x.pagas > 0 THEN 'visualizada'
                ELSE cr.status
             END"
        );
    }

    if (tabela_existe($pdo, 'contas_pagar') && tabela_existe($pdo, 'contas_pagar_parcelas')) {
        $pdo->exec(
            "UPDATE contas_pagar cp
             INNER JOIN (
                SELECT conta_pagar_id,
                       SUM(CASE WHEN status = 'vencida' THEN 1 ELSE 0 END) AS vencidas,
                       SUM(CASE WHEN status = 'paga' THEN 1 ELSE 0 END) AS pagas,
                       COUNT(*) AS total
                FROM contas_pagar_parcelas
                GROUP BY conta_pagar_id
             ) x ON x.conta_pagar_id = cp.id
             SET cp.status = CASE
                WHEN x.pagas = x.total THEN 'paga'
                WHEN x.pagas > 0 THEN 'parcialmente_paga'
                WHEN x.vencidas > 0 AND cp.status IN ('aprovada', 'pendente_aprovacao') THEN 'vencida'
                ELSE cp.status
             END"
        );
    }

    $pdo->commit();

    echo 'JOB_ATUALIZAR_STATUS_OK' . PHP_EOL;
    echo 'CR_PARCELAS_VENCIDAS=' . $crParcelasVencidas . PHP_EOL;
    echo 'CP_PARCELAS_VENCIDAS=' . $cpParcelasVencidas . PHP_EOL;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, 'JOB_ATUALIZAR_STATUS_ERROR: ' . $e->getMessage() . PHP_EOL);
    exit(1);
}
