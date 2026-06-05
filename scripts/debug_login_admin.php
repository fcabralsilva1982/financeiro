<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/database.php';
require_once __DIR__ . '/../helpers/functions.php';

$pdo = db();
$email = 'joao.silva@alphatech.com.br';
$senhaTeste = 'admin123';

$stmt = $pdo->prepare('SELECT id,codigo,nome,email,status,senha FROM usuarios WHERE email = :email LIMIT 1');
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    echo "USER_NOT_FOUND\n";
    exit(0);
}

echo "USER_FOUND\n";
echo 'ID=' . $user['id'] . "\n";
echo 'CODIGO=' . $user['codigo'] . "\n";
echo 'STATUS=' . $user['status'] . "\n";
echo 'PASSWORD_MATCH=' . (verificar_senha($senhaTeste, (string)$user['senha']) ? 'YES' : 'NO') . "\n";

$stmt2 = $pdo->prepare(
    'SELECT ue.id, ue.status AS vinculo_status, e.codigo AS empresa_codigo, e.status AS empresa_status, p.nome AS perfil_nome, p.status AS perfil_status
     FROM usuario_empresa ue
     INNER JOIN empresas e ON e.id = ue.empresa_id
     INNER JOIN perfis p ON p.id = ue.perfil_id
     WHERE ue.usuario_id = :uid'
);
$stmt2->execute([':uid' => $user['id']]);
$vinculos = $stmt2->fetchAll(PDO::FETCH_ASSOC);

echo 'VINCULOS=' . count($vinculos) . "\n";
foreach ($vinculos as $v) {
    echo json_encode($v, JSON_UNESCAPED_UNICODE) . "\n";
}
