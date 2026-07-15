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

$sql = file_get_contents(__DIR__ . '/../database/migrations/20260714_m11.sql');
if ($sql === false) {
    throw new RuntimeException('Nao foi possivel ler o arquivo de migration M11.');
}

$pdo->exec($sql);

echo "M11_MIGRATION_OK\n";

$columns = $pdo->query("SHOW COLUMNS FROM empresas")->fetchAll();
$columnNames = array_column($columns, 'Field');

echo in_array('tipo_pessoa', $columnNames, true) ? "EMPRESAS_TIPO_PESSOA_OK\n" : "EMPRESAS_TIPO_PESSOA_MISSING\n";
echo in_array('cpf', $columnNames, true) ? "EMPRESAS_CPF_OK\n" : "EMPRESAS_CPF_MISSING\n";
