<?php
declare(strict_types=1);

/**
 * Roteador principal do sistema
 * Entry point — todas as requisições passam por aqui
 */

// Carrega configurações
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/helpers/functions.php';
require_once __DIR__ . '/helpers/database.php';
require_once __DIR__ . '/helpers/logger.php';

// Página e ação solicitadas
$page   = $_GET['page'] ?? '';
$action = $_GET['action'] ?? 'index';

// Páginas válidas
$validPages = ['login', 'dashboard', 'empresas', 'usuarios', 'perfis', 'logs', 'clientes', 'fornecedores', 'contas_receber', 'contas_pagar', 'categorias'];

// Se nenhuma página informada ou página inválida, redireciona para login
if (empty($page) || !in_array($page, $validPages)) {
    redirect(url('login'));
}

// Proteção de rotas: se não está logado e não é a página de login, redireciona
if ($page !== 'login' && !is_logged_in()) {
    set_flash('warning', __('login.erro_sessao_expirada'));
    redirect(url('login'));
}

// Controle de tempo de sessão: expira após SESSION_TIMEOUT segundos de inatividade
if ($page !== 'login' && is_logged_in()) {
    $ultimaAtividade = $_SESSION['ultima_atividade'] ?? 0;
    if ($ultimaAtividade > 0 && (time() - $ultimaAtividade) > SESSION_TIMEOUT) {
        log_info('auth', 'sessao_expirada', 'Sessão expirada por inatividade após ' . SESSION_TIMEOUT . ' segundos');
        session_unset();
        session_destroy();
        session_start();
        set_flash('warning', __('login.erro_sessao_expirada'));
        redirect(url('login'));
    }
    $_SESSION['ultima_atividade'] = time();
}

// Exige aceite dos termos antes de acessar qualquer modulo interno.
if ($page !== 'login' && is_logged_in() && usuario_deve_aceitar_termos()) {
    set_flash('warning', __('termos.aviso_aceite_obrigatorio'));
    redirect(url('login', 'termos'));
}

// Proteção de tenant: empresas só podem ser gerenciadas pela empresa master
if ($page === 'empresas' && is_logged_in() && !can_manage_empresas()) {
    set_flash('danger', __('common.acesso_negado'));
    redirect(url('usuarios'));
}

// Proteção de tenant: logs só podem ser visualizados pela empresa master
if ($page === 'logs' && is_logged_in() && !is_master_empresa()) {
    set_flash('danger', __('common.acesso_negado'));
    redirect(url('usuarios'));
}

// Monta o caminho do controller
$controllerFile = __DIR__ . '/controllers/' . $page . '.php';

if ($page === 'login') {
    $controllerFile = __DIR__ . '/controllers/auth.php';
}

// Carrega o controller correspondente
if (file_exists($controllerFile)) {
    require_once $controllerFile;
} else {
    http_response_code(404);
    echo '<h1>404 — Página não encontrada</h1>';
    exit;
}
