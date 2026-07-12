<?php
/**
 * View: Formulário de Fornecedor (criar/editar)
 * Variáveis esperadas: $fornecedor (array|null), $isEdit (bool), $errors (array)
 */
$errors = $errors ?? [];
$fornecedor = $fornecedor ?? [];
$isEdit = $isEdit ?? false;
$documentosFornecedor = $documentosFornecedor ?? [];
$pageTitle = $isEdit ? __('fornecedores.edit') : __('fornecedores.new');
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<div id="sidebarOverlay" class="sidebar-overlay"></div>

<!-- Page Header -->
<div class="page-header">
    <h2>
        <i class="bi bi-truck me-2"></i><?= e($pageTitle) ?>
        <?php if ($isEdit && !empty($fornecedor['codigo'])): ?>
            <small class="text-muted ms-2"><span class="badge bg-secondary font-monospace"><?= e($fornecedor['codigo']) ?></span></small>
        <?php endif; ?>
    </h2>
    <a href="<?= url('fornecedores') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i><?= e(__('common.voltar')) ?>
    </a>
</div>

<!-- Erros de validação -->
<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle me-2"></i><strong><?= e(__('fornecedores.erro_validacao')) ?></strong>
    <ul class="mb-0 mt-1">
        <?php foreach ($errors as $msg): ?>
            <li><?= e($msg) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form action="<?= url('fornecedores', 'store') ?>" method="POST">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= e($fornecedor['id'] ?? '') ?>">
    <?php endif; ?>

    <!-- Dados Principais -->
    <div class="form-section">
        <div class="form-section-title"><i class="bi bi-person me-1"></i><?= e(__('fornecedores.dados_principais')) ?></div>
        <div class="row g-3">
            <div class="col-md-3">
                <label for="tipo_pessoa" class="form-label"><?= e(__('fornecedores.tipo_pessoa')) ?> <span class="text-danger">*</span></label>
                <select class="form-select" id="tipo_pessoa" name="tipo_pessoa" required>
                    <option value="PJ" <?= ($fornecedor['tipo_pessoa'] ?? 'PJ') === 'PJ' ? 'selected' : '' ?>><?= e(__('fornecedores.pj')) ?></option>
                    <option value="PF" <?= ($fornecedor['tipo_pessoa'] ?? '') === 'PF' ? 'selected' : '' ?>><?= e(__('fornecedores.pf')) ?></option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="cpf_cnpj" class="form-label"><?= e(__('fornecedores.cpf_cnpj')) ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control <?= isset($errors['cpf_cnpj']) ? 'is-invalid' : '' ?>" id="cpf_cnpj" name="cpf_cnpj"
                       value="<?= e($fornecedor['cpf_cnpj'] ?? '') ?>" required maxlength="18">
                <?php if (isset($errors['cpf_cnpj'])): ?><div class="invalid-feedback"><?= e($errors['cpf_cnpj']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-5">
                <label for="razao_social" class="form-label"><?= e(__('fornecedores.razao_social')) ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control <?= isset($errors['razao_social']) ? 'is-invalid' : '' ?>" id="razao_social" name="razao_social"
                       value="<?= e($fornecedor['razao_social'] ?? '') ?>" required>
                <?php if (isset($errors['razao_social'])): ?><div class="invalid-feedback"><?= e($errors['razao_social']) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-4" id="nome_fantasia_group">
                <label for="nome_fantasia" class="form-label"><?= e(__('fornecedores.nome_fantasia')) ?></label>
                <input type="text" class="form-control" id="nome_fantasia" name="nome_fantasia"
                       value="<?= e($fornecedor['nome_fantasia'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label for="email" class="form-label"><?= e(__('fornecedores.email')) ?></label>
                <input type="email" class="form-control" id="email" name="email"
                       value="<?= e($fornecedor['email'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label for="telefone" class="form-label"><?= e(__('fornecedores.telefone')) ?></label>
                <input type="text" class="form-control" id="telefone" name="telefone"
                       value="<?= e($fornecedor['telefone'] ?? '') ?>"
                       placeholder="+55 11 9090-9090" maxlength="17">
            </div>
            <div class="col-md-2">
                <label for="celular" class="form-label"><?= e(__('fornecedores.celular')) ?></label>
                <input type="text" class="form-control" id="celular" name="celular"
                       value="<?= e($fornecedor['celular'] ?? '') ?>"
                       placeholder="+55 11 99999-9999" maxlength="18">
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-4">
                <label for="categoria" class="form-label"><?= e(__('fornecedores.categoria')) ?></label>
                <input type="text" class="form-control" id="categoria" name="categoria"
                       value="<?= e($fornecedor['categoria'] ?? '') ?>"
                       placeholder="<?= e(__('fornecedores.categoria_hint')) ?>">
            </div>
            <?php if ($isEdit): ?>
            <div class="col-md-3">
                <label for="status" class="form-label"><?= e(__('fornecedores.status')) ?></label>
                <select class="form-select" id="status" name="status">
                    <option value="ativo" <?= ($fornecedor['status'] ?? '') === 'ativo' ? 'selected' : '' ?>><?= e(__('common.ativo')) ?></option>
                    <option value="bloqueado" <?= ($fornecedor['status'] ?? '') === 'bloqueado' ? 'selected' : '' ?>><?= e(__('common.bloqueado')) ?></option>
                </select>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Endereço -->
    <div class="form-section">
        <div class="form-section-title"><i class="bi bi-geo-alt me-1"></i><?= e(__('fornecedores.endereco')) ?></div>
        <div class="row g-3">
            <div class="col-md-2">
                <label for="cep" class="form-label"><?= e(__('fornecedores.cep')) ?></label>
                <input type="text" class="form-control" id="cep" name="cep"
                       value="<?= e($fornecedor['cep'] ?? '') ?>"
                       placeholder="00000-000" maxlength="9">
            </div>
            <div class="col-md-6">
                <label for="logradouro" class="form-label"><?= e(__('fornecedores.logradouro')) ?></label>
                <input type="text" class="form-control" id="logradouro" name="logradouro"
                       value="<?= e($fornecedor['logradouro'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label for="numero" class="form-label"><?= e(__('fornecedores.numero')) ?></label>
                <input type="text" class="form-control" id="numero" name="numero"
                       value="<?= e($fornecedor['numero'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label for="complemento" class="form-label"><?= e(__('fornecedores.complemento')) ?></label>
                <input type="text" class="form-control" id="complemento" name="complemento"
                       value="<?= e($fornecedor['complemento'] ?? '') ?>">
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-4">
                <label for="bairro" class="form-label"><?= e(__('fornecedores.bairro')) ?></label>
                <input type="text" class="form-control" id="bairro" name="bairro"
                       value="<?= e($fornecedor['bairro'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label for="cidade" class="form-label"><?= e(__('fornecedores.cidade')) ?></label>
                <input type="text" class="form-control" id="cidade" name="cidade"
                       value="<?= e($fornecedor['cidade'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label for="uf" class="form-label"><?= e(__('fornecedores.uf')) ?></label>
                <select class="form-select" id="uf" name="uf">
                    <option value=""><?= e(__('common.selecione')) ?></option>
                    <?php
                    $ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
                    foreach ($ufs as $uf):
                    ?>
                    <option value="<?= $uf ?>" <?= ($fornecedor['uf'] ?? '') === $uf ? 'selected' : '' ?>><?= $uf ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Dados Bancários -->
    <div class="form-section">
        <div class="form-section-title"><i class="bi bi-bank me-1"></i><?= e(__('fornecedores.dados_bancarios')) ?></div>
        <div class="row g-3">
            <div class="col-md-4">
                <label for="banco" class="form-label"><?= e(__('fornecedores.banco')) ?></label>
                <input type="text" class="form-control" id="banco" name="banco"
                       value="<?= e($fornecedor['banco'] ?? '') ?>"
                       placeholder="Banco do Brasil, Itaú, Bradesco...">
            </div>
            <div class="col-md-2">
                <label for="agencia" class="form-label"><?= e(__('fornecedores.agencia')) ?></label>
                <input type="text" class="form-control" id="agencia" name="agencia"
                       value="<?= e($fornecedor['agencia'] ?? '') ?>"
                       placeholder="0001" maxlength="20">
            </div>
            <div class="col-md-3">
                <label for="conta" class="form-label"><?= e(__('fornecedores.conta')) ?></label>
                <input type="text" class="form-control" id="conta" name="conta"
                       value="<?= e($fornecedor['conta'] ?? '') ?>"
                       placeholder="12345-6" maxlength="30">
            </div>
            <div class="col-md-3">
                <label for="chave_pix" class="form-label"><?= e(__('fornecedores.chave_pix')) ?></label>
                <input type="text" class="form-control" id="chave_pix" name="chave_pix"
                       value="<?= e($fornecedor['chave_pix'] ?? '') ?>">
                <div class="form-text"><?= e(__('fornecedores.chave_pix_hint')) ?></div>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-12">
                <label for="dados_bancarios_adicionais" class="form-label"><?= e(__('fornecedores.dados_bancarios_adicionais')) ?></label>
                <textarea class="form-control" id="dados_bancarios_adicionais" name="dados_bancarios_adicionais" rows="2"
                          placeholder="<?= e(__('fornecedores.dados_bancarios_adicionais_hint')) ?>"><?= e($fornecedor['dados_bancarios_adicionais'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <!-- Observações -->
    <div class="form-section">
        <div class="form-section-title"><i class="bi bi-chat-left-text me-1"></i><?= e(__('fornecedores.observacoes')) ?></div>
        <div class="row g-3">
            <div class="col-12">
                <textarea class="form-control" id="observacoes" name="observacoes" rows="3"><?= e($fornecedor['observacoes'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <!-- Botões -->
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i><?= e(__('common.salvar')) ?>
        </button>
        <a href="<?= url('fornecedores') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-x-lg me-1"></i><?= e(__('common.cancelar')) ?>
        </a>
    </div>
</form>

<!-- Documentos -->
<div class="form-section mt-3">
    <div class="form-section-title"><i class="bi bi-file-earmark-arrow-up me-1"></i>Documentos</div>

    <?php if ($isEdit && !empty($fornecedor['id'])): ?>
        <form action="<?= url('fornecedores', 'upload_documento') ?>" method="POST" enctype="multipart/form-data" class="mb-3">
            <?= csrf_field() ?>
            <input type="hidden" name="fornecedor_id" value="<?= e((string) $fornecedor['id']) ?>">

            <div class="row g-3 document-upload-row">
                <div class="col-md-3">
                    <label for="tipo_documento" class="form-label">Tipo de Documento</label>
                    <select class="form-select" id="tipo_documento" name="tipo_documento">
                        <option value="rg">RG</option>
                        <option value="cpf">CPF</option>
                        <option value="cnh">CNH</option>
                        <option value="comprovante_residencia">Comprovante de Residencia</option>
                        <option value="cartao_cnpj">Cartao CNPJ</option>
                        <option value="contrato_social">Contrato Social</option>
                        <option value="inscricao_estadual">Inscricao Estadual</option>
                        <option value="procuracao">Procuracao</option>
                        <option value="foto_frente">Foto Frente</option>
                        <option value="foto_verso">Foto Verso</option>
                        <option value="outro">Outro</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="documento" class="form-label">Arquivo</label>
                    <input type="file" class="form-control document-upload-input" id="documento" name="documento" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                    <div class="form-text">Envie PDF ou imagem para documentos cadastrais e fiscais do fornecedor.</div>
                </div>
                <div class="col-md-3 document-upload-action">
                    <button type="submit" class="btn btn-outline-primary document-upload-button">
                        <i class="bi bi-upload me-1"></i>Enviar Documento
                    </button>
                </div>
            </div>
        </form>

        <div class="table-responsive contas-pagar-lista-borda rounded">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Tipo</th>
                        <th>Arquivo</th>
                        <th>Tamanho</th>
                        <th>Enviado em</th>
                        <th style="width: 180px;">Acoes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($documentosFornecedor)): ?>
                        <?php foreach ($documentosFornecedor as $documento): ?>
                            <tr>
                                <td><?= e(strtoupper(str_replace('_', ' ', (string) $documento['tipo_documento']))) ?></td>
                                <td><?= e((string) $documento['nome_original']) ?></td>
                                <td><?= e(number_format(((int) $documento['tamanho_bytes']) / 1024, 1, ',', '.')) ?> KB</td>
                                <td><?= e(date('d/m/Y H:i', strtotime((string) $documento['criado_em']))) ?></td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="<?= url('fornecedores', 'download_documento', ['documento_id' => $documento['id']]) ?>" class="btn btn-sm btn-outline-dark btn-action" title="Baixar"><i class="bi bi-download"></i></a>
                                        <form action="<?= url('fornecedores', 'delete_documento') ?>" method="POST" onsubmit="return confirm('Excluir este documento?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="documento_id" value="<?= e((string) $documento['id']) ?>">
                                            <input type="hidden" name="fornecedor_id" value="<?= e((string) $fornecedor['id']) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger btn-action" title="Excluir"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-3">Nenhum documento enviado para este fornecedor.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-secondary mb-0">
            Salve o fornecedor primeiro para habilitar uploads de documentos.
        </div>
    <?php endif; ?>
</div>

<!-- Script: toggle campos PF/PJ e busca CEP -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const tipoPessoa = document.getElementById('tipo_pessoa');
    const cpfCnpjInput = document.getElementById('cpf_cnpj');
    const nomeFantasiaGroup = document.getElementById('nome_fantasia_group');

    function toggleTipoPessoa() {
        if (!tipoPessoa) return;
        const isPJ = tipoPessoa.value === 'PJ';
        if (cpfCnpjInput) {
            cpfCnpjInput.placeholder = isPJ ? '00.000.000/0000-00' : '000.000.000-00';
            cpfCnpjInput.maxLength = isPJ ? 18 : 14;
        }
        if (nomeFantasiaGroup) {
            nomeFantasiaGroup.style.display = isPJ ? '' : 'none';
        }
    }

    if (tipoPessoa) {
        toggleTipoPessoa();
        tipoPessoa.addEventListener('change', toggleTipoPessoa);
    }

    // Busca CEP via ViaCEP
    const cepInput = document.getElementById('cep');
    if (cepInput) {
        cepInput.addEventListener('blur', function() {
            const cep = this.value.replace(/\D/g, '');
            if (cep.length !== 8) return;

            fetch('https://viacep.com.br/ws/' + encodeURIComponent(cep) + '/json/')
                .then(r => r.json())
                .then(data => {
                    if (data.erro) return;
                    const setVal = (id, val) => { const el = document.getElementById(id); if (el && val) el.value = val; };
                    setVal('logradouro', data.logradouro);
                    setVal('bairro', data.bairro);
                    setVal('cidade', data.localidade);
                    const ufSel = document.getElementById('uf');
                    if (ufSel && data.uf) ufSel.value = data.uf;
                })
                .catch(() => {});
        });
    }
});
</script>

<?php require __DIR__ . '/../layout/footer.php'; ?>
