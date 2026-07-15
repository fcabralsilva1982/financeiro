<?php
$pageTitle = 'Categorias Financeiras';
$canCreateCategoria = can_user('categorias', 'criar');
$canEditCategoria = can_user('categorias', 'editar');
$canDeleteCategoria = can_user('categorias', 'excluir');
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<div id="sidebarOverlay" class="sidebar-overlay"></div>

<div class="page-header">
    <h2><i class="bi bi-tags me-2"></i>Categorias Financeiras</h2>
    <?php if ($canCreateCategoria): ?>
    <a href="<?= url('categorias', 'create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nova Categoria
    </a>
    <?php endif; ?>
</div>

<form method="GET" action="<?= url('categorias') ?>" class="filter-bar">
    <input type="hidden" name="page" value="categorias">
    <div class="row g-2 align-items-center">
        <div class="col-md-4">
            <input type="text" class="form-control form-control-sm" name="busca" value="<?= e($filtros['busca'] ?? '') ?>" placeholder="Buscar por codigo ou nome...">
        </div>
        <div class="col-md-2">
            <select class="form-select form-select-sm" name="tipo">
                <option value="">Todos os tipos</option>
                <option value="receita" <?= ($filtros['tipo'] ?? '') === 'receita' ? 'selected' : '' ?>>Receita</option>
                <option value="despesa" <?= ($filtros['tipo'] ?? '') === 'despesa' ? 'selected' : '' ?>>Despesa</option>
            </select>
        </div>
        <div class="col-md-2">
            <select class="form-select form-select-sm" name="status">
                <option value="">Todos os status</option>
                <option value="ativo" <?= ($filtros['status'] ?? '') === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                <option value="bloqueado" <?= ($filtros['status'] ?? '') === 'bloqueado' ? 'selected' : '' ?>>Bloqueado</option>
            </select>
        </div>
        <div class="col-md-4">
            <button type="submit" class="btn btn-sm btn-primary me-1"><i class="bi bi-funnel me-1"></i>Filtrar</button>
            <a href="<?= url('categorias') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg me-1"></i>Limpar</a>
        </div>
    </div>
</form>

<div class="table-responsive">
    <table class="table table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th style="width: 120px;">Codigo</th>
                <th>Nome</th>
                <th style="width: 120px;">Tipo</th>
                <th>Status</th>
                <th style="width: 170px;">Acoes</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($categorias)): ?>
                <?php foreach ($categorias as $categoria): ?>
                    <tr>
                        <td><span class="badge bg-light text-dark font-monospace"><?= e($categoria['codigo']) ?></span></td>
                        <td><?= e($categoria['nome']) ?></td>
                        <td>
                            <span class="badge <?= $categoria['tipo'] === 'receita' ? 'bg-success' : 'bg-danger' ?>">
                                <?= e(ucfirst($categoria['tipo'])) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($categoria['status'] === 'ativo'): ?>
                                <span class="badge badge-ativo">Ativo</span>
                            <?php else: ?>
                                <span class="badge badge-bloqueado">Bloqueado</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($canEditCategoria): ?>
                            <a href="<?= url('categorias', 'edit', ['id' => $categoria['id']]) ?>" class="btn btn-sm btn-outline-primary btn-action" title="Editar">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <?php endif; ?>
                            <?php if ($canDeleteCategoria): ?>
                            <button type="button"
                                    class="btn btn-sm btn-outline-danger btn-action"
                                    data-bs-toggle="modal" data-bs-target="#modalExcluir"
                                    data-url="<?= url('categorias', 'delete', ['id' => $categoria['id']]) ?>"
                                    title="Excluir">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="text-center text-muted py-4">Nenhuma categoria cadastrada.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
