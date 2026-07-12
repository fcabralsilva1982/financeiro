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

$action = $action ?? ($_GET['action'] ?? 'index');

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

function listar_documentos_cliente(string $clienteId): array {
    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT id, cliente_id, empresa_id, tipo_documento, nome_original, arquivo_path, mime_type, tamanho_bytes, criado_em
         FROM cliente_documentos
         WHERE cliente_id = :cliente_id AND empresa_id = :empresa_id
         ORDER BY criado_em DESC"
    );
    $stmt->execute([
        ':cliente_id' => $clienteId,
        ':empresa_id' => current_empresa_id(),
    ]);
    return $stmt->fetchAll();
}

function processar_upload_documento_cliente(string $clienteId): ?array {
    if (upload_rate_limit_exceeded('cliente_documento')) {
        registrar_tentativa_upload('cliente_documento', 'bloqueado_rate_limit');
        throw new RuntimeException('Limite de tentativas de upload excedido. Tente novamente em alguns minutos.');
    }

    if (!isset($_FILES['documento']) || !is_array($_FILES['documento'])) {
        return null;
    }

    if ((int) ($_FILES['documento']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ((int) ($_FILES['documento']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Falha no upload do documento.');
    }

    $tamanho = (int) ($_FILES['documento']['size'] ?? 0);
    if ($tamanho <= 0 || $tamanho > UPLOAD_COMPROVANTE_MAX_BYTES) {
        throw new RuntimeException('Documento fora do limite permitido.');
    }

    $tmpName = (string) ($_FILES['documento']['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        registrar_tentativa_upload('cliente_documento', 'arquivo_invalido');
        throw new RuntimeException('Arquivo de upload invalido.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmpName);
    $permitidos = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($permitidos[$mime])) {
        registrar_tentativa_upload('cliente_documento', 'mime_nao_permitido');
        throw new RuntimeException('Formato de documento nao permitido. Use PDF ou imagem.');
    }

    $destinoDir = upload_storage_root() . '/cliente_documentos';
    if (!is_dir($destinoDir) && !mkdir($destinoDir, 0775, true) && !is_dir($destinoDir)) {
        throw new RuntimeException('Nao foi possivel preparar diretorio de documentos do cliente.');
    }

    $quarantineDir = str_replace('\\', '/', rtrim((string) UPLOAD_QUARANTINE_PATH, '/'));
    if (!is_dir($quarantineDir) && !mkdir($quarantineDir, 0775, true) && !is_dir($quarantineDir)) {
        throw new RuntimeException('Nao foi possivel preparar diretorio de quarentena.');
    }

    $arquivo = 'cli_' . preg_replace('/[^a-zA-Z0-9]/', '', $clienteId) . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $permitidos[$mime];
    $destinoAbs = $destinoDir . '/' . $arquivo;
    $quarantineAbs = $quarantineDir . '/q_' . $arquivo;

    if (!move_uploaded_file($tmpName, $quarantineAbs)) {
        registrar_tentativa_upload('cliente_documento', 'falha_quarentena');
        throw new RuntimeException('Falha ao salvar documento no servidor.');
    }

    try {
        scan_uploaded_file_or_fail($quarantineAbs, 'cliente_documento');
    } catch (Throwable $e) {
        if (is_file($quarantineAbs)) {
            unlink($quarantineAbs);
        }
        registrar_tentativa_upload('cliente_documento', 'scan_reprovado');
        throw $e;
    }

    if (!rename($quarantineAbs, $destinoAbs)) {
        if (is_file($quarantineAbs)) {
            unlink($quarantineAbs);
        }
        registrar_tentativa_upload('cliente_documento', 'falha_movimentacao_final');
        throw new RuntimeException('Falha ao finalizar armazenamento do documento.');
    }

    registrar_tentativa_upload('cliente_documento', 'sucesso');

    return [
        'tipo_documento' => (string) ($_POST['tipo_documento'] ?? 'outro'),
        'nome_original' => (string) ($_FILES['documento']['name'] ?? $arquivo),
        'arquivo_path' => 'cliente_documentos/' . $arquivo,
        'mime_type' => $mime,
        'tamanho_bytes' => $tamanho,
    ];
}

function salvar_documento_cliente(string $clienteId, array $documento): void {
    $tiposValidos = ['rg', 'cpf', 'cnh', 'comprovante_residencia', 'cartao_cnpj', 'contrato_social', 'inscricao_estadual', 'procuracao', 'foto_frente', 'foto_verso', 'outro'];
    $tipoDocumento = in_array($documento['tipo_documento'] ?? '', $tiposValidos, true)
        ? $documento['tipo_documento']
        : 'outro';

    $pdo = db();
    $stmt = $pdo->prepare(
        "INSERT INTO cliente_documentos
            (id, cliente_id, empresa_id, tipo_documento, nome_original, arquivo_path, mime_type, tamanho_bytes)
         VALUES
            (:id, :cliente_id, :empresa_id, :tipo_documento, :nome_original, :arquivo_path, :mime_type, :tamanho_bytes)"
    );
    $stmt->execute([
        ':id' => generate_uuid(),
        ':cliente_id' => $clienteId,
        ':empresa_id' => current_empresa_id(),
        ':tipo_documento' => $tipoDocumento,
        ':nome_original' => $documento['nome_original'],
        ':arquivo_path' => $documento['arquivo_path'],
        ':mime_type' => $documento['mime_type'],
        ':tamanho_bytes' => $documento['tamanho_bytes'],
    ]);
}

function excluir_arquivo_documento_cliente(string $arquivoPath): void {
    $relativo = normalize_cliente_document_relative_path($arquivoPath);
    if ($relativo === null) {
        return;
    }

    $abs = upload_storage_root() . '/' . $relativo;
    if (is_file($abs)) {
        unlink($abs);
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
        $cliente = null;
        $isEdit  = false;
        $errors  = [];
        $documentosCliente = [];
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
        $documentosCliente = listar_documentos_cliente((string) $id);
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
            $documentosCliente = ($isEdit && !empty($dados['id']))
                ? listar_documentos_cliente((string) $dados['id'])
                : [];
            require __DIR__ . '/../views/clientes/form.php';
            break;
        }

        $pdo = null;
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
            if ($pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            log_error('clientes', $isEdit ? 'editar' : 'criar', 'Erro ao salvar cliente: ' . $ex->getMessage());
            set_flash('danger', __('clientes.erro_salvar'));
            redirect(url('clientes'));
        }
        break;

    case 'upload_documento':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(url('clientes'));
        }
        if (!validar_csrf_clientes()) {
            set_flash('danger', __('common.erro_csrf'));
            redirect(url('clientes'));
        }

        $clienteId = (string) ($_POST['cliente_id'] ?? '');
        $cliente = $clienteId !== '' ? buscar_cliente_por_id($clienteId) : null;
        if (!$cliente) {
            set_flash('danger', __('clientes.erro_nao_encontrado'));
            redirect(url('clientes'));
        }

        try {
            $documento = processar_upload_documento_cliente($clienteId);
            if ($documento === null) {
                throw new RuntimeException('Selecione um documento para enviar.');
            }
            salvar_documento_cliente($clienteId, $documento);
            set_flash('success', 'Documento enviado com sucesso.');
        } catch (Throwable $e) {
            set_flash('danger', $e->getMessage());
        }
        redirect(url('clientes', 'edit', ['id' => $clienteId]));
        break;

    case 'download_documento':
        $documentoId = (string) ($_GET['documento_id'] ?? '');
        if ($documentoId === '') {
            http_response_code(404);
            echo 'Documento nao encontrado.';
            exit;
        }

        $pdo = db();
        $stmt = $pdo->prepare(
            "SELECT id, cliente_id, empresa_id, nome_original, arquivo_path, mime_type
             FROM cliente_documentos
             WHERE id = :id AND empresa_id = :empresa_id
             LIMIT 1"
        );
        $stmt->execute([':id' => $documentoId, ':empresa_id' => current_empresa_id()]);
        $documento = $stmt->fetch();
        if (!$documento) {
            http_response_code(404);
            echo 'Documento nao encontrado.';
            exit;
        }

        $relativo = normalize_cliente_document_relative_path((string) $documento['arquivo_path']);
        if ($relativo === null) {
            http_response_code(403);
            echo 'Acesso negado.';
            exit;
        }

        $arquivoAbs = realpath(upload_storage_root() . '/' . $relativo);
        $base = realpath(upload_storage_root() . '/cliente_documentos');
        if ($arquivoAbs === false || $base === false || !str_starts_with(str_replace('\\', '/', $arquivoAbs), str_replace('\\', '/', $base)) || !is_file($arquivoAbs)) {
            http_response_code(404);
            echo 'Arquivo nao encontrado.';
            exit;
        }

        header('Content-Type: ' . ((string) ($documento['mime_type'] ?? 'application/octet-stream')));
        header('Content-Length: ' . (string) filesize($arquivoAbs));
        header('Content-Disposition: attachment; filename="' . basename((string) $documento['nome_original']) . '"');
        header('X-Content-Type-Options: nosniff');
        readfile($arquivoAbs);
        exit;

    case 'delete_documento':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(url('clientes'));
        }
        if (!validar_csrf_clientes()) {
            set_flash('danger', __('common.erro_csrf'));
            redirect(url('clientes'));
        }

        $documentoId = (string) ($_POST['documento_id'] ?? '');
        $clienteId = (string) ($_POST['cliente_id'] ?? '');
        if ($documentoId === '' || $clienteId === '') {
            set_flash('danger', 'Documento nao encontrado.');
            redirect(url('clientes'));
        }

        $pdo = db();
        $stmt = $pdo->prepare(
            "SELECT id, arquivo_path
             FROM cliente_documentos
             WHERE id = :id AND empresa_id = :empresa_id AND cliente_id = :cliente_id
             LIMIT 1"
        );
        $stmt->execute([
            ':id' => $documentoId,
            ':empresa_id' => current_empresa_id(),
            ':cliente_id' => $clienteId,
        ]);
        $documento = $stmt->fetch();
        if (!$documento) {
            set_flash('danger', 'Documento nao encontrado.');
            redirect(url('clientes', 'edit', ['id' => $clienteId]));
        }

        $pdo->prepare('DELETE FROM cliente_documentos WHERE id = :id AND empresa_id = :empresa_id')
            ->execute([':id' => $documentoId, ':empresa_id' => current_empresa_id()]);
        excluir_arquivo_documento_cliente((string) $documento['arquivo_path']);
        set_flash('success', 'Documento excluido com sucesso.');
        redirect(url('clientes', 'edit', ['id' => $clienteId]));
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
