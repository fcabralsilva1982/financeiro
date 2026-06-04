<?php
declare(strict_types=1);

/**
 * Controller: Perfis de Acesso
 *
 * CRUD completo de perfis com gerenciamento de permissões granulares.
 * Cada perfil pode ter múltiplas permissões no formato 'modulo.acao'
 * (ex: 'empresas.criar', 'usuarios.editar').
 *
 * Ações disponíveis (via $action):
 *   - index (default): listagem paginada com contagem de usuários vinculados
 *   - create: formulário de novo perfil com checkboxes de permissões
 *   - edit: formulário de edição com permissões carregadas
 *   - store: INSERT ou UPDATE (POST) com transação e log
 *   - block/unblock: altera status entre 'ativo' e 'bloqueado'
 *   - delete: exclui perfil (falha se houver usuários vinculados via FK RESTRICT)
 */

// ============================================================
// FUNÇÕES DE CONSULTA
// ============================================================

/**
 * Busca perfis no banco com filtros, paginação e contagem de usuários.
 *
 * Inclui uma subquery para contar usuários distintos vinculados a cada perfil
 * (via tabela usuario_empresa). Suporta busca textual e filtro por status.
 *
 * @param array $filtros   Filtros opcionais: 'busca' (nome, descrição, código), 'status'
 * @param int   $pagina    Número da página (começa em 1)
 * @param int   $porPagina Registros por página (padrão: 15)
 * @return array ['perfis' => array[], 'total' => int] onde cada perfil inclui 'num_usuarios'
 */
function buscar_perfis($filtros = [], $pagina = 1, $porPagina = 15) {
    $pdo = db();
    $where = [];
    $params = [];

    if (!empty($filtros['busca'])) {
        $termo = '%' . $filtros['busca'] . '%';
        $where[] = "(p.nome LIKE :b1 OR p.descricao LIKE :b2 OR p.codigo LIKE :b3)";
        $params[':b1'] = $termo;
        $params[':b2'] = $termo;
        $params[':b3'] = $termo;
    }

    if (!empty($filtros['status'])) {
        $where[] = "p.status = :status";
        $params[':status'] = $filtros['status'];
    }

    $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Total
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM perfis p {$whereSql}");
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    // Dados paginados com contagem de usuários vinculados
    $offset = ($pagina - 1) * $porPagina;
    $sql = "SELECT p.id, p.codigo, p.nome, p.descricao, p.status, p.criado_em, p.atualizado_em,
                (SELECT COUNT(DISTINCT ue.usuario_id)
                 FROM usuario_empresa ue
                 JOIN usuarios u ON u.id = ue.usuario_id
                 WHERE ue.perfil_id = p.id) AS num_usuarios
            FROM perfis p
            {$whereSql}
            ORDER BY p.codigo ASC
            LIMIT :lim OFFSET :off";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':lim', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return ['perfis' => $stmt->fetchAll(), 'total' => $total];
}

/**
 * Busca um perfil por UUID, incluindo suas permissões.
 *
 * As permissões são retornadas como array de strings no formato 'modulo.acao'
 * (ex: ['empresas.visualizar', 'empresas.criar', 'usuarios.editar']).
 *
 * @param string $id UUID do perfil (CHAR(36))
 * @return array|null Array associativo com dados do perfil e chave 'permissoes', ou null se não encontrado
 */
function buscar_perfil_por_id($id) {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT id, codigo, nome, descricao, status, criado_em, atualizado_em FROM perfis WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $perfil = $stmt->fetch();

    if (!$perfil) return null;

    // Permissões como array de "modulo.acao"
    $stmt = $pdo->prepare("SELECT CONCAT(modulo, '.', acao) AS perm FROM perfil_permissoes WHERE perfil_id = :pid ORDER BY modulo, acao");
    $stmt->execute([':pid' => $id]);
    $perfil['permissoes'] = $stmt->fetchAll(PDO::FETCH_COLUMN);

    return $perfil;
}

