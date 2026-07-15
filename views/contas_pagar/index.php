<?php
$pageTitle = 'Contas a Pagar';
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<div id="sidebarOverlay" class="sidebar-overlay"></div>

<div class="page-header">
    <h2><i class="bi bi-arrow-up-circle me-2"></i>Contas a Pagar</h2>
    <a href="<?= url('contas_pagar', 'create') ?>" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i>Nova Conta
    </a>
</div>

<form method="GET" action="<?= url('contas_pagar') ?>" class="filter-bar">
    <input type="hidden" name="page" value="contas_pagar">
    <div class="row g-2 align-items-center">
        <div class="col-md-3">
            <input type="text" class="form-control form-control-sm" name="busca" value="<?= e($filtros['busca'] ?? '') ?>" placeholder="Buscar por codigo, descricao ou fornecedor...">
        </div>
        <div class="col-md-2">
            <select class="form-select form-select-sm" name="status">
                <option value="">Todos os status</option>
                <?php $statusOptions = ['rascunho','pendente_aprovacao','aprovada','parcialmente_paga','paga','vencida','cancelada']; ?>
                <?php foreach ($statusOptions as $status): ?>
                    <option value="<?= e($status) ?>" <?= ($filtros['status'] ?? '') === $status ? 'selected' : '' ?>><?= e(str_replace('_', ' ', ucfirst($status))) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select class="form-select form-select-sm" name="fornecedor_id">
                <option value="">Todos os fornecedores</option>
                <?php foreach ($fornecedores as $fornecedor): ?>
                    <option value="<?= e($fornecedor['id']) ?>" <?= ($filtros['fornecedor_id'] ?? '') === $fornecedor['id'] ? 'selected' : '' ?>>
                        <?= e($fornecedor['razao_social']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select class="form-select form-select-sm" name="categoria_id">
                <option value="">Todas as categorias</option>
                <?php foreach ($categorias as $categoria): ?>
                    <option value="<?= e($categoria['id']) ?>" <?= ($filtros['categoria_id'] ?? '') === $categoria['id'] ? 'selected' : '' ?>>
                        <?= e($categoria['nome']) ?>
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
        <div class="col-md-12">
            <button type="submit" class="btn btn-sm btn-primary me-1"><i class="bi bi-funnel me-1"></i>Filtrar</button>
            <a href="<?= url('contas_pagar') ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-lg me-1"></i>Limpar</a>
            <?php if (can_user('categorias', 'visualizar')): ?>
            <a href="<?= url('categorias') ?>" class="btn btn-sm btn-outline-dark"><i class="bi bi-tags me-1"></i>Categorias</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<div class="table-responsive contas-pagar-lista-borda rounded">
    <table class="table table-hover align-middle table-striped">
        <thead class="table-light">
            <tr>
                <th style="width: 115px;">Codigo</th>
                <th>Fornecedor</th>
                <th>Descricao</th>
                <th>Categoria</th>
                <th>Vencimento</th>
                <th>Valor</th>
                <th>Status</th>
                <th>Parcelas</th>
                <th style="width: 260px;">Acoes</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($contas)): ?>
                <?php foreach ($contas as $conta): ?>
                    <tr>
                        <td><span class="badge bg-light text-dark font-monospace"><?= e($conta['codigo']) ?></span></td>
                        <td><?= e($conta['fornecedor_nome']) ?></td>
                        <td><?= e($conta['descricao']) ?></td>
                        <td><?= e((string) ($conta['categoria_nome'] ?? '-')) ?></td>
                        <td><?= e(date_fmt($conta['data_vencimento'])) ?></td>
                        <td>R$ <?= number_format((float) $conta['valor_total'], 2, ',', '.') ?></td>
                        <td><span class="badge bg-secondary"><?= e(str_replace('_', ' ', ucfirst((string) $conta['status']))) ?></span></td>
                        <td><?= (int) $conta['parcelas_pagas'] ?>/<?= (int) $conta['parcelas_total'] ?></td>
                        <td>
                            <a href="<?= url('contas_pagar', 'edit', ['id' => $conta['id']]) ?>" class="btn btn-sm btn-outline-primary btn-action" title="Editar"><i class="bi bi-pencil"></i></a>
                            <form method="post" action="<?= html_entity_decode(url(), ENT_QUOTES, 'UTF-8') ?>" class="d-inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="page" value="contas_pagar">
                                <input type="hidden" name="action" value="enviar_aprovacao">
                                <input type="hidden" name="id" value="<?= e((string) $conta['id']) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-warning btn-action" title="Enviar para aprovacao"><i class="bi bi-send"></i></button>
                            </form>
                            <?php if (can_user('contas_pagar', 'aprovar')): ?>
                                <form method="post" action="<?= html_entity_decode(url(), ENT_QUOTES, 'UTF-8') ?>" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="page" value="contas_pagar">
                                    <input type="hidden" name="action" value="aprovar">
                                    <input type="hidden" name="id" value="<?= e((string) $conta['id']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-success btn-action" title="Aprovar"><i class="bi bi-check2-circle"></i></button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= html_entity_decode(url(), ENT_QUOTES, 'UTF-8') ?>" class="d-inline js-confirm-cancelar-conta-pagar">
                                <?= csrf_field() ?>
                                <input type="hidden" name="page" value="contas_pagar">
                                <input type="hidden" name="action" value="cancelar">
                                <input type="hidden" name="id" value="<?= e((string) $conta['id']) ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger btn-action" title="Cancelar"><i class="bi bi-x-circle"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php
                    $parcelasConta = ContasPagar::listarParcelas((string) $conta['id'], (string) current_empresa_id());
                    ?>
                    <?php if (!empty($parcelasConta)): ?>
                        <tr>
                            <td colspan="9" class="bg-light-subtle">
                                <div class="small mb-2 fw-semibold">Parcelas</div>
                                <div class="table-responsive">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Vencimento</th>
                                                <th>Valor</th>
                                                <th>Status</th>
                                                <th>Pagamento</th>
                                                <th style="width: 200px;">Acoes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($parcelasConta as $parcela): ?>
                                                <tr>
                                                    <td><?= (int) $parcela['numero_parcela'] ?></td>
                                                    <td><?= e(date_fmt($parcela['data_vencimento'])) ?></td>
                                                    <td>R$ <?= number_format((float) $parcela['valor'], 2, ',', '.') ?></td>
                                                    <td><?= e($parcela['status']) ?></td>
                                                    <td><?= e(date_fmt($parcela['data_pagamento'] ?? null)) ?></td>
                                                    <td>
                                                        <?php if (in_array($parcela['status'], ['pendente', 'vencida'], true)): ?>
                                                            <a href="<?= url('contas_pagar', 'baixar_parcela', ['parcela_id' => $parcela['id']]) ?>" class="btn btn-sm btn-outline-success">Baixar</a>
                                                        <?php endif; ?>
                                                        <?php if (!empty($parcela['comprovante_path'])): ?>
                                                            <a href="<?= url('contas_pagar', 'download_comprovante', ['parcela_id' => $parcela['id']]) ?>" class="btn btn-sm btn-outline-dark">Comprovante</a>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">Nenhuma conta a pagar encontrada.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script nonce="<?= e(CSP_SCRIPT_NONCE) ?>">
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.js-confirm-cancelar-conta-pagar').forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!confirm('Confirmar cancelamento da conta?')) {
                event.preventDefault();
            }
        });
    });
});
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
