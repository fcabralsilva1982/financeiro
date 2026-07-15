<?php
$pageTitle = 'Dashboard';
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<div id="sidebarOverlay" class="sidebar-overlay"></div>

<div class="page-header">
    <h2><i class="bi bi-speedometer2 me-2"></i><?= e(__('menu.dashboard')) ?></h2>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-2 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Recebimentos em Aberto</div>
                <div class="fs-4 fw-bold">R$ <?= number_format((float) $kpis['total_receber_aberto'], 2, ',', '.') ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Despesas em Aberto</div>
                <div class="fs-4 fw-bold">R$ <?= number_format((float) $kpis['total_pagar_aberto'], 2, ',', '.') ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Saldo Previsto <small class="text-muted">(em aberto)</small></div>
                <div class="fs-4 fw-bold <?= ((float) $kpis['saldo_previsto']) >= 0 ? 'text-success' : 'text-danger' ?>">
                    R$ <?= number_format((float) $kpis['saldo_previsto'], 2, ',', '.') ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-3 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Saldo Realizado <small class="text-muted">(caixa efetivo)</small></div>
                <div class="fs-4 fw-bold <?= ((float) $kpis['saldo_realizado']) >= 0 ? 'text-success' : 'text-danger' ?>">
                    R$ <?= number_format((float) $kpis['saldo_realizado'], 2, ',', '.') ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-2 col-sm-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Inadimplência</div>
                <div class="fs-4 fw-bold text-danger">R$ <?= number_format((float) $kpis['inadimplencia'], 2, ',', '.') ?></div>
            </div>
        </div>
    </div>
</div>

<!-- ===== FILTRO DE MÊS ===== -->
<div class="card border-0 shadow-sm mb-3">
    <div class="card-body py-2">
        <form method="get" action="" class="d-flex align-items-center gap-3 flex-wrap">
            <input type="hidden" name="page" value="dashboard">
            <label for="filtroMes" class="fw-semibold mb-0 text-nowrap">
                <i class="bi bi-calendar-month me-1"></i>Análise do mês:
            </label>
            <input type="month" id="filtroMes" name="mes"
                   value="<?= e($mesAnoRaw) ?>"
                   min="2020-01" max="<?= date('Y-m', strtotime('+12 months')) ?>"
                   class="form-control form-control-sm" style="width:180px">
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="bi bi-search me-1"></i>Filtrar
            </button>
            <?php if ($mesAnoRaw !== date('Y-m')): ?>
                <a href="<?= e(url('dashboard')) ?>" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-counterclockwise me-1"></i>Mês atual
                </a>
            <?php endif; ?>
            <span class="text-muted small ms-auto">
                Exibindo: <?= e(sprintf('%02d/%04d', $filtroMes, $filtroAno)) ?>
            </span>
        </form>
    </div>
</div>

<!-- ===== KPIs DO MÊS SELECIONADO ===== -->
<?php
$nomeMes = (new DateTimeImmutable(sprintf('%04d-%02d-01', $filtroAno, $filtroMes)))->format('F/Y');
$mesesPtBr = ['January'=>'Janeiro','February'=>'Fevereiro','March'=>'Março','April'=>'Abril',
               'May'=>'Maio','June'=>'Junho','July'=>'Julho','August'=>'Agosto',
               'September'=>'Setembro','October'=>'Outubro','November'=>'Novembro','December'=>'Dezembro'];
