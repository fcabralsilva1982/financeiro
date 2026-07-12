<?php
/**
 * View: Formulário de Perfil de Acesso (criar/editar)
 * Variáveis esperadas: $perfil (array|null), $isEdit (bool), $errors (array)
 */
$errors = $errors ?? [];
$isEdit = $isEdit ?? false;
$perfil = $perfil ?? [];
$pageTitle = $isEdit ? __('perfis.edit') : __('perfis.new');
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';

// Módulos e permissões disponíveis
$modulos = [
    'empresas'  => __('permissions.modulo_empresas'),
    'usuarios'  => __('permissions.modulo_usuarios'),
    'perfis'    => __('permissions.modulo_perfis'),
    'clientes'  => __('permissions.modulo_clientes'),
    'fornecedores' => __('permissions.modulo_fornecedores'),
    'contas_receber' => __('permissions.modulo_contas_receber'),
    'contas_pagar' => __('permissions.modulo_contas_pagar'),
    'dashboard' => __('permissions.modulo_dashboard'),
    'logs' => __('permissions.modulo_logs'),
    'relatorios' => __('permissions.modulo_relatorios'),
    'conciliacao' => __('permissions.modulo_conciliacao'),
    'importacao' => __('permissions.modulo_importacao'),
    'categorias' => __('permissions.modulo_categorias'),
];
$acoes = ['visualizar', 'criar', 'editar', 'aprovar', 'bloquear', 'excluir'];
$permissoesAtuais = $perfil['permissoes'] ?? [];
?>

<div id="sidebarOverlay" class="sidebar-overlay"></div>

<!-- Page Header -->
<div class="page-header">
    <h2>
        <i class="bi bi-shield-lock me-2"></i><?= e($pageTitle) ?>
        <?php if ($isEdit && !empty($perfil['codigo'])): ?>
            <small class="text-muted ms-2"><span class="badge bg-secondary font-monospace"><?= e($perfil['codigo']) ?></span></small>
        <?php endif; ?>
    </h2>
    <a href="<?= url('perfis') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i><?= e(__('common.voltar')) ?>
    </a>
</div>

<!-- Erros de validação -->
<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle me-2"></i><strong><?= e(__('perfis.erro_validacao')) ?></strong>
    <ul class="mb-0 mt-1">
        <?php foreach ($errors as $msg): ?>
            <li><?= e($msg) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form action="<?= url('perfis', 'store') ?>" method="POST">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= e($perfil['id'] ?? '') ?>">
    <?php endif; ?>

    <!-- Dados do Perfil -->
    <div class="form-section">
        <div class="form-section-title"><?= e(__('perfis.title')) ?></div>
        <div class="row g-3">
            <div class="col-md-4">
                <label for="nome" class="form-label"><?= e(__('perfis.nome')) ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control <?= isset($errors['nome']) ? 'is-invalid' : '' ?>" id="nome" name="nome"
                       value="<?= e($perfil['nome'] ?? '') ?>" required>
                <?php if (isset($errors['nome'])): ?><div class="invalid-feedback"><?= e($errors['nome']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-6">
                <label for="descricao" class="form-label"><?= e(__('perfis.descricao')) ?></label>
                <input type="text" class="form-control" id="descricao" name="descricao"
                       value="<?= e($perfil['descricao'] ?? '') ?>">
            </div>
            <?php if ($isEdit): ?>
            <div class="col-md-2">
                <label for="status" class="form-label"><?= e(__('perfis.status')) ?></label>
                <select class="form-select" id="status" name="status">
                    <option value="ativo" <?= ($perfil['status'] ?? '') === 'ativo' ? 'selected' : '' ?>><?= e(__('common.ativo')) ?></option>
                    <option value="bloqueado" <?= ($perfil['status'] ?? '') === 'bloqueado' ? 'selected' : '' ?>><?= e(__('common.bloqueado')) ?></option>
                </select>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Permissões -->
    <div class="form-section">
        <div class="form-section-title"><i class="bi bi-key me-1"></i><?= e(__('perfis.permissoes')) ?></div>

        <div class="permissions-grid">
            <?php foreach ($modulos as $moduloKey => $moduloLabel): ?>
            <div class="permissions-module">
                <h6><i class="bi bi-folder me-1"></i><?= e($moduloLabel) ?></h6>
                <div class="row">
                    <?php foreach ($acoes as $acao): ?>
                    <div class="col-auto">
                        <div class="form-check form-check-inline">
                            <?php
                            $permKey = $moduloKey . '.' . $acao;
                            $isChecked = in_array($permKey, $permissoesAtuais) ? 'checked' : '';
                            $isDisabled = in_array($moduloKey, ['relatorios'], true) ? 'disabled' : '';
                            ?>
                            <input class="form-check-input" type="checkbox"
                                   name="permissoes[]" value="<?= e($permKey) ?>"
                                   id="perm_<?= e($moduloKey . '_' . $acao) ?>"
                                   <?= $isChecked ?> <?= $isDisabled ?>>
                            <label class="form-check-label small" for="perm_<?= e($moduloKey . '_' . $acao) ?>">
                                <?= e(__('permissions.' . $acao)) ?>
                            </label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Botões -->
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i><?= e(__('common.salvar')) ?>
        </button>
        <a href="<?= url('perfis') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-x-lg me-1"></i><?= e(__('common.cancelar')) ?>
        </a>
    </div>
</form>

<?php require __DIR__ . '/../layout/footer.php'; ?>
