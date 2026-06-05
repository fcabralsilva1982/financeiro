<?php
declare(strict_types=1);

/**
 * Controller: Autenticacao
 *
 * Implementa login, logout, selecao de empresa no primeiro acesso
 * e alternancia de empresa ativa para usuarios com multiplos vinculos.
 */

$action = $action ?? ($_GET['action'] ?? 'index');

function buscar_usuario_base_por_email(string $email): ?array {
    $pdo = db();

    $sql = "SELECT id, codigo, nome, email, senha, status AS usuario_status
            FROM usuarios
            WHERE email = :email
            LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email]);
    return $stmt->fetch() ?: null;
}

/**
 * Compatibilidade legado: retorna usuario com o primeiro vinculo ativo.
 *
 * Mantido para testes e chamadas antigas.
 */
function buscar_usuario_por_email($email) {
    $usuarioBase = buscar_usuario_base_por_email((string) $email);
    if (!$usuarioBase) {
        return null;
    }

    $vinculos = buscar_vinculos_ativos_usuario((string) $usuarioBase['id']);
    if (empty($vinculos)) {
        return null;
    }

    $vinculo = $vinculos[0];
    return [
        'id' => $usuarioBase['id'],
        'codigo' => $usuarioBase['codigo'],
        'nome' => $usuarioBase['nome'],
        'email' => $usuarioBase['email'],
        'senha' => $usuarioBase['senha'],
        'usuario_status' => $usuarioBase['usuario_status'],
        'empresa_id' => $vinculo['empresa_id'],
        'empresa_codigo' => $vinculo['empresa_codigo'],
        'empresa_nome' => $vinculo['empresa_nome'],
        'empresa_logo' => $vinculo['empresa_logo'],
        'perfil_id' => $vinculo['perfil_id'],
        'perfil_codigo' => $vinculo['perfil_codigo'],
        'perfil_nome' => $vinculo['perfil_nome'],
    ];
}

function buscar_vinculos_ativos_usuario(string $usuarioId): array {
    $pdo = db();

    $sql = "SELECT
                ue.id AS vinculo_id,
                ue.empresa_id,
                e.codigo AS empresa_codigo,
                e.nome_fantasia AS empresa_nome,
                e.logo AS empresa_logo,
                ue.perfil_id,
                p.codigo AS perfil_codigo,
                p.nome AS perfil_nome
            FROM usuario_empresa ue
            INNER JOIN empresas e ON e.id = ue.empresa_id AND e.status = 'ativo'
            INNER JOIN perfis p ON p.id = ue.perfil_id AND p.status = 'ativo'
            WHERE ue.usuario_id = :usuario_id AND ue.status = 'ativo'
            ORDER BY e.nome_fantasia ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':usuario_id' => $usuarioId]);
    return $stmt->fetchAll();
}

function atualizar_ultimo_login(string $usuarioId): void {
    $pdo = db();
    $stmt = $pdo->prepare('UPDATE usuarios SET ultimo_login = NOW() WHERE id = :id');
    $stmt->execute([':id' => $usuarioId]);
}

function contar_tentativas_recentes(string $ip, string $email, int $intervaloSegundos = 900): int {
    $pdo = db();
    $desde = date('Y-m-d H:i:s', time() - $intervaloSegundos);
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM log_acoes
         WHERE modulo = 'auth' AND acao = 'login_falha'
           AND (ip = :ip OR descricao LIKE :email)
           AND criado_em >= :desde"
    );
    $stmt->execute([
        ':ip' => $ip,
        ':email' => '%' . $email . '%',
        ':desde' => $desde,
    ]);

    return (int) $stmt->fetchColumn();
}

function montar_logo_empresa(?string $logo, string $nomeEmpresa): string {
    if (!empty($logo)) {
        return $logo;
    }

    $nomeEnc = urlencode($nomeEmpresa);
    return "https://ui-avatars.com/api/?name={$nomeEnc}&background=0d6efd&color=fff&size=128";
}

