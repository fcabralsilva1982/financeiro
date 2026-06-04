<?php
/**
 * View: Listagem de Clientes
 * Variáveis do controller: $clientes, $total, $pagina, $totalPaginas, $porPagina, $filtros
 */
$pageTitle = __('clientes.title');
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<!-- Overlay sidebar mobile -->
<div id="sidebarOverlay" class="sidebar-overlay"></div>

<!-- Page Header -->
<div class="page-header">
    <h2><i class="bi bi-person-vcard me-2"></i><?= e(__('clientes.title')) ?></h2>
    <a href="<?= url('clientes', 'create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i><?= e(__('clientes.new')) ?>
    </a>
</div>

<!-- Filter Bar -->
<form method="GET" action="<?= url('clientes') ?>" class="filter-bar">
    <input type="hidden" name="page" value="clientes">
    <div class="row g-2 align-items-center">
        <div class="col-md-3">
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" name="busca"
                       value="<?= e($filtros['busca'] ?? '') ?>"
                       placeholder="<?= e(__('clientes.search')) ?>">
            </div>
        </div>
        <div class="col-md-2">
            <select class="form-select form-select-sm" name="status">
                <option value=""><?= e(__('usuarios.todos_status')) ?></option>
                <option value="ativo" <?= ($filtros['status'] ?? '') === 'ativo' ? 'selected' : '' ?>><?= e(__('common.ativo')) ?></option>
                <option value="bloqueado" <?= ($filtros['status'] ?? '') === 'bloqueado' ? 'selected' : '' ?>><?= e(__('common.bloqueado')) ?></option>
            </select>
        </div>
        <div class="col-md-2">
            <select class="form-select form-select-sm" name="tipo_pessoa">
                <option value=""><?= e(__('clientes.todos_tipos')) ?></option>
                <option value="PF" <?= ($filtros['tipo_pessoa'] ?? '') === 'PF' ? 'selected' : '' ?>><?= e(__('clientes.pf')) ?></option>
                <option value="PJ" <?= ($filtros['tipo_pessoa'] ?? '') === 'PJ' ? 'selected' : '' ?>><?= e(__('clientes.pj')) ?></option>
            </select>
        </div>
        <div class="col-md-2">
            <select class="form-select form-select-sm" name="categoria">
                <option value=""><?= e(__('clientes.todas_categorias')) ?></option>
                <option value="recorrente" <?= ($filtros['categoria'] ?? '') === 'recorrente' ? 'selected' : '' ?>><?= e(__('clientes.cat_recorrente')) ?></option>
                <option value="avulso" <?= ($filtros['categoria'] ?? '') === 'avulso' ? 'selected' : '' ?>><?= e(__('clientes.cat_avulso')) ?></option>
                <option value="parceiro" <?= ($filtros['categoria'] ?? '') === 'parceiro' ? 'selected' : '' ?>><?= e(__('clientes.cat_parceiro')) ?></option>
            </select>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-sm btn-primary me-1"><i class="bi bi-funnel me-1"></i><?= e(__('logs.filtrar')) ?></button>
            <a href="<?= url('clientes') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg me-1"></i><?= e(__('logs.limpar')) ?></a>
        </div>
    </div>
</form>

<!-- Table -->
<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th style="width: 110px;"><?= e(__('common.codigo')) ?></th>
                <th style="width: 60px;"><?= e(__('clientes.tipo_pessoa')) ?></th>
                <th><?= e(__('clientes.cpf_cnpj')) ?></th>
                <th><?= e(__('clientes.razao_social')) ?></th>
                <th><?= e(__('clientes.email')) ?></th>
                <th><?= e(__('clientes.celular')) ?></th>
                <th style="width: 100px;"><?= e(__('clientes.categoria')) ?></th>
                <th style="width: 100px;"><?= e(__('clientes.status')) ?></th>
                <th style="width: 150px;"><?= e(__('clientes.acoes')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($clientes)): ?>
                <?php foreach ($clientes as $cli): ?>
                <tr>
                    <td><span class="badge bg-light text-dark font-monospace"><?= e($cli['codigo']) ?></span></td>
                    <td>
                        <span class="badge <?= $cli['tipo_pessoa'] === 'PJ' ? 'bg-info' : 'bg-secondary' ?>"><?= e($cli['tipo_pessoa']) ?></span>
                    </td>
                    <td><?= e($cli['cpf_cnpj']) ?></td>
                    <td><?= e($cli['razao_social']) ?></td>
                    <td><?= e($cli['email']) ?></td>
                    <td><?= e($cli['celular'] ?? '') ?></td>
                    <td>
                        <?php if (!empty($cli['categoria'])): ?>
                            <span class="badge bg-light text-dark"><?= e(__('clientes.cat_' . $cli['categoria'])) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($cli['status'] === 'ativo'): ?>
                            <span class="badge badge-ativo"><?= e(__('common.ativo')) ?></span>
                        <?php else: ?>
                            <span class="badge badge-bloqueado"><?= e(__('common.bloqueado')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <!-- Editar -->
                        <a href="<?= url('clientes', 'edit', ['id' => $cli['id']]) ?>"
                           class="btn btn-sm btn-outline-primary btn-action"
                           data-bs-toggle="tooltip" title="<?= e(__('common.editar')) ?>">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <!-- Bloquear/Desbloquear -->
                        <?php if ($cli['status'] === 'ativo'): ?>
                        <button type="button"
                                class="btn btn-sm btn-outline-warning btn-action"
                                data-bs-toggle="modal" data-bs-target="#modalBloquear"
                                data-url="<?= url('clientes', 'block', ['id' => $cli['id']]) ?>"
                                data-title="<?= e(__('common.confirmar_bloqueio')) ?>"
                                data-msg="<?= e(__('common.confirmar_bloqueio_msg')) ?>"
                                title="<?= e(__('common.bloquear')) ?>">
                            <i class="bi bi-lock"></i>
                        </button>
                        <?php else: ?>
                        <button type="button"
                                class="btn btn-sm btn-outline-success btn-action"
                                data-bs-toggle="modal" data-bs-target="#modalBloquear"
                                data-url="<?= url('clientes', 'unblock', ['id' => $cli['id']]) ?>"
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
                                data-url="<?= url('clientes', 'delete', ['id' => $cli['id']]) ?>"
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
                <a class="page-link" href="<?= url('clientes', '', array_merge($filtros, ['pg' => $pagina - 1])) ?>"><?= e(__('common.anterior')) ?></a>
            </li>
            <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
            <li class="page-item <?= $p === $pagina ? 'active' : '' ?>">
                <a class="page-link" href="<?= url('clientes', '', array_merge($filtros, ['pg' => $p])) ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
            <li class="page-item <?= $pagina >= $totalPaginas ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= url('clientes', '', array_merge($filtros, ['pg' => $pagina + 1])) ?>"><?= e(__('common.proximo')) ?></a>
            </li>
        </ul>
    </nav>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../layout/footer.php'; ?>
