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
            Conciliacao::executarMatchingAutomatico((string) $importacao['id'], $empresaId, $usuarioId);

            log_info('conciliacao', 'importar', 'Extrato OFX importado', (string) $importacao['id']);
            set_flash('success', __('conciliacao.importacao_sucesso'));
            redirect(url('conciliacao', 'index', ['id' => (string) $importacao['id']]));
        } catch (Throwable $e) {
            log_error('conciliacao', 'importar', 'Erro ao importar extrato OFX: ' . $e->getMessage());
            set_flash('danger', $e->getMessage());
            redirect(url('conciliacao'));
        }
        break;

    case 'conciliar_receber':
    case 'conciliar_pagar':
    case 'movimento_interno':
    case 'ignorar':
        if (!enforce_post_request()) {
            break;
        }

        if (!can_user('conciliacao', 'editar')) {
            set_flash('danger', __('common.acesso_negado'));
            redirect(url('conciliacao'));
        }

        if (!validar_csrf_conciliacao()) {
            set_flash('danger', __('common.erro_csrf'));
            redirect(url('conciliacao'));
        }

        $transacaoId = trim((string) ($_POST['transacao_id'] ?? ''));
        $importacaoId = trim((string) ($_POST['importacao_id'] ?? ''));

        try {
            if ($transacaoId === '' || $importacaoId === '') {
                throw new RuntimeException(__('conciliacao.erro_transacao_invalida'));
            }

            $transacao = Conciliacao::buscarTransacao($transacaoId, $empresaId);
            if (!$transacao || (string) $transacao['conciliacao_bancaria_id'] !== $importacaoId) {
                throw new RuntimeException(__('conciliacao.erro_transacao_invalida'));
            }

            if ((string) $transacao['status_conciliacao'] !== 'pendente') {
                throw new RuntimeException(__('conciliacao.erro_transacao_nao_pendente'));
            }

            if ($action === 'conciliar_receber') {
                if ((string) $transacao['tipo_movimento'] !== 'credito') {
                    throw new RuntimeException(__('conciliacao.erro_tipo_incompativel'));
                }

                $contaReceberId = trim((string) ($_POST['conta_receber_id'] ?? ''));
                if ($contaReceberId === '') {
                    throw new RuntimeException(__('conciliacao.erro_conta_receber_obrigatoria'));
                }

                Conciliacao::marcarConciliadoContaReceber($transacaoId, $empresaId, $contaReceberId, $usuarioId, false, 80);
                log_info('conciliacao', 'conciliar_receber', 'Transacao conciliada manualmente com conta a receber', $transacaoId);
                set_flash('success', __('conciliacao.sucesso_conciliada_manual'));
            } elseif ($action === 'conciliar_pagar') {
                if ((string) $transacao['tipo_movimento'] !== 'debito') {
                    throw new RuntimeException(__('conciliacao.erro_tipo_incompativel'));
                }

                $parcelaId = trim((string) ($_POST['conta_pagar_parcela_id'] ?? ''));
                if ($parcelaId === '') {
                    throw new RuntimeException(__('conciliacao.erro_parcela_pagar_obrigatoria'));
                }

                Conciliacao::marcarConciliadoContaPagarParcela($transacaoId, $empresaId, $parcelaId, $usuarioId, false, 80);
                log_info('conciliacao', 'conciliar_pagar', 'Transacao conciliada manualmente com parcela a pagar', $transacaoId);
                set_flash('success', __('conciliacao.sucesso_conciliada_manual'));
            } elseif ($action === 'movimento_interno') {
                Conciliacao::marcarMovimentoInterno($transacaoId, $empresaId, $usuarioId);
                log_info('conciliacao', 'movimento_interno', 'Transacao classificada como movimento interno', $transacaoId);
                set_flash('success', __('conciliacao.sucesso_movimento_interno'));
            } else {
                Conciliacao::marcarIgnorado($transacaoId, $empresaId, $usuarioId);
                log_info('conciliacao', 'ignorar', 'Transacao ignorada na conciliacao', $transacaoId);
                set_flash('success', __('conciliacao.sucesso_ignorada'));
            }
        } catch (Throwable $e) {
            log_error('conciliacao', $action, 'Erro na revisao manual: ' . $e->getMessage(), $transacaoId);
            set_flash('danger', $e->getMessage());
        }

        redirect(url('conciliacao', 'index', ['id' => $importacaoId]));
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
        $contasReceberElegiveis = [];
        $parcelasPagarElegiveis = [];

        if ($importacaoId !== '') {
            $importacaoSelecionada = Conciliacao::buscarImportacao($importacaoId, $empresaId);
            if ($importacaoSelecionada) {
                $transacoes = Conciliacao::listarTransacoes($importacaoId, $empresaId);
            }
        } elseif ($importacoes !== []) {
            $importacaoSelecionada = $importacoes[0];
            $transacoes = Conciliacao::listarTransacoes((string) $importacaoSelecionada['id'], $empresaId);
        }

        if ($importacaoSelecionada && can_user('conciliacao', 'editar')) {
            $contasReceberElegiveis = Conciliacao::listarContasReceberElegiveis($empresaId);
            $parcelasPagarElegiveis = Conciliacao::listarParcelasPagarElegiveis($empresaId);
        }

        require __DIR__ . '/../views/conciliacao/index.php';
        break;
}
