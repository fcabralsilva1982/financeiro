<?php
declare(strict_types=1);

/**
 * Controller: Usuarios
 *
 * CRUD de usuarios com persistencia real em banco.
 * Mantem multi-tenancy via usuario_empresa e restringe dados por empresa ativa.
 */

$action = $action ?? ($_GET['action'] ?? 'index');

function validar_csrf_usuarios(): bool {
    $enviado = $_POST['csrf_token'] ?? '';
    $sessao = $_SESSION['csrf_token'] ?? '';
    return $enviado !== '' && $sessao !== '' && hash_equals($sessao, $enviado);
}

function buscar_empresas_para_select(): array {
    $pdo = db();

    if (can_see_all_empresas()) {
        $stmt = $pdo->query("SELECT id, nome_fantasia FROM empresas WHERE status = 'ativo' ORDER BY nome_fantasia ASC");
        return $stmt->fetchAll();
    }

    $stmt = $pdo->prepare("SELECT id, nome_fantasia FROM empresas WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => current_empresa_id()]);
    return $stmt->fetchAll();
}

function buscar_perfis_para_select(): array {
    $pdo = db();
    $stmt = $pdo->query("SELECT id, nome FROM perfis WHERE status = 'ativo' ORDER BY nome ASC");
    return $stmt->fetchAll();
}

function gerar_proximo_codigo_usuario(): string {
    $pdo = db();
    $stmt = $pdo->query("SELECT codigo FROM usuarios ORDER BY codigo DESC LIMIT 1");
    $ultimo = $stmt->fetchColumn();

    $seq = 1;
    if ($ultimo && preg_match('/USR-(\d+)/', (string) $ultimo, $m)) {
        $seq = (int) $m[1] + 1;
    }

    return generate_codigo('USR', $seq);
}

function buscar_usuarios(array $filtros = [], int $pagina = 1, int $porPagina = 15): array {
    $pdo = db();
    $where = [];
    $params = [];

    if (!can_see_all_empresas()) {
        $where[] = 'ue.empresa_id = :empresa_id';
        $params[':empresa_id'] = current_empresa_id();
    } elseif (!empty($filtros['empresa_id'])) {
        $where[] = 'ue.empresa_id = :empresa_filtro';
        $params[':empresa_filtro'] = $filtros['empresa_id'];
    }

    if (!empty($filtros['status'])) {
        $where[] = 'u.status = :status';
        $params[':status'] = $filtros['status'];
    }

    if (!empty($filtros['busca'])) {
        $termo = '%' . trim((string) $filtros['busca']) . '%';
        $where[] = '(u.nome LIKE :b1 OR u.email LIKE :b2 OR u.codigo LIKE :b3)';
        $params[':b1'] = $termo;
        $params[':b2'] = $termo;
        $params[':b3'] = $termo;
    }

    $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    $countSql = "SELECT COUNT(*)
                 FROM usuarios u
                 INNER JOIN usuario_empresa ue ON ue.usuario_id = u.id
                 INNER JOIN empresas e ON e.id = ue.empresa_id
                 INNER JOIN perfis p ON p.id = ue.perfil_id
                 {$whereSql}";
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($params);
    $total = (int) $stmtCount->fetchColumn();

    $offset = ($pagina - 1) * $porPagina;
    $sql = "SELECT
                u.id,
                ue.id AS vinculo_id,
                u.codigo,
                u.nome,
                u.email,
                u.telefone,
                u.celular,
                u.status,
                ue.empresa_id,
                e.nome_fantasia AS empresa,
                ue.perfil_id,
                p.nome AS perfil
            FROM usuarios u
            INNER JOIN usuario_empresa ue ON ue.usuario_id = u.id
            INNER JOIN empresas e ON e.id = ue.empresa_id
            INNER JOIN perfis p ON p.id = ue.perfil_id
            {$whereSql}
            ORDER BY u.codigo ASC
            LIMIT :lim OFFSET :off";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':lim', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return ['usuarios' => $stmt->fetchAll(), 'total' => $total];
}

function buscar_usuario_para_edicao(string $usuarioId, ?string $vinculoId = null): ?array {
    $pdo = db();

    $params = [':usuario_id' => $usuarioId];
    $tenantSql = '';
    if (!can_see_all_empresas()) {
        $tenantSql = ' AND ue.empresa_id = :empresa_id';
        $params[':empresa_id'] = current_empresa_id();
    }

    if (!empty($vinculoId)) {
        $params[':vinculo_id'] = $vinculoId;
        $sql = "SELECT
                    u.id,
                    ue.id AS vinculo_id,
                    u.codigo,
                    u.nome,
                    u.email,
                    u.telefone,
                    u.celular,
                    u.status,
                    ue.empresa_id,
                    ue.perfil_id
                FROM usuarios u
                INNER JOIN usuario_empresa ue ON ue.usuario_id = u.id
                WHERE u.id = :usuario_id AND ue.id = :vinculo_id {$tenantSql}
                LIMIT 1";
    } else {
        $sql = "SELECT
                    u.id,
                    ue.id AS vinculo_id,
                    u.codigo,
                    u.nome,
                    u.email,
                    u.telefone,
                    u.celular,
                    u.status,
                    ue.empresa_id,
                    ue.perfil_id
                FROM usuarios u
                INNER JOIN usuario_empresa ue ON ue.usuario_id = u.id
                WHERE u.id = :usuario_id {$tenantSql}
                ORDER BY ue.criado_em ASC
                LIMIT 1";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch() ?: null;
}

function usuario_email_duplicado(string $email, ?string $usuarioId = null): bool {
    $pdo = db();
    $sql = 'SELECT id FROM usuarios WHERE email = :email';
    $params = [':email' => $email];

    if (!empty($usuarioId)) {
        $sql .= ' AND id != :id';
        $params[':id'] = $usuarioId;
    }

    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool) $stmt->fetch();
}

function existe_vinculo_usuario_empresa(string $usuarioId, string $empresaId, ?string $ignorarVinculoId = null): bool {
    $pdo = db();
    $sql = 'SELECT id FROM usuario_empresa WHERE usuario_id = :uid AND empresa_id = :eid';
    $params = [':uid' => $usuarioId, ':eid' => $empresaId];

    if (!empty($ignorarVinculoId)) {
        $sql .= ' AND id != :vid';
        $params[':vid'] = $ignorarVinculoId;
    }

    $sql .= ' LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (bool) $stmt->fetch();
}

function validar_usuario(array $dados, bool $isEdit = false): array {
    $erros = [];

    $nome = trim((string) ($dados['nome'] ?? ''));
    $email = trim((string) ($dados['email'] ?? ''));
    $empresaId = trim((string) ($dados['empresa_id'] ?? ''));
    $perfilId = trim((string) ($dados['perfil_id'] ?? ''));

    if ($nome === '') {
        $erros['nome'] = 'O nome e obrigatorio.';
    }

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erros['email'] = 'Informe um e-mail valido.';
    } elseif (usuario_email_duplicado($email, $isEdit ? (string) ($dados['id'] ?? '') : null)) {
        $erros['email'] = 'Ja existe um usuario com este e-mail.';
    }

    if ($empresaId === '') {
        $erros['empresa_id'] = 'Selecione uma empresa.';
    }

    if ($perfilId === '') {
        $erros['perfil_id'] = 'Selecione um perfil.';
    }

    $alterarSenha = !$isEdit || !empty($dados['alterar_senha']);
    if ($alterarSenha) {
        $senha = (string) ($dados['senha'] ?? '');
        $confirmarSenha = (string) ($dados['confirmar_senha'] ?? '');

        if (strlen($senha) < 8) {
            $erros['senha'] = 'A senha deve ter no minimo 8 caracteres.';
        }

        if ($senha !== $confirmarSenha) {
            $erros['confirmar_senha'] = 'A confirmacao de senha nao confere.';
        }
    }

    if ($isEdit && $empresaId !== '' && !empty($dados['id']) && existe_vinculo_usuario_empresa((string) $dados['id'], $empresaId, (string) ($dados['vinculo_id'] ?? ''))) {
        $erros['empresa_id'] = 'Este usuario ja possui vinculo com a empresa selecionada.';
    }

    return $erros;
}

