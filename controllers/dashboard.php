<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/Dashboard.php';

if (!is_logged_in()) {
    redirect(url('login'));
}

$empresaId = (string) current_empresa_id();

$kpis = Dashboard::kpis($empresaId);
$fluxoCaixa = Dashboard::fluxoCaixaMensal($empresaId, 6);
$vencimentos = Dashboard::vencimentosProximos($empresaId, 7);
$topClientes = Dashboard::topClientesDevedores($empresaId, 5);
$topFornecedores = Dashboard::topFornecedores($empresaId, 5);

require __DIR__ . '/../views/dashboard/index.php';
