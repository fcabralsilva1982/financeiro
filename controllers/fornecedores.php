<?php
declare(strict_types=1);

/**
 * Controller: Fornecedores
 *
 * CRUD completo de fornecedores (PF e PJ) com banco de dados MySQL.
 * Inclui dados bancários para pagamentos (TED, DOC, PIX), validação de CPF/CNPJ,
 * filtros de busca, paginação e multi-tenancy.
 *
 * Ações disponíveis (via $action):
 *   - index (default): listagem paginada com filtros (busca, status)
 *   - create: exibe formulário de novo fornecedor
 *   - edit: exibe formulário de edição com dados carregados do banco
 *   - store: INSERT ou UPDATE (POST) com validação, transação e log
 *   - block/unblock: altera status entre 'ativo' e 'bloqueado'
 *   - delete: exclui fornecedor
 *
 * Multi-tenancy: registros filtrados por empresa_id do usuário logado.
 */

// ============================================================
// FUNÇÕES DE CONSULTA
// ============================================================

/**
 * Busca fornecedores no banco com filtros opcionais e paginação.
 *
 * @param array $filtros   Filtros: 'busca', 'status', 'categoria'
 * @param int   $pagina    Número da página (começa em 1)
 * @param int   $porPagina Registros por página (padrão: 15)
 * @return array ['fornecedores' => array[], 'total' => int]
 */
function buscar_fornecedores($filtros = [], $pagina = 1, $porPagina = 15) {
    $pdo = db();
    $where = [];
    $params = [];

    // Multi-tenancy
    if (!can_see_all_empresas()) {
        $where[] = "f.empresa_id = :empresa_id";
        $params[':empresa_id'] = current_empresa_id();
    }

    if (!empty($filtros['busca'])) {
        $termo = '%' . $filtros['busca'] . '%';
        $where[] = "(f.razao_social LIKE :b1 OR f.nome_fantasia LIKE :b2 OR f.cpf_cnpj LIKE :b3 OR f.email LIKE :b4 OR f.celular LIKE :b5 OR f.codigo LIKE :b6)";
        $params[':b1'] = $termo;
        $params[':b2'] = $termo;
        $params[':b3'] = $termo;
        $params[':b4'] = $termo;
        $params[':b5'] = $termo;
        $params[':b6'] = $termo;
    }

    if (!empty($filtros['status'])) {
        $where[] = "f.status = :status";
        $params[':status'] = $filtros['status'];
    }

    if (!empty($filtros['categoria'])) {
        $where[] = "f.categoria = :categoria";
        $params[':categoria'] = $filtros['categoria'];
    }

    $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Total
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM fornecedores f {$whereSql}");
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    // Dados paginados
    $offset = ($pagina - 1) * $porPagina;
    $sql = "SELECT f.* FROM fornecedores f {$whereSql} ORDER BY f.codigo ASC LIMIT :lim OFFSET :off";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':lim', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return ['fornecedores' => $stmt->fetchAll(), 'total' => $total];
}

/**
 * Busca um fornecedor por UUID.
 *
 * @param string $id UUID do fornecedor
 * @return array|null Dados do fornecedor ou null se não encontrado
 */
