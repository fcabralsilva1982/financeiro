<?php
/**
 * View: Listagem de Usuários
 * Variáveis esperadas: $usuarios (array), $empresasMock (array), $perfisMock (array)
 */
$pageTitle = __('usuarios.title');
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<div id="sidebarOverlay" class="sidebar-overlay"></div>

<!-- Page Header -->
<div class="page-header">
    <h2><i class="bi bi-people me-2"></i><?= e(__('usuarios.title')) ?></h2>
    <a href="<?= url('usuarios', 'create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i><?= e(__('usuarios.new')) ?>
    </a>
</div>

<!-- Filter Bar -->
<div class="filter-bar">
    <div class="row g-2 align-items-center">
        <div class="col-md-3">
            <div class="input-group input-group-sm">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input type="text" class="form-control" placeholder="<?= e(__('usuarios.search')) ?>">
            </div>
        </div>
        <?php if (can_see_all_empresas()): ?>
        <div class="col-md-3">
            <select class="form-select form-select-sm">
                <option value=""><?= e(__('usuarios.todas_empresas')) ?></option>
                <?php if (!empty($empresasMock)): ?>
                    <?php foreach ($empresasMock as $emp): ?>
                        <option value="<?= e($emp['id']) ?>"><?= e($emp['nome_fantasia']) ?></option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
        </div>
        <?php endif; ?>
        <div class="col-md-2">
            <select class="form-select form-select-sm">
                <option value=""><?= e(__('usuarios.todos_status')) ?></option>
                <option value="ativo"><?= e(__('common.ativo')) ?></option>
                <option value="bloqueado"><?= e(__('common.bloqueado')) ?></option>
            </select>
        </div>
    </div>
</div>

<!-- Table -->
<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th style="width: 110px;"><?= e(__('common.codigo')) ?></th>
                <th><?= e(__('usuarios.nome')) ?></th>
                <th><?= e(__('usuarios.email')) ?></th>
                <?php if (can_see_all_empresas()): ?>
                <th><?= e(__('usuarios.empresa')) ?></th>
                <?php endif; ?>
                <th><?= e(__('usuarios.perfil')) ?></th>
                <th style="width: 110px;"><?= e(__('usuarios.status')) ?></th>
                <th style="width: 150px;"><?= e(__('usuarios.acoes')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($usuarios)): ?>
                <?php foreach ($usuarios as $usuario): ?>
                <tr>
                    <td><span class="badge bg-light text-dark font-monospace"><?= e($usuario['codigo']) ?></span></td>
                    <td><?= e($usuario['nome']) ?></td>
                    <td><?= e($usuario['email']) ?></td>
                    <?php if (can_see_all_empresas()): ?>
                    <td><?= e($usuario['empresa']) ?></td>
                    <?php endif; ?>
                    <td><span class="badge bg-info text-dark"><?= e($usuario['perfil']) ?></span></td>
                    <td>
                        <?php if ($usuario['status'] === 'ativo'): ?>
                            <span class="badge badge-ativo"><?= e(__('common.ativo')) ?></span>
                        <?php else: ?>
                            <span class="badge badge-bloqueado"><?= e(__('common.bloqueado')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="<?= url('usuarios', 'edit', ['id' => $usuario['id']]) ?>"
                           class="btn btn-sm btn-outline-primary btn-action"
                           data-bs-toggle="tooltip" title="<?= e(__('common.editar')) ?>">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <?php if ($usuario['status'] === 'ativo'): ?>
                        <button type="button"
                                class="btn btn-sm btn-outline-warning btn-action"
                                data-bs-toggle="modal" data-bs-target="#modalBloquear"
                                data-url="<?= url('usuarios', 'block', ['id' => $usuario['id']]) ?>"
                                data-title="<?= e(__('common.confirmar_bloqueio')) ?>"
                                data-msg="<?= e(__('common.confirmar_bloqueio_msg')) ?>"
                                title="<?= e(__('common.bloquear')) ?>">
                            <i class="bi bi-lock"></i>
                        </button>
                        <?php else: ?>
                        <button type="button"
                                class="btn btn-sm btn-outline-success btn-action"
                                data-bs-toggle="modal" data-bs-target="#modalBloquear"
                                data-url="<?= url('usuarios', 'unblock', ['id' => $usuario['id']]) ?>"
                                data-title="<?= e(__('common.confirmar_desbloqueio')) ?>"
                                data-msg="<?= e(__('common.confirmar_desbloqueio_msg')) ?>"
                                title="<?= e(__('common.desbloquear')) ?>">
                            <i class="bi bi-unlock"></i>
                        </button>
                        <?php endif; ?>
                        <button type="button"
                                class="btn btn-sm btn-outline-danger btn-action"
                                data-bs-toggle="modal" data-bs-target="#modalExcluir"
                                data-url="<?= url('usuarios', 'delete', ['id' => $usuario['id']]) ?>"
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

<!-- Pagination -->
<nav>
    <ul class="pagination pagination-sm justify-content-end">
        <li class="page-item disabled"><a class="page-link" href="#"><?= e(__('common.anterior')) ?></a></li>
        <li class="page-item active"><a class="page-link" href="#">1</a></li>
        <li class="page-item"><a class="page-link" href="#">2</a></li>
        <li class="page-item"><a class="page-link" href="#"><?= e(__('common.proximo')) ?></a></li>
    </ul>
</nav>

<?php require __DIR__ . '/../layout/footer.php'; ?>
