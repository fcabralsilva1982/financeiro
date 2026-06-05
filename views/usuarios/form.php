<?php
/**
 * View: Formulário de Usuário (criar/editar)
 * Variáveis esperadas: $usuario (array|null), $isEdit (bool), $empresasMock (array), $perfisMock (array)
 */
$pageTitle = $isEdit ? __('usuarios.edit') : __('usuarios.new');
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<div id="sidebarOverlay" class="sidebar-overlay"></div>

<!-- Page Header -->
<div class="page-header">
    <h2><i class="bi bi-people me-2"></i><?= e($pageTitle) ?></h2>
    <a href="<?= url('usuarios') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i><?= e(__('common.voltar')) ?>
    </a>
</div>

<form action="<?= url('usuarios', 'store') ?>" method="POST">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= e($usuario['id'] ?? '') ?>">
        <input type="hidden" name="vinculo_id" value="<?= e($usuario['vinculo_id'] ?? '') ?>">
    <?php endif; ?>

    <!-- Dados Pessoais -->
    <div class="form-section">
        <div class="form-section-title"><?= e(__('usuarios.title')) ?></div>
        <div class="row g-3">
            <div class="col-md-6">
                <label for="nome" class="form-label"><?= e(__('usuarios.nome')) ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="nome" name="nome"
                       value="<?= e($usuario['nome'] ?? '') ?>" required>
            </div>
            <div class="col-md-6">
                <label for="email" class="form-label"><?= e(__('usuarios.email')) ?> <span class="text-danger">*</span></label>
                <input type="email" class="form-control" id="email" name="email"
                       value="<?= e($usuario['email'] ?? '') ?>" required>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-6">
                <label for="telefone" class="form-label"><?= e(__('usuarios.telefone')) ?></label>
                <input type="text" class="form-control" id="telefone" name="telefone"
                       value="<?= e($usuario['telefone'] ?? '') ?>" placeholder="+55 11 9090-9090"
                       data-mask="phone" maxlength="17" pattern="\+\d{2} \d{2} \d{4}-\d{4}">
            </div>
            <div class="col-md-6">
                <label for="celular" class="form-label"><?= e(__('usuarios.celular')) ?></label>
                <input type="text" class="form-control" id="celular" name="celular"
                       value="<?= e($usuario['celular'] ?? '') ?>" placeholder="+55 11 99999-9999"
                       data-mask="mobile" maxlength="18" pattern="\+\d{2} \d{2} \d{5}-\d{4}">
            </div>
        </div>

        <!-- Senha -->
        <div class="row g-3 mt-1">
            <?php if ($isEdit): ?>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="alterar_senha" name="alterar_senha" value="1">
                    <label class="form-check-label" for="alterar_senha"><?= e(__('usuarios.alterar_senha')) ?></label>
                </div>
            </div>
            <?php endif; ?>
            <div class="col-md-6 <?= $isEdit ? 'senha-fields d-none' : '' ?>">
                <label for="senha" class="form-label"><?= e(__('usuarios.senha')) ?> <?php if (!$isEdit): ?><span class="text-danger">*</span><?php endif; ?></label>
                <input type="password" class="form-control" id="senha" name="senha"
                       <?= !$isEdit ? 'required' : '' ?>>
            </div>
            <div class="col-md-6 <?= $isEdit ? 'senha-fields d-none' : '' ?>">
                <label for="confirmar_senha" class="form-label"><?= e(__('usuarios.confirmar_senha')) ?> <?php if (!$isEdit): ?><span class="text-danger">*</span><?php endif; ?></label>
                <input type="password" class="form-control" id="confirmar_senha" name="confirmar_senha"
                       <?= !$isEdit ? 'required' : '' ?>>
            </div>
        </div>
    </div>

    <!-- Empresa e Perfil -->
    <div class="form-section">
        <div class="form-section-title"><i class="bi bi-gear me-1"></i>Acesso</div>
        <div class="row g-3">
            <div class="col-md-4">
                <label for="empresa_id" class="form-label"><?= e(__('usuarios.empresa')) ?> <span class="text-danger">*</span></label>
                <?php if (can_see_all_empresas()): ?>
                <select class="form-select" id="empresa_id" name="empresa_id" required>
                    <option value=""><?= e(__('common.selecione')) ?></option>
                    <?php if (!empty($empresasMock)): ?>
                        <?php foreach ($empresasMock as $emp): ?>
                            <option value="<?= e($emp['id']) ?>"
                                <?= ($usuario['empresa_id'] ?? '') == $emp['id'] ? 'selected' : '' ?>>
                                <?= e($emp['nome_fantasia']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
                <?php else: ?>
                <!-- Empresa fixa: usuário só pode cadastrar na própria empresa -->
                <input type="hidden" name="empresa_id" value="<?= e(current_empresa_id()) ?>">
                <input type="text" class="form-control" value="<?= e(current_empresa_nome()) ?>" disabled>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <label for="perfil_id" class="form-label"><?= e(__('usuarios.perfil')) ?> <span class="text-danger">*</span></label>
                <select class="form-select" id="perfil_id" name="perfil_id" required>
                    <option value=""><?= e(__('common.selecione')) ?></option>
                    <?php if (!empty($perfisMock)): ?>
                        <?php foreach ($perfisMock as $perfil): ?>
                            <option value="<?= e($perfil['id']) ?>"
                                <?= ($usuario['perfil_id'] ?? '') == $perfil['id'] ? 'selected' : '' ?>>
                                <?= e($perfil['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
            <?php if ($isEdit): ?>
            <div class="col-md-4">
                <label for="status" class="form-label"><?= e(__('usuarios.status')) ?></label>
                <select class="form-select" id="status" name="status">
                    <option value="ativo" <?= ($usuario['status'] ?? '') === 'ativo' ? 'selected' : '' ?>><?= e(__('common.ativo')) ?></option>
                    <option value="bloqueado" <?= ($usuario['status'] ?? '') === 'bloqueado' ? 'selected' : '' ?>><?= e(__('common.bloqueado')) ?></option>
                </select>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Botões -->
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i><?= e(__('common.salvar')) ?>
        </button>
        <a href="<?= url('usuarios') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-x-lg me-1"></i><?= e(__('common.cancelar')) ?>
        </a>
    </div>
</form>

<!-- Toggle senha fields (edição) -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const cb = document.getElementById('alterar_senha');
    if (cb) {
        cb.addEventListener('change', function() {
            document.querySelectorAll('.senha-fields').forEach(function(el) {
                el.classList.toggle('d-none', !cb.checked);
            });
        });
    }
});
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