function aplicar_empresa_na_sessao(array $usuarioBase, array $vinculo, array $vinculosDisponiveis): void {
    session_regenerate_id(true);

    $logoUrl = montar_logo_empresa((string) ($vinculo['empresa_logo'] ?? ''), (string) ($vinculo['empresa_nome'] ?? ''));

    $_SESSION['user'] = [
        'id' => $usuarioBase['id'],
        'codigo' => $usuarioBase['codigo'],
        'nome' => $usuarioBase['nome'],
        'email' => $usuarioBase['email'],
        'empresa_id' => $vinculo['empresa_id'],
        'empresa_codigo' => $vinculo['empresa_codigo'],
        'empresa_nome' => $vinculo['empresa_nome'],
        'empresa_logo' => $logoUrl,
        'perfil_id' => $vinculo['perfil_id'],
        'perfil_nome' => $vinculo['perfil_nome'],
    ];

    $_SESSION['user_empresas'] = array_map(static function (array $item): array {
        $item['empresa_logo'] = montar_logo_empresa((string) ($item['empresa_logo'] ?? ''), (string) ($item['empresa_nome'] ?? ''));
        return $item;
    }, $vinculosDisponiveis);

    $_SESSION['empresa_logo'] = $logoUrl;
    $_SESSION['ultima_atividade'] = time();
    unset($_SESSION['terms_accepted_version']);
}

function registrar_aceite_termo(string $usuarioId): void {
    $pdo = db();
    $stmt = $pdo->prepare(
        "INSERT INTO usuario_termos (id, usuario_id, versao_termo, aceito_em, ip)
         VALUES (:id, :usuario_id, :versao, NOW(), :ip)
         ON DUPLICATE KEY UPDATE aceito_em = NOW(), ip = VALUES(ip), atualizado_em = CURRENT_TIMESTAMP"
    );
    $stmt->execute([
        ':id' => generate_uuid(),
        ':usuario_id' => $usuarioId,
        ':versao' => TERMS_CURRENT_VERSION,
        ':ip' => get_client_ip(),
    ]);
}

function limpar_auth_pendente(): void {
    unset($_SESSION['auth_pending_user'], $_SESSION['auth_pending_empresas']);
}

