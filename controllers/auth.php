<?php
declare(strict_types=1);

/**
 * Controller: Autenticação
 *
 * Gerencia o fluxo de login e logout do sistema.
 * Valida credenciais (e-mail + senha bcrypt) contra o banco de dados
 * e monta a sessão com dados do usuário, empresa ativa e perfil de acesso.
 *
 * Ações disponíveis (via $action):
 *   - authenticate: POST com e-mail e senha → valida CSRF, rate limiting, credenciais
 *   - logout: destrói a sessão e redireciona para login
 *   - index (default): exibe o formulário de login
 *
 * Proteções de segurança:
 *   - CSRF: valida token via hash_equals()
 *   - Rate limiting: bloqueia após 5 tentativas falhas em 15 minutos
 *   - Rehash automático: recalcula hash se BCRYPT_COST foi alterado
 *   - Regeneração de ID de sessão após login bem-sucedido
 */

/**
 * Busca usuário por e-mail com seus vínculos ativos (empresa + perfil).
 *
 * Realiza INNER JOIN entre usuarios, usuario_empresa, empresas e perfis,
 * filtrando apenas vínculos com status 'ativo' em todas as tabelas.
 * Retorna o primeiro vínculo válido encontrado.
 *
 * @param string $email E-mail do usuário (case-sensitive no MySQL com collation padrão)
 * @return array|null Array associativo com dados do usuário, empresa e perfil, ou null se não encontrado
 *                    Chaves: id, codigo, nome, email, senha, usuario_status,
 *                            empresa_id, empresa_codigo, empresa_nome, empresa_logo, empresa_status,
 *                            perfil_id, perfil_codigo, perfil_nome
 */
function buscar_usuario_por_email($email) {
    $pdo = db();

    // Busca usuário + primeiro vínculo ativo (empresa + perfil)
    $sql = "
        SELECT
            u.id, u.codigo, u.nome, u.email, u.senha, u.status AS usuario_status,
            e.id AS empresa_id, e.codigo AS empresa_codigo, e.nome_fantasia AS empresa_nome,
            e.logo AS empresa_logo, e.status AS empresa_status,
            p.id AS perfil_id, p.codigo AS perfil_codigo, p.nome AS perfil_nome
        FROM usuarios u
        INNER JOIN usuario_empresa ue ON ue.usuario_id = u.id AND ue.status = 'ativo'
        INNER JOIN empresas e ON e.id = ue.empresa_id AND e.status = 'ativo'
        INNER JOIN perfis p ON p.id = ue.perfil_id AND p.status = 'ativo'
        WHERE u.email = :email
        LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email]);
    return $stmt->fetch() ?: null;
}

/**
 * Atualiza o campo 'ultimo_login' do usuário com a data/hora atual.
 *
 * Chamada após autenticação bem-sucedida para rastreamento de atividade.
 *
 * @param string $usuario_id UUID do usuário (CHAR(36))
 * @return void
 */
function atualizar_ultimo_login($usuario_id) {
    $pdo = db();
    $stmt = $pdo->prepare("UPDATE usuarios SET ultimo_login = NOW() WHERE id = :id");
    $stmt->execute([':id' => $usuario_id]);
}

/**
 * Conta tentativas recentes de login falhadas por IP e/ou e-mail.
 *
 * Consulta a tabela log_acoes buscando registros com modulo='auth'
 * e acao='login_falha' dentro do intervalo de tempo especificado.
 * Usa OR entre IP e e-mail para cobrir ataques distribuídos.
 *
 * @param string $ip                 Endereço IP do cliente (IPv4 ou IPv6)
 * @param string $email              E-mail utilizado na tentativa de login
 * @param int    $intervaloSegundos  Janela de tempo em segundos (padrão: 900 = 15 min)
 * @return int Número de tentativas falhadas no intervalo
 */
function contar_tentativas_recentes(string $ip, string $email, int $intervaloSegundos = 900): int {
    $pdo = db();
    $desde = date('Y-m-d H:i:s', time() - $intervaloSegundos);
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM log_acoes
        WHERE modulo = 'auth' AND acao = 'login_falha'
          AND (ip = :ip OR descricao LIKE :email)
          AND criado_em >= :desde
    ");
    $stmt->execute([
        ':ip'    => $ip,
        ':email' => '%' . $email . '%',
        ':desde' => $desde,
    ]);
    return (int) $stmt->fetchColumn();
}

