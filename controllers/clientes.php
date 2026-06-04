<?php
declare(strict_types=1);

/**
 * Controller: Clientes
 *
 * CRUD completo de clientes (PF e PJ) com banco de dados MySQL.
 * Inclui validação de CPF/CNPJ, filtros de busca, paginação e multi-tenancy.
 *
 * Ações disponíveis (via $action):
 *   - index (default): listagem paginada com filtros (busca, status, tipo, categoria)
 *   - create: exibe formulário de novo cliente
 *   - edit: exibe formulário de edição com dados carregados do banco
 *   - store: INSERT ou UPDATE (POST) com validação, transação e log
 *   - block/unblock: altera status entre 'ativo' e 'bloqueado'
 *   - delete: exclui cliente
 *
 * Multi-tenancy: registros filtrados por empresa_id do usuário logado.
 */

// ============================================================
// FUNÇÕES DE CONSULTA
// ============================================================

/**
 * Busca clientes no banco com filtros opcionais e paginação.
 *
 * @param array $filtros   Filtros: 'busca', 'status', 'tipo_pessoa', 'categoria'
 * @param int   $pagina    Número da página (começa em 1)
 * @param int   $porPagina Registros por página (padrão: 15)
 * @return array ['clientes' => array[], 'total' => int]
 */
function buscar_clientes($filtros = [], $pagina = 1, $porPagina = 15) {
    $pdo = db();
    $where = [];
    $params = [];

    // Multi-tenancy
    if (!can_see_all_empresas()) {
        $where[] = "c.empresa_id = :empresa_id";
        $params[':empresa_id'] = current_empresa_id();
    }

    if (!empty($filtros['busca'])) {
        $termo = '%' . $filtros['busca'] . '%';
        $where[] = "(c.razao_social LIKE :b1 OR c.nome_fantasia LIKE :b2 OR c.cpf_cnpj LIKE :b3 OR c.email LIKE :b4 OR c.celular LIKE :b5 OR c.codigo LIKE :b6)";
        $params[':b1'] = $termo;
        $params[':b2'] = $termo;
        $params[':b3'] = $termo;
        $params[':b4'] = $termo;
        $params[':b5'] = $termo;
        $params[':b6'] = $termo;
    }

    if (!empty($filtros['status'])) {
        $where[] = "c.status = :status";
        $params[':status'] = $filtros['status'];
    }

    if (!empty($filtros['tipo_pessoa'])) {
        $where[] = "c.tipo_pessoa = :tipo_pessoa";
        $params[':tipo_pessoa'] = $filtros['tipo_pessoa'];
    }

    if (!empty($filtros['categoria'])) {
        $where[] = "c.categoria = :categoria";
        $params[':categoria'] = $filtros['categoria'];
    }

    $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Total
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM clientes c {$whereSql}");
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    // Dados paginados
    $offset = ($pagina - 1) * $porPagina;
    $sql = "SELECT c.* FROM clientes c {$whereSql} ORDER BY c.codigo ASC LIMIT :lim OFFSET :off";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':lim', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return ['clientes' => $stmt->fetchAll(), 'total' => $total];
}

/**
 * Busca um cliente por UUID.
 *
 * @param string $id UUID do cliente
 * @return array|null Dados do cliente ou null se não encontrado
 */
