<?php
/**
 * View: Listagem de Empresas
 * Variáveis do controller: $empresas, $total, $pagina, $totalPaginas, $porPagina, $filtros
 */
$pageTitle = __('empresas.title');
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<!-- Overlay sidebar mobile -->
<div id="sidebarOverlay" class="sidebar-overlay"></div>

<!-- Alerta: módulo restrito à empresa master -->
<?php if (!can_manage_empresas()): ?>
<div class="alert alert-danger">
    <i class="bi bi-shield-exclamation me-2"></i><?= e(__('common.acesso_negado')) ?>
</div>
<?php return; endif; ?>

<!-- Page Header -->
<div class="page-header">
    <h2><i class="bi bi-building me-2"></i><?= e(__('empresas.title')) ?></h2>
    <a href="<?= url('empresas', 'create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i><?= e(__('empresas.new')) ?>
    </a>
</div>

<!-- Filter Bar -->
<form method="GET" action="<?= url('empresas') ?>" class="filter-bar">
    <input type="hidden" name="page" value="empresas">
    <div class="row g-2 align-items-center">
        <div class="col-md-4">
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" name="busca"
                       value="<?= e($filtros['busca'] ?? '') ?>"
                       placeholder="<?= e(__('empresas.search')) ?>">
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
            <a href="<?= url('empresas') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg me-1"></i><?= e(__('logs.limpar')) ?></a>
        </div>
    </div>
</form>

<!-- Table -->
<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th style="width: 110px;"><?= e(__('common.codigo')) ?></th>
                <th style="width: 50px;"><?= e(__('empresas.logo')) ?></th>
                <th><?= e(__('empresas.cnpj')) ?></th>
                <th><?= e(__('empresas.razao_social')) ?></th>
                <th><?= e(__('empresas.nome_fantasia')) ?></th>
                <th style="width: 110px;"><?= e(__('empresas.status')) ?></th>
                <th style="width: 150px;"><?= e(__('empresas.acoes')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($empresas)): ?>
                <?php foreach ($empresas as $empresa): ?>
                <tr>
                    <td><span class="badge bg-light text-dark font-monospace"><?= e($empresa['codigo']) ?></span></td>
                    <td>
                        <?php
                        $logoUrl = $empresa['logo'] ?? '';
                        if (!empty($logoUrl) && strpos($logoUrl, 'http') !== 0) {
                            $logoUrl = empresa_logo_url((string) ($empresa['id'] ?? ''));
                        }
                        ?>
                        <?php if (!empty($logoUrl)): ?>
                            <img src="<?= e($logoUrl) ?>" alt="Logo" class="table-logo rounded">
                        <?php else: ?>
                            <span class="table-logo-placeholder rounded"><i class="bi bi-building"></i></span>
                        <?php endif; ?>
                    </td>
                    <td><?= e($empresa['cnpj']) ?></td>
                    <td><?= e($empresa['razao_social']) ?></td>
                    <td><?= e($empresa['nome_fantasia']) ?></td>
                    <td>
                        <?php if ($empresa['status'] === 'ativo'): ?>
                            <span class="badge badge-ativo"><?= e(__('common.ativo')) ?></span>
                        <?php else: ?>
                            <span class="badge badge-bloqueado"><?= e(__('common.bloqueado')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <!-- Editar -->
                        <a href="<?= url('empresas', 'edit', ['id' => $empresa['id']]) ?>"
                           class="btn btn-sm btn-outline-primary btn-action"
                           data-bs-toggle="tooltip" title="<?= e(__('common.editar')) ?>">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <!-- Bloquear/Desbloquear -->
                        <?php if ($empresa['status'] === 'ativo'): ?>
                        <button type="button"
                                class="btn btn-sm btn-outline-warning btn-action"
                                data-bs-toggle="modal" data-bs-target="#modalBloquear"
                                data-url="<?= url('empresas', 'block', ['id' => $empresa['id']]) ?>"
                                data-title="<?= e(__('common.confirmar_bloqueio')) ?>"
                                data-msg="<?= e(__('common.confirmar_bloqueio_msg')) ?>"
                                title="<?= e(__('common.bloquear')) ?>">
                            <i class="bi bi-lock"></i>
                        </button>
                        <?php else: ?>
                        <button type="button"
                                class="btn btn-sm btn-outline-success btn-action"
                                data-bs-toggle="modal" data-bs-target="#modalBloquear"
                                data-url="<?= url('empresas', 'unblock', ['id' => $empresa['id']]) ?>"
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
                                data-url="<?= url('empresas', 'delete', ['id' => $empresa['id']]) ?>"
                                title="<?= e(__('common.excluir')) ?>">
                            <i class="bi bi-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
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
                <a class="page-link" href="<?= url('empresas', '', array_merge($filtros, ['pg' => $pagina - 1])) ?>"><?= e(__('common.anterior')) ?></a>
            </li>
            <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
            <li class="page-item <?= $p === $pagina ? 'active' : '' ?>">
                <a class="page-link" href="<?= url('empresas', '', array_merge($filtros, ['pg' => $p])) ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
            <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= url('empresas', '', array_merge($filtros, ['pg' => $pagina + 1])) ?>"><?= e(__('common.proximo')) ?></a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