switch ($action) {
    case 'authenticate':
        // Validação CSRF
        $csrfEnviado = $_POST['csrf_token'] ?? '';
        $csrfSessao  = $_SESSION['csrf_token'] ?? '';
        if ($csrfEnviado === '' || $csrfSessao === '' || !hash_equals($csrfSessao, $csrfEnviado)) {
            set_flash('danger', __('common.erro_csrf'));
            redirect(url('login'));
        }

        $email = trim($_POST['email'] ?? '');
        $senha = $_POST['password'] ?? '';

        // Validação básica
        if (empty($email) || empty($senha)) {
            set_flash('danger', __('login.erro_campos'));
            redirect(url('login'));
        }

        // Rate limiting: bloqueia após 5 tentativas em 15 minutos
        $clientIp = get_client_ip();
        $tentativas = contar_tentativas_recentes($clientIp, $email, 900);
        if ($tentativas >= 5) {
            log_warning('auth', 'brute_force_bloqueado', 'Tentativas excessivas de login bloqueadas para IP: ' . $clientIp . ' / e-mail: ' . $email);
            set_flash('danger', __('login.erro_credenciais'));
            http_response_code(429);
            redirect(url('login'));
        }

        // Busca usuário no banco
        $usuario = buscar_usuario_por_email($email);

        if (!$usuario) {
            log_warning('auth', 'login_falha', 'Tentativa de login com e-mail não encontrado ou sem vínculo ativo: ' . $email);
            set_flash('danger', __('login.erro_credenciais'));
            redirect(url('login'));
        }

        // Verifica se o usuário está bloqueado
        if ($usuario['usuario_status'] !== 'ativo') {
            log_warning('auth', 'login_falha', 'Tentativa de login com usuário bloqueado: ' . $usuario['codigo'] . ' (' . $usuario['email'] . ')');
            set_flash('danger', __('login.erro_bloqueado'));
            redirect(url('login'));
        }

        // Verifica a senha
        if (!verificar_senha($senha, $usuario['senha'])) {
            log_warning('auth', 'login_falha', 'Senha incorreta para: ' . $usuario['codigo'] . ' (' . $usuario['email'] . ')');
            set_flash('danger', __('login.erro_credenciais'));
            redirect(url('login'));
        }

        // Rehash se necessário (custo do bcrypt foi aumentado)
        if (senha_precisa_rehash($usuario['senha'])) {
            $novoHash = hash_senha($senha);
            $pdo = db();
            $stmt = $pdo->prepare("UPDATE usuarios SET senha = :senha WHERE id = :id");
            $stmt->execute([':senha' => $novoHash, ':id' => $usuario['id']]);
        }

        // Monta a sessão
        session_regenerate_id(true);

        $logoUrl = $usuario['empresa_logo'];
        if (empty($logoUrl)) {
            $nomeEnc = urlencode($usuario['empresa_nome']);
            $logoUrl = "https://ui-avatars.com/api/?name={$nomeEnc}&background=0d6efd&color=fff&size=128";
        }

        $_SESSION['user'] = [
            'id'              => $usuario['id'],
            'codigo'          => $usuario['codigo'],
            'nome'            => $usuario['nome'],
            'email'           => $usuario['email'],
            'empresa_id'      => $usuario['empresa_id'],
            'empresa_codigo'  => $usuario['empresa_codigo'],
            'empresa_nome'    => $usuario['empresa_nome'],
            'empresa_logo'    => $logoUrl,
            'perfil_id'       => $usuario['perfil_id'],
            'perfil_nome'     => $usuario['perfil_nome'],
        ];
        $_SESSION['empresa_logo'] = $logoUrl;
        $_SESSION['ultima_atividade'] = time();

        // Atualiza último login
        atualizar_ultimo_login($usuario['id']);

        log_info('auth', 'login', 'Usuário ' . $usuario['codigo'] . ' (' . $usuario['nome'] . ') realizou login na empresa ' . $usuario['empresa_codigo'] . ' (' . $usuario['empresa_nome'] . ')');

        set_flash('success', __('login.bem_vindo') . ', ' . $usuario['nome'] . '!');
        redirect(url('empresas'));
        break;

    case 'logout':
        log_info('auth', 'logout', 'Usuário realizou logout do sistema');
        session_destroy();
        redirect(url('login'));
        break;

    case 'index':
    default:
        require __DIR__ . '/../views/auth/login.php';
        break;
}
