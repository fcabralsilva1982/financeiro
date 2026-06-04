<?php
declare(strict_types=1);

/**
 * Controller: Usuários (stub com dados mock)
 *
 * Implementação temporária para visualização da interface.
 * As operações de CRUD não persistem no banco de dados — apenas
 * geram logs e mensagens flash para simular o comportamento real.
 *
 * Quando o CRUD real for implementado, as funções serão similares
 * ao controller de empresas: buscar_usuarios(), validar_usuario(), etc.
 *
 * Ações disponíveis (via $action):
 *   - index (default): listagem com dados mock filtrados por empresa
 *   - create: formulário de novo usuário
 *   - edit: formulário de edição com dados mock
 *   - store: simula salvamento (gera log + flash)
 *   - block/unblock: simula alteração de status
 *   - delete: simula exclusão
 *
 * Multi-tenancy: dados mock são filtrados por filter_by_empresa().
 */

// Dados mock de empresas (para selects e filtros)
$allEmpresasMock = [
    ['id' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890', 'nome_fantasia' => 'Alpha Tech'],
    ['id' => 'b2c3d4e5-f6a7-8901-bcde-f12345678901', 'nome_fantasia' => 'Beta Store'],
    ['id' => 'c3000000-0000-0000-0000-000000000003', 'nome_fantasia' => 'Gamma Services'],
    ['id' => 'd4000000-0000-0000-0000-000000000004', 'nome_fantasia' => 'Delta Consult'],
    ['id' => 'e5000000-0000-0000-0000-000000000005', 'nome_fantasia' => 'Epsilon IT'],
    ['id' => 'f6000000-0000-0000-0000-000000000006', 'nome_fantasia' => 'Zeta Log'],
];

// Se não é empresa master, mostra apenas a empresa do usuário logado
if (can_see_all_empresas()) {
    $empresasMock = $allEmpresasMock;
} else {
    $empresasMock = array_filter($allEmpresasMock, function($e) {
        return $e['id'] == current_empresa_id();
    });
}

// Dados mock de perfis (para selects)
$perfisMock = [
    ['id' => 'c3d4e5f6-a7b8-9012-cdef-123456789012', 'nome' => 'Administrador'],
    ['id' => 'd4e5f6a7-b8c9-0123-defa-234567890123', 'nome' => 'Gerente'],
    ['id' => 'e5f6a7b8-c9d0-1234-efab-345678901234', 'nome' => 'Operador'],
    ['id' => 'f6a7b8c9-d0e1-2345-fabc-456789012345', 'nome' => 'Visualizador'],
];

// Dados mock de usuários
$usuarios = [
    [
        'id' => '11111111-1111-1111-1111-111111111111',
        'codigo' => 'USR-0001',
        'nome' => 'João Silva',
        'email' => 'joao.silva@alphatech.com.br',
        'telefone' => '(11) 3333-1111',
        'celular' => '(11) 99999-1111',
        'empresa' => 'Alpha Tech',
        'empresa_id' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
        'perfil' => 'Administrador',
        'perfil_id' => 'c3d4e5f6-a7b8-9012-cdef-123456789012',
        'status' => 'ativo',
    ],
    [
        'id' => '22222222-2222-2222-2222-222222222222',
        'codigo' => 'USR-0002',
        'nome' => 'Maria Santos',
        'email' => 'maria.santos@betastore.com.br',
        'telefone' => '(21) 3333-2222',
        'celular' => '(21) 98888-2222',
        'empresa' => 'Beta Store',
        'empresa_id' => 'b2c3d4e5-f6a7-8901-bcde-f12345678901',
        'perfil' => 'Gerente',
        'perfil_id' => 'd4e5f6a7-b8c9-0123-defa-234567890123',
        'status' => 'ativo',
    ],
    [
        'id' => '33333333-3333-3333-3333-333333333333',
        'codigo' => 'USR-0003',
        'nome' => 'Carlos Oliveira',
        'email' => 'carlos@gamma.com.br',
        'telefone' => '',
        'celular' => '(31) 97777-3333',
        'empresa' => 'Gamma Services',
        'empresa_id' => 'c3000000-0000-0000-0000-000000000003',
        'perfil' => 'Operador',
        'perfil_id' => 'e5f6a7b8-c9d0-1234-efab-345678901234',
        'status' => 'bloqueado',
    ],
    [
        'id' => '44444444-4444-4444-4444-444444444444',
        'codigo' => 'USR-0004',
        'nome' => 'Ana Costa',
        'email' => 'ana.costa@deltaconsult.com.br',
        'telefone' => '(41) 3333-4444',
        'celular' => '(41) 96666-4444',
        'empresa' => 'Delta Consult',
        'empresa_id' => 'd4000000-0000-0000-0000-000000000004',
        'perfil' => 'Gerente',
        'perfil_id' => 'd4e5f6a7-b8c9-0123-defa-234567890123',
        'status' => 'ativo',
    ],
    [
        'id' => '55555555-5555-5555-5555-555555555555',
        'codigo' => 'USR-0005',
        'nome' => 'Pedro Ferreira',
        'email' => 'pedro@epsilonit.com.br',
        'telefone' => '',
        'celular' => '(51) 95555-5555',
        'empresa' => 'Epsilon IT',
        'empresa_id' => 'e5000000-0000-0000-0000-000000000005',
        'perfil' => 'Administrador',
        'perfil_id' => 'c3d4e5f6-a7b8-9012-cdef-123456789012',
        'status' => 'ativo',
    ],
    [
        'id' => '66666666-6666-6666-6666-666666666666',
        'codigo' => 'USR-0006',
        'nome' => 'Beatriz Lima',
        'email' => 'bia.lima@zetalog.com.br',
        'telefone' => '(71) 3333-6666',
        'celular' => '(71) 94444-6666',
        'empresa' => 'Zeta Log',
        'empresa_id' => 'f6000000-0000-0000-0000-000000000006',
        'perfil' => 'Visualizador',
        'perfil_id' => 'f6a7b8c9-d0e1-2345-fabc-456789012345',
        'status' => 'bloqueado',
    ],
    [
        'id' => '77777777-7777-7777-7777-777777777777',
        'codigo' => 'USR-0007',
        'nome' => 'Rafael Mendes',
        'email' => 'rafael@alphatech.com.br',
        'telefone' => '(11) 3333-7777',
        'celular' => '(11) 97777-7777',
        'empresa' => 'Alpha Tech',
        'empresa_id' => 'a1b2c3d4-e5f6-7890-abcd-ef1234567890',
        'perfil' => 'Operador',
        'perfil_id' => 'e5f6a7b8-c9d0-1234-efab-345678901234',
        'status' => 'ativo',
    ],
    [
        'id' => '88888888-8888-8888-8888-888888888888',
        'codigo' => 'USR-0008',
        'nome' => 'Camila Rocha',
        'email' => 'camila@betastore.com.br',
        'telefone' => '',
        'celular' => '(21) 98888-8888',
        'empresa' => 'Beta Store',
        'empresa_id' => 'b2c3d4e5-f6a7-8901-bcde-f12345678901',
        'perfil' => 'Visualizador',
        'perfil_id' => 'f6a7b8c9-d0e1-2345-fabc-456789012345',
        'status' => 'ativo',
    ],
];

// Filtra usuários pela empresa do usuário logado (empresa master vê tudo)
$usuarios = filter_by_empresa($usuarios);
// Re-indexa o array após filtragem
$usuarios = array_values($usuarios);

switch ($action) {
    case 'create':
        $usuario = null;
        $isEdit = false;
        require __DIR__ . '/../views/usuarios/form.php';
        break;

    case 'edit':
        $id = $_GET['id'] ?? '';
        $usuario = null;
        foreach ($usuarios as $u) {
            if ($u['id'] === $id) {
                $usuario = $u;
                break;
            }
        }
        $isEdit = true;
        require __DIR__ . '/../views/usuarios/form.php';
        break;

    case 'store':
        // STUB: no futuro, salvar no banco de dados
        $usuario_nome = $_POST['nome'] ?? 'N/A';
        $isEdit = !empty($_POST['id']);
        $acao = $isEdit ? 'editar' : 'criar';
        log_info('usuarios', $acao, 'Usuário ' . ($isEdit ? 'editado' : 'criado') . ': ' . $usuario_nome, $_POST['id'] ?? null, $_POST['codigo'] ?? null, null, $_POST);
        set_flash('success', 'Usuário salvo com sucesso! (stub)');
        redirect(url('usuarios'));
        break;

    case 'block':
    case 'unblock':
        $id = $_GET['id'] ?? '';
        $usr = null;
        foreach ($usuarios as $u) { if ($u['id'] === $id) { $usr = $u; break; } }
        $acao = ($action === 'block') ? 'bloquear' : 'desbloquear';
        $msg = ($action === 'block') ? 'Usuário bloqueado!' : 'Usuário desbloqueado!';
        log_warning('usuarios', $acao, $msg . ' ' . ($usr['nome'] ?? ''), $id, $usr['codigo'] ?? null, ['status' => $usr['status'] ?? ''], ['status' => ($action === 'block') ? 'bloqueado' : 'ativo']);
        set_flash('warning', $msg . ' (stub)');
        redirect(url('usuarios'));
        break;

    case 'delete':
        $id = $_GET['id'] ?? '';
        $usr = null;
        foreach ($usuarios as $u) { if ($u['id'] === $id) { $usr = $u; break; } }
        log_warning('usuarios', 'excluir', 'Usuário excluído: ' . ($usr['nome'] ?? ''), $id, $usr['codigo'] ?? null, $usr);
        set_flash('danger', 'Usuário excluído! (stub)');
        redirect(url('usuarios'));
        break;

    case 'index':
    default:
        require __DIR__ . '/../views/usuarios/index.php';
        break;
}
