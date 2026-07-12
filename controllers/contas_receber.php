<?php
/**
 * Controller — Contas a Receber
 * CRUD multi-tenant para faturas/cobranças
 */
require_once __DIR__ . '/../models/ContasReceber.php';

// Permissão: apenas usuários logados
if (!is_logged_in()) {
    redirect(url('login'));
}

function validar_csrf_contas_receber(): bool
{
    $enviado = (string) ($_POST['csrf_token'] ?? '');
    $sessao = (string) ($_SESSION['csrf_token'] ?? '');
    return $enviado !== '' && $sessao !== '' && hash_equals($sessao, $enviado);
}

function processar_upload_comprovante_recebimento(): ?string
{
    if (upload_rate_limit_exceeded('comprovante_receber')) {
        registrar_tentativa_upload('comprovante_receber', 'bloqueado_rate_limit');
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
        registrar_tentativa_upload('comprovante_receber', 'arquivo_invalido');
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
        registrar_tentativa_upload('comprovante_receber', 'mime_nao_permitido');
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

    $arquivo = 'cr_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $permitidos[$mime];
    $destinoAbs = $destinoDir . '/' . $arquivo;
    $quarantineAbs = $quarantineDir . '/q_' . $arquivo;

    if (!move_uploaded_file($tmpName, $quarantineAbs)) {
        registrar_tentativa_upload('comprovante_receber', 'falha_quarentena');
        throw new RuntimeException('Falha ao salvar comprovante no servidor.');
    }

    try {
        scan_uploaded_file_or_fail($quarantineAbs, 'comprovante_receber');
    } catch (Throwable $e) {
        if (is_file($quarantineAbs)) {
            unlink($quarantineAbs);
        }
        registrar_tentativa_upload('comprovante_receber', 'scan_reprovado');
        throw $e;
    }

    if (!rename($quarantineAbs, $destinoAbs)) {
        if (is_file($quarantineAbs)) {
            unlink($quarantineAbs);
        }
        registrar_tentativa_upload('comprovante_receber', 'falha_movimentacao_final');
        throw new RuntimeException('Falha ao finalizar armazenamento do comprovante.');
    }

    registrar_tentativa_upload('comprovante_receber', 'sucesso');
    return 'comprovantes/' . $arquivo;
}

$empresaId = current_empresa_id();
$usuarioId = (string) (current_user()['id'] ?? '');
$action = $_GET['action'] ?? 'index';
$id = $_GET['id'] ?? null;

switch ($action) {
    case 'index':
        // Listagem de cobranças
        $filtros = [
            'empresa_id' => $empresaId,
            'search' => trim((string) ($_GET['search'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'cliente_id' => trim((string) ($_GET['cliente_id'] ?? '')),
            'periodo_inicio' => trim((string) ($_GET['periodo_inicio'] ?? '')),
            'periodo_fim' => trim((string) ($_GET['periodo_fim'] ?? '')),
        ];
        $contas = ContasReceber::listar($filtros);
        $clientes = get_clientes_empresa($empresaId);
        require __DIR__ . '/../views/contas_receber/index.php';
        break;

    case 'new':
        // Formulário de nova cobrança
        $clientes = get_clientes_empresa($empresaId);
        require __DIR__ . '/../views/contas_receber/form.php';
        break;

    case 'edit':
        // Formulário de edição
        $conta = ContasReceber::buscar($id, $empresaId);
        $clientes = get_clientes_empresa($empresaId);
        require __DIR__ . '/../views/contas_receber/form.php';
        break;

    case 'save':
        if (!enforce_post_request()) {
            break;
        }

        if (!validar_csrf_contas_receber()) {
            set_flash('danger', __('common.erro_csrf'));
            redirect(url('contas_receber'));
        }

        // Salvar nova ou editar cobrança
        $dados = $_POST;
        $dados['empresa_id'] = $empresaId;
        $erros = ContasReceber::validar($dados);
        if ($erros) {
            set_flash('danger', __('contas_receber.erro_validacao'));
            $clientes = get_clientes_empresa($empresaId);
            require __DIR__ . '/../views/contas_receber/form.php';
            break;
        }
        if (empty($dados['id'])) {
            ContasReceber::criar($dados);
            set_flash('success', __('contas_receber.sucesso_criada'));
        } else {
            ContasReceber::atualizar($dados['id'], $dados);
            set_flash('success', __('contas_receber.sucesso_editada'));
        }
        redirect(url('contas_receber'));
        break;

    case 'delete':
        if (!enforce_post_request()) {
            break;
        }

        if (!validar_csrf_contas_receber()) {
            set_flash('danger', __('common.erro_csrf'));
            redirect(url('contas_receber'));
        }

        $id = (string) ($_POST['id'] ?? '');
        if ($id === '') {
            set_flash('danger', __('contas_receber.erro_nao_encontrada'));
            redirect(url('contas_receber'));
        }

        // Excluir cobrança
        ContasReceber::excluir($id, $empresaId);
        set_flash('success', __('contas_receber.sucesso_excluida'));
        redirect(url('contas_receber'));
        break;

    case 'baixar':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validar_csrf_contas_receber()) {
                set_flash('danger', __('common.erro_csrf'));
                redirect(url('contas_receber'));
            }

            try {
                $dadosBaixa = $_POST;
                $dadosBaixa['comprovante_path'] = processar_upload_comprovante_recebimento();
                ContasReceber::baixar((string) ($_POST['id'] ?? ''), $empresaId, $dadosBaixa, $usuarioId);
                set_flash('success', __('contas_receber.sucesso_baixada'));
                redirect(url('contas_receber'));
            } catch (Throwable $e) {
                set_flash('danger', $e->getMessage());
                redirect(url('contas_receber', 'baixar', ['id' => (string) ($_POST['id'] ?? '')]));
            }
        }

        $conta = ContasReceber::buscar((string) $id, $empresaId);
        if (!$conta) {
            set_flash('danger', __('contas_receber.erro_nao_encontrada'));
            redirect(url('contas_receber'));
        }

        require __DIR__ . '/../views/contas_receber/baixar.php';
        break;

    case 'download_comprovante':
        $conta = ContasReceber::buscar((string) $id, $empresaId);
        if (!$conta || empty($conta['comprovante_path'])) {
            http_response_code(404);
            echo 'Comprovante nao encontrado.';
            exit;
        }

        $pathRelativo = (string) $conta['comprovante_path'];
        $pathNormalizado = normalize_comprovante_relative_path($pathRelativo);
        if ($pathNormalizado === null) {
            http_response_code(403);
            echo 'Acesso negado.';
            exit;
        }

        $arquivoAbs = realpath(upload_storage_root() . '/' . $pathNormalizado);
        $baseUploads = realpath(upload_storage_root() . '/comprovantes');

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
        $nomeDownload = 'recebimento_' . preg_replace('/[^a-zA-Z0-9\-_]/', '', (string) ($conta['codigo'] ?? 'cr')) . '.' . $ext;

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($arquivoAbs));
        header('Content-Disposition: attachment; filename="' . $nomeDownload . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($arquivoAbs);
        exit;

    default:
        http_response_code(404);
        echo '<h1>404 — Ação não encontrada</h1>';
        exit;
}
