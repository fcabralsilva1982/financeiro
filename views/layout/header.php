<!DOCTYPE html>
<html lang="<?= CURRENT_LANG === 'pt-br' ? 'pt-BR' : 'en' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title><?= e($pageTitle ?? __('app.name')) ?> — <?= e(__('app.name')) ?></title>

    <!-- Favicon -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'><text y='14' font-size='14'>💰</text></svg>">

    <!-- Preconnect CDN -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?= asset('css/style.css') ?>" rel="stylesheet">
</head>
<body class="page-loading">

<!-- Loader global de carregamento de tela -->
<div id="pageLoader" class="page-loader" aria-live="polite" aria-label="Carregando" role="status">
    <div class="spinner-border text-primary" aria-hidden="true"></div>
</div>

<!-- Navbar Top -->
<nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom fixed-top shadow-sm">
    <div class="container-fluid">
        <!-- Sidebar toggle (mobile) -->
        <button class="btn btn-outline-secondary me-2 d-lg-none" type="button" id="sidebarToggle">
            <i class="bi bi-list"></i>
        </button>

        <!-- Brand -->
        <a class="navbar-brand fw-bold text-primary d-flex align-items-center" href="<?= url('empresas') ?>">
            <?php
            // Exibe o logo da empresa ativa (futuro: empresa da sessão)
            $logoUrl = $_SESSION['empresa_logo'] ?? '';
            if (!empty($logoUrl)): ?>
                <img src="<?= e($logoUrl) ?>" alt="Logo" class="navbar-logo me-2">
            <?php else: ?>
                <i class="bi bi-wallet2 me-1"></i>
            <?php endif; ?>
            <?= e(__('app.name')) ?>
        </a>

        <!-- Right side -->
        <div class="d-flex align-items-center ms-auto">
            <!-- Language switcher -->
            <a href="<?= lang_switch_url() ?>" class="btn btn-sm btn-outline-secondary me-2" title="<?= e(__('common.idioma')) ?>">
                <i class="bi bi-translate me-1"></i><?= current_lang_label() ?>
            </a>

            <!-- Empresa ativa (badge) -->
            <?php if (is_logged_in()): ?>
            <span class="badge bg-light text-dark border me-2" title="<?= e(__('common.empresa_ativa')) ?>">
                <i class="bi bi-building me-1"></i><?= e(current_empresa_nome()) ?>
                <?php if (is_master_empresa()): ?>
                    <span class="badge bg-primary ms-1"><?= e(__('common.master')) ?></span>
                <?php endif; ?>
            </span>
            <?php endif; ?>

            <!-- User dropdown -->
            <div class="dropdown">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle me-1"></i><?= e(current_user()['nome'] ?? __('common.usuario')) ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <?php if (is_logged_in()): ?>
                    <li><span class="dropdown-item-text small text-muted"><?= e(current_user()['email'] ?? '') ?></span></li>
                    <li><span class="dropdown-item-text small text-muted">
                        <i class="bi bi-shield me-1"></i><?= e(current_user()['perfil_nome'] ?? '') ?>
                    </span></li>
                    <?php if (can_switch_empresa()): ?>
                    <li><hr class="dropdown-divider"></li>
                    <li><span class="dropdown-item-text small text-muted">Alternar empresa</span></li>
                    <?php foreach (user_empresas_disponiveis() as $empresa): ?>
                    <li>
                        <a class="dropdown-item d-flex justify-content-between align-items-center"
                           href="<?= url('login', 'switch_empresa', ['empresa_id' => $empresa['empresa_id']]) ?>">
                            <span><?= e($empresa['empresa_nome']) ?></span>
                            <?php if (($empresa['empresa_id'] ?? '') === current_empresa_id()): ?>
                                <i class="bi bi-check2 text-success"></i>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    <li><hr class="dropdown-divider"></li>
                    <?php endif; ?>
                    <li><a class="dropdown-item" href="<?= url('usuarios', 'meu_perfil') ?>"><i class="bi bi-person me-2"></i><?= e(__('common.meu_perfil')) ?></a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item text-danger" href="<?= url('login', 'logout') ?>"><i class="bi bi-box-arrow-right me-2"></i><?= e(__('menu.sair')) ?></a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<!-- Main wrapper -->
<div class="d-flex" id="wrapper">
