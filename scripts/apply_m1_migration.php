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

$sql = file_get_contents(__DIR__ . '/../database/migrations/20260604_m1.sql');
if ($sql === false) {
    throw new RuntimeException('Nao foi possivel ler o arquivo de migration.');
}

$pdo->exec($sql);

echo "MIGRATION_OK\n";

$stmt = $pdo->query("SHOW TABLES LIKE 'usuario_termos'");
if ($stmt->fetchColumn()) {
    echo "TABLE_OK\n";
} else {
    echo "TABLE_MISSING\n";
}
