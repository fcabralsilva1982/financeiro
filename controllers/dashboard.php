<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/Dashboard.php';

if (!is_logged_in()) {
    redirect(url('login'));
}

$empresaId = (string) current_empresa_id();

// Filtro de mês: aceita query string ?mes=YYYY-MM; padrão = mês atual
$mesAnoRaw = isset($_GET['mes']) ? trim((string) $_GET['mes']) : date('Y-m');
$mesAnoRaw = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $mesAnoRaw) ? $mesAnoRaw : date('Y-m');
[$filtroAno, $filtroMes] = array_map('intval', explode('-', $mesAnoRaw));

$kpis          = Dashboard::kpis($empresaId);
$kpisMensal    = Dashboard::kpisMensal($empresaId, $filtroAno, $filtroMes);
$fluxoCaixa    = Dashboard::fluxoCaixaMensal($empresaId, 6);
$vencimentos   = Dashboard::vencimentosProximos($empresaId, 7);
$topClientes   = Dashboard::topClientesDevedores($empresaId, 5);
$topFornecedores = Dashboard::topFornecedores($empresaId, 5);

require __DIR__ . '/../views/dashboard/index.php';
