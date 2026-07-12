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

$sql = file_get_contents(__DIR__ . '/../database/migrations/20260712_m7.sql');
if ($sql === false) {
    throw new RuntimeException('Nao foi possivel ler o arquivo de migration M7.');
}

$pdo->exec($sql);

echo "M7_MIGRATION_OK\n";

$stmt = $pdo->query("SHOW COLUMNS FROM contas_receber LIKE 'comprovante_path'");
echo $stmt->fetchColumn() ? "COMPROVANTE_COLUMN_OK\n" : "COMPROVANTE_COLUMN_MISSING\n";
