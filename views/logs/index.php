<?php
/**
 * View: Log de Ações do Sistema
 * Variáveis esperadas: $logs, $totalLogs, $totalPaginas, $paginaAtual,
 *                      $filtros, $modulosDisponiveis, $acoesDisponiveis, $niveisDisponiveis
 */
$pageTitle = __('logs.title');
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<div id="sidebarOverlay" class="sidebar-overlay"></div>

<!-- Page Header -->
<div class="page-header">
    <h2><i class="bi bi-journal-text me-2"></i><?= e(__('logs.title')) ?></h2>
    <span class="badge bg-secondary fs-6"><?= e($totalLogs) ?> <?= e(__('common.registros')) ?></span>
</div>

<!-- Filtros -->
<div class="card mb-3">
    <div class="card-body py-2">
        <form method="get" action="<?= BASE_URL ?>/index.php" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="logs">
            <input type="hidden" name="action" value="index">

            <!-- Código da Empresa -->
            <div class="col-md-2">
                <label class="form-label form-label-sm mb-0"><?= e(__('logs.empresa_codigo')) ?></label>
                <input type="text" name="empresa_codigo" class="form-control form-control-sm"
                       placeholder="EMP-0001" value="<?= e($filtros['empresa_codigo']) ?>">
            </div>

            <!-- Código do Usuário -->
            <div class="col-md-2">
                <label class="form-label form-label-sm mb-0"><?= e(__('logs.usuario_codigo')) ?></label>
                <input type="text" name="usuario_codigo" class="form-control form-control-sm"
                       placeholder="USR-0001" value="<?= e($filtros['usuario_codigo']) ?>">
            </div>

            <!-- Módulo -->
            <div class="col-md-1">
                <label class="form-label form-label-sm mb-0"><?= e(__('logs.modulo')) ?></label>
                <select name="modulo" class="form-select form-select-sm">
                    <option value=""><?= e(__('logs.todos')) ?></option>
                    <?php foreach ($modulosDisponiveis as $mod): ?>
                        <option value="<?= e($mod) ?>" <?= $filtros['modulo'] === $mod ? 'selected' : '' ?>><?= e(ucfirst($mod)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Ação -->
            <div class="col-md-1">
                <label class="form-label form-label-sm mb-0"><?= e(__('logs.acao')) ?></label>
                <select name="acao" class="form-select form-select-sm">
                    <option value=""><?= e(__('logs.todos')) ?></option>
                    <?php foreach ($acoesDisponiveis as $ac): ?>
                        <option value="<?= e($ac) ?>" <?= $filtros['acao'] === $ac ? 'selected' : '' ?>><?= e(ucfirst($ac)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Nível -->
            <div class="col-md-1">
                <label class="form-label form-label-sm mb-0"><?= e(__('logs.nivel')) ?></label>
                <select name="nivel" class="form-select form-select-sm">
                    <option value=""><?= e(__('logs.todos')) ?></option>
                    <?php foreach ($niveisDisponiveis as $nv): ?>
                        <option value="<?= e($nv) ?>" <?= $filtros['nivel'] === $nv ? 'selected' : '' ?>><?= e(ucfirst($nv)) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Data Início -->
            <div class="col-md-2">
                <label class="form-label form-label-sm mb-0"><?= e(__('logs.data_inicio')) ?></label>
                <input type="date" name="data_inicio" class="form-control form-control-sm"
                       value="<?= e($filtros['data_inicio']) ?>">
            </div>

            <!-- Data Fim -->
            <div class="col-md-2">
                <label class="form-label form-label-sm mb-0"><?= e(__('logs.data_fim')) ?></label>
                <input type="date" name="data_fim" class="form-control form-control-sm"
                       value="<?= e($filtros['data_fim']) ?>">
            </div>

            <!-- Botões -->
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-sm btn-primary" title="<?= e(__('logs.filtrar')) ?>">
                    <i class="bi bi-funnel"></i>
                </button>
                <a href="<?= url('logs') ?>" class="btn btn-sm btn-outline-secondary" title="<?= e(__('logs.limpar')) ?>">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tabela de Logs -->
<div class="table-responsive">
    <table class="table table-sm table-hover align-middle">
        <thead class="table-light">
            <tr>
                <th style="width: 140px;"><?= e(__('logs.data_hora')) ?></th>
                <th style="width: 80px;"><?= e(__('logs.nivel')) ?></th>
                <th style="width: 100px;"><?= e(__('logs.empresa_codigo')) ?></th>
                <th style="width: 100px;"><?= e(__('logs.usuario_codigo')) ?></th>
                <th><?= e(__('logs.usuario_nome')) ?></th>
                <th style="width: 90px;"><?= e(__('logs.modulo')) ?></th>
                <th style="width: 90px;"><?= e(__('logs.acao')) ?></th>
                <th><?= e(__('logs.descricao')) ?></th>
                <th style="width: 120px;"><?= e(__('logs.ip')) ?></th>
                <th style="width: 50px;"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($logs)): ?>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td class="text-nowrap small"><?= e(date('d/m/Y H:i:s', strtotime($log['criado_em']))) ?></td>
                    <td>
                        <?php
                        $nivelBadge = [
                            'info'     => 'bg-info text-dark',
                            'warning'  => 'bg-warning text-dark',
                            'error'    => 'bg-danger',
                            'critical' => 'bg-dark',
                        ];
                        $badgeClass = $nivelBadge[$log['nivel']] ?? 'bg-secondary';
                        ?>
                        <span class="badge <?= $badgeClass ?>"><?= e(ucfirst($log['nivel'])) ?></span>
                    </td>
                    <td><span class="badge bg-light text-dark font-monospace"><?= e($log['empresa_codigo'] ?? '—') ?></span></td>
                    <td><span class="badge bg-light text-dark font-monospace"><?= e($log['usuario_codigo'] ?? '—') ?></span></td>
                    <td class="small"><?= e($log['usuario_nome'] ?? '—') ?></td>
                    <td><span class="badge bg-primary bg-opacity-75"><?= e(ucfirst($log['modulo'])) ?></span></td>
                    <td><span class="badge bg-secondary"><?= e(ucfirst($log['acao'])) ?></span></td>
                    <td class="small text-truncate" style="max-width: 300px;" title="<?= e($log['descricao']) ?>">
                        <?= e($log['descricao']) ?>
                    </td>
                    <td class="small text-muted"><?= e($log['ip'] ?? '') ?></td>
                    <td>
                        <?php if (!empty($log['dados_anteriores']) || !empty($log['dados_novos'])): ?>
                        <button type="button" class="btn btn-sm btn-outline-info btn-action"
                                data-bs-toggle="modal" data-bs-target="#modalDetalheLog"
                                data-anterior="<?= e($log['dados_anteriores'] ?? '{}') ?>"
                                data-novo="<?= e($log['dados_novos'] ?? '{}') ?>"
                                data-descricao="<?= e($log['descricao']) ?>"
                                data-url-log="<?= e($log['url'] ?? '') ?>"
                                data-metodo="<?= e($log['metodo_http'] ?? '') ?>"
                                data-useragent="<?= e($log['user_agent'] ?? '') ?>"
                                title="<?= e(__('logs.detalhes')) ?>">
                            <i class="bi bi-eye"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="10" class="text-center text-muted py-4">
                        <i class="bi bi-inbox me-1"></i><?= e(__('common.nenhum_registro')) ?>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Paginação -->
<?php if ($totalPaginas > 1): ?>
<nav>
    <ul class="pagination pagination-sm justify-content-end">
        <li class="page-item <?= $paginaAtual <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= url('logs', 'index', array_merge($filtros, ['pg' => $paginaAtual - 1])) ?>">
                <?= e(__('common.anterior')) ?>
            </a>
        </li>
        <?php
        $inicio = max(1, $paginaAtual - 2);
        $fim = min($totalPaginas, $paginaAtual + 2);
        for ($pg = $inicio; $pg <= $fim; $pg++): ?>
        <li class="page-item <?= $pg === $paginaAtual ? 'active' : '' ?>">
            <a class="page-link" href="<?= url('logs', 'index', array_merge($filtros, ['pg' => $pg])) ?>">
                <?= $pg ?>
            </a>
        </li>
        <?php endfor; ?>
        <li class="page-item <?= $paginaAtual >= $totalPaginas ? 'disabled' : '' ?>">
            <a class="page-link" href="<?= url('logs', 'index', array_merge($filtros, ['pg' => $paginaAtual + 1])) ?>">
                <?= e(__('common.proximo')) ?>
            </a>
        </li>
    </ul>
</nav>
<?php endif; ?>

<!-- Modal de Detalhes -->
<div class="modal fade" id="modalDetalheLog" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-journal-text me-2"></i><?= e(__('logs.detalhes')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="fw-bold mb-1" id="logDetalheDescricao"></p>
                <div class="row mb-2">
                    <div class="col-md-4"><small class="text-muted"><?= e(__('logs.metodo_url')) ?>:</small> <span id="logDetalheUrl" class="small"></span></div>
                    <div class="col-md-8"><small class="text-muted">User Agent:</small> <span id="logDetalheUA" class="small text-muted"></span></div>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-danger"><i class="bi bi-arrow-left me-1"></i><?= e(__('logs.dados_anteriores')) ?></h6>
                        <pre id="logAnterior" class="bg-light p-2 rounded small" style="max-height: 300px; overflow: auto;"></pre>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-success"><i class="bi bi-arrow-right me-1"></i><?= e(__('logs.dados_novos')) ?></h6>
                        <pre id="logNovo" class="bg-light p-2 rounded small" style="max-height: 300px; overflow: auto;"></pre>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal"><?= e(__('common.cancelar')) ?></button>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= e(CSP_SCRIPT_NONCE) ?>">
// Popula modal de detalhes do log
document.getElementById('modalDetalheLog')?.addEventListener('show.bs.modal', function(event) {
    var btn = event.relatedTarget;
    var anterior = btn.getAttribute('data-anterior') || '{}';
    var novo = btn.getAttribute('data-novo') || '{}';

    document.getElementById('logDetalheDescricao').textContent = btn.getAttribute('data-descricao') || '';
    document.getElementById('logDetalheUrl').textContent = (btn.getAttribute('data-metodo') || '') + ' ' + (btn.getAttribute('data-url-log') || '');
    document.getElementById('logDetalheUA').textContent = btn.getAttribute('data-useragent') || '';

    try { anterior = JSON.stringify(JSON.parse(anterior), null, 2); } catch(e) {}
    try { novo = JSON.stringify(JSON.parse(novo), null, 2); } catch(e) {}

    document.getElementById('logAnterior').textContent = anterior;
    document.getElementById('logNovo').textContent = novo;
});
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
