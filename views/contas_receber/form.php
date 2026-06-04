<?php
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';
$isEdit = !empty($conta['id']);
?>
<h4><i class="bi bi-arrow-down-circle me-2"></i><?= e($isEdit ? __('contas_receber.edit') : __('contas_receber.new')) ?></h4>
<form method="post" action="<?= url('contas_receber', 'save') ?>">
    <input type="hidden" name="id" value="<?= e($conta['id'] ?? '') ?>">
    <div class="row mb-3">
        <div class="col-md-6">
            <label class="form-label">*</label>
            <select name="cliente_id" class="form-select" required>
                <option value="">-- <?= e(__('contas_receber.cliente')) ?> --</option>
                <?php foreach ($clientes as $cli): ?>
                    <option value="<?= e($cli['id']) ?>" <?= isset($conta['cliente_id']) && $conta['cliente_id'] == $cli['id'] ? 'selected' : '' ?>><?= e($cli['razao_social']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label"><?= e(__('contas_receber.valor_total')) ?> *</label>
            <input type="number" step="0.01" min="0" name="valor_total" class="form-control" value="<?= e($conta['valor_total'] ?? '') ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label"><?= e(__('contas_receber.data_vencimento')) ?> *</label>
            <input type="date" name="data_vencimento" class="form-control" value="<?= e($conta['data_vencimento'] ?? '') ?>" required>
        </div>
    </div>
    <div class="row mb-3">
        <div class="col-md-6">
            <label class="form-label"><?= e(__('contas_receber.descricao')) ?> *</label>
            <input type="text" name="descricao" class="form-control" value="<?= e($conta['descricao'] ?? '') ?>" required>
        </div>
        <div class="col-md-3">
            <label class="form-label"><?= e(__('contas_receber.forma_cobranca')) ?> *</label>
            <select name="forma_cobranca" class="form-select" required>
                <option value="">-- <?= e(__('contas_receber.forma_cobranca')) ?> --</option>
                <option value="boleto" <?= (isset($conta['forma_cobranca']) && $conta['forma_cobranca'] == 'boleto') ? 'selected' : '' ?>><?= e(__('contas_receber.boleto')) ?></option>
                <option value="pix" <?= (isset($conta['forma_cobranca']) && $conta['forma_cobranca'] == 'pix') ? 'selected' : '' ?>><?= e(__('contas_receber.pix')) ?></option>
                <option value="nfse" <?= (isset($conta['forma_cobranca']) && $conta['forma_cobranca'] == 'nfse') ? 'selected' : '' ?>><?= e(__('contas_receber.nfse')) ?></option>
                <option value="cartao" <?= (isset($conta['forma_cobranca']) && $conta['forma_cobranca'] == 'cartao') ? 'selected' : '' ?>><?= e(__('contas_receber.cartao')) ?></option>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label"><?= e(__('contas_receber.data_emissao')) ?></label>
            <input type="date" name="data_emissao" class="form-control" value="<?= e($conta['data_emissao'] ?? date('Y-m-d')) ?>">
        </div>
    </div>
    <div class="mb-3">
        <label class="form-label"><?= e(__('contas_receber.observacoes')) ?></label>
        <textarea name="observacoes" class="form-control" rows="2"><?= e($conta['observacoes'] ?? '') ?></textarea>
    </div>
    <div class="mb-3">
        <button type="submit" class="btn btn-success">
            <i class="bi bi-save me-1"></i> <?= e(__('common.salvar')) ?>
        </button>
        <a href="<?= url('contas_receber') ?>" class="btn btn-secondary ms-2">
            <i class="bi bi-arrow-left"></i> <?= e(__('common.voltar')) ?>
        </a>
    </div>
</form>
<?php require_once __DIR__ . '/../layout/footer.php'; ?>
