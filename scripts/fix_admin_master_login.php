<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/database.php';
require_once __DIR__ . '/../helpers/functions.php';

$pdo = db();
$email = 'joao.silva@alphatech.com.br';
$senha = 'admin123';

$pdo->beginTransaction();

$stmt = $pdo->prepare('SELECT id, codigo FROM usuarios WHERE email = :email LIMIT 1');
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    $pdo->rollBack();
    echo "USER_NOT_FOUND\n";
    exit(1);
}

$upUser = $pdo->prepare('UPDATE usuarios SET senha = :senha, status = :status WHERE id = :id');
$upUser->execute([
    ':senha' => hash_senha($senha),
    ':status' => 'ativo',
    ':id' => $user['id'],
]);

$upLink = $pdo->prepare('UPDATE usuario_empresa SET status = :status WHERE usuario_id = :uid');
$upLink->execute([
    ':status' => 'ativo',
    ':uid' => $user['id'],
]);

$pdo->commit();

echo "ADMIN_RESET_OK\n";

$check = $pdo->prepare('SELECT senha, status FROM usuarios WHERE id = :id');
$check->execute([':id' => $user['id']]);
$row = $check->fetch(PDO::FETCH_ASSOC);

echo 'PASSWORD_MATCH=' . (verificar_senha($senha, (string)($row['senha'] ?? '')) ? 'YES' : 'NO') . "\n";
echo 'USER_STATUS=' . ($row['status'] ?? 'N/A') . "\n";
