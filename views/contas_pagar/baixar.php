<?php
$pageTitle = 'Baixar Parcela';
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<div id="sidebarOverlay" class="sidebar-overlay"></div>

<div class="page-header">
    <h2><i class="bi bi-cash-stack me-2"></i>Baixar Parcela</h2>
    <a href="<?= url('contas_pagar') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i><?= e(__('common.voltar')) ?></a>
</div>

<div class="card">
    <div class="card-body">
        <p class="mb-1"><strong>Conta:</strong> <?= e((string) $parcela['conta_codigo']) ?></p>
        <p class="mb-1"><strong>Parcela:</strong> #<?= (int) $parcela['numero_parcela'] ?></p>
        <p class="mb-1"><strong>Vencimento:</strong> <?= e(date_fmt((string) $parcela['data_vencimento'])) ?></p>
        <p class="mb-3"><strong>Valor:</strong> R$ <?= number_format((float) $parcela['valor'], 2, ',', '.') ?></p>

        <form action="<?= url('contas_pagar', 'baixar_parcela') ?>" method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="parcela_id" value="<?= e($parcela['id']) ?>">

            <div class="row g-3">
                <div class="col-md-3">
                    <label for="data_pagamento" class="form-label">Data Pagamento <span class="text-danger">*</span></label>
                    <input type="date" id="data_pagamento" name="data_pagamento" class="form-control" required value="<?= e(date('Y-m-d')) ?>">
                </div>
                <div class="col-md-3">
                    <label for="valor_pago" class="form-label">Valor Pago <span class="text-danger">*</span></label>
                    <input type="text" inputmode="decimal" id="valor_pago" name="valor_pago" class="form-control" required data-mask="currency" value="<?= e(format_money_br($parcela['valor'])) ?>">
                </div>
                <div class="col-md-3">
                    <label for="forma_pagamento" class="form-label">Forma Pagamento <span class="text-danger">*</span></label>
                    <select id="forma_pagamento" name="forma_pagamento" class="form-select" required>
                        <?php foreach (['boleto','pix','ted','doc','cartao','dinheiro','outro'] as $opcao): ?>
                            <option value="<?= e($opcao) ?>" <?= ((string) ($parcela['forma_pagamento'] ?? '') === $opcao) ? 'selected' : '' ?>><?= e(strtoupper($opcao)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="comprovante" class="form-label">Comprovante</label>
                    <input type="file" id="comprovante" name="comprovante" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
                    <div class="form-text">PDF ou imagem (JPG/PNG/WEBP).</div>
                </div>
                <div class="col-12">
                    <label for="observacoes" class="form-label">Observacoes</label>
                    <textarea id="observacoes" name="observacoes" class="form-control" rows="2"></textarea>
                </div>
            </div>

            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-success"><i class="bi bi-check2-circle me-1"></i>Confirmar Baixa</button>
                <a href="<?= url('contas_pagar') ?>" class="btn btn-outline-secondary"><i class="bi bi-x-lg me-1"></i>Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
