<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/ContasPagar.php';

if (!is_logged_in()) {
    redirect(url('login'));
}

$empresaId = (string) current_empresa_id();
$usuarioId = (string) (current_user()['id'] ?? '');
$action = $_GET['action'] ?? 'index';

function validar_csrf_contas_pagar(): bool
{
    $enviado = (string) ($_POST['csrf_token'] ?? '');
    $sessao = (string) ($_SESSION['csrf_token'] ?? '');
    return $enviado !== '' && $sessao !== '' && hash_equals($sessao, $enviado);
}

function processar_upload_comprovante(): ?string
{
    if (upload_rate_limit_exceeded('comprovante')) {
        registrar_tentativa_upload('comprovante', 'bloqueado_rate_limit');
        throw new RuntimeException('Limite de tentativas de upload excedido. Tente novamente em alguns minutos.');
    }

    if (!isset($_FILES['comprovante']) || !is_array($_FILES['comprovante'])) {
        return null;
    }

    if ((int) ($_FILES['comprovante']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ((int) ($_FILES['comprovante']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Falha no upload do comprovante.');
    }

    $tamanho = (int) ($_FILES['comprovante']['size'] ?? 0);
    if ($tamanho <= 0 || $tamanho > UPLOAD_COMPROVANTE_MAX_BYTES) {
        throw new RuntimeException('Comprovante fora do limite permitido.');
    }

    $tmpName = (string) ($_FILES['comprovante']['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        registrar_tentativa_upload('comprovante', 'arquivo_invalido');
        throw new RuntimeException('Arquivo de upload invalido.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmpName);
    $permitidos = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($permitidos[$mime])) {
        registrar_tentativa_upload('comprovante', 'mime_nao_permitido');
        throw new RuntimeException('Formato de comprovante nao permitido. Use PDF ou imagem.');
    }

    $destinoDir = upload_storage_root() . '/comprovantes';
    if (!is_dir($destinoDir) && !mkdir($destinoDir, 0775, true) && !is_dir($destinoDir)) {
        throw new RuntimeException('Nao foi possivel preparar diretorio de comprovantes.');
    }

    $quarantineDir = str_replace('\\', '/', rtrim((string) UPLOAD_QUARANTINE_PATH, '/'));
    if (!is_dir($quarantineDir) && !mkdir($quarantineDir, 0775, true) && !is_dir($quarantineDir)) {
        throw new RuntimeException('Nao foi possivel preparar diretorio de quarentena.');
    }

    $arquivo = 'cp_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $permitidos[$mime];
    $destinoAbs = $destinoDir . '/' . $arquivo;
    $quarantineAbs = $quarantineDir . '/q_' . $arquivo;

    if (!move_uploaded_file($tmpName, $quarantineAbs)) {
        registrar_tentativa_upload('comprovante', 'falha_quarentena');
        throw new RuntimeException('Falha ao salvar comprovante no servidor.');
    }

    try {
        scan_uploaded_file_or_fail($quarantineAbs, 'comprovante');
    } catch (Throwable $e) {
        if (is_file($quarantineAbs)) {
            unlink($quarantineAbs);
        }
        registrar_tentativa_upload('comprovante', 'scan_reprovado');
        throw $e;
    }

    if (!rename($quarantineAbs, $destinoAbs)) {
        if (is_file($quarantineAbs)) {
            unlink($quarantineAbs);
        }
        registrar_tentativa_upload('comprovante', 'falha_movimentacao_final');
        throw new RuntimeException('Falha ao finalizar armazenamento do comprovante.');
    }

    registrar_tentativa_upload('comprovante', 'sucesso');

    return 'comprovantes/' . $arquivo;
}

switch ($action) {
    case 'create':
        $conta = null;
        $parcelas = [];
        $isEdit = false;
        $errors = [];
        $fornecedores = ContasPagar::listarFornecedoresAtivos($empresaId);
        $categorias = ContasPagar::listarCategoriasDespesa($empresaId);
        require __DIR__ . '/../views/contas_pagar/form.php';
        break;

    case 'edit':
        $id = (string) ($_GET['id'] ?? '');
        $conta = $id !== '' ? ContasPagar::buscar($id, $empresaId) : null;
        if (!$conta) {
            set_flash('danger', 'Conta a pagar nao encontrada.');
            redirect(url('contas_pagar'));
        }

        $parcelas = ContasPagar::listarParcelas($id, $empresaId);
        $isEdit = true;
        $errors = [];
        $fornecedores = ContasPagar::listarFornecedoresAtivos($empresaId);
        $categorias = ContasPagar::listarCategoriasDespesa($empresaId);
        require __DIR__ . '/../views/contas_pagar/form.php';
        break;

    case 'store':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(url('contas_pagar'));
        }
        if (!validar_csrf_contas_pagar()) {
            set_flash('danger', __('common.erro_csrf'));
            redirect(url('contas_pagar'));
        }

        $dados = $_POST;
        $isEdit = !empty($dados['id']);
        $errors = ContasPagar::validar($dados);

        if (!empty($errors)) {
            $conta = $dados;
            $parcelas = [];
            $fornecedores = ContasPagar::listarFornecedoresAtivos($empresaId);
            $categorias = ContasPagar::listarCategoriasDespesa($empresaId);
            require __DIR__ . '/../views/contas_pagar/form.php';
            break;
        }

        try {
            if ($isEdit) {
                $contaId = (string) $dados['id'];
                ContasPagar::atualizar($contaId, $dados, $empresaId, $usuarioId);
                log_info('contas_pagar', 'editar', 'Conta a pagar editada', $contaId);
                set_flash('success', 'Conta a pagar atualizada com sucesso.');
            } else {
                $contaId = ContasPagar::criar($dados, $empresaId, $usuarioId);
                log_info('contas_pagar', 'criar', 'Conta a pagar criada', $contaId);
                set_flash('success', 'Conta a pagar criada com sucesso.');
            }
            redirect(url('contas_pagar'));
        } catch (Throwable $e) {
            log_error('contas_pagar', 'store', 'Erro ao salvar conta a pagar: ' . $e->getMessage());
            set_flash('danger', $e->getMessage());
            redirect(url('contas_pagar'));
        }
        break;

    case 'enviar_aprovacao':
        if (!enforce_post_request()) {
            break;
        }
        if (!validar_csrf_contas_pagar()) {
            set_flash('danger', __('common.erro_csrf'));
            redirect(url('contas_pagar'));
        }

        $id = (string) ($_POST['id'] ?? '');
        if ($id === '') {
            set_flash('danger', 'Conta a pagar nao encontrada.');
            redirect(url('contas_pagar'));
        }

        try {
            ContasPagar::enviarParaAprovacao($id, $empresaId, $usuarioId);
            log_info('contas_pagar', 'enviar_aprovacao', 'Conta enviada para aprovacao', $id);
            set_flash('success', 'Conta enviada para aprovacao.');
        } catch (Throwable $e) {
            set_flash('danger', $e->getMessage());
        }
        redirect(url('contas_pagar'));
        break;

    case 'aprovar':
        if (!enforce_post_request()) {
            break;
        }
        if (!validar_csrf_contas_pagar()) {
            set_flash('danger', __('common.erro_csrf'));
            redirect(url('contas_pagar'));
        }

        $id = (string) ($_POST['id'] ?? '');
        if ($id === '') {
            set_flash('danger', 'Conta a pagar nao encontrada.');
            redirect(url('contas_pagar'));
        }

        if (!can_user('contas_pagar', 'aprovar')) {
            set_flash('danger', __('common.acesso_negado'));
            redirect(url('contas_pagar'));
        }

        try {
            ContasPagar::aprovar($id, $empresaId, $usuarioId);
            log_info('contas_pagar', 'aprovar', 'Conta aprovada', $id);
            set_flash('success', 'Conta aprovada com sucesso.');
        } catch (Throwable $e) {
            set_flash('danger', $e->getMessage());
        }
        redirect(url('contas_pagar'));
        break;

    case 'cancelar':
        if (!enforce_post_request()) {
            break;
        }
        if (!validar_csrf_contas_pagar()) {
            set_flash('danger', __('common.erro_csrf'));
            redirect(url('contas_pagar'));
        }

        $id = (string) ($_POST['id'] ?? '');
        if ($id === '') {
            set_flash('danger', 'Conta a pagar nao encontrada.');
            redirect(url('contas_pagar'));
        }

        try {
            ContasPagar::cancelar($id, $empresaId, $usuarioId);
            log_info('contas_pagar', 'cancelar', 'Conta cancelada', $id);
            set_flash('success', 'Conta cancelada com sucesso.');
        } catch (Throwable $e) {
            set_flash('danger', $e->getMessage());
        }
        redirect(url('contas_pagar'));
        break;

    case 'baixar_parcela':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validar_csrf_contas_pagar()) {
                set_flash('danger', __('common.erro_csrf'));
                redirect(url('contas_pagar'));
            }

            $parcelaId = (string) ($_POST['parcela_id'] ?? '');
            try {
                $comprovantePath = processar_upload_comprovante();
                $dadosBaixa = [
                    'data_pagamento' => (string) ($_POST['data_pagamento'] ?? ''),
                    'valor_pago' => (string) ($_POST['valor_pago'] ?? ''),
                    'forma_pagamento' => (string) ($_POST['forma_pagamento'] ?? ''),
                    'observacoes' => (string) ($_POST['observacoes'] ?? ''),
                    'comprovante_path' => $comprovantePath,
                ];
                ContasPagar::baixarParcela($parcelaId, $empresaId, $dadosBaixa, $usuarioId);
                log_info('contas_pagar', 'baixar_parcela', 'Parcela baixada', $parcelaId);
                set_flash('success', 'Parcela baixada com sucesso.');
                redirect(url('contas_pagar'));
            } catch (Throwable $e) {
                set_flash('danger', $e->getMessage());
                redirect(url('contas_pagar'));
            }
        }

        $parcelaId = (string) ($_GET['parcela_id'] ?? '');
        $parcela = $parcelaId !== '' ? ContasPagar::buscarParcela($parcelaId, $empresaId) : null;
        if (!$parcela) {
            set_flash('danger', 'Parcela nao encontrada.');
            redirect(url('contas_pagar'));
        }
        require __DIR__ . '/../views/contas_pagar/baixar.php';
        break;

    case 'download_comprovante':
        $parcelaId = (string) ($_GET['parcela_id'] ?? '');
        $parcela = $parcelaId !== '' ? ContasPagar::buscarParcelaParaDownload($parcelaId, $empresaId) : null;

        if (!$parcela || empty($parcela['comprovante_path'])) {
            http_response_code(404);
            echo 'Comprovante nao encontrado.';
            exit;
        }

        $pathRelativo = (string) $parcela['comprovante_path'];
        $pathNormalizado = normalize_comprovante_relative_path($pathRelativo);
        if ($pathNormalizado === null) {
            http_response_code(403);
            echo 'Acesso negado.';
            exit;
        }

        $arquivoAbs = realpath(upload_storage_root() . '/' . $pathNormalizado);
        $baseUploads = realpath(upload_storage_root() . '/comprovantes');

        // Compatibilidade com comprovantes legados em uploads/comprovantes
        if ($arquivoAbs === false || $baseUploads === false || !str_starts_with(str_replace('\\', '/', $arquivoAbs), str_replace('\\', '/', $baseUploads))) {
            $legacyRel = ltrim(str_replace('\\', '/', $pathRelativo), '/');
            $arquivoAbs = realpath(__DIR__ . '/../' . $legacyRel);
            $baseUploads = realpath(__DIR__ . '/../uploads/comprovantes');
        }

        if ($arquivoAbs === false || $baseUploads === false || !str_starts_with(str_replace('\\', '/', $arquivoAbs), str_replace('\\', '/', $baseUploads)) || !is_file($arquivoAbs)) {
            http_response_code(404);
            echo 'Arquivo nao encontrado.';
            exit;
        }

        $mime = (string) (mime_content_type($arquivoAbs) ?: 'application/octet-stream');
        $ext = pathinfo($arquivoAbs, PATHINFO_EXTENSION);
        $nomeDownload = 'comprovante_' . preg_replace('/[^a-zA-Z0-9\-_]/', '', (string) ($parcela['conta_codigo'] ?? 'cp')) . '.' . $ext;

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($arquivoAbs));
        header('Content-Disposition: attachment; filename="' . $nomeDownload . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($arquivoAbs);
        exit;

    case 'index':
    default:
        $filtros = [
            'busca' => trim((string) ($_GET['busca'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'fornecedor_id' => trim((string) ($_GET['fornecedor_id'] ?? '')),
            'categoria_id' => trim((string) ($_GET['categoria_id'] ?? '')),
            'periodo_inicio' => trim((string) ($_GET['periodo_inicio'] ?? '')),
            'periodo_fim' => trim((string) ($_GET['periodo_fim'] ?? '')),
        ];
        $contas = ContasPagar::listar($empresaId, $filtros);
        $fornecedores = ContasPagar::listarFornecedoresAtivos($empresaId);
        $categorias = ContasPagar::listarCategoriasDespesa($empresaId);
        require __DIR__ . '/../views/contas_pagar/index.php';
        break;
}
