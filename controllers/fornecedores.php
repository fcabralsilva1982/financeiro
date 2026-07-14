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

require_once __DIR__ . '/../helpers/functions.php';

$action = $action ?? ($_GET['action'] ?? 'index');

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
    } elseif (!empty($filtros['empresa_id'])) {
        $where[] = "f.empresa_id = :empresa_filtro";
        $params[':empresa_filtro'] = (string) $filtros['empresa_id'];
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
 * Lista empresas ativas para filtro da listagem (somente para master).
 *
 * @return array<int, array<string, mixed>>
 */
function buscar_empresas_para_filtro_fornecedores(): array {
    if (!can_see_all_empresas()) {
        return [];
    }

    $pdo = db();
    $stmt = $pdo->query("SELECT id, codigo, nome_fantasia FROM empresas WHERE status = 'ativo' ORDER BY codigo ASC");
    return $stmt->fetchAll();
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

function listar_documentos_fornecedor(string $fornecedorId): array {
    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT id, fornecedor_id, empresa_id, tipo_documento, nome_original, arquivo_path, mime_type, tamanho_bytes, criado_em
         FROM fornecedor_documentos
         WHERE fornecedor_id = :fornecedor_id AND empresa_id = :empresa_id
         ORDER BY criado_em DESC"
    );
    $stmt->execute([
        ':fornecedor_id' => $fornecedorId,
        ':empresa_id' => current_empresa_id(),
    ]);
    return $stmt->fetchAll();
}

function processar_upload_documento_fornecedor(string $fornecedorId): ?array {
    if (upload_rate_limit_exceeded('fornecedor_documento')) {
        registrar_tentativa_upload('fornecedor_documento', 'bloqueado_rate_limit');
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
        registrar_tentativa_upload('fornecedor_documento', 'arquivo_invalido');
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
        registrar_tentativa_upload('fornecedor_documento', 'mime_nao_permitido');
        throw new RuntimeException('Formato de documento nao permitido. Use PDF ou imagem.');
    }

    $destinoDir = upload_storage_root() . '/fornecedor_documentos';
    if (!is_dir($destinoDir) && !mkdir($destinoDir, 0775, true) && !is_dir($destinoDir)) {
        throw new RuntimeException('Nao foi possivel preparar diretorio de documentos do fornecedor.');
    }

    $quarantineDir = str_replace('\\', '/', rtrim((string) UPLOAD_QUARANTINE_PATH, '/'));
    if (!is_dir($quarantineDir) && !mkdir($quarantineDir, 0775, true) && !is_dir($quarantineDir)) {
        throw new RuntimeException('Nao foi possivel preparar diretorio de quarentena.');
    }

    $arquivo = 'for_' . preg_replace('/[^a-zA-Z0-9]/', '', $fornecedorId) . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $permitidos[$mime];
    $destinoAbs = $destinoDir . '/' . $arquivo;
    $quarantineAbs = $quarantineDir . '/q_' . $arquivo;

    if (!move_uploaded_file($tmpName, $quarantineAbs)) {
        registrar_tentativa_upload('fornecedor_documento', 'falha_quarentena');
        throw new RuntimeException('Falha ao salvar documento no servidor.');
    }

    try {
        scan_uploaded_file_or_fail($quarantineAbs, 'fornecedor_documento');
    } catch (Throwable $e) {
        if (is_file($quarantineAbs)) {
            unlink($quarantineAbs);
        }
        registrar_tentativa_upload('fornecedor_documento', 'scan_reprovado');
        throw $e;
    }

    if (!rename($quarantineAbs, $destinoAbs)) {
        if (is_file($quarantineAbs)) {
            unlink($quarantineAbs);
        }
        registrar_tentativa_upload('fornecedor_documento', 'falha_movimentacao_final');
        throw new RuntimeException('Falha ao finalizar armazenamento do documento.');
    }

    registrar_tentativa_upload('fornecedor_documento', 'sucesso');

    return [
        'tipo_documento' => (string) ($_POST['tipo_documento'] ?? 'outro'),
        'nome_original' => (string) ($_FILES['documento']['name'] ?? $arquivo),
        'arquivo_path' => 'fornecedor_documentos/' . $arquivo,
        'mime_type' => $mime,
        'tamanho_bytes' => $tamanho,
    ];
}

function salvar_documento_fornecedor(string $fornecedorId, array $documento): void {
    $tiposValidos = ['rg', 'cpf', 'cnh', 'comprovante_residencia', 'cartao_cnpj', 'contrato_social', 'inscricao_estadual', 'procuracao', 'foto_frente', 'foto_verso', 'outro'];
    $tipoDocumento = in_array($documento['tipo_documento'] ?? '', $tiposValidos, true)
        ? $documento['tipo_documento']
        : 'outro';

    $pdo = db();
    $stmt = $pdo->prepare(
        "INSERT INTO fornecedor_documentos
            (id, fornecedor_id, empresa_id, tipo_documento, nome_original, arquivo_path, mime_type, tamanho_bytes)
         VALUES
            (:id, :fornecedor_id, :empresa_id, :tipo_documento, :nome_original, :arquivo_path, :mime_type, :tamanho_bytes)"
    );
    $stmt->execute([
        ':id' => generate_uuid(),
        ':fornecedor_id' => $fornecedorId,
        ':empresa_id' => current_empresa_id(),
        ':tipo_documento' => $tipoDocumento,
        ':nome_original' => $documento['nome_original'],
        ':arquivo_path' => $documento['arquivo_path'],
        ':mime_type' => $documento['mime_type'],
        ':tamanho_bytes' => $documento['tamanho_bytes'],
    ]);
}

function excluir_arquivo_documento_fornecedor(string $arquivoPath): void {
    $relativo = normalize_fornecedor_document_relative_path($arquivoPath);
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
        $fornecedor = null;
        $isEdit  = false;
        $errors  = [];
        $documentosFornecedor = [];
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
        $documentosFornecedor = listar_documentos_fornecedor((string) $id);
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
            $documentosFornecedor = ($isEdit && !empty($dados['id']))
                ? listar_documentos_fornecedor((string) $dados['id'])
                : [];
            require __DIR__ . '/../views/fornecedores/form.php';
            break;
        }

        $pdo = null;
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
            if ($pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            log_error('fornecedores', $isEdit ? 'editar' : 'criar', 'Erro ao salvar fornecedor: ' . $ex->getMessage());
            set_flash('danger', __('fornecedores.erro_salvar'));
            redirect(url('fornecedores'));
        }
        break;

    case 'upload_documento':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(url('fornecedores'));
        }
        if (!validar_csrf_fornecedores()) {
            set_flash('danger', __('common.erro_csrf'));
            redirect(url('fornecedores'));
        }

        $fornecedorId = (string) ($_POST['fornecedor_id'] ?? '');
        $fornecedor = $fornecedorId !== '' ? buscar_fornecedor_por_id($fornecedorId) : null;
        if (!$fornecedor) {
            set_flash('danger', __('fornecedores.erro_nao_encontrado'));
            redirect(url('fornecedores'));
        }

        try {
            $documento = processar_upload_documento_fornecedor($fornecedorId);
            if ($documento === null) {
                throw new RuntimeException('Selecione um documento para enviar.');
            }
            salvar_documento_fornecedor($fornecedorId, $documento);
            set_flash('success', 'Documento enviado com sucesso.');
        } catch (Throwable $e) {
            set_flash('danger', $e->getMessage());
        }
        redirect(url('fornecedores', 'edit', ['id' => $fornecedorId]));
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
            "SELECT id, fornecedor_id, empresa_id, nome_original, arquivo_path, mime_type
             FROM fornecedor_documentos
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

        $relativo = normalize_fornecedor_document_relative_path((string) $documento['arquivo_path']);
        if ($relativo === null) {
            http_response_code(403);
            echo 'Acesso negado.';
            exit;
        }

        $arquivoAbs = realpath(upload_storage_root() . '/' . $relativo);
        $base = realpath(upload_storage_root() . '/fornecedor_documentos');
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
        if (!enforce_post_request()) {
            break;
        }
        if (!validar_csrf_fornecedores()) {
            set_flash('danger', __('common.erro_csrf'));
            redirect(url('fornecedores'));
        }

        $documentoId = (string) ($_POST['documento_id'] ?? '');
        $fornecedorId = (string) ($_POST['fornecedor_id'] ?? '');
        if ($documentoId === '' || $fornecedorId === '') {
            set_flash('danger', 'Documento nao encontrado.');
            redirect(url('fornecedores'));
        }

        $pdo = db();
        $stmt = $pdo->prepare(
            "SELECT id, arquivo_path
             FROM fornecedor_documentos
             WHERE id = :id AND empresa_id = :empresa_id AND fornecedor_id = :fornecedor_id
             LIMIT 1"
        );
        $stmt->execute([
            ':id' => $documentoId,
            ':empresa_id' => current_empresa_id(),
            ':fornecedor_id' => $fornecedorId,
        ]);
        $documento = $stmt->fetch();
        if (!$documento) {
            set_flash('danger', 'Documento nao encontrado.');
            redirect(url('fornecedores', 'edit', ['id' => $fornecedorId]));
        }

        $pdo->prepare('DELETE FROM fornecedor_documentos WHERE id = :id AND empresa_id = :empresa_id')
            ->execute([':id' => $documentoId, ':empresa_id' => current_empresa_id()]);
        excluir_arquivo_documento_fornecedor((string) $documento['arquivo_path']);
        set_flash('success', 'Documento excluido com sucesso.');
        redirect(url('fornecedores', 'edit', ['id' => $fornecedorId]));
        break;

    // ----------------------------------------------------------
    // BLOQUEAR
    // ----------------------------------------------------------
    case 'block':
        if (!enforce_post_request()) {
            break;
        }
        if (!validar_csrf_fornecedores()) {
            set_flash('danger', __('common.erro_csrf'));
            redirect(url('fornecedores'));
        }

        $id = $_POST['id'] ?? '';
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
        if (!enforce_post_request()) {
            break;
        }
        if (!validar_csrf_fornecedores()) {
            set_flash('danger', __('common.erro_csrf'));
            redirect(url('fornecedores'));
        }

        $id = $_POST['id'] ?? '';
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
        if (!enforce_post_request()) {
            break;
        }
        if (!validar_csrf_fornecedores()) {
            set_flash('danger', __('common.erro_csrf'));
            redirect(url('fornecedores'));
        }

        $id = $_POST['id'] ?? '';
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
            'empresa_id' => $_GET['empresa_id'] ?? '',
        ];

        $empresasFiltro = buscar_empresas_para_filtro_fornecedores();
        $resultado     = buscar_fornecedores($filtros, $pagina, $porPagina);
        $fornecedores  = $resultado['fornecedores'];
        $total         = $resultado['total'];
        $totalPaginas  = (int) ceil($total / $porPagina);

        require __DIR__ . '/../views/fornecedores/index.php';
        break;
}