function buscar_fornecedor_por_id($id) {
    $pdo = db();
    $sql = "SELECT * FROM fornecedores WHERE id = :id";
    $params = [':id' => $id];

    // Multi-tenancy
    if (!can_see_all_empresas()) {
        $sql .= " AND empresa_id = :empresa_id";
        $params[':empresa_id'] = current_empresa_id();
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch() ?: null;
}

/**
 * Gera o próximo código sequencial para fornecedor (FOR-XXXX).
 *
 * @return string Código no formato 'FOR-XXXX'
 */
function gerar_proximo_codigo_fornecedor() {
    $pdo = db();
    $stmt = $pdo->query("SELECT codigo FROM fornecedores ORDER BY codigo DESC LIMIT 1");
    $ultimo = $stmt->fetchColumn();

    $seq = 1;
    if ($ultimo && preg_match('/FOR-(\d+)/', $ultimo, $m)) {
        $seq = (int) $m[1] + 1;
    }

    return generate_codigo('FOR', $seq);
}

// ============================================================
// VALIDAÇÃO
// ============================================================

/**
 * Valida o token CSRF enviado via POST.
 *
 * @return bool
 */
function validar_csrf_fornecedores() {
    $enviado = $_POST['csrf_token'] ?? '';
    $sessao  = $_SESSION['csrf_token'] ?? '';
    return $enviado !== '' && $sessao !== '' && hash_equals($sessao, $enviado);
}

/**
 * Valida os dados de um fornecedor antes de salvar.
 *
 * @param array $dados  Dados do formulário ($_POST)
 * @param bool  $isEdit true se for edição
 * @return array Erros indexados por campo. Vazio se válido.
 */
function validar_fornecedor($dados, $isEdit = false) {
    $erros = [];

    // CPF/CNPJ obrigatório e único por empresa
    $cpfCnpj = trim($dados['cpf_cnpj'] ?? '');
    if ($cpfCnpj === '') {
        $erros['cpf_cnpj'] = __('fornecedores.erro_cpf_cnpj_obrigatorio');
    } else {
        $pdo = db();
        $sql = "SELECT id FROM fornecedores WHERE cpf_cnpj = :cpf_cnpj AND empresa_id = :empresa_id";
        $p = [':cpf_cnpj' => $cpfCnpj, ':empresa_id' => current_empresa_id()];
        if ($isEdit && !empty($dados['id'])) {
            $sql .= " AND id != :id";
            $p[':id'] = $dados['id'];
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($p);
        if ($stmt->fetch()) {
            $erros['cpf_cnpj'] = __('fornecedores.erro_cpf_cnpj_duplicado');
        }
    }

    // Razão Social / Nome obrigatório
    if (trim($dados['razao_social'] ?? '') === '') {
        $erros['razao_social'] = __('fornecedores.erro_razao_obrigatoria');
    }

    return $erros;
}

// ============================================================
// ROTEAMENTO
// ============================================================

switch ($action) {
    // ----------------------------------------------------------
    // CRIAR
    // ----------------------------------------------------------
    case 'create':
        $fornecedor = null;
        $isEdit  = false;
        $errors  = [];
        require __DIR__ . '/../views/fornecedores/form.php';
        break;

    // ----------------------------------------------------------
    // EDITAR
    // ----------------------------------------------------------
    case 'edit':
        $id = $_GET['id'] ?? '';
        if (empty($id)) {
            set_flash('danger', __('fornecedores.erro_nao_encontrado'));
            redirect(url('fornecedores'));
        }
        $fornecedor = buscar_fornecedor_por_id($id);
        if (!$fornecedor) {
            set_flash('danger', __('fornecedores.erro_nao_encontrado'));
            redirect(url('fornecedores'));
        }
        $isEdit = true;
        $errors = [];
        require __DIR__ . '/../views/fornecedores/form.php';
        break;

    // ----------------------------------------------------------
    // SALVAR (INSERT / UPDATE)
    // ----------------------------------------------------------
    case 'store':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(url('fornecedores'));
        }
        if (!validar_csrf_fornecedores()) {
            set_flash('danger', __('common.erro_csrf'));
            redirect(url('fornecedores'));
        }

        $isEdit = !empty($_POST['id']);
        $dados  = $_POST;
        $errors = validar_fornecedor($dados, $isEdit);

        if (!empty($errors)) {
            $fornecedor = $dados;
            require __DIR__ . '/../views/fornecedores/form.php';
            break;
        }

        try {
            $pdo = db();
            $pdo->beginTransaction();

            if ($isEdit) {
                $id = $dados['id'];
                $fornecedorAnterior = buscar_fornecedor_por_id($id);

                if (!$fornecedorAnterior) {
                    $pdo->rollBack();
                    set_flash('danger', __('fornecedores.erro_nao_encontrado'));
                    redirect(url('fornecedores'));
                }

                $stmt = $pdo->prepare("UPDATE fornecedores SET
                    tipo_pessoa = :tipo_pessoa, cpf_cnpj = :cpf_cnpj, razao_social = :razao,
                    nome_fantasia = :fantasia, email = :email, telefone = :fone,
                    celular = :cel, cep = :cep, logradouro = :logr, numero = :num,
                    complemento = :compl, bairro = :bairro, cidade = :cidade, uf = :uf,
                    banco = :banco, agencia = :agencia, conta = :conta, chave_pix = :pix,
                    dados_bancarios_adicionais = :dados_banc, categoria = :categoria,
                    observacoes = :obs, status = :status
                    WHERE id = :id AND empresa_id = :empresa_id");

                $stmt->execute([
                    ':tipo_pessoa'  => $dados['tipo_pessoa'] ?? 'PJ',
                    ':cpf_cnpj'     => trim($dados['cpf_cnpj']),
                    ':razao'        => trim($dados['razao_social']),
                    ':fantasia'     => trim($dados['nome_fantasia'] ?? ''),
                    ':email'        => trim($dados['email'] ?? ''),
                    ':fone'         => trim($dados['telefone'] ?? ''),
                    ':cel'          => trim($dados['celular'] ?? ''),
                    ':cep'          => trim($dados['cep'] ?? ''),
                    ':logr'         => trim($dados['logradouro'] ?? ''),
                    ':num'          => trim($dados['numero'] ?? ''),
                    ':compl'        => trim($dados['complemento'] ?? ''),
                    ':bairro'       => trim($dados['bairro'] ?? ''),
                    ':cidade'       => trim($dados['cidade'] ?? ''),
                    ':uf'           => trim($dados['uf'] ?? ''),
                    ':banco'        => trim($dados['banco'] ?? ''),
                    ':agencia'      => trim($dados['agencia'] ?? ''),
                    ':conta'        => trim($dados['conta'] ?? ''),
                    ':pix'          => trim($dados['chave_pix'] ?? ''),
                    ':dados_banc'   => trim($dados['dados_bancarios_adicionais'] ?? ''),
                    ':categoria'    => trim($dados['categoria'] ?? ''),
                    ':obs'          => trim($dados['observacoes'] ?? ''),
                    ':status'       => $dados['status'] ?? $fornecedorAnterior['status'],
                    ':id'           => $id,
                    ':empresa_id'   => current_empresa_id(),
                ]);

                $pdo->commit();

                $fornecedorNovo = buscar_fornecedor_por_id($id);
                log_info('fornecedores', 'editar',
                    'Fornecedor editado: ' . $fornecedorNovo['codigo'] . ' – ' . $fornecedorNovo['razao_social'],
                    $id, $fornecedorNovo['codigo'], $fornecedorAnterior, $fornecedorNovo);

                set_flash('success', __('fornecedores.sucesso_editado'));

            } else {
                $id     = generate_uuid();
                $codigo = gerar_proximo_codigo_fornecedor();

                $stmt = $pdo->prepare("INSERT INTO fornecedores
                    (id, codigo, empresa_id, tipo_pessoa, cpf_cnpj, razao_social, nome_fantasia,
                     email, telefone, celular, cep, logradouro, numero, complemento,
                     bairro, cidade, uf, banco, agencia, conta, chave_pix,
                     dados_bancarios_adicionais, categoria, observacoes, status)
                    VALUES
                    (:id, :codigo, :empresa_id, :tipo_pessoa, :cpf_cnpj, :razao, :fantasia,
                     :email, :fone, :cel, :cep, :logr, :num, :compl,
                     :bairro, :cidade, :uf, :banco, :agencia, :conta, :pix,
                     :dados_banc, :categoria, :obs, 'ativo')");

                $stmt->execute([
                    ':id'           => $id,
                    ':codigo'       => $codigo,
                    ':empresa_id'   => current_empresa_id(),
                    ':tipo_pessoa'  => $dados['tipo_pessoa'] ?? 'PJ',
                    ':cpf_cnpj'     => trim($dados['cpf_cnpj']),
                    ':razao'        => trim($dados['razao_social']),
                    ':fantasia'     => trim($dados['nome_fantasia'] ?? ''),
                    ':email'        => trim($dados['email'] ?? ''),
                    ':fone'         => trim($dados['telefone'] ?? ''),
                    ':cel'          => trim($dados['celular'] ?? ''),
                    ':cep'          => trim($dados['cep'] ?? ''),
                    ':logr'         => trim($dados['logradouro'] ?? ''),
                    ':num'          => trim($dados['numero'] ?? ''),
                    ':compl'        => trim($dados['complemento'] ?? ''),
                    ':bairro'       => trim($dados['bairro'] ?? ''),
                    ':cidade'       => trim($dados['cidade'] ?? ''),
                    ':uf'           => trim($dados['uf'] ?? ''),
                    ':banco'        => trim($dados['banco'] ?? ''),
                    ':agencia'      => trim($dados['agencia'] ?? ''),
                    ':conta'        => trim($dados['conta'] ?? ''),
                    ':pix'          => trim($dados['chave_pix'] ?? ''),
                    ':dados_banc'   => trim($dados['dados_bancarios_adicionais'] ?? ''),
                    ':categoria'    => trim($dados['categoria'] ?? ''),
                    ':obs'          => trim($dados['observacoes'] ?? ''),
                ]);

                $pdo->commit();

                $fornecedorCriado = buscar_fornecedor_por_id($id);
                log_info('fornecedores', 'criar',
                    'Fornecedor criado: ' . $codigo . ' – ' . trim($dados['razao_social']),
                    $id, $codigo, null, $fornecedorCriado);

                set_flash('success', __('fornecedores.sucesso_criado'));
            }

            redirect(url('fornecedores'));

        } catch (Exception $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            log_error('fornecedores', $isEdit ? 'editar' : 'criar', 'Erro ao salvar fornecedor: ' . $ex->getMessage());
            set_flash('danger', __('fornecedores.erro_salvar'));
            redirect(url('fornecedores'));
        }
        break;

    // ----------------------------------------------------------
    // BLOQUEAR
    // ----------------------------------------------------------
    case 'block':
        $id = $_GET['id'] ?? '';
        $fornecedor = $id ? buscar_fornecedor_por_id($id) : null;
        if (!$fornecedor) {
            set_flash('danger', __('fornecedores.erro_nao_encontrado'));
            redirect(url('fornecedores'));
        }
        $pdo = db();
        $pdo->prepare("UPDATE fornecedores SET status = 'bloqueado' WHERE id = :id AND empresa_id = :eid")
            ->execute([':id' => $id, ':eid' => current_empresa_id()]);
        log_info('fornecedores', 'bloquear', 'Fornecedor bloqueado: ' . $fornecedor['codigo'] . ' – ' . $fornecedor['razao_social'], $id, $fornecedor['codigo']);
        set_flash('success', __('fornecedores.sucesso_bloqueado'));
        redirect(url('fornecedores'));
        break;

    // ----------------------------------------------------------
    // DESBLOQUEAR
    // ----------------------------------------------------------
    case 'unblock':
        $id = $_GET['id'] ?? '';
        $fornecedor = $id ? buscar_fornecedor_por_id($id) : null;
        if (!$fornecedor) {
            set_flash('danger', __('fornecedores.erro_nao_encontrado'));
            redirect(url('fornecedores'));
        }
        $pdo = db();
        $pdo->prepare("UPDATE fornecedores SET status = 'ativo' WHERE id = :id AND empresa_id = :eid")
            ->execute([':id' => $id, ':eid' => current_empresa_id()]);
        log_info('fornecedores', 'desbloquear', 'Fornecedor desbloqueado: ' . $fornecedor['codigo'] . ' – ' . $fornecedor['razao_social'], $id, $fornecedor['codigo']);
        set_flash('success', __('fornecedores.sucesso_desbloqueado'));
        redirect(url('fornecedores'));
        break;

    // ----------------------------------------------------------
    // EXCLUIR
    // ----------------------------------------------------------
    case 'delete':
        $id = $_GET['id'] ?? '';
        $fornecedor = $id ? buscar_fornecedor_por_id($id) : null;
        if (!$fornecedor) {
            set_flash('danger', __('fornecedores.erro_nao_encontrado'));
            redirect(url('fornecedores'));
        }
        try {
            $pdo = db();
            $pdo->prepare("DELETE FROM fornecedores WHERE id = :id AND empresa_id = :eid")
                ->execute([':id' => $id, ':eid' => current_empresa_id()]);
            log_info('fornecedores', 'excluir', 'Fornecedor excluído: ' . $fornecedor['codigo'] . ' – ' . $fornecedor['razao_social'], $id, $fornecedor['codigo'], $fornecedor, null);
            set_flash('success', __('fornecedores.sucesso_excluido'));
        } catch (Exception $ex) {
            log_error('fornecedores', 'excluir', 'Erro ao excluir fornecedor: ' . $ex->getMessage());
            set_flash('danger', __('fornecedores.erro_excluir'));
        }
        redirect(url('fornecedores'));
        break;

    // ----------------------------------------------------------
    // LISTAGEM (INDEX)
    // ----------------------------------------------------------
    case 'index':
    default:
        $porPagina = 15;
        $pagina    = max(1, (int) ($_GET['pg'] ?? 1));
        $filtros   = [
            'busca'     => $_GET['busca'] ?? '',
            'status'    => $_GET['status'] ?? '',
            'categoria' => $_GET['categoria'] ?? '',
        ];

        $resultado     = buscar_fornecedores($filtros, $pagina, $porPagina);
        $fornecedores  = $resultado['fornecedores'];
        $total         = $resultado['total'];
        $totalPaginas  = (int) ceil($total / $porPagina);

        require __DIR__ . '/../views/fornecedores/index.php';
        break;
}
