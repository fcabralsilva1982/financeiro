<?php
$errors = $errors ?? ($erros ?? []);
$isEdit = !empty($conta['id']);
$pageTitle = $isEdit ? __('contas_receber.edit') : __('contas_receber.new');
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<div id="sidebarOverlay" class="sidebar-overlay"></div>

<div class="page-header">
    <h2>
        <i class="bi bi-arrow-down-circle me-2"></i><?= e($pageTitle) ?>
        <?php if ($isEdit && !empty($conta['codigo'])): ?>
            <small class="text-muted ms-2"><span class="badge bg-secondary font-monospace"><?= e($conta['codigo']) ?></span></small>
        <?php endif; ?>
    </h2>
    <a href="<?= url('contas_receber') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i><?= e(__('common.voltar')) ?></a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <strong><?= e(__('contas_receber.erro_validacao')) ?></strong>
    <ul class="mb-0 mt-1">
        <?php foreach ($errors as $erro): ?>
            <li><?= e($erro) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form method="post" action="<?= url('contas_receber', 'save') ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= e($conta['id'] ?? '') ?>">
    <input type="hidden" name="status" value="<?= e($conta['status'] ?? 'emitida') ?>">

    <div class="form-section">
        <div class="form-section-title"><i class="bi bi-pencil-square me-1"></i>Dados da Cobrança</div>
        <div class="row g-3">
            <div class="col-md-4">
                <label for="cliente_id" class="form-label"><?= e(__('contas_receber.cliente')) ?> <span class="text-danger">*</span></label>
                <select id="cliente_id" name="cliente_id" class="form-select" required>
                    <option value="">-- <?= e(__('common.selecione')) ?> --</option>
                    <?php foreach ($clientes as $cliente): ?>
                        <option value="<?= e($cliente['id']) ?>" <?= (string) ($conta['cliente_id'] ?? '') === (string) $cliente['id'] ? 'selected' : '' ?>>
                            <?= e($cliente['razao_social']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="descricao" class="form-label"><?= e(__('contas_receber.descricao')) ?> <span class="text-danger">*</span></label>
                <input type="text" id="descricao" name="descricao" class="form-control" required value="<?= e($conta['descricao'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label for="valor_total" class="form-label"><?= e(__('contas_receber.valor_total')) ?> <span class="text-danger">*</span></label>
                <input type="text" inputmode="decimal" id="valor_total" name="valor_total" class="form-control" required data-mask="currency" value="<?= e(format_money_br($conta['valor_total'] ?? '')) ?>">
            </div>
            <div class="col-md-2">
                <label for="forma_cobranca" class="form-label"><?= e(__('contas_receber.forma_cobranca')) ?> <span class="text-danger">*</span></label>
                <?php $forma = (string) ($conta['forma_cobranca'] ?? 'boleto'); ?>
                <select id="forma_cobranca" name="forma_cobranca" class="form-select" required>
                    <?php foreach (['boleto', 'pix', 'nfse', 'cartao'] as $opcao): ?>
                        <option value="<?= e($opcao) ?>" <?= $forma === $opcao ? 'selected' : '' ?>>
                            <?= e(__('contas_receber.' . $opcao, strtoupper($opcao))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label for="data_emissao" class="form-label"><?= e(__('contas_receber.data_emissao')) ?> <span class="text-danger">*</span></label>
                <input type="date" id="data_emissao" name="data_emissao" class="form-control" required value="<?= e($conta['data_emissao'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="col-md-2">
                <label for="data_vencimento" class="form-label"><?= e(__('contas_receber.data_vencimento')) ?> <span class="text-danger">*</span></label>
                <input type="date" id="data_vencimento" name="data_vencimento" class="form-control" required value="<?= e($conta['data_vencimento'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="col-12">
                <label for="observacoes" class="form-label"><?= e(__('contas_receber.observacoes')) ?></label>
                <textarea id="observacoes" name="observacoes" rows="3" class="form-control"><?= e($conta['observacoes'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= e(__('common.salvar')) ?></button>
        <a href="<?= url('contas_receber') ?>" class="btn btn-outline-secondary"><i class="bi bi-x-lg me-1"></i><?= e(__('common.cancelar')) ?></a>
    </div>
</form>

<?php require __DIR__ . '/../layout/footer.php'; ?>