function buscar_cliente_por_id($id) {
    $pdo = db();
    $sql = "SELECT * FROM clientes WHERE id = :id";
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
 * Gera o próximo código sequencial para cliente (CLI-XXXX).
 *
 * @return string Código no formato 'CLI-XXXX'
 */
function gerar_proximo_codigo_cliente() {
    $pdo = db();
    $stmt = $pdo->query("SELECT codigo FROM clientes ORDER BY codigo DESC LIMIT 1");
    $ultimo = $stmt->fetchColumn();

    $seq = 1;
    if ($ultimo && preg_match('/CLI-(\d+)/', $ultimo, $m)) {
        $seq = (int) $m[1] + 1;
    }

    return generate_codigo('CLI', $seq);
}

// ============================================================
// VALIDAÇÃO
// ============================================================

/**
 * Valida o token CSRF enviado via POST.
 *
 * @return bool
 */
function validar_csrf_clientes() {
    $enviado = $_POST['csrf_token'] ?? '';
    $sessao  = $_SESSION['csrf_token'] ?? '';
    return $enviado !== '' && $sessao !== '' && hash_equals($sessao, $enviado);
}

/**
 * Valida os dados de um cliente antes de salvar.
 *
 * @param array $dados  Dados do formulário ($_POST)
 * @param bool  $isEdit true se for edição
 * @return array Erros indexados por campo. Vazio se válido.
 */
function validar_cliente($dados, $isEdit = false) {
    $erros = [];

    // CPF/CNPJ obrigatório e único por empresa
    $cpfCnpj = trim($dados['cpf_cnpj'] ?? '');
    if ($cpfCnpj === '') {
        $erros['cpf_cnpj'] = __('clientes.erro_cpf_cnpj_obrigatorio');
    } else {
        $pdo = db();
        $sql = "SELECT id FROM clientes WHERE cpf_cnpj = :cpf_cnpj AND empresa_id = :empresa_id";
        $p = [':cpf_cnpj' => $cpfCnpj, ':empresa_id' => current_empresa_id()];
        if ($isEdit && !empty($dados['id'])) {
            $sql .= " AND id != :id";
            $p[':id'] = $dados['id'];
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($p);
        if ($stmt->fetch()) {
            $erros['cpf_cnpj'] = __('clientes.erro_cpf_cnpj_duplicado');
        }
    }

    // Razão Social / Nome obrigatório
    if (trim($dados['razao_social'] ?? '') === '') {
        $erros['razao_social'] = __('clientes.erro_razao_obrigatoria');
    }

    // E-mail obrigatório
    if (trim($dados['email'] ?? '') === '') {
        $erros['email'] = __('clientes.erro_email_obrigatorio');
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
        $cliente = null;
        $isEdit  = false;
        $errors  = [];
        require __DIR__ . '/../views/clientes/form.php';
        break;

    // ----------------------------------------------------------
    // EDITAR
    // ----------------------------------------------------------
    case 'edit':
        $id = $_GET['id'] ?? '';
        if (empty($id)) {
            set_flash('danger', __('clientes.erro_nao_encontrado'));
            redirect(url('clientes'));
        }
        $cliente = buscar_cliente_por_id($id);
        if (!$cliente) {
            set_flash('danger', __('clientes.erro_nao_encontrado'));
            redirect(url('clientes'));
        }
        $isEdit = true;
        $errors = [];
        require __DIR__ . '/../views/clientes/form.php';
        break;

    // ----------------------------------------------------------
    // SALVAR (INSERT / UPDATE)
    // ----------------------------------------------------------
    case 'store':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(url('clientes'));
        }
        if (!validar_csrf_clientes()) {
            set_flash('danger', __('common.erro_csrf'));
            redirect(url('clientes'));
        }

        $isEdit = !empty($_POST['id']);
        $dados  = $_POST;
        $errors = validar_cliente($dados, $isEdit);

        if (!empty($errors)) {
            $cliente = $dados;
            require __DIR__ . '/../views/clientes/form.php';
            break;
        }

        try {
            $pdo = db();
            $pdo->beginTransaction();

            if ($isEdit) {
                $id = $dados['id'];
                $clienteAnterior = buscar_cliente_por_id($id);

                if (!$clienteAnterior) {
                    $pdo->rollBack();
                    set_flash('danger', __('clientes.erro_nao_encontrado'));
                    redirect(url('clientes'));
                }

                $stmt = $pdo->prepare("UPDATE clientes SET
                    tipo_pessoa = :tipo_pessoa, cpf_cnpj = :cpf_cnpj, razao_social = :razao,
                    nome_fantasia = :fantasia, email = :email, telefone = :fone,
                    celular = :cel, cep = :cep, logradouro = :logr, numero = :num,
                    complemento = :compl, bairro = :bairro, cidade = :cidade, uf = :uf,
                    prazo_pagamento = :prazo, forma_pagamento = :forma, limite_credito = :limite,
                    email_fatura = :email_fatura, preferencia_canal = :canal,
                    opt_out_lembretes = :opt_out, categoria = :categoria, tags = :tags,
                    observacoes = :obs, status = :status
                    WHERE id = :id AND empresa_id = :empresa_id");

                $stmt->execute([
                    ':tipo_pessoa'   => $dados['tipo_pessoa'] ?? 'PJ',
                    ':cpf_cnpj'      => trim($dados['cpf_cnpj']),
                    ':razao'         => trim($dados['razao_social']),
                    ':fantasia'      => trim($dados['nome_fantasia'] ?? ''),
                    ':email'         => trim($dados['email']),
                    ':fone'          => trim($dados['telefone'] ?? ''),
                    ':cel'           => trim($dados['celular'] ?? ''),
                    ':cep'           => trim($dados['cep'] ?? ''),
                    ':logr'          => trim($dados['logradouro'] ?? ''),
                    ':num'           => trim($dados['numero'] ?? ''),
                    ':compl'         => trim($dados['complemento'] ?? ''),
                    ':bairro'        => trim($dados['bairro'] ?? ''),
                    ':cidade'        => trim($dados['cidade'] ?? ''),
                    ':uf'            => trim($dados['uf'] ?? ''),
                    ':prazo'         => !empty($dados['prazo_pagamento']) ? (int) $dados['prazo_pagamento'] : null,
                    ':forma'         => !empty($dados['forma_pagamento']) ? $dados['forma_pagamento'] : null,
                    ':limite'        => !empty($dados['limite_credito']) ? (float) $dados['limite_credito'] : null,
                    ':email_fatura'  => trim($dados['email_fatura'] ?? ''),
                    ':canal'         => !empty($dados['preferencia_canal']) ? $dados['preferencia_canal'] : 'email',
                    ':opt_out'       => !empty($dados['opt_out_lembretes']) ? 1 : 0,
                    ':categoria'     => !empty($dados['categoria']) ? $dados['categoria'] : null,
                    ':tags'          => trim($dados['tags'] ?? ''),
                    ':obs'           => trim($dados['observacoes'] ?? ''),
                    ':status'        => $dados['status'] ?? $clienteAnterior['status'],
                    ':id'            => $id,
                    ':empresa_id'    => current_empresa_id(),
                ]);

                $pdo->commit();

                $clienteNovo = buscar_cliente_por_id($id);
                log_info('clientes', 'editar',
                    'Cliente editado: ' . $clienteNovo['codigo'] . ' – ' . $clienteNovo['razao_social'],
                    $id, $clienteNovo['codigo'], $clienteAnterior, $clienteNovo);

                set_flash('success', __('clientes.sucesso_editado'));

            } else {
                $id     = generate_uuid();
                $codigo = gerar_proximo_codigo_cliente();

                $stmt = $pdo->prepare("INSERT INTO clientes
                    (id, codigo, empresa_id, tipo_pessoa, cpf_cnpj, razao_social, nome_fantasia,
                     email, telefone, celular, cep, logradouro, numero, complemento,
                     bairro, cidade, uf, prazo_pagamento, forma_pagamento, limite_credito,
                     email_fatura, preferencia_canal, opt_out_lembretes, categoria, tags,
                     observacoes, status)
                    VALUES
                    (:id, :codigo, :empresa_id, :tipo_pessoa, :cpf_cnpj, :razao, :fantasia,
                     :email, :fone, :cel, :cep, :logr, :num, :compl,
                     :bairro, :cidade, :uf, :prazo, :forma, :limite,
                     :email_fatura, :canal, :opt_out, :categoria, :tags,
                     :obs, 'ativo')");

                $stmt->execute([
                    ':id'            => $id,
                    ':codigo'        => $codigo,
                    ':empresa_id'    => current_empresa_id(),
                    ':tipo_pessoa'   => $dados['tipo_pessoa'] ?? 'PJ',
                    ':cpf_cnpj'      => trim($dados['cpf_cnpj']),
                    ':razao'         => trim($dados['razao_social']),
                    ':fantasia'      => trim($dados['nome_fantasia'] ?? ''),
                    ':email'         => trim($dados['email']),
                    ':fone'          => trim($dados['telefone'] ?? ''),
                    ':cel'           => trim($dados['celular'] ?? ''),
                    ':cep'           => trim($dados['cep'] ?? ''),
                    ':logr'          => trim($dados['logradouro'] ?? ''),
                    ':num'           => trim($dados['numero'] ?? ''),
                    ':compl'         => trim($dados['complemento'] ?? ''),
                    ':bairro'        => trim($dados['bairro'] ?? ''),
                    ':cidade'        => trim($dados['cidade'] ?? ''),
                    ':uf'            => trim($dados['uf'] ?? ''),
                    ':prazo'         => !empty($dados['prazo_pagamento']) ? (int) $dados['prazo_pagamento'] : null,
                    ':forma'         => !empty($dados['forma_pagamento']) ? $dados['forma_pagamento'] : null,
                    ':limite'        => !empty($dados['limite_credito']) ? (float) $dados['limite_credito'] : null,
                    ':email_fatura'  => trim($dados['email_fatura'] ?? ''),
                    ':canal'         => !empty($dados['preferencia_canal']) ? $dados['preferencia_canal'] : 'email',
                    ':opt_out'       => !empty($dados['opt_out_lembretes']) ? 1 : 0,
                    ':categoria'     => !empty($dados['categoria']) ? $dados['categoria'] : null,
                    ':tags'          => trim($dados['tags'] ?? ''),
                    ':obs'           => trim($dados['observacoes'] ?? ''),
                ]);

                $pdo->commit();

                $clienteCriado = buscar_cliente_por_id($id);
                log_info('clientes', 'criar',
                    'Cliente criado: ' . $codigo . ' – ' . trim($dados['razao_social']),
                    $id, $codigo, null, $clienteCriado);

                set_flash('success', __('clientes.sucesso_criado'));
            }

            redirect(url('clientes'));

        } catch (Exception $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            log_error('clientes', $isEdit ? 'editar' : 'criar', 'Erro ao salvar cliente: ' . $ex->getMessage());
            set_flash('danger', __('clientes.erro_salvar'));
            redirect(url('clientes'));
        }
        break;

    // ----------------------------------------------------------
    // BLOQUEAR
    // ----------------------------------------------------------
    case 'block':
        $id = $_GET['id'] ?? '';
        $cliente = $id ? buscar_cliente_por_id($id) : null;
        if (!$cliente) {
            set_flash('danger', __('clientes.erro_nao_encontrado'));
            redirect(url('clientes'));
        }
        $pdo = db();
        $pdo->prepare("UPDATE clientes SET status = 'bloqueado' WHERE id = :id AND empresa_id = :eid")
            ->execute([':id' => $id, ':eid' => current_empresa_id()]);
        log_info('clientes', 'bloquear', 'Cliente bloqueado: ' . $cliente['codigo'] . ' – ' . $cliente['razao_social'], $id, $cliente['codigo']);
        set_flash('success', __('clientes.sucesso_bloqueado'));
        redirect(url('clientes'));
        break;

    // ----------------------------------------------------------
    // DESBLOQUEAR
    // ----------------------------------------------------------
    case 'unblock':
        $id = $_GET['id'] ?? '';
        $cliente = $id ? buscar_cliente_por_id($id) : null;
        if (!$cliente) {
            set_flash('danger', __('clientes.erro_nao_encontrado'));
            redirect(url('clientes'));
        }
        $pdo = db();
        $pdo->prepare("UPDATE clientes SET status = 'ativo' WHERE id = :id AND empresa_id = :eid")
            ->execute([':id' => $id, ':eid' => current_empresa_id()]);
        log_info('clientes', 'desbloquear', 'Cliente desbloqueado: ' . $cliente['codigo'] . ' – ' . $cliente['razao_social'], $id, $cliente['codigo']);
        set_flash('success', __('clientes.sucesso_desbloqueado'));
        redirect(url('clientes'));
        break;

    // ----------------------------------------------------------
    // EXCLUIR
    // ----------------------------------------------------------
    case 'delete':
        $id = $_GET['id'] ?? '';
        $cliente = $id ? buscar_cliente_por_id($id) : null;
        if (!$cliente) {
            set_flash('danger', __('clientes.erro_nao_encontrado'));
            redirect(url('clientes'));
        }
        try {
            $pdo = db();
            $pdo->prepare("DELETE FROM clientes WHERE id = :id AND empresa_id = :eid")
                ->execute([':id' => $id, ':eid' => current_empresa_id()]);
            log_info('clientes', 'excluir', 'Cliente excluído: ' . $cliente['codigo'] . ' – ' . $cliente['razao_social'], $id, $cliente['codigo'], $cliente, null);
            set_flash('success', __('clientes.sucesso_excluido'));
        } catch (Exception $ex) {
            log_error('clientes', 'excluir', 'Erro ao excluir cliente: ' . $ex->getMessage());
            set_flash('danger', __('clientes.erro_excluir'));
        }
        redirect(url('clientes'));
        break;

    // ----------------------------------------------------------
    // LISTAGEM (INDEX)
    // ----------------------------------------------------------
    case 'index':
    default:
        $porPagina = 15;
        $pagina    = max(1, (int) ($_GET['pg'] ?? 1));
        $filtros   = [
            'busca'       => $_GET['busca'] ?? '',
            'status'      => $_GET['status'] ?? '',
            'tipo_pessoa' => $_GET['tipo_pessoa'] ?? '',
            'categoria'   => $_GET['categoria'] ?? '',
        ];

        $resultado    = buscar_clientes($filtros, $pagina, $porPagina);
        $clientes     = $resultado['clientes'];
        $total        = $resultado['total'];
        $totalPaginas = (int) ceil($total / $porPagina);

        require __DIR__ . '/../views/clientes/index.php';
        break;
}
