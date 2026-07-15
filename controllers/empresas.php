<?php
declare(strict_types=1);

/**
 * Controller: Empresas
 *
 * CRUD completo de empresas com banco de dados MySQL.
 * Inclui upload de logo, gerenciamento de contatos e validação de CNPJ.
 *
 * Ações disponíveis (via $action):
 *   - index (default): listagem paginada com filtros de busca e status
 *   - create: exibe formulário de nova empresa
 *   - edit: exibe formulário de edição com dados carregados do banco
 *   - store: INSERT ou UPDATE (POST) com validação, transação e log
 *   - block/unblock: altera status entre 'ativo' e 'bloqueado'
 *   - delete: exclui empresa (impede se houver usuários vinculados)
 *
 * Acesso restrito: apenas usuários da empresa master (verificado no index.php).
 */

$action = $action ?? ($_GET['action'] ?? 'index');

// ============================================================
// FUNÇÕES DE CONSULTA
// ============================================================

/**
 * Busca empresas no banco com filtros opcionais e paginação.
 *
 * Suporta busca textual (razão social, nome fantasia, CNPJ, código)
 * e filtro por status (ativo/bloqueado). A busca usa LIKE com '%termo%'.
 *
 * @param array $filtros   Filtros opcionais: 'busca' (string), 'status' ('ativo'|'bloqueado')
 * @param int   $pagina    Número da página (começa em 1)
 * @param int   $porPagina Quantidade de registros por página (padrão: 15)
 * @return array ['empresas' => array[], 'total' => int] onde 'total' é o count sem paginação
 */
