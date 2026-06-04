<?php
declare(strict_types=1);

/**
 * Controller: Logs do Sistema (Auditoria)
 *
 * Exibe o histórico completo de ações registradas na tabela log_acoes.
 * Suporta filtros detalhados e paginação.
 *
 * Acesso restrito à empresa master (verificado no index.php).
 * Não possui funções próprias — utiliza buscar_logs() do helper logger.php.
 *
 * Filtros disponíveis (via GET):
 *   - empresa_codigo: código da empresa (ex: 'EMP-0001')
 *   - usuario_codigo: código do usuário (ex: 'USR-0001')
 *   - modulo: módulo do sistema (auth, empresas, usuarios, perfis)
 *   - acao: ação específica (login, criar, editar, excluir, etc.)
 *   - nivel: severidade do log (info, warning, error, critical)
 *   - data_inicio / data_fim: intervalo de datas (formato YYYY-MM-DD)
 *
 * Paginação: 50 registros por página, controlada pelo parâmetro GET 'pg'.
 */

// Módulos e ações disponíveis para filtros
$modulosDisponiveis = ['auth', 'empresas', 'usuarios', 'perfis'];
$acoesDisponiveis   = ['login', 'logout', 'criar', 'editar', 'bloquear', 'desbloquear', 'excluir', 'visualizar'];
$niveisDisponiveis  = ['info', 'warning', 'error', 'critical'];

// Captura filtros da URL
$filtros = [
    'empresa_codigo'  => $_GET['empresa_codigo'] ?? '',
    'usuario_codigo'  => $_GET['usuario_codigo'] ?? '',
    'modulo'          => $_GET['modulo'] ?? '',
    'acao'            => $_GET['acao'] ?? '',
    'nivel'           => $_GET['nivel'] ?? '',
    'data_inicio'     => $_GET['data_inicio'] ?? '',
    'data_fim'        => $_GET['data_fim'] ?? '',
];

// Paginação
$porPagina = 50;
$paginaAtual = max(1, (int)($_GET['pg'] ?? 1));
$offset = ($paginaAtual - 1) * $porPagina;

switch ($action) {
    case 'index':
    default:
        // Busca logs do banco de dados
        $resultado = buscar_logs($filtros, $porPagina, $offset);
        $logs = $resultado['logs'];
        $totalLogs = $resultado['total'];
        $totalPaginas = max(1, ceil($totalLogs / $porPagina));

        require __DIR__ . '/../views/logs/index.php';
        break;
}
