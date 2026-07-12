<?php
$pageTitle = 'Dar Baixa no Recebimento';
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<div id="sidebarOverlay" class="sidebar-overlay"></div>

<div class="page-header">
    <h2><i class="bi bi-cash-coin me-2"></i>Dar Baixa no Recebimento</h2>
    <a href="<?= url('contas_receber') ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i><?= e(__('common.voltar')) ?></a>
</div>

<div class="card">
    <div class="card-body">
        <p class="mb-1"><strong>Conta:</strong> <?= e((string) $conta['codigo']) ?></p>
        <p class="mb-1"><strong>Cliente:</strong> <?= e((string) $conta['cliente_nome']) ?></p>
        <p class="mb-1"><strong>Vencimento:</strong> <?= e(date_fmt((string) $conta['data_vencimento'])) ?></p>
        <p class="mb-3"><strong>Valor em Aberto:</strong> R$ <?= number_format((float) $conta['valor_total'], 2, ',', '.') ?></p>

        <form action="<?= url('contas_receber', 'baixar') ?>" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= e((string) $conta['id']) ?>">

            <div class="row g-3">
                <div class="col-md-3">
                    <label for="data_pagamento" class="form-label">Data do Pagamento <span class="text-danger">*</span></label>
                    <input type="date" id="data_pagamento" name="data_pagamento" class="form-control" required value="<?= e(date('Y-m-d')) ?>">
                </div>
                <div class="col-md-3">
                    <label for="valor_pago" class="form-label">Valor Recebido <span class="text-danger">*</span></label>
                    <input type="number" step="0.01" min="0" id="valor_pago" name="valor_pago" class="form-control" required value="<?= e((string) $conta['valor_total']) ?>">
                </div>
                <div class="col-md-3">
                    <label for="forma_recebimento" class="form-label">Forma de Recebimento <span class="text-danger">*</span></label>
                    <select id="forma_recebimento" name="forma_recebimento" class="form-select" required>
                        <?php foreach (['boleto','pix','cartao','dinheiro','transferencia','outro'] as $opcao): ?>
                            <option value="<?= e($opcao) ?>"><?= e(strtoupper($opcao)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label for="observacoes_baixa" class="form-label">Observações da Baixa</label>
                    <textarea id="observacoes_baixa" name="observacoes_baixa" rows="3" class="form-control"></textarea>
                </div>
            </div>

            <div class="mt-3 d-flex gap-2">
                <button type="submit" class="btn btn-success"><i class="bi bi-check2-circle me-1"></i>Confirmar Baixa</button>
                <a href="<?= url('contas_receber') ?>" class="btn btn-outline-secondary"><i class="bi bi-x-lg me-1"></i>Cancelar</a>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>