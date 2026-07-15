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

$sql = file_get_contents(__DIR__ . '/../database/migrations/20260714_m12.sql');
if ($sql === false) {
    throw new RuntimeException('Nao foi possivel ler o arquivo de migration M12.');
}

$pdo->exec($sql);

echo "M12_MIGRATION_OK\n";

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM perfil_permissoes pp
    JOIN perfis p ON p.id = pp.perfil_id
    WHERE p.nome = 'Administrador'
      AND pp.modulo = 'categorias'
      AND pp.acao IN ('visualizar', 'criar', 'editar', 'bloquear', 'excluir')
");

echo ((int) $stmt->fetchColumn() >= 5) ? "CATEGORIAS_ADMIN_PERMISSIONS_OK\n" : "CATEGORIAS_ADMIN_PERMISSIONS_MISSING\n";
