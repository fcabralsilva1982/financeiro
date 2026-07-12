<?php
$errors = $errors ?? [];
$pageTitle = $isEdit ? 'Editar Conta a Pagar' : 'Nova Conta a Pagar';
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<div id="sidebarOverlay" class="sidebar-overlay"></div>

<div class="page-header">
    <h2>
        <i class="bi bi-arrow-up-circle me-2"></i><?= e($pageTitle) ?>
        <?php if ($isEdit && !empty($conta['codigo'])): ?>
            <small class="text-muted ms-2"><span class="badge bg-secondary font-monospace"><?= e($conta['codigo']) ?></span></small>
        <?php endif; ?>
    </h2>
    <a href="<?= url('contas_pagar') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i><?= e(__('common.voltar')) ?></a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <strong>Corrija os erros:</strong>
    <ul class="mb-0 mt-1">
        <?php foreach ($errors as $erro): ?>
            <li><?= e($erro) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form action="<?= url('contas_pagar', 'store') ?>" method="POST">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= e($conta['id'] ?? '') ?>">
    <?php endif; ?>

    <div class="form-section">
        <div class="form-section-title"><i class="bi bi-pencil-square me-1"></i>Dados da Conta</div>
        <div class="row g-3">
            <div class="col-md-4">
                <label for="fornecedor_id" class="form-label">Fornecedor <span class="text-danger">*</span></label>
                <select id="fornecedor_id" name="fornecedor_id" class="form-select" required>
                    <option value="">-- Selecione --</option>
                    <?php foreach ($fornecedores as $fornecedor): ?>
                        <option value="<?= e($fornecedor['id']) ?>" <?= (string) ($conta['fornecedor_id'] ?? '') === (string) $fornecedor['id'] ? 'selected' : '' ?>>
                            <?= e($fornecedor['razao_social']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="categoria_id" class="form-label">Categoria</label>
                <select id="categoria_id" name="categoria_id" class="form-select">
                    <option value="">-- Sem categoria --</option>
                    <?php foreach ($categorias as $categoria): ?>
                        <option value="<?= e($categoria['id']) ?>" <?= (string) ($conta['categoria_id'] ?? '') === (string) $categoria['id'] ? 'selected' : '' ?>>
                            <?= e($categoria['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="descricao" class="form-label">Descricao <span class="text-danger">*</span></label>
                <input type="text" id="descricao" name="descricao" class="form-control" required value="<?= e($conta['descricao'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label for="valor_total" class="form-label">Valor Total <span class="text-danger">*</span></label>
                <input type="text" inputmode="decimal" id="valor_total" name="valor_total" class="form-control" required data-mask="currency" value="<?= e(format_money_br($conta['valor_total'] ?? '')) ?>">
            </div>
            <div class="col-md-2">
                <label for="qtd_parcelas" class="form-label">Parcelas</label>
                <input type="number" step="1" min="1" max="120" id="qtd_parcelas" name="qtd_parcelas" class="form-control" value="<?= e($conta['qtd_parcelas'] ?? (($parcelas ?? []) !== [] ? count($parcelas) : 1)) ?>">
            </div>
            <div class="col-md-2">
                <label for="data_emissao" class="form-label">Data Emissao <span class="text-danger">*</span></label>
                <input type="date" id="data_emissao" name="data_emissao" class="form-control" required value="<?= e($conta['data_emissao'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="col-md-2">
                <label for="data_vencimento" class="form-label">Data Vencimento <span class="text-danger">*</span></label>
                <input type="date" id="data_vencimento" name="data_vencimento" class="form-control" required value="<?= e($conta['data_vencimento'] ?? date('Y-m-d')) ?>">
            </div>
            <div class="col-md-2">
                <label for="forma_pagamento" class="form-label">Forma Pagamento <span class="text-danger">*</span></label>
                <?php $forma = (string) ($conta['forma_pagamento'] ?? 'boleto'); ?>
                <select id="forma_pagamento" name="forma_pagamento" class="form-select" required>
                    <?php foreach (['boleto','pix','ted','doc','cartao','dinheiro','outro'] as $opcao): ?>
                        <option value="<?= e($opcao) ?>" <?= $forma === $opcao ? 'selected' : '' ?>><?= e(strtoupper($opcao)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" role="switch" id="recorrente" name="recorrente" value="1" <?= (string) ($conta['recorrente'] ?? '0') === '1' ? 'checked' : '' ?>>
                    <label class="form-check-label" for="recorrente">Recorrente</label>
                </div>
            </div>
            <div class="col-md-2" id="periodicidadeWrapper">
                <label for="periodicidade" class="form-label">Periodicidade</label>
                <?php $periodicidade = (string) ($conta['periodicidade'] ?? 'mensal'); ?>
                <select id="periodicidade" name="periodicidade" class="form-select">
                    <?php foreach (['semanal','quinzenal','mensal','bimestral','trimestral','semestral','anual'] as $op): ?>
                        <option value="<?= e($op) ?>" <?= $periodicidade === $op ? 'selected' : '' ?>><?= e(ucfirst($op)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-12">
                <label for="observacoes" class="form-label">Observacoes</label>
                <textarea id="observacoes" name="observacoes" rows="3" class="form-control"><?= e($conta['observacoes'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <?php if (!empty($parcelas)): ?>
        <div class="form-section">
            <div class="form-section-title"><i class="bi bi-list-check me-1"></i>Parcelas Atuais</div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Vencimento</th>
                            <th>Valor</th>
                            <th>Status</th>
                            <th>Pagamento</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($parcelas as $parcela): ?>
                            <tr>
                                <td><?= (int) $parcela['numero_parcela'] ?></td>
                                <td><?= e(date_fmt($parcela['data_vencimento'])) ?></td>
                                <td>R$ <?= number_format((float) $parcela['valor'], 2, ',', '.') ?></td>
                                <td><?= e($parcela['status']) ?></td>
                                <td><?= e(date_fmt($parcela['data_pagamento'] ?? null)) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i><?= e(__('common.salvar')) ?></button>
        <a href="<?= url('contas_pagar') ?>" class="btn btn-outline-secondary"><i class="bi bi-x-lg me-1"></i><?= e(__('common.cancelar')) ?></a>
    </div>
</form>

<script nonce="<?= e(CSP_SCRIPT_NONCE) ?>">
document.addEventListener('DOMContentLoaded', function () {
    const recorrente = document.getElementById('recorrente');
    const periodicidadeWrapper = document.getElementById('periodicidadeWrapper');

    function atualizarPeriodicidade() {
        if (!recorrente || !periodicidadeWrapper) return;
        periodicidadeWrapper.style.display = recorrente.checked ? '' : 'none';
    }

    if (recorrente) {
        recorrente.addEventListener('change', atualizarPeriodicidade);
        atualizarPeriodicidade();
    }
});
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
