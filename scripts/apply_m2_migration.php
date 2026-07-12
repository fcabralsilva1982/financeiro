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

$sql = file_get_contents(__DIR__ . '/../database/migrations/20260604_m2.sql');
if ($sql === false) {
    throw new RuntimeException('Nao foi possivel ler o arquivo de migration M2.');
}

$pdo->exec($sql);

echo "M2_MIGRATION_OK\n";

$tables = [
    'categorias',
    'contas_pagar',
    'contas_pagar_parcelas',
    'contas_pagar_historico',
];

foreach ($tables as $table) {
    $stmt = $pdo->query("SHOW TABLES LIKE '" . $table . "'");
    echo strtoupper($table) . '=' . ($stmt->fetchColumn() ? 'OK' : 'MISSING') . "\n";
}
