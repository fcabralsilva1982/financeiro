<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/Conciliacao.php';
require_once __DIR__ . '/../models/ConciliacaoOfxParser.php';

if (!is_logged_in()) {
    redirect(url('login'));
}

const CONCILIACAO_OFX_MAX_BYTES = 5242880;

function validar_csrf_conciliacao(): bool
{
    $enviado = (string) ($_POST['csrf_token'] ?? '');
    $sessao = (string) ($_SESSION['csrf_token'] ?? '');
    return $enviado !== '' && $sessao !== '' && hash_equals($sessao, $enviado);
}

function ler_arquivo_ofx_upload(): string
{
    if (!isset($_FILES['arquivo_ofx']) || !is_array($_FILES['arquivo_ofx'])) {
        throw new RuntimeException(__('conciliacao.erro_arquivo_obrigatorio'));
    }

    $arquivo = $_FILES['arquivo_ofx'];
    if ((int) ($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException(__('conciliacao.erro_arquivo_obrigatorio'));
    }

    if ((int) ($arquivo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException(__('conciliacao.erro_arquivo_invalido'));
    }

    $tmpName = (string) ($arquivo['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        throw new RuntimeException(__('conciliacao.erro_arquivo_invalido'));
    }

    $nomeOriginal = (string) ($arquivo['name'] ?? '');
    if (strtolower((string) pathinfo($nomeOriginal, PATHINFO_EXTENSION)) !== 'ofx') {
        throw new RuntimeException(__('conciliacao.erro_extensao_invalida'));
    }

    $tamanho = (int) ($arquivo['size'] ?? 0);
    if ($tamanho <= 0 || $tamanho > CONCILIACAO_OFX_MAX_BYTES) {
        throw new RuntimeException(__('conciliacao.erro_tamanho_invalido'));
    }

    $conteudo = file_get_contents($tmpName);
    if ($conteudo === false) {
        throw new RuntimeException(__('conciliacao.erro_arquivo_invalido'));
    }

    return $conteudo;
}

$empresaId = (string) current_empresa_id();
$usuarioId = (string) (current_user()['id'] ?? '');
$action = $_GET['action'] ?? ($_POST['action'] ?? 'index');

switch ($action) {
    case 'importar':
        if (!enforce_post_request()) {
            break;
        }

        if (!validar_csrf_conciliacao()) {
            set_flash('danger', __('common.erro_csrf'));
            redirect(url('conciliacao'));
        }

        try {
            $conteudoOfx = ler_arquivo_ofx_upload();
            $transacoes = ConciliacaoOfxParser::parse($conteudoOfx);
            $nomeArquivo = (string) ($_FILES['arquivo_ofx']['name'] ?? 'extrato.ofx');
            $importacao = Conciliacao::criarImportacao($empresaId, $usuarioId, $nomeArquivo, $transacoes);

            log_info('conciliacao', 'importar', 'Extrato OFX importado', (string) $importacao['id']);
            set_flash('success', __('conciliacao.importacao_sucesso'));
            redirect(url('conciliacao', 'index', ['id' => (string) $importacao['id']]));
        } catch (Throwable $e) {
            log_error('conciliacao', 'importar', 'Erro ao importar extrato OFX: ' . $e->getMessage());
            set_flash('danger', $e->getMessage());
            redirect(url('conciliacao'));
        }
        break;

    case 'index':
    default:
        if (!can_user('conciliacao', 'visualizar')) {
            set_flash('danger', __('common.acesso_negado'));
            redirect(url('dashboard'));
        }

        $importacaoId = trim((string) ($_GET['id'] ?? ''));
        $importacoes = Conciliacao::listarImportacoes($empresaId);
        $importacaoSelecionada = null;
        $transacoes = [];

        if ($importacaoId !== '') {
            $importacaoSelecionada = Conciliacao::buscarImportacao($importacaoId, $empresaId);
            if ($importacaoSelecionada) {
                $transacoes = Conciliacao::listarTransacoes($importacaoId, $empresaId);
            }
        } elseif ($importacoes !== []) {
            $importacaoSelecionada = $importacoes[0];
            $transacoes = Conciliacao::listarTransacoes((string) $importacaoSelecionada['id'], $empresaId);
        }

        require __DIR__ . '/../views/conciliacao/index.php';
        break;
}