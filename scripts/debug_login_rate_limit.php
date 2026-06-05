<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/database.php';

$pdo = db();
$email = 'joao.silva@alphatech.com.br';
$desde = date('Y-m-d H:i:s', time() - 900);

$stmt = $pdo->prepare(
    "SELECT id, acao, descricao, ip, criado_em
     FROM log_acoes
     WHERE modulo = 'auth'
       AND acao IN ('login_falha', 'brute_force_bloqueado', 'login')
       AND (descricao LIKE :email)
       AND criado_em >= :desde
     ORDER BY criado_em DESC"
);
$stmt->execute([
    ':email' => '%' . $email . '%',
    ':desde' => $desde,
]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo 'EVENTOS_15MIN=' . count($rows) . "\n";
foreach ($rows as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . "\n";
}

$stmt2 = $pdo->prepare(
    "SELECT COUNT(*)
     FROM log_acoes
     WHERE modulo = 'auth'
       AND acao = 'login_falha'
       AND (descricao LIKE :email)
       AND criado_em >= :desde"
);
$stmt2->execute([
    ':email' => '%' . $email . '%',
    ':desde' => $desde,
]);

echo 'LOGIN_FALHA_15MIN=' . (int)$stmt2->fetchColumn() . "\n";