switch ($action) {
    case 'authenticate':
        $csrfEnviado = $_POST['csrf_token'] ?? '';
        $csrfSessao = $_SESSION['csrf_token'] ?? '';
        if ($csrfEnviado === '' || $csrfSessao === '' || !hash_equals($csrfSessao, $csrfEnviado)) {
            set_flash('danger', __('common.erro_csrf'));
            redirect(url('login'));
        }

        $email = trim((string) ($_POST['email'] ?? ''));
        $senha = (string) ($_POST['password'] ?? '');

        if ($email === '' || $senha === '') {
            set_flash('danger', __('login.erro_campos'));
            redirect(url('login'));
        }

        $clientIp = get_client_ip();
        $tentativas = contar_tentativas_recentes($clientIp, $email, 900);
        if ($tentativas >= 5) {
            log_warning('auth', 'brute_force_bloqueado', 'Tentativas excessivas de login bloqueadas para IP: ' . $clientIp . ' / e-mail: ' . $email);
            set_flash('danger', __('login.erro_credenciais'));
            http_response_code(429);
            redirect(url('login'));
        }

        $usuarioBase = buscar_usuario_base_por_email($email);
        if (!$usuarioBase) {
            log_warning('auth', 'login_falha', 'Tentativa de login com e-mail inexistente: ' . $email);
            set_flash('danger', __('login.erro_credenciais'));
            redirect(url('login'));
        }

        if ($usuarioBase['usuario_status'] !== 'ativo') {
            log_warning('auth', 'login_falha', 'Tentativa de login com usuario bloqueado: ' . $usuarioBase['codigo'] . ' (' . $usuarioBase['email'] . ')');
            set_flash('danger', __('login.erro_bloqueado'));
            redirect(url('login'));
        }

        if (!verificar_senha($senha, (string) $usuarioBase['senha'])) {
            log_warning('auth', 'login_falha', 'Senha incorreta para: ' . $usuarioBase['codigo'] . ' (' . $usuarioBase['email'] . ')');
            set_flash('danger', __('login.erro_credenciais'));
            redirect(url('login'));
        }

        if (senha_precisa_rehash((string) $usuarioBase['senha'])) {
            $novoHash = hash_senha($senha);
            $pdo = db();
            $stmt = $pdo->prepare('UPDATE usuarios SET senha = :senha WHERE id = :id');
            $stmt->execute([':senha' => $novoHash, ':id' => $usuarioBase['id']]);
        }

        $vinculos = buscar_vinculos_ativos_usuario((string) $usuarioBase['id']);
        if (empty($vinculos)) {
            log_warning('auth', 'login_falha', 'Usuario sem vinculo ativo: ' . $usuarioBase['codigo']);
            set_flash('danger', __('login.erro_credenciais'));
            redirect(url('login'));
        }

        if (count($vinculos) > 1) {
            $_SESSION['auth_pending_user'] = [
                'id' => $usuarioBase['id'],
                'codigo' => $usuarioBase['codigo'],
                'nome' => $usuarioBase['nome'],
                'email' => $usuarioBase['email'],
            ];
            $_SESSION['auth_pending_empresas'] = $vinculos;
            redirect(url('login', 'select_empresa'));
        }

        aplicar_empresa_na_sessao($usuarioBase, $vinculos[0], $vinculos);
        atualizar_ultimo_login((string) $usuarioBase['id']);

        if (usuario_deve_aceitar_termos()) {
            set_flash('warning', __('termos.aviso_aceite_obrigatorio'));
            redirect(url('login', 'termos'));
        }

        log_info('auth', 'login', 'Usuario ' . $usuarioBase['codigo'] . ' (' . $usuarioBase['nome'] . ') realizou login na empresa ' . $vinculos[0]['empresa_codigo'] . ' (' . $vinculos[0]['empresa_nome'] . ')');
        set_flash('success', __('login.bem_vindo') . ', ' . $usuarioBase['nome'] . '!');
        redirect(url('empresas'));
        break;

    case 'termos':
        if (!is_logged_in()) {
            set_flash('warning', __('login.erro_sessao_expirada'));
            redirect(url('login'));
        }
        require __DIR__ . '/../views/auth/termos.php';
        break;

    case 'aceitar_termos':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(url('login', 'termos'));
        }

        if (!is_logged_in()) {
            set_flash('warning', __('login.erro_sessao_expirada'));
            redirect(url('login'));
        }

        $csrfEnviado = $_POST['csrf_token'] ?? '';
        $csrfSessao = $_SESSION['csrf_token'] ?? '';
        if ($csrfEnviado === '' || $csrfSessao === '' || !hash_equals($csrfSessao, $csrfEnviado)) {
            set_flash('danger', __('common.erro_csrf'));
            redirect(url('login', 'termos'));
        }

        $aceite = $_POST['aceite_termos'] ?? '';
        if ($aceite !== '1') {
            set_flash('danger', __('termos.erro_aceite_obrigatorio'));
            redirect(url('login', 'termos'));
        }

        $usuarioId = (string) (current_user()['id'] ?? '');
        if ($usuarioId === '') {
            set_flash('warning', __('login.erro_sessao_expirada'));
            redirect(url('login'));
        }

        registrar_aceite_termo($usuarioId);
        $_SESSION['terms_accepted_version'] = TERMS_CURRENT_VERSION;

        log_info('auth', 'aceite_termos', 'Usuario aceitou os termos versao ' . TERMS_CURRENT_VERSION, $usuarioId);
        set_flash('success', __('termos.sucesso_aceite'));
        redirect(url('empresas'));
        break;

    case 'select_empresa':
        if (empty($_SESSION['auth_pending_user']) || empty($_SESSION['auth_pending_empresas'])) {
            set_flash('warning', 'Sua autenticacao expirou. Faca login novamente.');
            redirect(url('login'));
        }

        $empresasDisponiveis = $_SESSION['auth_pending_empresas'];
        require __DIR__ . '/../views/auth/select_empresa.php';
        break;

    case 'choose_empresa':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirect(url('login'));
        }

        $csrfEnviado = $_POST['csrf_token'] ?? '';
        $csrfSessao = $_SESSION['csrf_token'] ?? '';
        if ($csrfEnviado === '' || $csrfSessao === '' || !hash_equals($csrfSessao, $csrfEnviado)) {
            set_flash('danger', __('common.erro_csrf'));
            redirect(url('login'));
        }

        $usuarioPendente = $_SESSION['auth_pending_user'] ?? null;
        $vinculosPendentes = $_SESSION['auth_pending_empresas'] ?? [];
        $empresaIdSelecionada = trim((string) ($_POST['empresa_id'] ?? ''));

        if (!$usuarioPendente || empty($vinculosPendentes) || $empresaIdSelecionada === '') {
            limpar_auth_pendente();
            set_flash('warning', 'Sua autenticacao expirou. Faca login novamente.');
            redirect(url('login'));
        }

        $vinculoSelecionado = null;
        foreach ($vinculosPendentes as $vinculo) {
            if (($vinculo['empresa_id'] ?? '') === $empresaIdSelecionada) {
                $vinculoSelecionado = $vinculo;
                break;
            }
        }

        if (!$vinculoSelecionado) {
            set_flash('danger', 'Empresa selecionada invalida.');
            redirect(url('login', 'select_empresa'));
        }

        aplicar_empresa_na_sessao($usuarioPendente, $vinculoSelecionado, $vinculosPendentes);
        atualizar_ultimo_login((string) $usuarioPendente['id']);
        limpar_auth_pendente();

        if (usuario_deve_aceitar_termos()) {
            set_flash('warning', __('termos.aviso_aceite_obrigatorio'));
            redirect(url('login', 'termos'));
        }

        log_info('auth', 'login', 'Usuario ' . $usuarioPendente['codigo'] . ' (' . $usuarioPendente['nome'] . ') realizou login na empresa ' . $vinculoSelecionado['empresa_codigo'] . ' (' . $vinculoSelecionado['empresa_nome'] . ')');
        set_flash('success', __('login.bem_vindo') . ', ' . $usuarioPendente['nome'] . '!');
        redirect(url('empresas'));
        break;

    case 'switch_empresa':
        if (!is_logged_in()) {
            redirect(url('login'));
        }

        $empresaId = trim((string) ($_GET['empresa_id'] ?? ''));
        if ($empresaId === '') {
            set_flash('danger', 'Empresa nao informada para alternancia.');
            redirect(url('empresas'));
        }

        $usuarioAtual = current_user();
        $vinculosDisponiveis = user_empresas_disponiveis();

        $vinculoSelecionado = null;
        foreach ($vinculosDisponiveis as $vinculo) {
            if (($vinculo['empresa_id'] ?? '') === $empresaId) {
                $vinculoSelecionado = $vinculo;
                break;
            }
        }

        if (!$vinculoSelecionado || !$usuarioAtual) {
            set_flash('danger', 'Empresa invalida para este usuario.');
            redirect(url('empresas'));
        }

        aplicar_empresa_na_sessao($usuarioAtual, $vinculoSelecionado, $vinculosDisponiveis);

        if (usuario_deve_aceitar_termos()) {
            set_flash('warning', __('termos.aviso_aceite_obrigatorio'));
            redirect(url('login', 'termos'));
        }

        log_info('auth', 'empresa_trocada', 'Usuario alternou empresa ativa para ' . $vinculoSelecionado['empresa_codigo'] . ' (' . $vinculoSelecionado['empresa_nome'] . ')');
        set_flash('success', 'Empresa ativa alterada para ' . $vinculoSelecionado['empresa_nome'] . '.');
        redirect(url('empresas'));
        break;

    case 'logout':
        log_info('auth', 'logout', 'Usuario realizou logout do sistema');
        session_destroy();
        redirect(url('login'));
        break;

    case 'index':
    default:
        require __DIR__ . '/../views/auth/login.php';
        break;
}
