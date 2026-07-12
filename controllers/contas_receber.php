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
                ContasReceber::baixar((string) ($_POST['id'] ?? ''), $empresaId, $_POST, $usuarioId);
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

    default:
        http_response_code(404);
        echo '<h1>404 — Ação não encontrada</h1>';
        exit;
}
