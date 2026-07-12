<?php
/**
 * View: Formulário de Cliente (criar/editar)
 * Variáveis esperadas: $cliente (array|null), $isEdit (bool), $errors (array)
 */
$errors = $errors ?? [];
$cliente = $cliente ?? [];
$isEdit = $isEdit ?? false;
$documentosCliente = $documentosCliente ?? [];
$pageTitle = $isEdit ? __('clientes.edit') : __('clientes.new');
require __DIR__ . '/../layout/header.php';
require __DIR__ . '/../layout/sidebar.php';
?>

<div id="sidebarOverlay" class="sidebar-overlay"></div>

<!-- Page Header -->
<div class="page-header">
    <h2>
        <i class="bi bi-person-vcard me-2"></i><?= e($pageTitle) ?>
        <?php if ($isEdit && !empty($cliente['codigo'])): ?>
            <small class="text-muted ms-2"><span class="badge bg-secondary font-monospace"><?= e($cliente['codigo']) ?></span></small>
        <?php endif; ?>
    </h2>
    <a href="<?= url('clientes') ?>" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i><?= e(__('common.voltar')) ?>
    </a>
</div>

<!-- Erros de validação -->
<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <i class="bi bi-exclamation-triangle me-2"></i><strong><?= e(__('clientes.erro_validacao')) ?></strong>
    <ul class="mb-0 mt-1">
        <?php foreach ($errors as $msg): ?>
            <li><?= e($msg) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form action="<?= url('clientes', 'store') ?>" method="POST">
    <?= csrf_field() ?>
    <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= e($cliente['id'] ?? '') ?>">
    <?php endif; ?>

    <!-- Dados Principais -->
    <div class="form-section">
        <div class="form-section-title"><i class="bi bi-person me-1"></i><?= e(__('clientes.dados_principais')) ?></div>
        <div class="row g-3">
            <div class="col-md-3">
                <label for="tipo_pessoa" class="form-label"><?= e(__('clientes.tipo_pessoa')) ?> <span class="text-danger">*</span></label>
                <select class="form-select" id="tipo_pessoa" name="tipo_pessoa" required>
                    <option value="PJ" <?= ($cliente['tipo_pessoa'] ?? 'PJ') === 'PJ' ? 'selected' : '' ?>><?= e(__('clientes.pj')) ?></option>
                    <option value="PF" <?= ($cliente['tipo_pessoa'] ?? '') === 'PF' ? 'selected' : '' ?>><?= e(__('clientes.pf')) ?></option>
                </select>
            </div>
            <div class="col-md-4">
                <label for="cpf_cnpj" class="form-label"><?= e(__('clientes.cpf_cnpj')) ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control <?= isset($errors['cpf_cnpj']) ? 'is-invalid' : '' ?>" id="cpf_cnpj" name="cpf_cnpj"
                       value="<?= e($cliente['cpf_cnpj'] ?? '') ?>" required maxlength="18">
                <?php if (isset($errors['cpf_cnpj'])): ?><div class="invalid-feedback"><?= e($errors['cpf_cnpj']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-5">
                <label for="razao_social" class="form-label"><?= e(__('clientes.razao_social')) ?> <span class="text-danger">*</span></label>
                <input type="text" class="form-control <?= isset($errors['razao_social']) ? 'is-invalid' : '' ?>" id="razao_social" name="razao_social"
                       value="<?= e($cliente['razao_social'] ?? '') ?>" required>
                <?php if (isset($errors['razao_social'])): ?><div class="invalid-feedback"><?= e($errors['razao_social']) ?></div><?php endif; ?>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-md-4" id="nome_fantasia_group">
                <label for="nome_fantasia" class="form-label"><?= e(__('clientes.nome_fantasia')) ?></label>
                <input type="text" class="form-control" id="nome_fantasia" name="nome_fantasia"
                       value="<?= e($cliente['nome_fantasia'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label for="email" class="form-label"><?= e(__('clientes.email')) ?> <span class="text-danger">*</span></label>
                <input type="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" id="email" name="email"
                       value="<?= e($cliente['email'] ?? '') ?>" required>
                <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= e($errors['email']) ?></div><?php endif; ?>
            </div>
            <div class="col-md-2">
                <label for="telefone" class="form-label"><?= e(__('clientes.telefone')) ?></label>
                <input type="text" class="form-control" id="telefone" name="telefone"
                       value="<?= e($cliente['telefone'] ?? '') ?>"
                       placeholder="+55 11 9090-9090" maxlength="17">
            </div>
            <div class="col-md-2">
                <label for="celular" class="form-label"><?= e(__('clientes.celular')) ?></label>
                <input type="text" class="form-control" id="celular" name="celular"
                       value="<?= e($cliente['celular'] ?? '') ?>"
                       placeholder="+55 11 99999-9999" maxlength="18">
            </div>
        </div>

        <?php if ($isEdit): ?>
        <div class="row g-3 mt-1">
            <div class="col-md-3">
                <label for="status" class="form-label"><?= e(__('clientes.status')) ?></label>
                <select class="form-select" id="status" name="status">
                    <option value="ativo" <?= ($cliente['status'] ?? '') === 'ativo' ? 'selected' : '' ?>><?= e(__('common.ativo')) ?></option>
                    <option value="bloqueado" <?= ($cliente['status'] ?? '') === 'bloqueado' ? 'selected' : '' ?>><?= e(__('common.bloqueado')) ?></option>
                </select>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Endereço -->
    <div class="form-section">
        <div class="form-section-title"><i class="bi bi-geo-alt me-1"></i><?= e(__('clientes.endereco')) ?></div>
        <div class="row g-3">
            <div class="col-md-2">
                <label for="cep" class="form-label"><?= e(__('clientes.cep')) ?></label>
                <input type="text" class="form-control" id="cep" name="cep"
                       value="<?= e($cliente['cep'] ?? '') ?>"
                       placeholder="00000-000" maxlength="9">
            </div>
            <div class="col-md-6">
                <label for="logradouro" class="form-label"><?= e(__('clientes.logradouro')) ?></label>
                <input type="text" class="form-control" id="logradouro" name="logradouro"
                       value="<?= e($cliente['logradouro'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label for="numero" class="form-label"><?= e(__('clientes.numero')) ?></label>
                <input type="text" class="form-control" id="numero" name="numero"
                       value="<?= e($cliente['numero'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label for="complemento" class="form-label"><?= e(__('clientes.complemento')) ?></label>
                <input type="text" class="form-control" id="complemento" name="complemento"
                       value="<?= e($cliente['complemento'] ?? '') ?>">
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-4">
                <label for="bairro" class="form-label"><?= e(__('clientes.bairro')) ?></label>
                <input type="text" class="form-control" id="bairro" name="bairro"
                       value="<?= e($cliente['bairro'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label for="cidade" class="form-label"><?= e(__('clientes.cidade')) ?></label>
                <input type="text" class="form-control" id="cidade" name="cidade"
                       value="<?= e($cliente['cidade'] ?? '') ?>">
            </div>
            <div class="col-md-4">
                <label for="uf" class="form-label"><?= e(__('clientes.uf')) ?></label>
                <select class="form-select" id="uf" name="uf">
                    <option value=""><?= e(__('common.selecione')) ?></option>
                    <?php
                    $ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
                    foreach ($ufs as $uf):
                    ?>
                    <option value="<?= $uf ?>" <?= ($cliente['uf'] ?? '') === $uf ? 'selected' : '' ?>><?= $uf ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- Dados de Pagamento -->
    <div class="form-section">
        <div class="form-section-title"><i class="bi bi-credit-card me-1"></i><?= e(__('clientes.dados_pagamento')) ?></div>
        <div class="row g-3">
            <div class="col-md-3">
                <label for="prazo_pagamento" class="form-label"><?= e(__('clientes.prazo_pagamento')) ?></label>
                <div class="input-group">
                    <input type="number" class="form-control" id="prazo_pagamento" name="prazo_pagamento"
                           value="<?= e($cliente['prazo_pagamento'] ?? '') ?>"
                           min="0" max="365" placeholder="30">
                    <span class="input-group-text">dias</span>
                </div>
                <div class="form-text"><?= e(__('clientes.prazo_pagamento_hint')) ?></div>
            </div>
            <div class="col-md-3">
                <label for="forma_pagamento" class="form-label"><?= e(__('clientes.forma_pagamento')) ?></label>
                <select class="form-select" id="forma_pagamento" name="forma_pagamento">
                    <option value=""><?= e(__('common.selecione')) ?></option>
                    <option value="boleto" <?= ($cliente['forma_pagamento'] ?? '') === 'boleto' ? 'selected' : '' ?>><?= e(__('clientes.boleto')) ?></option>
                    <option value="pix" <?= ($cliente['forma_pagamento'] ?? '') === 'pix' ? 'selected' : '' ?>><?= e(__('clientes.pix')) ?></option>
                    <option value="cartao" <?= ($cliente['forma_pagamento'] ?? '') === 'cartao' ? 'selected' : '' ?>><?= e(__('clientes.cartao')) ?></option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="limite_credito" class="form-label"><?= e(__('clientes.limite_credito')) ?></label>
                <div class="input-group">
                    <span class="input-group-text">R$</span>
                    <input type="number" class="form-control" id="limite_credito" name="limite_credito"
                           value="<?= e($cliente['limite_credito'] ?? '') ?>"
                           min="0" step="0.01" placeholder="0,00">
                </div>
                <div class="form-text"><?= e(__('clientes.limite_credito_hint')) ?></div>
            </div>
        </div>
    </div>

    <!-- Configurações de Cobrança -->
    <div class="form-section">
        <div class="form-section-title"><i class="bi bi-bell me-1"></i><?= e(__('clientes.config_cobranca')) ?></div>
        <div class="row g-3">
            <div class="col-md-6">
                <label for="email_fatura" class="form-label"><?= e(__('clientes.email_fatura')) ?></label>
                <input type="text" class="form-control" id="email_fatura" name="email_fatura"
                       value="<?= e($cliente['email_fatura'] ?? '') ?>"
                       placeholder="faturamento@empresa.com, financeiro@empresa.com">
                <div class="form-text"><?= e(__('clientes.email_fatura_hint')) ?></div>
            </div>
            <div class="col-md-3">
                <label for="preferencia_canal" class="form-label"><?= e(__('clientes.preferencia_canal')) ?></label>
                <select class="form-select" id="preferencia_canal" name="preferencia_canal">
                    <option value="email" <?= ($cliente['preferencia_canal'] ?? 'email') === 'email' ? 'selected' : '' ?>><?= e(__('clientes.canal_email')) ?></option>
                    <option value="whatsapp" <?= ($cliente['preferencia_canal'] ?? '') === 'whatsapp' ? 'selected' : '' ?>><?= e(__('clientes.canal_whatsapp')) ?></option>
                    <option value="ambos" <?= ($cliente['preferencia_canal'] ?? '') === 'ambos' ? 'selected' : '' ?>><?= e(__('clientes.canal_ambos')) ?></option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="opt_out_lembretes" name="opt_out_lembretes" value="1"
                           <?= !empty($cliente['opt_out_lembretes']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="opt_out_lembretes"><?= e(__('clientes.opt_out_lembretes')) ?></label>
                </div>
            </div>
        </div>
        <div class="row g-3 mt-1">
            <div class="col-md-3">
                <label for="categoria" class="form-label"><?= e(__('clientes.categoria')) ?></label>
                <select class="form-select" id="categoria" name="categoria">
                    <option value=""><?= e(__('common.selecione')) ?></option>
                    <option value="recorrente" <?= ($cliente['categoria'] ?? '') === 'recorrente' ? 'selected' : '' ?>><?= e(__('clientes.cat_recorrente')) ?></option>
                    <option value="avulso" <?= ($cliente['categoria'] ?? '') === 'avulso' ? 'selected' : '' ?>><?= e(__('clientes.cat_avulso')) ?></option>
                    <option value="parceiro" <?= ($cliente['categoria'] ?? '') === 'parceiro' ? 'selected' : '' ?>><?= e(__('clientes.cat_parceiro')) ?></option>
                </select>
            </div>
            <div class="col-md-9">
                <label for="tags" class="form-label"><?= e(__('clientes.tags')) ?></label>
                <input type="text" class="form-control" id="tags" name="tags"
                       value="<?= e($cliente['tags'] ?? '') ?>"
                       placeholder="vip, regiao-sul, industria">
                <div class="form-text"><?= e(__('clientes.tags_hint')) ?></div>
            </div>
        </div>
    </div>

    <!-- Observações -->
    <div class="form-section">
        <div class="form-section-title"><i class="bi bi-chat-left-text me-1"></i><?= e(__('clientes.observacoes')) ?></div>
        <div class="row g-3">
            <div class="col-12">
                <textarea class="form-control" id="observacoes" name="observacoes" rows="3"><?= e($cliente['observacoes'] ?? '') ?></textarea>
            </div>
        </div>
    </div>

    <!-- Botões -->
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i><?= e(__('common.salvar')) ?>
        </button>
        <a href="<?= url('clientes') ?>" class="btn btn-outline-secondary">
            <i class="bi bi-x-lg me-1"></i><?= e(__('common.cancelar')) ?>
        </a>
    </div>
</form>

<!-- Documentos -->
<div class="form-section mt-3">
    <div class="form-section-title"><i class="bi bi-file-earmark-arrow-up me-1"></i>Documentos</div>

    <?php if ($isEdit && !empty($cliente['id'])): ?>
        <form action="<?= url('clientes', 'upload_documento') ?>" method="POST" enctype="multipart/form-data" class="mb-3">
            <?= csrf_field() ?>
            <input type="hidden" name="cliente_id" value="<?= e($cliente['id']) ?>">

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
                    <div class="form-text">Envie PDF ou imagem para RG, CPF, Cartao CNPJ e documentos relacionados.</div>
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
                    <?php if (!empty($documentosCliente)): ?>
                        <?php foreach ($documentosCliente as $documento): ?>
                            <tr>
                                <td><?= e(strtoupper(str_replace('_', ' ', (string) $documento['tipo_documento']))) ?></td>
                                <td><?= e((string) $documento['nome_original']) ?></td>
                                <td><?= e(number_format(((int) $documento['tamanho_bytes']) / 1024, 1, ',', '.')) ?> KB</td>
                                <td><?= e(date('d/m/Y H:i', strtotime((string) $documento['criado_em']))) ?></td>
                                <td>
                                    <div class="d-flex gap-1">
                                        <a href="<?= url('clientes', 'download_documento', ['documento_id' => $documento['id']]) ?>" class="btn btn-sm btn-outline-dark btn-action" title="Baixar"><i class="bi bi-download"></i></a>
                                        <form action="<?= url('clientes', 'delete_documento') ?>" method="POST" onsubmit="return confirm('Excluir este documento?')">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="documento_id" value="<?= e((string) $documento['id']) ?>">
                                            <input type="hidden" name="cliente_id" value="<?= e((string) $cliente['id']) ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger btn-action" title="Excluir"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted py-3">Nenhum documento enviado para este cliente.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="alert alert-secondary mb-0">
            Salve o cliente primeiro para habilitar uploads de documentos como RG, CPF e Cartao CNPJ.
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
