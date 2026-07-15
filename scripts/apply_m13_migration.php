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

$sql = file_get_contents(__DIR__ . '/../database/migrations/20260715_m13.sql');
if ($sql === false) {
    throw new RuntimeException('Nao foi possivel ler o arquivo de migration M13.');
}

$pdo->exec($sql);

echo "M13_MIGRATION_OK\n";

$stmt = $pdo->query("SHOW TABLES LIKE 'conciliacao_bancaria'");
echo ($stmt->fetch() !== false) ? "CONCILIACAO_BANCARIA_TABLE_OK\n" : "CONCILIACAO_BANCARIA_TABLE_MISSING\n";

$stmt = $pdo->query("SHOW TABLES LIKE 'conciliacao_transacoes'");
echo ($stmt->fetch() !== false) ? "CONCILIACAO_TRANSACOES_TABLE_OK\n" : "CONCILIACAO_TRANSACOES_TABLE_MISSING\n";

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM perfil_permissoes pp
    JOIN perfis p ON p.id = pp.perfil_id
    WHERE p.nome = 'Administrador'
      AND pp.modulo = 'conciliacao'
      AND pp.acao IN ('visualizar', 'criar', 'editar', 'bloquear', 'excluir')
");

echo ((int) $stmt->fetchColumn() >= 5) ? "CONCILIACAO_ADMIN_PERMISSIONS_OK\n" : "CONCILIACAO_ADMIN_PERMISSIONS_MISSING\n";