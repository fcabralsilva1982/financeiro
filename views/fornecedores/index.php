<?php
/**
 * View: Listagem de Fornecedores
 * Variáveis do controller: $fornecedores, $total, $pagina, $totalPaginas, $porPagina, $filtros
 */
$pageTitle = __('fornecedores.title');
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<!-- Overlay sidebar mobile -->
<div id="sidebarOverlay" class="sidebar-overlay"></div>

<!-- Page Header -->
<div class="page-header">
    <h2><i class="bi bi-truck me-2"></i><?= e(__('fornecedores.title')) ?></h2>
    <a href="<?= url('fornecedores', 'create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i><?= e(__('fornecedores.new')) ?>
    </a>
</div>

<!-- Filter Bar -->
<form method="GET" action="<?= url('fornecedores') ?>" class="filter-bar">
    <input type="hidden" name="page" value="fornecedores">
    <div class="row g-2 align-items-center">
        <div class="col-md-4">
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" name="busca"
                       value="<?= e($filtros['busca'] ?? '') ?>"
                       placeholder="<?= e(__('fornecedores.search')) ?>">
            </div>
        </div>
        <div class="col-md-2">
            <select class="form-select form-select-sm" name="status">
                <option value=""><?= e(__('usuarios.todos_status')) ?></option>
                <option value="ativo" <?= ($filtros['status'] ?? '') === 'ativo' ? 'selected' : '' ?>><?= e(__('common.ativo')) ?></option>
                <option value="bloqueado" <?= ($filtros['status'] ?? '') === 'bloqueado' ? 'selected' : '' ?>><?= e(__('common.bloqueado')) ?></option>
            </select>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-sm btn-primary me-1"><i class="bi bi-funnel me-1"></i><?= e(__('logs.filtrar')) ?></button>
            <a href="<?= url('fornecedores') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg me-1"></i><?= e(__('logs.limpar')) ?></a>
        </div>
    </div>
</form>

<!-- Table -->
<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th style="width: 110px;"><?= e(__('common.codigo')) ?></th>
                <th style="width: 60px;"><?= e(__('fornecedores.tipo_pessoa')) ?></th>
                <th><?= e(__('fornecedores.cpf_cnpj')) ?></th>
                <th><?= e(__('fornecedores.razao_social')) ?></th>
                <th><?= e(__('fornecedores.email')) ?></th>
                <th><?= e(__('fornecedores.celular')) ?></th>
                <th><?= e(__('fornecedores.categoria')) ?></th>
                <th style="width: 100px;"><?= e(__('fornecedores.status')) ?></th>
                <th style="width: 150px;"><?= e(__('fornecedores.acoes')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($fornecedores)): ?>
                <?php foreach ($fornecedores as $forn): ?>
                <tr>
                    <td><span class="badge bg-light text-dark font-monospace"><?= e($forn['codigo']) ?></span></td>
                    <td>
                        <span class="badge <?= $forn['tipo_pessoa'] === 'PJ' ? 'bg-info' : 'bg-secondary' ?>"><?= e($forn['tipo_pessoa']) ?></span>
                    </td>
                    <td><?= e($forn['cpf_cnpj']) ?></td>
                    <td><?= e($forn['razao_social']) ?></td>
                    <td><?= e($forn['email'] ?? '') ?></td>
                    <td><?= e($forn['celular'] ?? '') ?></td>
                    <td><?= e($forn['categoria'] ?? '') ?></td>
                    <td>
                        <?php if ($forn['status'] === 'ativo'): ?>
                            <span class="badge badge-ativo"><?= e(__('common.ativo')) ?></span>
                        <?php else: ?>
                            <span class="badge badge-bloqueado"><?= e(__('common.bloqueado')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <!-- Editar -->
                        <a href="<?= url('fornecedores', 'edit', ['id' => $forn['id']]) ?>"
                           class="btn btn-sm btn-outline-primary btn-action"
                           data-bs-toggle="tooltip" title="<?= e(__('common.editar')) ?>">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <!-- Bloquear/Desbloquear -->
                        <?php if ($forn['status'] === 'ativo'): ?>
                        <button type="button"
                                class="btn btn-sm btn-outline-warning btn-action"
                                data-bs-toggle="modal" data-bs-target="#modalBloquear"
                                data-url="<?= url('fornecedores', 'block', ['id' => $forn['id']]) ?>"
                                data-title="<?= e(__('common.confirmar_bloqueio')) ?>"
                                data-msg="<?= e(__('common.confirmar_bloqueio_msg')) ?>"
                                title="<?= e(__('common.bloquear')) ?>">
                            <i class="bi bi-lock"></i>
                        </button>
                        <?php else: ?>
                        <button type="button"
                                class="btn btn-sm btn-outline-success btn-action"
                                data-bs-toggle="modal" data-bs-target="#modalBloquear"
                                data-url="<?= url('fornecedores', 'unblock', ['id' => $forn['id']]) ?>"
                                data-title="<?= e(__('common.confirmar_desbloqueio')) ?>"
                                data-msg="<?= e(__('common.confirmar_desbloqueio_msg')) ?>"
                                title="<?= e(__('common.desbloquear')) ?>">
                            <i class="bi bi-unlock"></i>
                        </button>
                        <?php endif; ?>
                        <!-- Excluir -->
                        <button type="button"
                                class="btn btn-sm btn-outline-danger btn-action"
                                data-bs-toggle="modal" data-bs-target="#modalExcluir"
                                data-url="<?= url('fornecedores', 'delete', ['id' => $forn['id']]) ?>"
                                title="<?= e(__('common.excluir')) ?>">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">
                        <i class="bi bi-inbox me-1"></i><?= e(__('common.nenhum_registro')) ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Info + Pagination -->
<?php if ($total > 0): ?>
<div class="d-flex justify-content-between align-items-center">
    <small class="text-muted">
        <?= e(__('common.mostrando')) ?> <?= (($pagina - 1) * $porPagina) + 1 ?>–<?= min($pagina * $porPagina, $total) ?>
        <?= e(__('common.de')) ?> <?= $total ?> <?= e(__('common.registros')) ?>
    </small>
    <?php if ($totalPaginas > 1): ?>
    <nav>
        <ul class="pagination pagination-sm mb-0">
            <li class="page-item <?= $pagina <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= url('fornecedores', '', array_merge($filtros, ['pg' => $pagina - 1])) ?>"><?= e(__('common.anterior')) ?></a>
            </li>
            <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
            <li class="page-item <?= $p === $pagina ? 'active' : '' ?>">
                <a class="page-link" href="<?= url('fornecedores', '', array_merge($filtros, ['pg' => $p])) ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
            <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= url('fornecedores', '', array_merge($filtros, ['pg' => $pagina + 1])) ?>"><?= e(__('common.proximo')) ?></a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
