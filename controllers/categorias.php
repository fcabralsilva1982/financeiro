<?php
declare(strict_types=1);

require_once __DIR__ . '/../models/CategoriasFinanceiras.php';

if (!is_logged_in()) {
    redirect(url('login'));
}

$empresaId = (string) current_empresa_id();
$action = $_GET['action'] ?? 'index';

function validar_csrf_categorias(): bool
{
    $enviado = (string) ($_POST['csrf_token'] ?? '');
    $sessao = (string) ($_SESSION['csrf_token'] ?? '');
    return $enviado !== '' && $sessao !== '' && hash_equals($sessao, $enviado);
}

switch ($action) {
    case 'create':
        $categoria = null;
        $isEdit = false;
        $errors = [];
        require __DIR__ . '/../views/categorias/form.php';
        break;

    case 'edit':
        $id = (string) ($_GET['id'] ?? '');
        $categoria = $id !== '' ? CategoriasFinanceiras::buscarPorId($id, $empresaId) : null;
        if (!$categoria) {
            set_flash('danger', 'Categoria nao encontrada.');
            redirect(url('categorias'));
        }
        $isEdit = true;
        $errors = [];
        require __DIR__ . '/../views/categorias/form.php';
        break;

    case 'store':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(url('categorias'));
        }
        if (!validar_csrf_categorias()) {
            set_flash('danger', __('common.erro_csrf'));
            redirect(url('categorias'));
        }

        $dados = $_POST;
        $isEdit = !empty($dados['id']);
        $errors = CategoriasFinanceiras::validar($dados, $empresaId, $isEdit ? (string) $dados['id'] : null);

        if (!empty($errors)) {
            $categoria = $dados;
            require __DIR__ . '/../views/categorias/form.php';
            break;
        }

        try {
            if ($isEdit) {
                $categoriaId = (string) $dados['id'];
                CategoriasFinanceiras::atualizar($categoriaId, $dados, $empresaId);
                log_info('categorias', 'editar', 'Categoria financeira editada', $categoriaId);
                set_flash('success', 'Categoria atualizada com sucesso.');
            } else {
                $categoriaId = CategoriasFinanceiras::criar($dados, $empresaId);
                log_info('categorias', 'criar', 'Categoria financeira criada', $categoriaId);
                set_flash('success', 'Categoria criada com sucesso.');
            }
            redirect(url('categorias'));
        } catch (Throwable $e) {
            log_error('categorias', 'store', 'Erro ao salvar categoria: ' . $e->getMessage());
            set_flash('danger', 'Erro ao salvar categoria.');
            redirect(url('categorias'));
        }
        break;

    case 'delete':
        $id = (string) ($_GET['id'] ?? '');
        if ($id === '') {
            set_flash('danger', 'Categoria nao informada.');
            redirect(url('categorias'));
        }

        try {
            CategoriasFinanceiras::excluir($id, $empresaId);
            log_info('categorias', 'excluir', 'Categoria financeira excluida', $id);
            set_flash('success', 'Categoria excluida com sucesso.');
        } catch (Throwable $e) {
            log_error('categorias', 'delete', 'Erro ao excluir categoria: ' . $e->getMessage());
            set_flash('danger', 'Erro ao excluir categoria.');
        }

        redirect(url('categorias'));
        break;

    case 'index':
    default:
        $filtros = [
            'busca' => trim((string) ($_GET['busca'] ?? '')),
            'tipo' => trim((string) ($_GET['tipo'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
        ];
        $categorias = CategoriasFinanceiras::listar($empresaId, $filtros);
        require __DIR__ . '/../views/categorias/index.php';
        break;
}