function buscar_empresas($filtros = [], $pagina = 1, $porPagina = 15) {
    $pdo = db();
    $where = [];
    $params = [];

    if (!empty($filtros['busca'])) {
        $termo = '%' . $filtros['busca'] . '%';
        $where[] = "(e.razao_social LIKE :b1 OR e.nome_fantasia LIKE :b2 OR e.cnpj LIKE :b3 OR e.cpf LIKE :b4 OR e.codigo LIKE :b5)";
        $params[':b1'] = $termo;
        $params[':b2'] = $termo;
        $params[':b3'] = $termo;
        $params[':b4'] = $termo;
        $params[':b5'] = $termo;
    }

    if (!empty($filtros['status'])) {
        $where[] = "e.status = :status";
        $params[':status'] = $filtros['status'];
    }

    $whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Total
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM empresas e {$whereSql}");
    $stmt->execute($params);
    $total = (int) $stmt->fetchColumn();

    // Dados paginados
    $offset = ($pagina - 1) * $porPagina;
    $sql = "SELECT e.* FROM empresas e {$whereSql} ORDER BY e.codigo ASC LIMIT :lim OFFSET :off";
    $stmt = $pdo->prepare($sql);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->bindValue(':lim', $porPagina, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return ['empresas' => $stmt->fetchAll(), 'total' => $total];
}

/**
 * Busca uma empresa por UUID, incluindo seus contatos.
 *
 * Retorna todos os campos da empresa mais um array 'contatos' com os
 * registros da tabela empresa_contatos ordenados por data de criação.
 *
 * @param string $id UUID da empresa (CHAR(36))
 * @return array|null Array associativo com dados da empresa e chave 'contatos', ou null se não encontrada
 */
function buscar_empresa_por_id($id) {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT id, codigo, tipo_pessoa, cnpj, cpf, inscricao_estadual, razao_social, nome_fantasia,
               email, telefone, celular, cep, logradouro, numero, complemento,
               bairro, cidade, uf, logo, observacoes, status, criado_em, atualizado_em
        FROM empresas WHERE id = :id
    ");
    $stmt->execute([':id' => $id]);
    $empresa = $stmt->fetch();

    if (!$empresa) return null;

    $stmt = $pdo->prepare("
        SELECT id, empresa_id, nome, email, telefone, cargo, criado_em, atualizado_em
        FROM empresa_contatos WHERE empresa_id = :eid ORDER BY criado_em ASC
    ");
    $stmt->execute([':eid' => $id]);
    $empresa['contatos'] = $stmt->fetchAll();

    return $empresa;
}

/**
 * Gera o próximo código sequencial para empresa (EMP-XXXX).
 *
 * Consulta o último código existente na tabela, extrai o número
 * sequencial via regex e incrementa em 1. Se a tabela estiver vazia,
 * retorna 'EMP-0001'.
 *
 * @return string Código no formato 'EMP-XXXX' (ex: 'EMP-0042')
 */
function gerar_proximo_codigo_empresa() {
    $pdo = db();
    $stmt = $pdo->query("SELECT codigo FROM empresas ORDER BY codigo DESC LIMIT 1");
    $ultimo = $stmt->fetchColumn();

    $seq = 1;
    if ($ultimo && preg_match('/EMP-(\d+)/', $ultimo, $m)) {
        $seq = (int) $m[1] + 1;
    }

    return generate_codigo('EMP', $seq);
}

// ============================================================
// VALIDAÇÃO
// ============================================================

/**
 * Valida o token CSRF enviado via POST contra o armazenado na sessão.
 *
 * Usa hash_equals() para comparação segura contra timing attacks.
 * Ambos os tokens devem ser não-vazios para a validação passar.
 *
 * @return bool true se o token é válido, false caso contrário
 */
function validar_csrf() {
    $enviado = $_POST['csrf_token'] ?? '';
    $sessao  = $_SESSION['csrf_token'] ?? '';
    return $enviado !== '' && $sessao !== '' && hash_equals($sessao, $enviado);
}

/**
 * Valida os dados de uma empresa antes de salvar.
 *
 * Verificações realizadas:
 *   - CNPJ obrigatório e único (ignora o próprio registro em edição)
 *   - Razão Social obrigatória
 *
 * @param array $dados  Dados do formulário ($_POST): cnpj, razao_social, [id]
 * @param bool  $isEdit true se for edição (para ignorar o próprio CNPJ na duplicata)
 * @return array Array de erros indexado por campo (ex: ['cnpj' => 'CNPJ duplicado']). Vazio se válido.
 */
function validar_empresa($dados, $isEdit = false) {
    $erros = [];

    $tipoPessoa = strtoupper(trim((string) ($dados['tipo_pessoa'] ?? 'PJ')));
    if (!in_array($tipoPessoa, ['PF', 'PJ'], true)) {
        $erros['tipo_pessoa'] = __('empresas.erro_tipo_pessoa_obrigatorio');
        $tipoPessoa = 'PJ';
    }

    $campoDocumento = $tipoPessoa === 'PF' ? 'cpf' : 'cnpj';
    $documento = strtoupper(trim((string) ($dados[$campoDocumento] ?? '')));
    if ($documento === '') {
        $erros[$campoDocumento] = $tipoPessoa === 'PF'
            ? __('empresas.erro_cpf_obrigatorio')
            : __('empresas.erro_cnpj_obrigatorio');
    } else {
        $pdo = db();
        $sql = "SELECT id FROM empresas WHERE {$campoDocumento} = :documento";
        $p = [':documento' => $documento];
        if ($isEdit && !empty($dados['id'])) {
            $sql .= " AND id != :id";
            $p[':id'] = $dados['id'];
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($p);
        if ($stmt->fetch()) {
            $erros[$campoDocumento] = $tipoPessoa === 'PF'
                ? __('empresas.erro_cpf_duplicado')
                : __('empresas.erro_cnpj_duplicado');
        }
    }

    if (trim($dados['razao_social'] ?? '') === '') {
        $erros['razao_social'] = __('empresas.erro_razao_obrigatoria');
    }

    return $erros;
}

function normalizar_dados_empresa(array $dados): array {
    $tipoPessoa = strtoupper(trim((string) ($dados['tipo_pessoa'] ?? 'PJ')));
    if (!in_array($tipoPessoa, ['PF', 'PJ'], true)) {
        $tipoPessoa = 'PJ';
    }

    $cnpj = strtoupper(trim((string) ($dados['cnpj'] ?? '')));
    $cpf = trim((string) ($dados['cpf'] ?? ''));
    $ie = trim((string) ($dados['inscricao_estadual'] ?? ''));

    if ($tipoPessoa === 'PF') {
        $cnpj = '';
        $ie = '';
    } elseif (!empty($dados['ie_isento'])) {
        $ie = 'ISENTO';
    }

    return [
        'tipo_pessoa' => $tipoPessoa,
        'cnpj' => $cnpj !== '' ? $cnpj : null,
        'cpf' => $tipoPessoa === 'PF' && $cpf !== '' ? $cpf : null,
        'inscricao_estadual' => $ie !== '' ? $ie : null,
    ];
}

function documento_empresa(array $empresa): string {
    $tipoPessoa = strtoupper((string) ($empresa['tipo_pessoa'] ?? 'PJ'));
    return $tipoPessoa === 'PF'
        ? (string) ($empresa['cpf'] ?? '')
        : (string) ($empresa['cnpj'] ?? '');
}

// ============================================================
// UPLOAD DE LOGO
// ============================================================

/**
 * Processa o upload de arquivo de logo da empresa.
 *
 * Validações aplicadas:
 *   - Tamanho máximo: constante UPLOAD_LOGO_MAX_BYTES
 *   - Extensões permitidas: jpg, jpeg, png
 *   - MIME types válidos: image/jpeg, image/png
 *   - Arquivo deve ser upload HTTP válido (is_uploaded_file)
 *   - Nome do arquivo é gerado aleatoriamente (16 bytes hex) para segurança
 *
 * Se o upload for válido, remove a logo anterior (se existir) e salva a nova
 * em storage externo fora do diretório público.
 *
 * @param array  $arquivo   Dados do arquivo ($_FILES['logo']): name, tmp_name, size, error
 * @param string $empresaId UUID da empresa (usado apenas para referência)
 * @param string $logoAtual Caminho relativo da logo atual (para remoção). Vazio se não houver.
 * @return string Caminho relativo da nova logo (ex: 'logos/abc123.png') ou $logoAtual se upload inválido
 */
function processar_logo_upload($arquivo, $empresaId, $logoAtual = '') {
    if (upload_rate_limit_exceeded('logo')) {
        registrar_tentativa_upload('logo', 'bloqueado_rate_limit');
        throw new RuntimeException('Limite de tentativas de upload excedido. Tente novamente em alguns minutos.');
    }

    if (empty($arquivo['name']) || $arquivo['error'] !== UPLOAD_ERR_OK) {
        return $logoAtual;
    }

    if (($arquivo['size'] ?? 0) > UPLOAD_LOGO_MAX_BYTES) {
        return $logoAtual;
    }

    $tmpName = (string) ($arquivo['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        registrar_tentativa_upload('logo', 'arquivo_invalido');
        return $logoAtual;
    }

    // Extensão permitida
    $ext = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
        registrar_tentativa_upload('logo', 'extensao_nao_permitida');
        return $logoAtual;
    }

    // MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $tmpName);
    finfo_close($finfo);
    if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
        registrar_tentativa_upload('logo', 'mime_nao_permitido');
        return $logoAtual;
    }

    // Diretório em storage externo
    $dir = upload_storage_root() . '/logos';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $quarantineDir = str_replace('\\', '/', rtrim((string) UPLOAD_QUARANTINE_PATH, '/'));
    if (!is_dir($quarantineDir)) {
        mkdir($quarantineDir, 0755, true);
    }

    // Nome aleatório para evitar previsibilidade
    $nomeArquivo = bin2hex(random_bytes(16)) . '.' . $ext;
    $destino = $dir . '/' . $nomeArquivo;
    $quarantinePath = $quarantineDir . '/q_logo_' . $nomeArquivo;

    if (!move_uploaded_file($tmpName, $quarantinePath)) {
        registrar_tentativa_upload('logo', 'falha_quarentena');
        return $logoAtual;
    }

    try {
        scan_uploaded_file_or_fail($quarantinePath, 'logo');
    } catch (Throwable $e) {
        if (is_file($quarantinePath)) {
            unlink($quarantinePath);
        }
        registrar_tentativa_upload('logo', 'scan_reprovado');
        throw $e;
    }

    if (!rename($quarantinePath, $destino)) {
        if (is_file($quarantinePath)) {
            unlink($quarantinePath);
        }
        registrar_tentativa_upload('logo', 'falha_movimentacao_final');
        return $logoAtual;
    }

    // Remover logo anterior local apenas apos sucesso da nova gravacao
    remover_logo_arquivo($logoAtual);
    registrar_tentativa_upload('logo', 'sucesso');
    return 'logos/' . $nomeArquivo;

    return $logoAtual;
}

/**
 * Remove o arquivo físico de logo do disco.
 *
 * Verifica e normaliza caminho de logo com controle de diretório seguro.
 * e se o arquivo existe antes de deletar.
 *
 * @param string $logo Caminho relativo da logo (ex: 'logos/abc123.png')
 * @return void
 */
function remover_logo_arquivo($logo) {
    if (empty($logo)) {
        return;
    }

    $logoRelativo = normalize_logo_relative_path((string) $logo);
    if ($logoRelativo !== null) {
        $caminhoStorage = upload_storage_root() . '/' . $logoRelativo;
        if (is_file($caminhoStorage)) {
            unlink($caminhoStorage);
            return;
        }
    }

    // Compatibilidade com logos antigos em public/uploads/logos
    $logoLegacy = ltrim(str_replace('\\', '/', (string) $logo), '/');
    if (str_starts_with($logoLegacy, 'uploads/logos/')) {
        $caminhoLegacy = __DIR__ . '/../public/' . $logoLegacy;
        if (is_file($caminhoLegacy)) {
            unlink($caminhoLegacy);
        }
    }
}

// ============================================================
// CONTATOS
// ============================================================

/**
 * Salva contatos de uma empresa (estratégia: delete + re-insert).
 *
 * Remove todos os contatos existentes da empresa e reinsere os informados.
 * Contatos com nome vazio são ignorados automaticamente.
 * Cada contato recebe um novo UUID.
 *
 * @param \PDO   $pdo       Instância PDO (deve estar dentro de uma transação)
 * @param string $empresaId UUID da empresa dona dos contatos
 * @param array  $contatos  Array de contatos, cada um com: nome, email, telefone, cargo
 * @return void
 */
function salvar_contatos($pdo, $empresaId, $contatos) {
    // Remove todos e reinsere
    $pdo->prepare("DELETE FROM empresa_contatos WHERE empresa_id = :eid")
        ->execute([':eid' => $empresaId]);

    if (empty($contatos) || !is_array($contatos)) return;

    $stmt = $pdo->prepare(
        "INSERT INTO empresa_contatos (id, empresa_id, nome, email, telefone, cargo)
         VALUES (:id, :eid, :nome, :email, :telefone, :cargo)"
    );

    foreach ($contatos as $c) {
        if (trim($c['nome'] ?? '') === '') continue;
        $stmt->execute([
            ':id'       => generate_uuid(),
            ':eid'      => $empresaId,
            ':nome'     => trim($c['nome']),
            ':email'    => trim($c['email'] ?? ''),
            ':telefone' => trim($c['telefone'] ?? ''),
            ':cargo'    => trim($c['cargo'] ?? ''),
        ]);
    }
}

// ============================================================
// ROTEAMENTO
// ============================================================

switch ($action) {
    // ----------------------------------------------------------
    // LOGO (download/stream seguro)
    // ----------------------------------------------------------
    case 'logo':
        $empresaIdLogo = trim((string) ($_GET['id'] ?? ''));
        if ($empresaIdLogo === '') {
            http_response_code(400);
            echo 'Empresa invalida.';
            exit;
        }

        if (!can_manage_empresas()) {
            $permitidas = [
                (string) current_empresa_id(),
            ];
            foreach (user_empresas_disponiveis() as $empresaDisponivel) {
                $permitidas[] = (string) ($empresaDisponivel['empresa_id'] ?? '');
            }
            $permitidas = array_values(array_unique(array_filter($permitidas)));

            if (!in_array($empresaIdLogo, $permitidas, true)) {
                http_response_code(403);
                echo 'Acesso negado.';
                exit;
            }
        }

        $empresaLogo = buscar_empresa_por_id($empresaIdLogo);
        $logoBanco = (string) ($empresaLogo['logo'] ?? '');
        if ($logoBanco === '') {
            http_response_code(404);
            echo 'Logo nao encontrada.';
            exit;
        }

        $logoRelativo = normalize_logo_relative_path($logoBanco);
        $arquivoAbs = false;
        if ($logoRelativo !== null) {
            $caminhoStorage = upload_storage_root() . '/' . $logoRelativo;
            $baseStorage = realpath(upload_storage_root() . '/logos');
            $arquivoStorage = realpath($caminhoStorage);
            if ($arquivoStorage !== false && $baseStorage !== false && str_starts_with(str_replace('\\', '/', $arquivoStorage), str_replace('\\', '/', $baseStorage))) {
                $arquivoAbs = $arquivoStorage;
            }
        }

        if ($arquivoAbs === false) {
            // Compatibilidade com logos antigas em public/uploads/logos
            $legacyRel = ltrim(str_replace('\\', '/', $logoBanco), '/');
            $legacyAbs = realpath(__DIR__ . '/../public/' . $legacyRel);
            $legacyBase = realpath(__DIR__ . '/../public/uploads/logos');
            if ($legacyAbs !== false && $legacyBase !== false && str_starts_with(str_replace('\\', '/', $legacyAbs), str_replace('\\', '/', $legacyBase))) {
                $arquivoAbs = $legacyAbs;
            }
        }

        if ($arquivoAbs === false || !is_file($arquivoAbs)) {
            http_response_code(404);
            echo 'Logo nao encontrada.';
            exit;
        }

        $mime = (string) (mime_content_type($arquivoAbs) ?: 'application/octet-stream');
        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            http_response_code(415);
            echo 'Formato de logo nao suportado.';
            exit;
        }

        $safeCodigo = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($empresaLogo['codigo'] ?? 'empresa'));
        $nomeArquivo = $safeCodigo === '' ? 'logo_empresa' : 'logo_' . $safeCodigo;

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($arquivoAbs));
        header('Content-Disposition: inline; filename="' . $nomeArquivo . '"');
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        readfile($arquivoAbs);
        exit;

    // ----------------------------------------------------------
    // CRIAR
    // ----------------------------------------------------------
    case 'create':
        $empresa = null;
        $isEdit  = false;
        $errors  = [];
        require __DIR__ . '/../views/empresas/form.php';
        break;

    // ----------------------------------------------------------
    // EDITAR
    // ----------------------------------------------------------
    case 'edit':
        $id = $_GET['id'] ?? '';
        if (empty($id)) {
            set_flash('danger', __('empresas.erro_nao_encontrada'));
            redirect(url('empresas'));
        }
        $empresa = buscar_empresa_por_id($id);
        if (!$empresa) {
            set_flash('danger', __('empresas.erro_nao_encontrada'));
            redirect(url('empresas'));
        }
        $isEdit = true;
        $errors = [];
        require __DIR__ . '/../views/empresas/form.php';
        break;

    // ----------------------------------------------------------
    // SALVAR (INSERT / UPDATE)
    // ----------------------------------------------------------
    case 'store':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(url('empresas'));
        }
        if (!validar_csrf()) {
            set_flash('danger', __('common.erro_csrf'));
            redirect(url('empresas'));
        }

        $isEdit = !empty($_POST['id']);
        $dados  = $_POST;
        $errors = validar_empresa($dados, $isEdit);

        if (!empty($errors)) {
            $empresa = $dados;
            $empresa['contatos'] = $_POST['contatos'] ?? [];
            require __DIR__ . '/../views/empresas/form.php';
            break;
        }

        $pdo = null;
        try {
            $pdo = db();
            $pdo->beginTransaction();

            $dadosNormalizados = normalizar_dados_empresa($dados);

            if ($isEdit) {
                // ---------- UPDATE ----------
                $id = $dados['id'];
                $empresaAnterior = buscar_empresa_por_id($id);

                if (!$empresaAnterior) {
                    $pdo->rollBack();
                    set_flash('danger', __('empresas.erro_nao_encontrada'));
                    redirect(url('empresas'));
                }

                // Logo
                $logo = $empresaAnterior['logo'] ?? '';
                if (!empty($_POST['remover_logo'])) {
                    remover_logo_arquivo($logo);
                    $logo = '';
                }
                if (!empty($_FILES['logo']['name'])) {
                    $logo = processar_logo_upload($_FILES['logo'], $id, $logo);
                }

                $stmt = $pdo->prepare("UPDATE empresas SET
                    tipo_pessoa = :tipo_pessoa, cnpj = :cnpj, cpf = :cpf, inscricao_estadual = :ie, razao_social = :razao,
                    nome_fantasia = :fantasia, email = :email, telefone = :fone,
                    celular = :cel, cep = :cep, logradouro = :logr, numero = :num,
                    complemento = :compl, bairro = :bairro, cidade = :cidade, uf = :uf,
                    logo = :logo, observacoes = :obs, status = :status
                    WHERE id = :id");

                $stmt->execute([
                    ':tipo_pessoa' => $dadosNormalizados['tipo_pessoa'],
                    ':cnpj'    => $dadosNormalizados['cnpj'],
                    ':cpf'     => $dadosNormalizados['cpf'],
                    ':ie'      => $dadosNormalizados['inscricao_estadual'],
                    ':razao'   => trim($dados['razao_social']),
                    ':fantasia'=> trim($dados['nome_fantasia'] ?? ''),
                    ':email'   => trim($dados['email'] ?? ''),
                    ':fone'    => trim($dados['telefone'] ?? ''),
                    ':cel'     => trim($dados['celular'] ?? ''),
                    ':cep'     => trim($dados['cep'] ?? ''),
                    ':logr'    => trim($dados['logradouro'] ?? ''),
                    ':num'     => trim($dados['numero'] ?? ''),
                    ':compl'   => trim($dados['complemento'] ?? ''),
                    ':bairro'  => trim($dados['bairro'] ?? ''),
                    ':cidade'  => trim($dados['cidade'] ?? ''),
                    ':uf'      => trim($dados['uf'] ?? ''),
                    ':logo'    => $logo,
                    ':obs'     => trim($dados['observacoes'] ?? ''),
                    ':status'  => $dados['status'] ?? $empresaAnterior['status'],
                    ':id'      => $id,
                ]);

                salvar_contatos($pdo, $id, $dados['contatos'] ?? []);
                $pdo->commit();

                $empresaNova = buscar_empresa_por_id($id);
                log_info('empresas', 'editar',
                    'Empresa editada: ' . $empresaNova['codigo'] . ' – ' . $empresaNova['razao_social'] . ' (' . $empresaNova['tipo_pessoa'] . ': ' . documento_empresa($empresaNova) . ')',
                    $id, $empresaNova['codigo'], $empresaAnterior, $empresaNova);

                set_flash('success', __('empresas.sucesso_editada'));

            } else {
                // ---------- INSERT ----------
                $id     = generate_uuid();
                $codigo = gerar_proximo_codigo_empresa();

                $logo = '';
                if (!empty($_FILES['logo']['name'])) {
                    $logo = processar_logo_upload($_FILES['logo'], $id, '');
                }

                $stmt = $pdo->prepare("INSERT INTO empresas
                    (id, codigo, tipo_pessoa, cnpj, cpf, inscricao_estadual, razao_social, nome_fantasia,
                     email, telefone, celular, cep, logradouro, numero, complemento,
                     bairro, cidade, uf, logo, observacoes, status)
                    VALUES
                    (:id, :codigo, :tipo_pessoa, :cnpj, :cpf, :ie, :razao, :fantasia,
                     :email, :fone, :cel, :cep, :logr, :num, :compl,
                     :bairro, :cidade, :uf, :logo, :obs, 'ativo')");

                $stmt->execute([
                    ':id'      => $id,
                    ':codigo'  => $codigo,
                    ':tipo_pessoa' => $dadosNormalizados['tipo_pessoa'],
                    ':cnpj'    => $dadosNormalizados['cnpj'],
                    ':cpf'     => $dadosNormalizados['cpf'],
                    ':ie'      => $dadosNormalizados['inscricao_estadual'],
                    ':razao'   => trim($dados['razao_social']),
                    ':fantasia'=> trim($dados['nome_fantasia'] ?? ''),
                    ':email'   => trim($dados['email'] ?? ''),
                    ':fone'    => trim($dados['telefone'] ?? ''),
                    ':cel'     => trim($dados['celular'] ?? ''),
                    ':cep'     => trim($dados['cep'] ?? ''),
                    ':logr'    => trim($dados['logradouro'] ?? ''),
                    ':num'     => trim($dados['numero'] ?? ''),
                    ':compl'   => trim($dados['complemento'] ?? ''),
                    ':bairro'  => trim($dados['bairro'] ?? ''),
                    ':cidade'  => trim($dados['cidade'] ?? ''),
                    ':uf'      => trim($dados['uf'] ?? ''),
                    ':logo'    => $logo,
                    ':obs'     => trim($dados['observacoes'] ?? ''),
                ]);

                salvar_contatos($pdo, $id, $dados['contatos'] ?? []);
                $pdo->commit();

                $empresaCriada = buscar_empresa_por_id($id);
                log_info('empresas', 'criar',
                    'Empresa criada: ' . $codigo . ' – ' . trim($dados['razao_social']) . ' (' . $dadosNormalizados['tipo_pessoa'] . ': ' . documento_empresa($empresaCriada) . ')',
                    $id, $codigo, null, $empresaCriada);

                set_flash('success', __('empresas.sucesso_criada'));
            }

            redirect(url('empresas'));

        } catch (Exception $ex) {
            if ($pdo instanceof PDO && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            log_error('empresas', $isEdit ? 'editar' : 'criar', 'Erro ao salvar empresa: ' . $ex->getMessage());
            set_flash('danger', __('empresas.erro_salvar'));
            redirect(url('empresas'));
        }
        break;

    // ----------------------------------------------------------
    // BLOQUEAR / DESBLOQUEAR
    // ----------------------------------------------------------
    case 'block':
    case 'unblock':
        if (!enforce_post_request()) {
            break;
        }
        if (!validar_csrf()) {
            set_flash('danger', __('common.erro_csrf'));
            redirect(url('empresas'));
        }

        $id = $_POST['id'] ?? '';
        if (empty($id)) {
            set_flash('danger', __('empresas.erro_nao_encontrada'));
            redirect(url('empresas'));
        }

        try {
            $empresa = buscar_empresa_por_id($id);
            if (!$empresa) {
                set_flash('danger', __('empresas.erro_nao_encontrada'));
                redirect(url('empresas'));
            }

            $novoStatus     = ($action === 'block') ? 'bloqueado' : 'ativo';
            $statusAnterior = $empresa['status'];

            $pdo = db();
            $pdo->prepare("UPDATE empresas SET status = :s WHERE id = :id")
                ->execute([':s' => $novoStatus, ':id' => $id]);

            $acao   = ($action === 'block') ? 'bloquear' : 'desbloquear';
            $msgKey = ($action === 'block') ? 'empresas.sucesso_bloqueada' : 'empresas.sucesso_desbloqueada';

            log_warning('empresas', $acao,
                'Empresa ' . $empresa['codigo'] . ' ' . ($action === 'block' ? 'bloqueada' : 'desbloqueada'),
                $id, $empresa['codigo'],
                ['status' => $statusAnterior], ['status' => $novoStatus]);

            set_flash('success', __($msgKey));

        } catch (Exception $ex) {
            log_error('empresas', 'status', 'Erro ao alterar status: ' . $ex->getMessage(), $id);
            set_flash('danger', __('empresas.erro_salvar'));
        }

        redirect(url('empresas'));
        break;

    // ----------------------------------------------------------
    // EXCLUIR
    // ----------------------------------------------------------
    case 'delete':
        if (!enforce_post_request()) {
            break;
        }
        if (!validar_csrf()) {
            set_flash('danger', __('common.erro_csrf'));
            redirect(url('empresas'));
        }

        $id = $_POST['id'] ?? '';
        if (empty($id)) {
            set_flash('danger', __('empresas.erro_nao_encontrada'));
            redirect(url('empresas'));
        }

        try {
            $empresa = buscar_empresa_por_id($id);
            if (!$empresa) {
                set_flash('danger', __('empresas.erro_nao_encontrada'));
                redirect(url('empresas'));
            }

            // Impedir exclusão se houver usuários vinculados
            $pdo  = db();
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM usuario_empresa WHERE empresa_id = :id");
            $stmt->execute([':id' => $id]);
            if ((int) $stmt->fetchColumn() > 0) {
                set_flash('danger', __('empresas.erro_excluir_com_usuarios'));
                redirect(url('empresas'));
            }

            // Remover arquivo de logo
            remover_logo_arquivo($empresa['logo'] ?? '');

            // Excluir empresa (CASCADE remove contatos)
            $pdo->prepare("DELETE FROM empresas WHERE id = :id")
                ->execute([':id' => $id]);

            log_warning('empresas', 'excluir',
                'Empresa excluída: ' . $empresa['codigo'] . ' – ' . $empresa['razao_social'],
                $id, $empresa['codigo'], $empresa);

            set_flash('success', __('empresas.sucesso_excluida'));

        } catch (Exception $ex) {
            log_error('empresas', 'excluir', 'Erro ao excluir empresa: ' . $ex->getMessage(), $id);
            set_flash('danger', __('empresas.erro_salvar'));
        }

        redirect(url('empresas'));
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
        $pagina      = max(1, (int) ($_GET['pg'] ?? 1));
        $porPagina   = 15;
        $resultado   = buscar_empresas($filtros, $pagina, $porPagina);
        $empresas    = $resultado['empresas'];
        $total       = $resultado['total'];
        $totalPaginas = max(1, (int) ceil($total / $porPagina));

        require __DIR__ . '/../views/empresas/index.php';
        break;
}
