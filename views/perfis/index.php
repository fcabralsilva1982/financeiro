<?php
/**
 * View: Listagem de Perfis de Acesso
 * Variáveis do controller: $perfis, $total, $pagina, $totalPaginas, $porPagina, $filtros
 */
$pageTitle = __('perfis.title');
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<div id="sidebarOverlay" class="sidebar-overlay"></div>

<!-- Page Header -->
<div class="page-header">
    <h2><i class="bi bi-shield-lock me-2"></i><?= e(__('perfis.title')) ?></h2>
    <a href="<?= url('perfis', 'create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i><?= e(__('perfis.new')) ?>
    </a>
</div>

<!-- Filter Bar -->
<form method="GET" action="<?= url('perfis') ?>" class="filter-bar">
    <input type="hidden" name="page" value="perfis">
    <div class="row g-2 align-items-center">
        <div class="col-md-4">
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" name="busca"
                       value="<?= e($filtros['busca'] ?? '') ?>"
                       placeholder="<?= e(__('perfis.search')) ?>">
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
            <a href="<?= url('perfis') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg me-1"></i><?= e(__('logs.limpar')) ?></a>
        </div>
    </div>
</form>

<!-- Table -->
<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th style="width: 110px;"><?= e(__('common.codigo')) ?></th>
                <th><?= e(__('perfis.nome')) ?></th>
                <th><?= e(__('perfis.descricao')) ?></th>
                <th style="width: 110px;"><?= e(__('perfis.num_usuarios')) ?></th>
                <th style="width: 110px;"><?= e(__('perfis.status')) ?></th>
                <th style="width: 150px;"><?= e(__('perfis.acoes')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($perfis)): ?>
                <?php foreach ($perfis as $perfil): ?>
                <tr>
                    <td><span class="badge bg-light text-dark font-monospace"><?= e($perfil['codigo']) ?></span></td>
                    <td><strong><?= e($perfil['nome']) ?></strong></td>
                    <td class="text-muted"><?= e($perfil['descricao']) ?></td>
                    <td><span class="badge bg-secondary"><?= (int) ($perfil['num_usuarios'] ?? 0) ?></span></td>
                    <td>
                        <?php if ($perfil['status'] === 'ativo'): ?>
                            <span class="badge badge-ativo"><?= e(__('common.ativo')) ?></span>
                        <?php else: ?>
                            <span class="badge badge-bloqueado"><?= e(__('common.bloqueado')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?= url('perfis', 'edit', ['id' => $perfil['id']]) ?>"
                           class="btn btn-sm btn-outline-primary btn-action"
                           data-bs-toggle="tooltip" title="<?= e(__('common.editar')) ?>">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <?php if ($perfil['status'] === 'ativo'): ?>
                        <button type="button"
                                class="btn btn-sm btn-outline-warning btn-action"
                                data-bs-toggle="modal" data-bs-target="#modalBloquear"
                                data-url="<?= url('perfis', 'block', ['id' => $perfil['id']]) ?>"
                                data-title="<?= e(__('common.confirmar_bloqueio')) ?>"
                                data-msg="<?= e(__('common.confirmar_bloqueio_msg')) ?>"
                                title="<?= e(__('common.bloquear')) ?>">
                            <i class="bi bi-lock"></i>
                        </button>
                        <?php else: ?>
                        <button type="button"
                                class="btn btn-sm btn-outline-success btn-action"
                                data-bs-toggle="modal" data-bs-target="#modalBloquear"
                                data-url="<?= url('perfis', 'unblock', ['id' => $perfil['id']]) ?>"
                                data-title="<?= e(__('common.confirmar_desbloqueio')) ?>"
                                data-msg="<?= e(__('common.confirmar_desbloqueio_msg')) ?>"
                                title="<?= e(__('common.desbloquear')) ?>">
                            <i class="bi bi-unlock"></i>
                        </button>
                        <?php endif; ?>
                        <button type="button"
                                class="btn btn-sm btn-outline-danger btn-action"
                                data-bs-toggle="modal" data-bs-target="#modalExcluir"
                                data-url="<?= url('perfis', 'delete', ['id' => $perfil['id']]) ?>"
                                title="<?= e(__('common.excluir')) ?>">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">
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
                <a class="page-link" href="<?= url('perfis', '', array_merge($filtros, ['pg' => $pagina - 1])) ?>"><?= e(__('common.anterior')) ?></a>
            </li>
            <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
            <li class="page-item <?= $p === $pagina ? 'active' : '' ?>">
                <a class="page-link" href="<?= url('perfis', '', array_merge($filtros, ['pg' => $p])) ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
            <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= url('perfis', '', array_merge($filtros, ['pg' => $pagina + 1])) ?>"><?= e(__('common.proximo')) ?></a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
