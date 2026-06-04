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

$empresaId = current_empresa_id();
$action = $_GET['action'] ?? 'index';
$id = $_GET['id'] ?? null;

switch ($action) {
    case 'index':
        // Listagem de cobranças
        $filtros = [
            'empresa_id' => $empresaId,
            'search' => $_GET['search'] ?? ''
        ];
        $contas = ContasReceber::listar($filtros);
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

    default:
        http_response_code(404);
        echo '<h1>404 — Ação não encontrada</h1>';
        exit;
}
