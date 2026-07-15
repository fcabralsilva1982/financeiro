<!-- Sidebar -->
<nav id="sidebar" class="sidebar bg-light border-end">
    <div class="sidebar-header p-3 border-bottom">
        <h6 class="mb-0 text-muted text-uppercase small fw-bold">
            <i class="bi bi-grid me-1"></i><?= e(__('menu.navigation')) ?>
        </h6>
    </div>
    <ul class="nav flex-column p-2">
        <!-- Dashboard -->
        <li class="nav-item">
            <a class="nav-link <?= selected_menu('dashboard') ?>" href="<?= url('dashboard') ?>">
                <i class="bi bi-speedometer2 me-2"></i><?= e(__('menu.dashboard')) ?>
            </a>
        </li>

        <!-- Grupo Cadastros -->
        <li class="nav-item">
            <a class="nav-link sidebar-group-toggle collapsed"
               href="#menuCadastros"
               data-bs-toggle="collapse"
               role="button"
               aria-expanded="false"
               aria-controls="menuCadastros">
                <i class="bi bi-folder me-2"></i><?= e(__('menu.cadastros')) ?>
                <i class="bi bi-chevron-down sidebar-group-arrow ms-auto"></i>
            </a>
            <div class="collapse" id="menuCadastros">
                <ul class="nav flex-column sidebar-submenu">
                    <li class="nav-item">
                        <a class="nav-link <?= selected_menu('clientes') ?>" href="<?= url('clientes') ?>">
                            <i class="bi bi-person-vcard me-2"></i><?= e(__('menu.clientes')) ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= selected_menu('fornecedores') ?>" href="<?= url('fornecedores') ?>">
                            <i class="bi bi-truck me-2"></i><?= e(__('menu.fornecedores')) ?>
                        </a>
                    </li>
                </ul>
            </div>
        </li>

        <!-- Grupo Financeiro -->
        <li class="nav-item">
            <a class="nav-link sidebar-group-toggle collapsed"
               href="#menuFinanceiro"
               data-bs-toggle="collapse"
               role="button"
               aria-expanded="false"
               aria-controls="menuFinanceiro">
                <i class="bi bi-cash-coin me-2"></i><?= e(__('menu.financeiro')) ?>
                <i class="bi bi-chevron-down sidebar-group-arrow ms-auto"></i>
            </a>
            <div class="collapse" id="menuFinanceiro">
                <ul class="nav flex-column sidebar-submenu">
                    <li class="nav-item">
                        <a class="nav-link <?= selected_menu('contas_receber') ?>" href="<?= url('contas_receber') ?>">
                            <i class="bi bi-arrow-down-circle me-2"></i><?= e(__('menu.contas_receber')) ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= selected_menu('contas_pagar') ?>" href="<?= url('contas_pagar') ?>">
                            <i class="bi bi-arrow-up-circle me-2"></i><?= e(__('menu.contas_pagar')) ?>
                        </a>
                    </li>
                    <?php if (can_user('categorias', 'visualizar')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= selected_menu('categorias') ?>" href="<?= url('categorias') ?>">
                            <i class="bi bi-tags me-2"></i><?= e(__('menu.categorias')) ?>
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if (can_user('conciliacao', 'visualizar')): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= selected_menu('conciliacao') ?>" href="<?= url('conciliacao') ?>">
                            <i class="bi bi-bank me-2"></i><?= e(__('menu.conciliacao')) ?>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </li>

        <!-- Grupo Administrativo -->
        <li class="nav-item">
            <a class="nav-link sidebar-group-toggle collapsed"
               href="#menuAdministrativo"
               data-bs-toggle="collapse"
               role="button"
               aria-expanded="false"
               aria-controls="menuAdministrativo">
                <i class="bi bi-gear me-2"></i><?= e(__('menu.administrativo')) ?>
                <i class="bi bi-chevron-down sidebar-group-arrow ms-auto"></i>
            </a>
            <div class="collapse" id="menuAdministrativo">
                <ul class="nav flex-column sidebar-submenu">
                    <!-- Empresas (somente visível para empresa master) -->
                    <?php if (can_manage_empresas()): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= selected_menu('empresas') ?>" href="<?= url('empresas') ?>">
                            <i class="bi bi-building me-2"></i><?= e(__('menu.empresas')) ?>
                        </a>
                    </li>
                    <?php endif; ?>

                    <!-- Usuários -->
                    <li class="nav-item">
                        <a class="nav-link <?= selected_menu('usuarios') ?>" href="<?= url('usuarios') ?>">
                            <i class="bi bi-people me-2"></i><?= e(__('menu.usuarios')) ?>
                        </a>
                    </li>

                    <!-- Perfis de Acesso -->
                    <li class="nav-item">
                        <a class="nav-link <?= selected_menu('perfis') ?>" href="<?= url('perfis') ?>">
                            <i class="bi bi-shield-lock me-2"></i><?= e(__('menu.perfis')) ?>
                        </a>
                    </li>

                    <!-- Logs do Sistema (somente visível para empresa master) -->
                    <?php if (is_master_empresa()): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= selected_menu('logs') ?>" href="<?= url('logs') ?>">
                            <i class="bi bi-journal-text me-2"></i><?= e(__('menu.logs')) ?>
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </li>

        <li><hr class="my-2"></li>

        <!-- Sair -->
        <li class="nav-item">
            <a class="nav-link text-danger" href="<?= url('login', 'logout') ?>">
                <i class="bi bi-box-arrow-right me-2"></i><?= e(__('menu.sair')) ?>
            </a>
        </li>
    </ul>
</nav>

<!-- Page content -->
<div id="page-content-wrapper" class="flex-grow-1">
    <div class="container-fluid p-4" style="margin-top: 56px;">
        <?= flash_message() ?>
