<?php
$pageTitle = __('contas_receber.title');
$clientes = $clientes ?? [];
$contas = $contas ?? [];
$filtros = $filtros ?? [];
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<div id="sidebarOverlay" class="sidebar-overlay"></div>

<div class="page-header">
    <h2><i class="bi bi-arrow-down-circle me-2"></i><?= e(__('contas_receber.title')) ?></h2>
    <a href="<?= url('contas_receber', 'new') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i><?= e(__('contas_receber.new')) ?>
    </a>
</div>

<form method="GET" action="<?= url('contas_receber') ?>" class="filter-bar">
    <input type="hidden" name="page" value="contas_receber">
    <div class="row g-2 align-items-center">
        <div class="col-md-3">
            <input type="text" class="form-control form-control-sm" name="search" value="<?= e($filtros['search'] ?? '') ?>" placeholder="<?= e(__('contas_receber.search')) ?>">
        </div>
        <div class="col-md-2">
            <select class="form-select form-select-sm" name="status">
                <option value="">Todos os status</option>
                <?php $statusOptions = ['rascunho','emitida','enviada','visualizada','paga','vencida','cancelada']; ?>
                <?php foreach ($statusOptions as $status): ?>
                    <option value="<?= e($status) ?>" <?= ($filtros['status'] ?? '') === $status ? 'selected' : '' ?>>
                        <?= e(__('contas_receber.status_' . $status, str_replace('_', ' ', ucfirst($status)))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select class="form-select form-select-sm" name="cliente_id">
                <option value="">Todos os clientes</option>
                <?php foreach ($clientes as $cliente): ?>
                    <option value="<?= e($cliente['id']) ?>" <?= ($filtros['cliente_id'] ?? '') === $cliente['id'] ? 'selected' : '' ?>>
                        <?= e($cliente['razao_social']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <div class="d-flex gap-2">
                <input type="date" class="form-control form-control-sm" name="periodo_inicio" value="<?= e($filtros['periodo_inicio'] ?? '') ?>">
                <input type="date" class="form-control form-control-sm" name="periodo_fim" value="<?= e($filtros['periodo_fim'] ?? '') ?>">
            </div>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-sm btn-primary me-1"><i class="bi bi-funnel me-1"></i>Filtrar</button>
            <a href="<?= url('contas_receber') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg me-1"></i>Limpar</a>
        </div>
    </div>
</form>

<div class="table-responsive contas-pagar-lista-borda rounded">
    <table class="table table-hover align-middle table-striped">
        <thead class="table-light">
            <tr>
                <th style="width: 115px;"><?= e(__('contas_receber.codigo')) ?></th>
                <th><?= e(__('contas_receber.cliente')) ?></th>
                <th><?= e(__('contas_receber.descricao')) ?></th>
                <th><?= e(__('contas_receber.data_vencimento')) ?></th>
                <th><?= e(__('contas_receber.valor_total')) ?></th>
                <th><?= e(__('contas_receber.status')) ?></th>
                <th style="width: 150px;"><?= e(__('common.acoes')) ?></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($contas)): ?>
                <?php foreach ($contas as $conta): ?>
                    <tr>
                        <td><span class="badge bg-light text-dark font-monospace"><?= e($conta['codigo']) ?></span></td>
                        <td><?= e($conta['cliente_nome']) ?></td>
                        <td><?= e($conta['descricao']) ?></td>
                        <td><?= e(date_fmt($conta['data_vencimento'])) ?></td>
                        <td>R$ <?= number_format((float) $conta['valor_total'], 2, ',', '.') ?></td>
                        <td>
                            <span class="badge bg-secondary">
                                <?= e(__('contas_receber.status_' . $conta['status'], str_replace('_', ' ', ucfirst((string) $conta['status'])))) ?>
                            </span>
                        </td>
                        <td>
                            <a href="<?= url('contas_receber', 'edit', ['id' => $conta['id']]) ?>" class="btn btn-sm btn-outline-primary btn-action" title="Editar"><i class="bi bi-pencil"></i></a>
                            <?php if (!in_array((string) $conta['status'], ['paga', 'cancelada'], true)): ?>
                                <a href="<?= url('contas_receber', 'baixar', ['id' => $conta['id']]) ?>" class="btn btn-sm btn-outline-success btn-action" title="Dar baixa"><i class="bi bi-cash-coin"></i></a>
                            <?php endif; ?>
                            <?php if ((string) ($conta['status'] ?? '') === 'paga' && !empty($conta['comprovante_path'])): ?>
                                <a href="<?= url('contas_receber', 'download_comprovante', ['id' => $conta['id']]) ?>" class="btn btn-sm btn-outline-dark btn-action" title="Comprovante"><i class="bi bi-paperclip"></i></a>
                            <?php endif; ?>
                            <form method="post" action="<?= html_entity_decode(url(), ENT_QUOTES, 'UTF-8') ?>" class="d-inline" onsubmit="return confirm('<?= e(__('contas_receber.confirmar_exclusao')) ?>');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="page" value="contas_receber">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= e((string) $conta['id']) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger btn-action" title="Excluir"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center text-muted py-4"><?= e(__('contas_receber.nenhuma_cobranca')) ?></td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>
