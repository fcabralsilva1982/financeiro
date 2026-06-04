<?php
// Listagem de contas a receber
require_once __DIR__ . '/../layout/header.php';
require_once __DIR__ . '/../layout/sidebar.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4><i class="bi bi-arrow-down-circle me-2"></i><?= e(__('contas_receber.title')) ?></h4>
    <a href="<?= url('contas_receber', 'new') ?>" class="btn btn-success">
        <i class="bi bi-plus-circle me-1"></i> <?= e(__('contas_receber.new')) ?>
    </a>
</div>
<form class="mb-3" method="get" action="<?= url('contas_receber') ?>">
    <div class="input-group">
        <input type="hidden" name="page" value="contas_receber">
        <input type="text" name="search" class="form-control" placeholder="<?= e(__('contas_receber.search')) ?>" value="<?= e($_GET['search'] ?? '') ?>">
        <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
    </div>
</form>
<?php if (empty($contas)): ?>
    <div class="alert alert-info"> <?= e(__('contas_receber.nenhuma_cobranca')) ?> </div>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th><?= e(__('contas_receber.codigo')) ?></th>
                    <th><?= e(__('contas_receber.cliente')) ?></th>
                    <th><?= e(__('contas_receber.descricao')) ?></th>
                    <th><?= e(__('contas_receber.valor_total')) ?></th>
                    <th><?= e(__('contas_receber.data_vencimento')) ?></th>
                    <th><?= e(__('contas_receber.status')) ?></th>
                    <th><?= e(__('common.acoes')) ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($contas as $c): ?>
                <tr>
                    <td><?= e($c['codigo']) ?></td>
                    <td><?= e($c['cliente_nome']) ?></td>
                    <td><?= e($c['descricao']) ?></td>
                    <td>R$ <?= number_format($c['valor_total'], 2, ',', '.') ?></td>
                    <td><?= e(date_fmt($c['data_vencimento'])) ?></td>
                    <td><span class="badge bg-secondary"> <?= e(__('contas_receber.status_' . $c['status'])) ?> </span></td>
                    <td>
                        <a href="<?= url('contas_receber', 'edit', ['id' => $c['id']]) ?>" class="btn btn-sm btn-primary"><i class="bi bi-pencil"></i></a>
                        <a href="<?= url('contas_receber', 'delete', ['id' => $c['id']]) ?>" class="btn btn-sm btn-danger" onclick="return confirm('<?= e(__('contas_receber.confirmar_exclusao')) ?>')"><i class="bi bi-trash"></i></a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php require_once __DIR__ . '/../layout/footer.php'; ?>