/**
 * Gera o próximo código sequencial para perfil (PRF-XXXX).
 *
 * Consulta o último código existente na tabela, extrai o número via regex
 * e incrementa em 1. Se a tabela estiver vazia, retorna 'PRF-0001'.
 *
 * @return string Código no formato 'PRF-XXXX' (ex: 'PRF-0003')
 */
function gerar_proximo_codigo_perfil() {
    $pdo = db();
    $stmt = $pdo->query("SELECT codigo FROM perfis ORDER BY codigo DESC LIMIT 1");
    $ultimo = $stmt->fetchColumn();

    $seq = 1;
    if ($ultimo && preg_match('/PRF-(\d+)/', $ultimo, $m)) {
        $seq = (int) $m[1] + 1;
    }

    return generate_codigo('PRF', $seq);
}

// ============================================================
// VALIDAÇÃO
// ============================================================

/**
 * Valida o token CSRF do formulário de perfis.
 *
 * Mesma lógica de validar_csrf() do controller empresas,
 * duplicada para evitar conflito de nomes de função entre controllers.
 *
 * @return bool true se o token CSRF é válido
 */
function validar_csrf_perfis() {
    $enviado = $_POST['csrf_token'] ?? '';
    $sessao  = $_SESSION['csrf_token'] ?? '';
    return $enviado !== '' && $sessao !== '' && hash_equals($sessao, $enviado);
}

/**
 * Valida os dados de um perfil antes de salvar.
 *
 * Verificações realizadas:
 *   - Nome obrigatório e único (ignora o próprio registro em edição)
 *
 * @param array $dados  Dados do formulário ($_POST): nome, [id]
 * @param bool  $isEdit true se for edição (para ignorar o próprio nome na duplicata)
 * @return array Array de erros indexado por campo. Vazio se válido.
 */
