<?php
/**
 * View: Formulário de Empresa (criar/editar)
 * Variáveis esperadas: $empresa (array|null), $isEdit (bool), $errors (array)
 */
$errors = $errors ?? [];
$pageTitle = $isEdit ? __('empresas.edit') : __('empresas.new');
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<div id="sidebarOverlay" class="sidebar-overlay"></div>

<!-- Page Header -->
<div class="page-header">
    <h2>
        <i class="bi bi-building me-2"></i><?= e($pageTitle) ?>
        <?php if ($isEdit && !empty($empresa['codigo'])): ?>
            <small class="text-muted ms-2"><span class="badge bg-secondary font-monospace"><?= e($empresa['codigo']) ?></span></small>
        <?php endif; ?>
    </h2>
    <a href="<?= url('empresas') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i><?= e(__('common.voltar')) ?>
    </a>
</div>

<!-- Erros de validação -->
<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle me-2"></i><strong><?= e(__('empresas.erro_validacao')) ?></strong>
    <ul class="mb-0 mt-1">
        <?php foreach ($errors as $msg): ?>
            <li><?= e($msg) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form action="<?= url('empresas', 'store') ?>" method="POST" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= e($empresa['id'] ?? '') ?>">
    <?php endif; ?>

    <!-- Logo -->
    <div class="form-section">
        <div class="form-section-title"><i class="bi bi-image me-1"></i><?= e(__('empresas.logo')) ?></div>
        <div class="row g-3 align-items-center">
            <div class="col-md-2 text-center">
                <?php
                $logoSrc = $empresa['logo'] ?? '';
                if (!empty($logoSrc) && strpos($logoSrc, 'http') !== 0) {
                    $logoSrc = empresa_logo_url((string) ($empresa['id'] ?? ''));
                }
                if (!empty($logoSrc)): ?>
                    <img src="<?= e($logoSrc) ?>" alt="Logo" class="logo-preview rounded" id="logoPreview">
                <?php else: ?>
                    <div class="logo-placeholder rounded" id="logoPreview">
                        <i class="bi bi-building"></i>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <label for="logo" class="form-label"><?= e(__('empresas.logo_upload')) ?></label>
                <input type="file" class="form-control" id="logo" name="logo" accept="image/png,image/jpeg,image/svg+xml">
                <div class="form-text"><?= e(__('empresas.logo_hint')) ?></div>
            </div>
            <?php if ($isEdit && !empty($empresa['logo'])): ?>
            <div class="col-md-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="remover_logo" name="remover_logo" value="1">
                    <label class="form-check-label" for="remover_logo"><?= e(__('empresas.logo_remove')) ?></label>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Dados Principais -->
    <div class="form-section">
        <div class="form-section-title"><?= e(__('empresas.title')) ?></div>
        <div class="row g-3">
            <div class="col-md-4">
                <label for="cnpj" class="form-label"><?= e(__('empresas.cnpj')) ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control text-uppercase <?= isset($errors['cnpj']) ? 'is-invalid' : '' ?>" id="cnpj" name="cnpj"
                       value="<?= e($empresa['cnpj'] ?? '') ?>" required maxlength="18"
                       placeholder="00.000.000/0000-00"
                       style="text-transform: uppercase;">
                <div class="form-text"><?= e(__('empresas.cnpj_hint')) ?></div>
                <?php if (isset($errors['cnpj'])): ?><div class="invalid-feedback"><?= e($errors['cnpj']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-4">
                <label for="inscricao_estadual" class="form-label"><?= e(__('empresas.inscricao_estadual')) ?></label>
                <div class="input-group">
                    <input type="text" class="form-control" id="inscricao_estadual" name="inscricao_estadual"
                           value="<?= e($empresa['inscricao_estadual'] ?? '') ?>"
                           placeholder="000.000.000.000">
                    <div class="input-group-text">
                        <input class="form-check-input mt-0" type="checkbox" id="ie_isento" name="ie_isento" value="1"
                               <?= ($empresa['inscricao_estadual'] ?? '') === 'ISENTO' ? 'checked' : '' ?>>
                        <label class="form-check-label ms-1 small" for="ie_isento"><?= e(__('empresas.ie_isento')) ?></label>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <label for="razao_social" class="form-label"><?= e(__('empresas.razao_social')) ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control <?= isset($errors['razao_social']) ? 'is-invalid' : '' ?>" id="razao_social" name="razao_social"
                       value="<?= e($empresa['razao_social'] ?? '') ?>" required>
                <?php if (isset($errors['razao_social'])): ?><div class="invalid-feedback"><?= e($errors['razao_social']) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-4">
                <label for="nome_fantasia" class="form-label"><?= e(__('empresas.nome_fantasia')) ?></label>
                <input type="text" class="form-control" id="nome_fantasia" name="nome_fantasia"
                       value="<?= e($empresa['nome_fantasia'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label for="email" class="form-label"><?= e(__('empresas.email')) ?></label>
                <input type="email" class="form-control" id="email" name="email"
                       value="<?= e($empresa['email'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label for="telefone" class="form-label"><?= e(__('empresas.telefone')) ?></label>
                <input type="text" class="form-control" id="telefone" name="telefone"
                       value="<?= e($empresa['telefone'] ?? '') ?>"
                       placeholder="+55 11 9090-9090" maxlength="17"
                       data-mask="phone" pattern="\+\d{2} \d{2} \d{4}-\d{4}">
            </div>
            <div class="col-md-4">
                <label for="celular" class="form-label"><?= e(__('empresas.celular')) ?></label>
                <input type="text" class="form-control" id="celular" name="celular"
                       value="<?= e($empresa['celular'] ?? '') ?>"
                       placeholder="+55 11 99999-9999" maxlength="18"
                       data-mask="mobile" pattern="\+\d{2} \d{2} \d{5}-\d{4}">
            </div>
            <?php if ($isEdit): ?>
            <div class="col-md-4">
                <label for="status" class="form-label"><?= e(__('empresas.status')) ?></label>
                <select class="form-select" id="status" name="status">
                    <option value="ativo" <?= ($empresa['status'] ?? '') === 'ativo' ? 'selected' : '' ?>><?= e(__('common.ativo')) ?></option>
                    <option value="bloqueado" <?= ($empresa['status'] ?? '') === 'bloqueado' ? 'selected' : '' ?>><?= e(__('common.bloqueado')) ?></option>
                </select>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Endereço -->
    <div class="form-section">
        <div class="form-section-title"><i class="bi bi-geo-alt me-1"></i>Endereço</div>
        <div class="row g-3">
            <div class="col-md-2">
                <label for="cep" class="form-label"><?= e(__('empresas.cep')) ?></label>
                <input type="text" class="form-control" id="cep" name="cep"
                       value="<?= e($empresa['cep'] ?? '') ?>"
                       placeholder="00000-000" maxlength="9">
            </div>
            <div class="col-md-6">
                <label for="logradouro" class="form-label"><?= e(__('empresas.logradouro')) ?></label>
                <input type="text" class="form-control" id="logradouro" name="logradouro"
                       value="<?= e($empresa['logradouro'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label for="numero" class="form-label"><?= e(__('empresas.numero')) ?></label>
                <input type="text" class="form-control" id="numero" name="numero"
                       value="<?= e($empresa['numero'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label for="complemento" class="form-label"><?= e(__('empresas.complemento')) ?></label>
                <input type="text" class="form-control" id="complemento" name="complemento"
                       value="<?= e($empresa['complemento'] ?? '') ?>">
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-4">
                <label for="bairro" class="form-label"><?= e(__('empresas.bairro')) ?></label>
                <input type="text" class="form-control" id="bairro" name="bairro"
                       value="<?= e($empresa['bairro'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label for="cidade" class="form-label"><?= e(__('empresas.cidade')) ?></label>
                <input type="text" class="form-control" id="cidade" name="cidade"
                       value="<?= e($empresa['cidade'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label for="uf" class="form-label"><?= e(__('empresas.uf')) ?></label>
                <select class="form-select" id="uf" name="uf">
                    <option value=""><?= e(__('common.selecione')) ?></option>
                    <?php
                    $ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
                    foreach ($ufs as $uf):
                    ?>
                    <option value="<?= $uf ?>" <?= ($empresa['uf'] ?? '') === $uf ? 'selected' : '' ?>><?= $uf ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Contatos -->
    <div class="form-section">
        <div class="form-section-title"><i class="bi bi-person-lines-fill me-1"></i><?= e(__('empresas.contatos')) ?></div>
        <div id="contatos-container">
            <?php
            $contatos = $empresa['contatos'] ?? [['nome' => '', 'email' => '', 'telefone' => '', 'cargo' => '']];
            foreach ($contatos as $idx => $contato):
            ?>
            <div class="contato-row border rounded p-3 mb-2 bg-light">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small"><?= e(__('empresas.contato_nome')) ?></label>
                        <input type="text" class="form-control form-control-sm" name="contatos[<?= $idx ?>][nome]"
                               value="<?= e($contato['nome'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small"><?= e(__('empresas.contato_email')) ?></label>
                        <input type="email" class="form-control form-control-sm" name="contatos[<?= $idx ?>][email]"
                               value="<?= e($contato['email'] ?? '') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small"><?= e(__('empresas.contato_telefone')) ?></label>
                        <input type="text" class="form-control form-control-sm" name="contatos[<?= $idx ?>][telefone]"
                               value="<?= e($contato['telefone'] ?? '') ?>" placeholder="+55 11 99999-9999"
                               data-mask="mobile" maxlength="18">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small"><?= e(__('empresas.contato_cargo')) ?></label>
                        <input type="text" class="form-control form-control-sm" name="contatos[<?= $idx ?>][cargo]"
                               value="<?= e($contato['cargo'] ?? '') ?>">
                    </div>
                    <div class="col-md-2 text-end">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-remover-contato" title="<?= e(__('empresas.remover_contato')) ?>">
                            <i class="bi bi-trash me-1"></i><?= e(__('empresas.remover_contato')) ?>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary" id="btnAdicionarContato">
            <i class="bi bi-plus-lg me-1"></i><?= e(__('empresas.adicionar_contato')) ?>
        </button>
    </div>

    <!-- Observações -->
    <div class="form-section">
        <div class="form-section-title"><i class="bi bi-chat-left-text me-1"></i><?= e(__('empresas.observacoes')) ?></div>
        <div class="row g-3">
            <div class="col-12">
                <textarea class="form-control" id="observacoes" name="observacoes" rows="4"
                          placeholder="<?= e(__('empresas.observacoes_hint')) ?>"><?= e($empresa['observacoes'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <!-- Botões -->
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i><?= e(__('common.salvar')) ?>
        </button>
        <a href="<?= url('empresas') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-x-lg me-1"></i><?= e(__('common.cancelar')) ?>
        </a>
    </div>
</form>

<!-- Preview de imagem ao selecionar arquivo -->
<script nonce="<?= e(CSP_SCRIPT_NONCE) ?>">
document.addEventListener('DOMContentLoaded', function() {
    // --- Logo preview ---
    const input = document.getElementById('logo');
    if (input) {
        input.addEventListener('change', function() {
            const file = this.files[0];
            const preview = document.getElementById('logoPreview');
            if (file && preview) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (preview.tagName === 'IMG') {
                        preview.src = e.target.result;
                    } else {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.alt = 'Logo';
                        img.className = 'logo-preview rounded';
                        img.id = 'logoPreview';
                        preview.replaceWith(img);
                    }
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // --- Checkbox IE Isento ---
    const ieIsento = document.getElementById('ie_isento');
    const ieInput = document.getElementById('inscricao_estadual');
    if (ieIsento && ieInput) {
        function toggleIE() {
            if (ieIsento.checked) {
                ieInput.value = 'ISENTO';
                ieInput.readOnly = true;
            } else {
                if (ieInput.value === 'ISENTO') ieInput.value = '';
                ieInput.readOnly = false;
            }
        }
        toggleIE();
        ieIsento.addEventListener('change', toggleIE);
    }

    // --- Contatos dinâmicos ---
    const container = document.getElementById('contatos-container');
    const btnAdd = document.getElementById('btnAdicionarContato');

    if (btnAdd && container) {
        btnAdd.addEventListener('click', function() {
            const idx = container.querySelectorAll('.contato-row').length;
            const tpl = `
            <div class="contato-row border rounded p-3 mb-2 bg-light">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label small"><?= e(__('empresas.contato_nome')) ?></label>
                        <input type="text" class="form-control form-control-sm" name="contatos[${idx}][nome]">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label small"><?= e(__('empresas.contato_email')) ?></label>
                        <input type="email" class="form-control form-control-sm" name="contatos[${idx}][email]">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small"><?= e(__('empresas.contato_telefone')) ?></label>
                        <input type="text" class="form-control form-control-sm" name="contatos[${idx}][telefone]" placeholder="+55 11 99999-9999" data-mask="mobile" maxlength="18">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label small"><?= e(__('empresas.contato_cargo')) ?></label>
                        <input type="text" class="form-control form-control-sm" name="contatos[${idx}][cargo]">
                    </div>
                    <div class="col-md-2 text-end">
                        <button type="button" class="btn btn-sm btn-outline-danger btn-remover-contato">
                            <i class="bi bi-trash me-1"></i><?= e(__('empresas.remover_contato')) ?>
                        </button>
                    </div>
                </div>
            </div>`;
            container.insertAdjacentHTML('beforeend', tpl);
        });

        container.addEventListener('click', function(e) {
            const btn = e.target.closest('.btn-remover-contato');
            if (btn) {
                const rows = container.querySelectorAll('.contato-row');
                if (rows.length > 1) {
                    btn.closest('.contato-row').remove();
                }
            }
        });
    }
});
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
