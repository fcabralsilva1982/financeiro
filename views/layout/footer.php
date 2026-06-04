    </div><!-- /.container-fluid -->
</div><!-- /#page-content-wrapper -->
</div><!-- /#wrapper -->

<!-- Modal de Confirmação de Exclusão -->
<div class="modal fade" id="modalExcluir" tabindex="-1" aria-labelledby="modalExcluirLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="modalExcluirLabel"><i class="bi bi-exclamation-triangle me-2"></i><?= e(__('common.confirmar_exclusao')) ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="<?= e(__('common.cancelar')) ?>"></button>
            </div>
            <div class="modal-body">
                <p><?= e(__('common.confirmar_exclusao_msg')) ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= e(__('common.nao')) ?></button>
                <a href="#" id="btnConfirmarExcluir" class="btn btn-danger" role="button"><?= e(__('common.sim')) ?>, <?= e(__('common.excluir')) ?></a>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Confirmação de Bloqueio -->
<div class="modal fade" id="modalBloquear" tabindex="-1" aria-labelledby="modalBloquearLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title" id="modalBloquearLabel"><i class="bi bi-lock me-2"></i><span id="modalBloquearTitle"><?= e(__('common.confirmar_bloqueio')) ?></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?= e(__('common.cancelar')) ?>"></button>
            </div>
            <div class="modal-body">
                <p id="modalBloquearMsg"><?= e(__('common.confirmar_bloqueio_msg')) ?></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= e(__('common.nao')) ?></button>
                <a href="#" id="btnConfirmarBloquear" class="btn btn-warning" role="button"><?= e(__('common.sim')) ?></a>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5.3 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
