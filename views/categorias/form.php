<?php
$errors = $errors ?? [];
$pageTitle = $isEdit ? 'Editar Categoria Financeira' : 'Nova Categoria Financeira';
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<div id="sidebarOverlay" class="sidebar-overlay"></div>

<div class="page-header">
    <h2><i class="bi bi-tags me-2"></i><?= e($pageTitle) ?></h2>
    <a href="<?= url('categorias') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i><?= e(__('common.voltar')) ?>
    </a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $erro): ?>
            <li><?= e($erro) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form action="<?= url('categorias', 'store') ?>" method="POST">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= e($categoria['id'] ?? '') ?>">
    <?php endif; ?>

    <div class="form-section">
        <div class="form-section-title"><i class="bi bi-pencil-square me-1"></i>Dados da Categoria</div>
        <div class="row g-3">
            <div class="col-md-6">
                <label for="nome" class="form-label">Nome <span class="text-danger">*</span></label>
                <input type="text" id="nome" name="nome" class="form-control" required value="<?= e($categoria['nome'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label for="tipo" class="form-label">Tipo <span class="text-danger">*</span></label>
                <select id="tipo" name="tipo" class="form-select" required>
                    <option value="">-- Selecione --</option>
                    <option value="receita" <?= ($categoria['tipo'] ?? '') === 'receita' ? 'selected' : '' ?>>Receita</option>
                    <option value="despesa" <?= ($categoria['tipo'] ?? '') === 'despesa' ? 'selected' : '' ?>>Despesa</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select">
                    <option value="ativo" <?= ($categoria['status'] ?? 'ativo') === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                    <option value="bloqueado" <?= ($categoria['status'] ?? '') === 'bloqueado' ? 'selected' : '' ?>>Bloqueado</option>
                </select>
            </div>
            <div class="col-12">
                <label for="descricao" class="form-label">Descricao</label>
                <textarea id="descricao" name="descricao" rows="3" class="form-control"><?= e($categoria['descricao'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= e(__('common.salvar')) ?></button>
        <a href="<?= url('categorias') ?>" class="btn btn-outline-secondary"><i class="bi bi-x-lg me-1"></i><?= e(__('common.cancelar')) ?></a>
    </div>
</form>

<?php require __DIR__ . '/../layout/footer.php'; ?>
