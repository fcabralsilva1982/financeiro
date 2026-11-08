<?php
$pageTitle = __('conciliacao.title');
$importacoes = $importacoes ?? [];
$importacaoSelecionada = $importacaoSelecionada ?? null;
$transacoes = $transacoes ?? [];
$contasReceberElegiveis = $contasReceberElegiveis ?? [];
$parcelasPagarElegiveis = $parcelasPagarElegiveis ?? [];
$podeEditarConciliacao = can_user('conciliacao', 'editar');
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<div id="sidebarOverlay" class="sidebar-overlay"></div>

<div class="page-header">
    <h2><i class="bi bi-bank me-2"></i><?= e(__('conciliacao.title')) ?></h2>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <h5 class="card-title mb-3"><?= e(__('conciliacao.upload_title')) ?></h5>
        <form method="POST" action="<?= url('conciliacao', 'importar') ?>" enctype="multipart/form-data" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="conciliacao">
            <input type="hidden" name="action" value="importar">
            <?= csrf_field() ?>
            <div class="col-md-8">
                <label for="arquivo_ofx" class="form-label"><?= e(__('conciliacao.arquivo')) ?></label>
                <input type="file" class="form-control" id="arquivo_ofx" name="arquivo_ofx" accept=".ofx" required>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-upload me-1"></i><?= e(__('conciliacao.importar')) ?>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <h5 class="card-title mb-3"><?= e(__('conciliacao.importacoes')) ?></h5>
                <?php if ($importacoes !== []): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($importacoes as $importacao): ?>
                            <a href="<?= url('conciliacao', 'index', ['id' => $importacao['id']]) ?>"
                               class="list-group-item list-group-item-action <?= (($importacaoSelecionada['id'] ?? '') === $importacao['id']) ? 'active' : '' ?>">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <strong class="me-2"><?= e($importacao['nome_arquivo']) ?></strong>
                                    <small><?= e(date('d/m/Y H:i', strtotime((string) $importacao['data_importacao']))) ?></small>
                                </div>
                                <small class="d-block"><?= e(__('conciliacao.pendentes')) ?>: <?= (int) $importacao['quantidade_pendentes'] ?></small>
                                <small class="d-block"><?= e(__('conciliacao.conciliadas_auto')) ?>: <?= (int) $importacao['quantidade_conciliadas_auto'] ?></small>
                                <small class="d-block"><?= e(__('conciliacao.duplicadas')) ?>: <?= (int) $importacao['quantidade_duplicadas'] ?></small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0"><?= e(__('conciliacao.nenhuma_importacao')) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0"><?= e(__('conciliacao.transacoes')) ?></h5>
                    <?php if ($importacaoSelecionada): ?>
                        <small class="text-muted"><?= e($importacaoSelecionada['nome_arquivo']) ?></small>
                    <?php endif; ?>
                </div>

                <?php if ($transacoes !== []): ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Data</th>
                                    <th><?= e(__('conciliacao.tipo_movimento')) ?></th>
                                    <th>Valor</th>
                                    <th><?= e(__('conciliacao.status_conciliacao')) ?></th>
                                    <th><?= e(__('conciliacao.descricao_banco')) ?></th>
                                    <th><?= e(__('conciliacao.memo')) ?></th>
                                    <th><?= e(__('conciliacao.fitid')) ?></th>
                                    <?php if ($podeEditarConciliacao): ?>
                                        <th><?= e(__('conciliacao.acoes')) ?></th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transacoes as $transacao): ?>
                                    <tr>
                                        <td><?= e(date_fmt((string) $transacao['data_movimento'])) ?></td>
                                        <td>
                                            <span class="badge <?= $transacao['tipo_movimento'] === 'credito' ? 'bg-success' : 'bg-danger' ?>">
                                                <?= e(ucfirst((string) $transacao['tipo_movimento'])) ?>
                                            </span>
                                        </td>
                                        <td><?= e(format_money_br((float) $transacao['valor'])) ?></td>
                                        <td><span class="badge bg-secondary"><?= e((string) $transacao['status_conciliacao']) ?></span></td>
                                        <td><?= e((string) ($transacao['descricao_banco'] ?? '')) ?></td>
                                        <td><?= e((string) ($transacao['memo'] ?? '')) ?></td>
                                        <td><span class="font-monospace small"><?= e((string) $transacao['fitid']) ?></span></td>
                                        <?php if ($podeEditarConciliacao): ?>
                                            <td style="min-width: 320px;">
                                                <?php if ((string) $transacao['status_conciliacao'] === 'pendente'): ?>
                                                    <?php if ((string) $transacao['tipo_movimento'] === 'credito'): ?>
                                                        <form method="POST" action="<?= url('conciliacao', 'conciliar_receber') ?>" class="d-flex gap-2 mb-2">
                                                            <input type="hidden" name="page" value="conciliacao">
                                                            <input type="hidden" name="action" value="conciliar_receber">
                                                            <input type="hidden" name="importacao_id" value="<?= e((string) $importacaoSelecionada['id']) ?>">
                                                            <input type="hidden" name="transacao_id" value="<?= e((string) $transacao['id']) ?>">
                                                            <?= csrf_field() ?>
                                                            <select name="conta_receber_id" class="form-select form-select-sm" required>
                                                                <option value=""><?= e(__('conciliacao.selecione_conta_receber')) ?></option>
                                                                <?php foreach ($contasReceberElegiveis as $contaReceber): ?>
                                                                    <option value="<?= e((string) $contaReceber['id']) ?>">
                                                                        <?= e((string) $contaReceber['codigo']) ?> —
                                                                        <?= e(format_money_br((float) $contaReceber['valor_total'])) ?> —
                                                                        <?= e(date_fmt((string) $contaReceber['data_vencimento'])) ?> —
                                                                        <?= e((string) ($contaReceber['cliente_nome'] ?? $contaReceber['descricao'])) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <button type="submit" class="btn btn-sm btn-outline-primary"><?= e(__('conciliacao.vincular')) ?></button>
                                                        </form>
                                                    <?php else: ?>
                                                        <form method="POST" action="<?= url('conciliacao', 'conciliar_pagar') ?>" class="d-flex gap-2 mb-2">
                                                            <input type="hidden" name="page" value="conciliacao">
                                                            <input type="hidden" name="action" value="conciliar_pagar">
                                                            <input type="hidden" name="importacao_id" value="<?= e((string) $importacaoSelecionada['id']) ?>">
                                                            <input type="hidden" name="transacao_id" value="<?= e((string) $transacao['id']) ?>">
                                                            <?= csrf_field() ?>
                                                            <select name="conta_pagar_parcela_id" class="form-select form-select-sm" required>
                                                                <option value=""><?= e(__('conciliacao.selecione_parcela_pagar')) ?></option>
                                                                <?php foreach ($parcelasPagarElegiveis as $parcela): ?>
                                                                    <option value="<?= e((string) $parcela['id']) ?>">
                                                                        <?= e((string) $parcela['conta_codigo']) ?>/<?= e((string) $parcela['numero_parcela']) ?> —
                                                                        <?= e(format_money_br((float) $parcela['valor'])) ?> —
                                                                        <?= e(date_fmt((string) $parcela['data_vencimento'])) ?> —
                                                                        <?= e((string) ($parcela['fornecedor_nome'] ?? $parcela['descricao'])) ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                            <button type="submit" class="btn btn-sm btn-outline-primary"><?= e(__('conciliacao.vincular')) ?></button>
                                                        </form>
                                                    <?php endif; ?>

                                                    <div class="d-flex gap-2">
                                                        <form method="POST" action="<?= url('conciliacao', 'movimento_interno') ?>">
                                                            <input type="hidden" name="page" value="conciliacao">
                                                            <input type="hidden" name="action" value="movimento_interno">
                                                            <input type="hidden" name="importacao_id" value="<?= e((string) $importacaoSelecionada['id']) ?>">
                                                            <input type="hidden" name="transacao_id" value="<?= e((string) $transacao['id']) ?>">
                                                            <?= csrf_field() ?>
                                                            <button type="submit" class="btn btn-sm btn-outline-secondary"><?= e(__('conciliacao.movimento_interno')) ?></button>
                                                        </form>
                                                        <form method="POST" action="<?= url('conciliacao', 'ignorar') ?>">
                                                            <input type="hidden" name="page" value="conciliacao">
                                                            <input type="hidden" name="action" value="ignorar">
                                                            <input type="hidden" name="importacao_id" value="<?= e((string) $importacaoSelecionada['id']) ?>">
                                                            <input type="hidden" name="transacao_id" value="<?= e((string) $transacao['id']) ?>">
                                                            <?= csrf_field() ?>
                                                            <button type="submit" class="btn btn-sm btn-outline-danger"><?= e(__('conciliacao.ignorar')) ?></button>
                                                        </form>
                                                    </div>
                                                <?php else: ?>
                                                    <?php if (!empty($transacao['conta_receber_codigo'])): ?>
                                                        <span class="badge bg-success"><?= e((string) $transacao['conta_receber_codigo']) ?></span>
                                                    <?php elseif (!empty($transacao['conta_pagar_codigo'])): ?>
                                                        <span class="badge bg-danger">
                                                            <?= e((string) $transacao['conta_pagar_codigo']) ?>/<?= e((string) $transacao['numero_parcela']) ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted small"><?= e((string) $transacao['origem_match']) ?></span>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                        <?php endif; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p class="text-muted mb-0"><?= e(__('conciliacao.nenhuma_transacao')) ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