function buscar_usuario_por_id(string $id): ?array {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT id, codigo, nome, email, senha FROM usuarios WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    return $stmt->fetch() ?: null;
}

$empresasMock = buscar_empresas_para_select();
$perfisMock = buscar_perfis_para_select();

switch ($action) {
    case 'meu_perfil':
        if (!is_logged_in()) {
            redirect(url('login'));
        }

        $usuarioLogado = current_user();
        $usuario = buscar_usuario_por_id((string) ($usuarioLogado['id'] ?? ''));
        if (!$usuario) {
            set_flash('danger', 'Usuario da sessao nao encontrado.');
            redirect(url('login', 'logout'));
        }

        require __DIR__ . '/../views/usuarios/meu_perfil.php';
        break;

    case 'alterar_minha_senha':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(url('usuarios', 'meu_perfil'));
        }

        if (!validar_csrf_usuarios()) {
            set_flash('danger', __('common.erro_csrf'));
            redirect(url('usuarios', 'meu_perfil'));
        }

        $usuarioLogado = current_user();
        $usuarioId = (string) ($usuarioLogado['id'] ?? '');
        $senhaAtual = (string) ($_POST['senha_atual'] ?? '');
        $novaSenha = (string) ($_POST['nova_senha'] ?? '');
        $confirmarSenha = (string) ($_POST['confirmar_nova_senha'] ?? '');

        if ($usuarioId === '') {
            set_flash('danger', 'Sessao invalida.');
            redirect(url('login', 'logout'));
        }

        $usuario = buscar_usuario_por_id($usuarioId);
        if (!$usuario) {
            set_flash('danger', 'Usuario nao encontrado.');
            redirect(url('usuarios', 'meu_perfil'));
        }

        if (!verificar_senha($senhaAtual, (string) $usuario['senha'])) {
            set_flash('danger', 'A senha atual esta incorreta.');
            redirect(url('usuarios', 'meu_perfil'));
        }

        if (strlen($novaSenha) < 8) {
            set_flash('danger', 'A nova senha deve ter no minimo 8 caracteres.');
            redirect(url('usuarios', 'meu_perfil'));
        }

        if ($novaSenha !== $confirmarSenha) {
            set_flash('danger', 'A confirmacao da nova senha nao confere.');
            redirect(url('usuarios', 'meu_perfil'));
        }

        $stmt = db()->prepare('UPDATE usuarios SET senha = :senha WHERE id = :id');
        $stmt->execute([
            ':senha' => hash_senha($novaSenha),
            ':id' => $usuarioId,
        ]);

        log_info('usuarios', 'alterar_senha_propria', 'Usuario alterou a propria senha.', $usuarioId, (string) ($usuario['codigo'] ?? null));
        set_flash('success', 'Senha alterada com sucesso.');
        redirect(url('usuarios', 'meu_perfil'));
        break;

    case 'create':
        $usuario = null;
        $isEdit = false;
        $errors = [];
        require __DIR__ . '/../views/usuarios/form.php';
        break;

    case 'edit':
        $id = (string) ($_GET['id'] ?? '');
        $vinculoId = (string) ($_GET['vinculo_id'] ?? '');

        if ($id === '') {
            set_flash('danger', 'Usuario nao encontrado.');
            redirect(url('usuarios'));
        }

        $usuario = buscar_usuario_para_edicao($id, $vinculoId !== '' ? $vinculoId : null);
        if (!$usuario) {
            set_flash('danger', 'Usuario nao encontrado.');
            redirect(url('usuarios'));
        }

        $isEdit = true;
        $errors = [];
        require __DIR__ . '/../views/usuarios/form.php';
        break;

    case 'store':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(url('usuarios'));
        }

        if (!validar_csrf_usuarios()) {
            set_flash('danger', __('common.erro_csrf'));
            redirect(url('usuarios'));
        }

        $isEdit = !empty($_POST['id']);
        $dados = $_POST;

        if (!can_see_all_empresas()) {
            $dados['empresa_id'] = (string) current_empresa_id();
        }

        $errors = validar_usuario($dados, $isEdit);

        if (!empty($errors)) {
            $usuario = $dados;
            $isEdit = !empty($dados['id']);
            require __DIR__ . '/../views/usuarios/form.php';
            break;
        }

        try {
            $pdo = db();
            $pdo->beginTransaction();

            if ($isEdit) {
                $usuarioId = (string) $dados['id'];
                $vinculoId = (string) ($dados['vinculo_id'] ?? '');

                $anterior = buscar_usuario_para_edicao($usuarioId, $vinculoId !== '' ? $vinculoId : null);
                if (!$anterior) {
                    $pdo->rollBack();
                    set_flash('danger', 'Usuario nao encontrado.');
                    redirect(url('usuarios'));
                }

                $sqlUsuario = "UPDATE usuarios SET
                                nome = :nome,
                                email = :email,
                                telefone = :telefone,
                                celular = :celular,
                                status = :status
                               WHERE id = :id";
                $stmtUsuario = $pdo->prepare($sqlUsuario);
                $stmtUsuario->execute([
                    ':nome' => trim((string) $dados['nome']),
                    ':email' => trim((string) $dados['email']),
                    ':telefone' => trim((string) ($dados['telefone'] ?? '')),
                    ':celular' => trim((string) ($dados['celular'] ?? '')),
                    ':status' => trim((string) ($dados['status'] ?? 'ativo')) === 'bloqueado' ? 'bloqueado' : 'ativo',
                    ':id' => $usuarioId,
                ]);

                if (!empty($dados['alterar_senha'])) {
                    $stmtSenha = $pdo->prepare('UPDATE usuarios SET senha = :senha WHERE id = :id');
                    $stmtSenha->execute([
                        ':senha' => hash_senha((string) $dados['senha']),
                        ':id' => $usuarioId,
                    ]);
                }

                if ($vinculoId !== '') {
                    $stmtVinculo = $pdo->prepare("UPDATE usuario_empresa
                                                  SET empresa_id = :empresa_id, perfil_id = :perfil_id
                                                  WHERE id = :vinculo_id AND usuario_id = :usuario_id");
                    $stmtVinculo->execute([
                        ':empresa_id' => (string) $dados['empresa_id'],
                        ':perfil_id' => (string) $dados['perfil_id'],
                        ':vinculo_id' => $vinculoId,
                        ':usuario_id' => $usuarioId,
                    ]);
                }

                $pdo->commit();

                $dadosAnteriores = [
                    'nome' => $anterior['nome'] ?? '',
                    'email' => $anterior['email'] ?? '',
                    'telefone' => $anterior['telefone'] ?? '',
                    'celular' => $anterior['celular'] ?? '',
                    'status' => $anterior['status'] ?? '',
                    'empresa_id' => $anterior['empresa_id'] ?? '',
                    'perfil_id' => $anterior['perfil_id'] ?? '',
                ];
                $dadosNovos = [
                    'nome' => trim((string) $dados['nome']),
                    'email' => trim((string) $dados['email']),
                    'telefone' => trim((string) ($dados['telefone'] ?? '')),
                    'celular' => trim((string) ($dados['celular'] ?? '')),
                    'status' => trim((string) ($dados['status'] ?? 'ativo')) === 'bloqueado' ? 'bloqueado' : 'ativo',
                    'empresa_id' => (string) $dados['empresa_id'],
                    'perfil_id' => (string) $dados['perfil_id'],
                ];

                log_info('usuarios', 'editar', 'Usuario atualizado: ' . trim((string) $dados['nome']), $usuarioId, (string) ($anterior['codigo'] ?? ''), $dadosAnteriores, $dadosNovos);
                set_flash('success', 'Usuario atualizado com sucesso.');
            } else {
                $usuarioId = generate_uuid();
                $codigo = gerar_proximo_codigo_usuario();
                $vinculoId = generate_uuid();

                $stmtUsuario = $pdo->prepare("INSERT INTO usuarios
                    (id, codigo, nome, email, senha, telefone, celular, status)
                    VALUES
                    (:id, :codigo, :nome, :email, :senha, :telefone, :celular, :status)");
                $stmtUsuario->execute([
                    ':id' => $usuarioId,
                    ':codigo' => $codigo,
                    ':nome' => trim((string) $dados['nome']),
                    ':email' => trim((string) $dados['email']),
                    ':senha' => hash_senha((string) $dados['senha']),
                    ':telefone' => trim((string) ($dados['telefone'] ?? '')),
                    ':celular' => trim((string) ($dados['celular'] ?? '')),
                    ':status' => 'ativo',
                ]);

                $stmtVinculo = $pdo->prepare("INSERT INTO usuario_empresa
                    (id, usuario_id, empresa_id, perfil_id, status)
                    VALUES
                    (:id, :usuario_id, :empresa_id, :perfil_id, :status)");
                $stmtVinculo->execute([
                    ':id' => $vinculoId,
                    ':usuario_id' => $usuarioId,
                    ':empresa_id' => (string) $dados['empresa_id'],
                    ':perfil_id' => (string) $dados['perfil_id'],
                    ':status' => 'ativo',
                ]);

                $pdo->commit();

                $dadosNovos = [
                    'codigo' => $codigo,
                    'nome' => trim((string) $dados['nome']),
                    'email' => trim((string) $dados['email']),
                    'empresa_id' => (string) $dados['empresa_id'],
                    'perfil_id' => (string) $dados['perfil_id'],
                ];

                log_info('usuarios', 'criar', 'Usuario criado: ' . trim((string) $dados['nome']), $usuarioId, $codigo, null, $dadosNovos);
                set_flash('success', 'Usuario criado com sucesso.');
            }

            redirect(url('usuarios'));
        } catch (Exception $ex) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            log_error('usuarios', 'store_erro', 'Falha ao salvar usuario: ' . $ex->getMessage());
            set_flash('danger', 'Erro ao salvar usuario. Tente novamente.');
            redirect(url('usuarios'));
        }
        break;

    case 'block':
    case 'unblock':
        $id = (string) ($_GET['id'] ?? '');
        if ($id === '') {
            set_flash('danger', 'Usuario nao encontrado.');
            redirect(url('usuarios'));
        }

        try {
            $novoStatus = $action === 'block' ? 'bloqueado' : 'ativo';
            $stmt = db()->prepare('UPDATE usuarios SET status = :status WHERE id = :id');
            $stmt->execute([':status' => $novoStatus, ':id' => $id]);

            if ($stmt->rowCount() === 0) {
                set_flash('danger', 'Usuario nao encontrado.');
                redirect(url('usuarios'));
            }

            log_warning('usuarios', $action === 'block' ? 'bloquear' : 'desbloquear', 'Status do usuario alterado para ' . $novoStatus, $id);
            set_flash('warning', $action === 'block' ? 'Usuario bloqueado com sucesso.' : 'Usuario desbloqueado com sucesso.');
        } catch (Exception $ex) {
            log_error('usuarios', 'status_erro', 'Falha ao alterar status de usuario: ' . $ex->getMessage(), $id);
            set_flash('danger', 'Erro ao alterar status do usuario.');
        }

        redirect(url('usuarios'));
        break;

    case 'delete':
        $id = (string) ($_GET['id'] ?? '');
        $vinculoId = (string) ($_GET['vinculo_id'] ?? '');

        if ($id === '') {
            set_flash('danger', 'Usuario nao encontrado.');
            redirect(url('usuarios'));
        }

        try {
            $pdo = db();
            $pdo->beginTransaction();

            if (can_see_all_empresas()) {
                $stmt = $pdo->prepare('DELETE FROM usuarios WHERE id = :id');
                $stmt->execute([':id' => $id]);
                $linhas = $stmt->rowCount();
            } else {
                if ($vinculoId === '') {
                    $pdo->rollBack();
                    set_flash('danger', 'Vinculo do usuario nao informado.');
                    redirect(url('usuarios'));
                }

                $stmt = $pdo->prepare('DELETE FROM usuario_empresa WHERE id = :vid AND usuario_id = :uid AND empresa_id = :eid');
                $stmt->execute([
                    ':vid' => $vinculoId,
                    ':uid' => $id,
                    ':eid' => current_empresa_id(),
                ]);
                $linhas = $stmt->rowCount();

                if ($linhas > 0) {
                    $stmtRestante = $pdo->prepare('SELECT COUNT(*) FROM usuario_empresa WHERE usuario_id = :uid');
                    $stmtRestante->execute([':uid' => $id]);
                    $restantes = (int) $stmtRestante->fetchColumn();

                    if ($restantes === 0) {
                        $pdo->prepare('DELETE FROM usuarios WHERE id = :id')->execute([':id' => $id]);
                    }
                }
            }

            if ($linhas === 0) {
                $pdo->rollBack();
                set_flash('danger', 'Usuario nao encontrado.');
                redirect(url('usuarios'));
            }

            $pdo->commit();
            log_warning('usuarios', 'excluir', 'Usuario removido.', $id);
            set_flash('danger', 'Usuario excluido com sucesso.');
        } catch (Exception $ex) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            log_error('usuarios', 'delete_erro', 'Falha ao excluir usuario: ' . $ex->getMessage(), $id);
            set_flash('danger', 'Nao foi possivel excluir o usuario.');
        }

        redirect(url('usuarios'));
        break;

    case 'index':
    default:
        $filtros = [
            'busca' => trim((string) ($_GET['busca'] ?? '')),
            'status' => trim((string) ($_GET['status'] ?? '')),
            'empresa_id' => trim((string) ($_GET['empresa_id'] ?? '')),
        ];
        $pagina = max(1, (int) ($_GET['pg'] ?? 1));
        $resultado = buscar_usuarios($filtros, $pagina, 15);
        $usuarios = $resultado['usuarios'];
        require __DIR__ . '/../views/usuarios/index.php';
        break;
}
