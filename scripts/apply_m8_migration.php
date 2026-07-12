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

$sql = file_get_contents(__DIR__ . '/../database/migrations/20260712_m8.sql');
if ($sql === false) {
    throw new RuntimeException('Nao foi possivel ler o arquivo de migration M8.');
}

$pdo->exec($sql);

echo "M8_MIGRATION_OK\n";

$stmt = $pdo->query("SHOW TABLES LIKE 'cliente_documentos'");
echo $stmt->fetchColumn() ? "CLIENTE_DOCUMENTOS_OK\n" : "CLIENTE_DOCUMENTOS_MISSING\n";