function validar_perfil($dados, $isEdit = false) {
    $erros = [];

    // Nome obrigatório
    $nome = trim($dados['nome'] ?? '');
    if ($nome === '') {
        $erros['nome'] = __('perfis.erro_nome_obrigatorio');
    } else {
        // Verificar unicidade
        $pdo = db();
        $sql = "SELECT id FROM perfis WHERE nome = :nome";
        $p = [':nome' => $nome];
        if ($isEdit && !empty($dados['id'])) {
            $sql .= " AND id != :id";
            $p[':id'] = $dados['id'];
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($p);
        if ($stmt->fetch()) {
            $erros['nome'] = __('perfis.erro_nome_duplicado');
        }
    }

    return $erros;
}

// ============================================================
// PERMISSÕES
// ============================================================

/**
 * Salva permissões de um perfil (estratégia: delete + re-insert).
 *
 * Remove todas as permissões existentes e reinsere as informadas.
 * Cada permissão deve estar no formato 'modulo.acao' (ex: 'empresas.criar').
 * Módulos futuros ('financeiro', 'relatorios') são ignorados automaticamente.
 *
 * @param \PDO   $pdo        Instância PDO (deve estar dentro de uma transação)
 * @param string $perfilId   UUID do perfil
 * @param array  $permissoes Array de permissões no formato ['modulo.acao', ...]
 * @return void
 */
function salvar_permissoes($pdo, $perfilId, $permissoes) {
    // Remove todas e reinsere
    $pdo->prepare("DELETE FROM perfil_permissoes WHERE perfil_id = :pid")
        ->execute([':pid' => $perfilId]);

    if (empty($permissoes) || !is_array($permissoes)) return;

    $stmt = $pdo->prepare(
        "INSERT INTO perfil_permissoes (id, perfil_id, modulo, acao) VALUES (:id, :pid, :mod, :acao)"
    );

    foreach ($permissoes as $perm) {
        $parts = explode('.', $perm, 2);
        if (count($parts) !== 2) continue;
        // Ignorar módulos futuros (financeiro, relatorios)
        if (in_array($parts[0], ['financeiro', 'relatorios'], true)) continue;
        $stmt->execute([
            ':id'   => generate_uuid(),
            ':pid'  => $perfilId,
            ':mod'  => trim($parts[0]),
            ':acao' => trim($parts[1]),
        ]);
    }
}

// ============================================================
// ROTEAMENTO
// ============================================================

switch ($action) {
    // ----------------------------------------------------------
    // CRIAR
    // ----------------------------------------------------------
    case 'create':
        $perfil  = null;
        $isEdit  = false;
        $errors  = [];
        require __DIR__ . '/../views/perfis/form.php';
        break;

    // ----------------------------------------------------------
    // EDITAR
    // ----------------------------------------------------------
    case 'edit':
        $id = $_GET['id'] ?? '';
        if (empty($id)) {
            set_flash('danger', __('perfis.erro_nao_encontrado'));
            redirect(url('perfis'));
        }
        $perfil = buscar_perfil_por_id($id);
        if (!$perfil) {
            set_flash('danger', __('perfis.erro_nao_encontrado'));
            redirect(url('perfis'));
        }
        $isEdit = true;
        $errors = [];
        require __DIR__ . '/../views/perfis/form.php';
        break;

    // ----------------------------------------------------------
    // SALVAR (INSERT / UPDATE)
    // ----------------------------------------------------------
    case 'store':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(url('perfis'));
        }
        if (!validar_csrf_perfis()) {
            set_flash('danger', __('common.erro_csrf'));
            redirect(url('perfis'));
        }

        $isEdit = !empty($_POST['id']);
        $dados  = $_POST;
        $errors = validar_perfil($dados, $isEdit);

        if (!empty($errors)) {
            $perfil = $dados;
            $perfil['permissoes'] = $_POST['permissoes'] ?? [];
            require __DIR__ . '/../views/perfis/form.php';
            break;
        }

        try {
            $pdo = db();
            $pdo->beginTransaction();

            if ($isEdit) {
                // ---------- UPDATE ----------
                $id = $dados['id'];
                $perfilAnterior = buscar_perfil_por_id($id);

                if (!$perfilAnterior) {
                    $pdo->rollBack();
                    set_flash('danger', __('perfis.erro_nao_encontrado'));
                    redirect(url('perfis'));
                }

                $stmt = $pdo->prepare("UPDATE perfis SET
                    nome = :nome, descricao = :desc, status = :status
                    WHERE id = :id");

                $stmt->execute([
                    ':nome'   => trim($dados['nome']),
                    ':desc'   => trim($dados['descricao'] ?? ''),
                    ':status' => $dados['status'] ?? $perfilAnterior['status'],
                    ':id'     => $id,
                ]);

                salvar_permissoes($pdo, $id, $dados['permissoes'] ?? []);
                $pdo->commit();

                $perfilNovo = buscar_perfil_por_id($id);
                log_info('perfis', 'editar',
                    'Perfil editado: ' . $perfilNovo['codigo'] . ' – ' . $perfilNovo['nome'],
                    $id, $perfilNovo['codigo'], $perfilAnterior, $perfilNovo);

                set_flash('success', __('perfis.sucesso_editado'));

            } else {
                // ---------- INSERT ----------
                $id     = generate_uuid();
                $codigo = gerar_proximo_codigo_perfil();

                $stmt = $pdo->prepare("INSERT INTO perfis (id, codigo, nome, descricao, status)
                    VALUES (:id, :codigo, :nome, :desc, 'ativo')");

                $stmt->execute([
                    ':id'     => $id,
                    ':codigo' => $codigo,
                    ':nome'   => trim($dados['nome']),
                    ':desc'   => trim($dados['descricao'] ?? ''),
                ]);

                salvar_permissoes($pdo, $id, $dados['permissoes'] ?? []);
                $pdo->commit();

                $perfilCriado = buscar_perfil_por_id($id);
                log_info('perfis', 'criar',
                    'Perfil criado: ' . $codigo . ' – ' . trim($dados['nome']),
                    $id, $codigo, null, $perfilCriado);

                set_flash('success', __('perfis.sucesso_criado'));
            }

            redirect(url('perfis'));

        } catch (Exception $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            log_error('perfis', $isEdit ? 'editar' : 'criar', 'Erro ao salvar perfil: ' . $ex->getMessage());
            set_flash('danger', __('perfis.erro_salvar'));
            redirect(url('perfis'));
        }
        break;

    // ----------------------------------------------------------
    // BLOQUEAR / DESBLOQUEAR
    // ----------------------------------------------------------
    case 'block':
    case 'unblock':
        $id = $_GET['id'] ?? '';
        if (empty($id)) {
            set_flash('danger', __('perfis.erro_nao_encontrado'));
            redirect(url('perfis'));
        }

        try {
            $perfil = buscar_perfil_por_id($id);
            if (!$perfil) {
                set_flash('danger', __('perfis.erro_nao_encontrado'));
                redirect(url('perfis'));
            }

            $novoStatus     = ($action === 'block') ? 'bloqueado' : 'ativo';
            $statusAnterior = $perfil['status'];

            $pdo = db();
            $pdo->prepare("UPDATE perfis SET status = :s WHERE id = :id")
                ->execute([':s' => $novoStatus, ':id' => $id]);

            $acao   = ($action === 'block') ? 'bloquear' : 'desbloquear';
            $msgKey = ($action === 'block') ? 'perfis.sucesso_bloqueado' : 'perfis.sucesso_desbloqueado';

            log_warning('perfis', $acao,
                'Perfil ' . $perfil['codigo'] . ' ' . ($action === 'block' ? 'bloqueado' : 'desbloqueado'),
                $id, $perfil['codigo'],
                ['status' => $statusAnterior], ['status' => $novoStatus]);

            set_flash('success', __($msgKey));

        } catch (Exception $ex) {
            log_error('perfis', 'status', 'Erro ao alterar status: ' . $ex->getMessage(), $id);
            set_flash('danger', __('perfis.erro_salvar'));
        }

        redirect(url('perfis'));
        break;

    // ----------------------------------------------------------
    // EXCLUIR
    // ----------------------------------------------------------
    case 'delete':
        $id = $_GET['id'] ?? '';
        if (empty($id)) {
            set_flash('danger', __('perfis.erro_nao_encontrado'));
            redirect(url('perfis'));
        }

        try {
            $perfil = buscar_perfil_por_id($id);
            if (!$perfil) {
                set_flash('danger', __('perfis.erro_nao_encontrado'));
                redirect(url('perfis'));
            }

            // Impedir exclusão se houver usuários vinculados
            $pdo  = db();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario_empresa WHERE perfil_id = :id");
            $stmt->execute([':id' => $id]);
            if ((int) $stmt->fetchColumn() > 0) {
                set_flash('danger', __('perfis.erro_excluir_com_usuarios'));
                redirect(url('perfis'));
            }

            // Excluir perfil (CASCADE remove permissões)
            $pdo->prepare("DELETE FROM perfis WHERE id = :id")
                ->execute([':id' => $id]);

            log_warning('perfis', 'excluir',
                'Perfil excluído: ' . $perfil['codigo'] . ' – ' . $perfil['nome'],
                $id, $perfil['codigo'], $perfil);

            set_flash('success', __('perfis.sucesso_excluido'));

        } catch (Exception $ex) {
            log_error('perfis', 'excluir', 'Erro ao excluir perfil: ' . $ex->getMessage(), $id);
            set_flash('danger', __('perfis.erro_salvar'));
        }

        redirect(url('perfis'));
        break;

    // ----------------------------------------------------------
    // LISTAGEM
    // ----------------------------------------------------------
    case 'index':
    default:
        $filtros = [
            'busca'  => trim($_GET['busca'] ?? ''),
            'status' => trim($_GET['status'] ?? ''),
        ];
        $pagina       = max(1, (int) ($_GET['pg'] ?? 1));
        $porPagina    = 15;
        $resultado    = buscar_perfis($filtros, $pagina, $porPagina);
        $perfis       = $resultado['perfis'];
        $total        = $resultado['total'];
        $totalPaginas = max(1, (int) ceil($total / $porPagina));

        require __DIR__ . '/../views/perfis/index.php';
        break;
}