$nomeMesPtBr = strtr($nomeMes, $mesesPtBr);
?>
<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold">
        <i class="bi bi-bar-chart-line me-1"></i>Resultado de <?= e($nomeMesPtBr) ?>
    </div>
    <div class="card-body pb-2">
        <div class="row g-3">
            <div class="col-md-2 col-sm-6">
                <div class="text-muted small">Previsto Receber</div>
                <div class="fs-5 fw-bold text-success">
                    R$ <?= number_format((float) $kpisMensal['previsto_receber'], 2, ',', '.') ?>
                </div>
                <div class="text-muted" style="font-size:.75rem">por vencimento</div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="text-muted small">Previsto Pagar</div>
                <div class="fs-5 fw-bold text-danger">
                    R$ <?= number_format((float) $kpisMensal['previsto_pagar'], 2, ',', '.') ?>
                </div>
                <div class="text-muted" style="font-size:.75rem">por vencimento</div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="text-muted small">Resultado Previsto</div>
                <?php $rp = (float) $kpisMensal['resultado_previsto']; ?>
                <div class="fs-5 fw-bold <?= $rp >= 0 ? 'text-success' : 'text-danger' ?>">
                    R$ <?= number_format($rp, 2, ',', '.') ?>
                </div>
                <div class="text-muted" style="font-size:.75rem">receber − pagar</div>
            </div>
            <div class="col-md-2 col-sm-6 border-start ps-3">
                <div class="text-muted small">Efetivamente Recebido</div>
                <div class="fs-5 fw-bold text-success">
                    R$ <?= number_format((float) $kpisMensal['recebido_real'], 2, ',', '.') ?>
                </div>
                <div class="text-muted" style="font-size:.75rem">pela data de recebimento</div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="text-muted small">Efetivamente Pago</div>
                <div class="fs-5 fw-bold text-danger">
                    R$ <?= number_format((float) $kpisMensal['pago_no_mes'], 2, ',', '.') ?>
                </div>
                <div class="text-muted" style="font-size:.75rem">contas pagas no venc.</div>
            </div>
            <div class="col-md-2 col-sm-6">
                <div class="text-muted small">Resultado Realizado</div>
                <?php $rr = (float) $kpisMensal['resultado_realizado']; ?>
                <div class="fs-5 fw-bold <?= $rr >= 0 ? 'text-success' : 'text-danger' ?>">
                    R$ <?= number_format($rr, 2, ',', '.') ?>
                </div>
                <div class="text-muted" style="font-size:.75rem">recebido − pago</div>
            </div>
        </div>
    </div>
</div>

<div class="card border-0 shadow-sm mb-3">
    <div class="card-header bg-white fw-semibold">Fluxo de Caixa Previsto (últimos 6 meses)</div>
    <div class="card-body">
        <canvas id="fluxoChart" height="90"></canvas>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white fw-semibold">Contas vencendo nos próximos 7 dias</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Tipo</th>
                                <th>Código</th>
                                <th>Descrição</th>
                                <th>Contraparte</th>
                                <th>Venc.</th>
                                <th>Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($vencimentos)): ?>
                            <?php foreach ($vencimentos as $item): ?>
                                <tr>
                                    <td><?= e($item['tipo']) ?></td>
                                    <td><?= e($item['codigo']) ?></td>
                                    <td><?= e($item['descricao']) ?></td>
                                    <td><?= e($item['contraparte']) ?></td>
                                    <td><?= e(date_fmt($item['data_vencimento'])) ?></td>
                                    <td>R$ <?= number_format((float) $item['valor_total'], 2, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center text-muted py-3">Sem vencimentos no período.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white fw-semibold">Top 5 Clientes com Títulos em Aberto</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Qtd Contas</th>
                                <th>Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($topClientes)): ?>
                            <?php foreach ($topClientes as $item): ?>
                                <tr>
                                    <td><?= e($item['razao_social']) ?></td>
                                    <td><?= (int) $item['qtd_contas'] ?></td>
                                    <td>R$ <?= number_format((float) $item['valor_total'], 2, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">Sem dados.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white fw-semibold">Top 5 Fornecedores com Títulos em Aberto</div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Fornecedor</th>
                                <th>Qtd Contas</th>
                                <th>Valor</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (!empty($topFornecedores)): ?>
                            <?php foreach ($topFornecedores as $item): ?>
                                <tr>
                                    <td><?= e($item['razao_social']) ?></td>
                                    <td><?= (int) $item['qtd_contas'] ?></td>
                                    <td>R$ <?= number_format((float) $item['valor_total'], 2, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="text-center text-muted py-3">Sem dados.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?= e(CSP_SCRIPT_NONCE) ?>" src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js" integrity="sha384-JUh163oCRItcbPme8pYnROHQMC6fNKTBWtRG3I3I0erJkzNgL7uxKlNwcrcFKeqF" crossorigin="anonymous"></script>
<script nonce="<?= e(CSP_SCRIPT_NONCE) ?>">
(() => {
    const labels = <?= json_encode(array_column($fluxoCaixa, 'mes'), JSON_UNESCAPED_UNICODE) ?>;
    const entradas = <?= json_encode(array_map(static fn($r) => (float) $r['entradas'], $fluxoCaixa), JSON_UNESCAPED_UNICODE) ?>;
    const saidas = <?= json_encode(array_map(static fn($r) => (float) $r['saidas'], $fluxoCaixa), JSON_UNESCAPED_UNICODE) ?>;

    const ctx = document.getElementById('fluxoChart');
    if (!ctx) return;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels,
            datasets: [
                { label: 'Entradas', data: entradas, backgroundColor: 'rgba(25, 135, 84, 0.75)' },
                { label: 'Saídas', data: saidas, backgroundColor: 'rgba(220, 53, 69, 0.75)' }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'top' } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return 'R$ ' + Number(value).toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
                        }
                    }
                }
            }
        }
    });
})();
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
