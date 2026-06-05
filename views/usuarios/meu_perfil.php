<?php
/**
 * View: Meu Perfil
 * Variaveis esperadas: $usuario (array)
 */
$pageTitle = 'Meu Perfil';
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<div id="sidebarOverlay" class="sidebar-overlay"></div>

<div class="page-header">
    <h2><i class="bi bi-person-circle me-2"></i>Meu Perfil</h2>
    <a href="<?= url('usuarios') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i><?= e(__('common.voltar')) ?>
    </a>
</div>

<div class="form-section mb-4">
    <div class="form-section-title">Dados do Usuario</div>
    <div class="row g-3">
        <div class="col-md-4">
            <label class="form-label">Codigo</label>
            <input type="text" class="form-control" value="<?= e($usuario['codigo'] ?? '') ?>" disabled>
        </div>
        <div class="col-md-4">
            <label class="form-label">Nome</label>
            <input type="text" class="form-control" value="<?= e($usuario['nome'] ?? '') ?>" disabled>
        </div>
        <div class="col-md-4">
            <label class="form-label">E-mail</label>
            <input type="text" class="form-control" value="<?= e($usuario['email'] ?? '') ?>" disabled>
        </div>
    </div>
</div>

<form action="<?= url('usuarios', 'alterar_minha_senha') ?>" method="POST">
    <?= csrf_field() ?>

    <div class="form-section">
        <div class="form-section-title">Alterar Senha</div>
        <div class="row g-3">
            <div class="col-md-4">
                <label for="senha_atual" class="form-label">Senha Atual <span class="text-danger">*</span></label>
                <input type="password" class="form-control" id="senha_atual" name="senha_atual" required>
            </div>
            <div class="col-md-4">
                <label for="nova_senha" class="form-label">Nova Senha <span class="text-danger">*</span></label>
                <input type="password" class="form-control" id="nova_senha" name="nova_senha" required>
            </div>
            <div class="col-md-4">
                <label for="confirmar_nova_senha" class="form-label">Confirmar Nova Senha <span class="text-danger">*</span></label>
                <input type="password" class="form-control" id="confirmar_nova_senha" name="confirmar_nova_senha" required>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2 mt-3">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-shield-lock me-1"></i>Atualizar Senha
        </button>
        <a href="<?= url('usuarios') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-x-lg me-1"></i><?= e(__('common.cancelar')) ?>
        </a>
    </div>
</form>

<?php require __DIR__ . '/../layout/footer.php'; ?>
