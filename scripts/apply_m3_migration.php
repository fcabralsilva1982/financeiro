<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

$pdo = new PDO(
    'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
    env('DB_USER', 'root'),
    env('DB_PASS', ''),
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);

/**
 * Cria indice de forma resiliente:
 * - ignora tabela inexistente (ambientes parciais)
 * - ignora indice duplicado (reexecucao)
 */
function criar_indice_se_possivel(PDO $pdo, string $tabela, string $indice, string $colunas): string
{
    try {
        $pdo->exec("CREATE INDEX {$indice} ON {$tabela} ({$colunas})");
        return 'CREATED';
    } catch (PDOException $e) {
        $code = (int) ($e->errorInfo[1] ?? 0);

        // 1061: duplicate key name
        if ($code === 1061) {
            return 'EXISTS';
        }

        // 1146: table doesn't exist
        if ($code === 1146) {
            return 'TABLE_MISSING';
        }

        throw $e;
    }
}

$indices = [
    ['contas_receber', 'idx_cr_empresa_status_vencimento', 'empresa_id, status, data_vencimento'],
    ['contas_receber', 'idx_cr_empresa_cliente_status', 'empresa_id, cliente_id, status'],
    ['contas_pagar', 'idx_cp_empresa_status_vencimento', 'empresa_id, status, data_vencimento'],
    ['contas_pagar', 'idx_cp_empresa_fornecedor_status', 'empresa_id, fornecedor_id, status'],
    ['contas_receber_parcelas', 'idx_crp_status_vencimento', 'status, data_vencimento'],
    ['contas_pagar_parcelas', 'idx_cpp_status_vencimento', 'status, data_vencimento'],
];

echo "M3_MIGRATION_OK\n";
foreach ($indices as [$tabela, $indice, $colunas]) {
    $resultado = criar_indice_se_possivel($pdo, $tabela, $indice, $colunas);
    echo strtoupper($indice) . '=' . $resultado . "\n";
}
