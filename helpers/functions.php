<?php
declare(strict_types=1);

/**
 * Gera um identificador único universal no formato UUID v4 (RFC 4122).
 *
 * Utiliza bytes aleatórios criptograficamente seguros (random_bytes) e
 * ajusta os bits de versão (4) e variante (RFC 4122) conforme a especificação.
 *
 * Formato: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx (36 caracteres)
 *
 * @return string UUID v4 em lowercase, ex: '550e8400-e29b-41d4-a716-446655440000'
 */
function generate_uuid() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // versão 4
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // variante RFC 4122
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Formata uma data (YYYY-MM-DD) para o padrão brasileiro (DD/MM/YYYY).
 * Se a data for vazia ou inválida, retorna string vazia.
 * @param string|null $data Data no formato YYYY-MM-DD
 * @return string Data formatada ou vazia
 */
function date_fmt($data) {
    if (empty($data) || $data === '0000-00-00') return '';
    $ts = strtotime($data);
    return $ts ? date('d/m/Y', $ts) : '';
}

// Funções utilitárias do sistema

// Carrega o arquivo de idioma
$langFile = __DIR__ . '/../lang/' . CURRENT_LANG . '.php';
$GLOBALS['translations'] = file_exists($langFile) ? require $langFile : [];
// ============================================================
// Funções de Senha Segura (bcrypt com custo alto)
// ============================================================

/**
 * Cria hash seguro de senha usando o algoritmo bcrypt.
 *
 * O custo (work factor) é definido pela constante BCRYPT_COST (padrão: 12).
 * Cada chamada gera um salt aleatório diferente, então dois hashes
 * da mesma senha serão sempre distintos.
 *
 * @param string $senhaPlana Senha em texto plano a ser hasheada
 * @return string Hash bcrypt no formato '$2y$XX$...' (60 caracteres)
 */
function hash_senha($senhaPlana) {
    return password_hash($senhaPlana, PASSWORD_BCRYPT, [
        'cost' => defined('BCRYPT_COST') ? BCRYPT_COST : 12,
    ]);
}

/**
 * Verifica se uma senha em texto plano corresponde a um hash bcrypt.
 *
 * @param string $senhaPlana Senha em texto plano informada pelo usuário
 * @param string $hash       Hash bcrypt armazenado no banco de dados
 * @return bool true se a senha corresponde ao hash, false caso contrário
 */
function verificar_senha($senhaPlana, $hash) {
    return password_verify($senhaPlana, $hash);
}

/**
 * Verifica se um hash existente precisa ser recalculado.
 *
 * Retorna true se o custo do bcrypt foi alterado desde a geração do hash.
 * Chamada durante o login para migrar hashes antigos transparentemente.
 *
 * @param string $hash Hash bcrypt atual armazenado no banco
 * @return bool true se o hash deve ser regenerado com o custo atual
 */
function senha_precisa_rehash($hash) {
    return password_needs_rehash($hash, PASSWORD_BCRYPT, [
        'cost' => defined('BCRYPT_COST') ? BCRYPT_COST : 12,
    ]);
}

/**
 * Gera código amigável sequencial para identificação visual de registros.
 *
 * Cada módulo possui um prefixo de 3 letras:
 *   - EMP: empresas (EMP-0001, EMP-0002, ...)
 *   - USR: usuários (USR-0001, USR-0002, ...)
 *   - PRF: perfis   (PRF-0001, PRF-0002, ...)
 *
 * @param string     $prefixo    Prefixo de 3 letras do módulo (será convertido para UPPERCASE)
 * @param string|int $sequencial Número sequencial a ser formatado com zero-fill de 4 dígitos
 * @return string Código no formato 'PREFIXO-XXXX', ex: 'EMP-0042'
 */
function generate_codigo($prefixo, $sequencial) {
    return strtoupper($prefixo) . '-' . str_pad((string) $sequencial, 4, '0', STR_PAD_LEFT);
}

/**
 * Gera URL interna do sistema com parâmetros de query string.
 *
 * Monta a URL completa para navegação, incluindo page, action e parâmetros
 * adicionais. A saída é escapada com htmlspecialchars (segura para uso em HTML).
 *
 * @param string $page   Nome da página/módulo (ex: 'empresas', 'usuarios', 'login')
 * @param string $action Ação do controller (ex: 'create', 'edit', 'store', 'delete')
 * @param array  $params Parâmetros adicionais na query string (ex: ['id' => 'uuid-123'])
 * @return string URL completa e escapada, ex: 'http://host/financeiro/index.php?page=empresas&amp;action=edit&amp;id=uuid-123'
 */
function url($page = '', $action = '', $params = []) {
    $url = BASE_URL . '/index.php';
    $query = [];
    
    if ($page) {
        $query['page'] = $page;
    }
    if ($action) {
        $query['action'] = $action;
    }
    if (!empty($params)) {
        $query = array_merge($query, $params);
    }
    
    if (!empty($query)) {
        $url .= '?' . http_build_query($query);
    }
    
    return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
}

/**
 * Gera URL absoluta para assets estáticos (CSS, JS, imagens).
 *
 * @param string $path Caminho relativo dentro de public/ (ex: 'css/style.css', 'js/app.js')
 * @return string URL completa do asset, ex: 'http://host/financeiro/public/css/style.css'
 */
function asset($path) {
    return BASE_URL . '/public/' . ltrim($path, '/');
}

/**
 * Retorna o diretorio raiz de storage externo de uploads.
 */
function upload_storage_root(): string
{
    $root = str_replace('\\', '/', (string) UPLOAD_STORAGE_PATH);
    return rtrim($root, '/');
}

/**
 * Retorna caminho relativo normalizado para logos no storage externo.
 * Aceita formato atual (logos/arquivo.png) e legado (uploads/logos/arquivo.png).
 */
function normalize_logo_relative_path(string $path): ?string
{
    $normalized = ltrim(str_replace('\\', '/', $path), '/');
    if ($normalized === '') {
        return null;
    }

    if (str_starts_with($normalized, 'logos/')) {
        return $normalized;
    }

    if (str_starts_with($normalized, 'uploads/logos/')) {
        return 'logos/' . substr($normalized, strlen('uploads/logos/'));
    }

    return null;
}

/**
 * Retorna caminho relativo normalizado para comprovantes no storage externo.
 * Aceita formato atual (comprovantes/arquivo.pdf) e legado (uploads/comprovantes/arquivo.pdf).
 */
function normalize_comprovante_relative_path(string $path): ?string
{
    $normalized = ltrim(str_replace('\\', '/', $path), '/');
    if ($normalized === '') {
        return null;
    }

    if (str_starts_with($normalized, 'comprovantes/')) {
        return $normalized;
    }

    if (str_starts_with($normalized, 'uploads/comprovantes/')) {
        return 'comprovantes/' . substr($normalized, strlen('uploads/comprovantes/'));
    }

    return null;
}

/**
 * Retorna caminho relativo normalizado para documentos de clientes.
 */
function normalize_cliente_document_relative_path(string $path): ?string
{
    $normalized = ltrim(str_replace('\\', '/', $path), '/');
    if ($normalized === '') {
        return null;
    }

    if (str_starts_with($normalized, 'cliente_documentos/')) {
        return $normalized;
    }

    return null;
}

/**
 * Retorna caminho relativo normalizado para documentos de fornecedores.
 */
function normalize_fornecedor_document_relative_path(string $path): ?string
{
    $normalized = ltrim(str_replace('\\', '/', $path), '/');
    if ($normalized === '') {
        return null;
    }

    if (str_starts_with($normalized, 'fornecedor_documentos/')) {
        return $normalized;
    }

    return null;
}

/**
 * Gera URL de exibicao da logo da empresa via endpoint autenticado.
 */
function empresa_logo_url(string $empresaId): string
{
    return html_entity_decode(url('empresas', 'logo', ['id' => $empresaId]), ENT_QUOTES, 'UTF-8');
}

/**
 * Verifica se o limite de tentativas de upload por IP foi excedido.
 */
function upload_rate_limit_exceeded(
    string $tipo,
    ?string $ip = null,
    ?int $windowSeconds = null,
    ?int $maxAttempts = null
): bool {
    $window = $windowSeconds ?? UPLOAD_RATE_LIMIT_WINDOW_SECONDS;
    $max = $maxAttempts ?? UPLOAD_RATE_LIMIT_MAX_ATTEMPTS;
    if ($window <= 0 || $max <= 0) {
        return false;
    }

    $ipAddr = $ip ?? get_client_ip();
    $desde = date('Y-m-d H:i:s', time() - $window);

    $pdo = db();
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM log_acoes
         WHERE modulo = 'upload'
           AND acao = :acao
           AND ip = :ip
           AND criado_em >= :desde"
    );
    $stmt->execute([
        ':acao' => 'tentativa_' . $tipo,
        ':ip' => $ipAddr,
        ':desde' => $desde,
    ]);

    return (int) $stmt->fetchColumn() >= $max;
}

/**
 * Registra tentativa de upload para trilha de auditoria e rate limit.
 */
function registrar_tentativa_upload(string $tipo, string $resultado): void
{
    log_info('upload', 'tentativa_' . $tipo, 'Resultado: ' . $resultado);
}

/**
 * Executa varredura antivírus no arquivo em quarentena.
 *
 * Comando esperado em UPLOAD_AV_SCAN_COMMAND:
 * - Ex.: clamscan --no-summary {file}
 * - Se {file} não for informado, o caminho é adicionado no final.
 */
function scan_uploaded_file_or_fail(string $filePath, string $tipo): void
{
    if (!UPLOAD_AV_SCAN_ENABLED) {
        return;
    }

    $template = trim((string) UPLOAD_AV_SCAN_COMMAND);
    if ($template === '') {
        throw new RuntimeException('Scanner antivirus de upload nao configurado.');
    }

    $fileArg = escapeshellarg($filePath);
    $command = str_contains($template, '{file}')
        ? str_replace('{file}', $fileArg, $template)
        : ($template . ' ' . $fileArg);

    $output = [];
    $exitCode = 0;
    exec($command . ' 2>&1', $output, $exitCode);

    if ($exitCode === 0) {
        return;
    }

    if ($exitCode === 1) {
        throw new RuntimeException('Arquivo bloqueado por verificacao de seguranca (' . $tipo . ').');
    }

    if (UPLOAD_AV_FAIL_OPEN) {
        log_warning('upload', 'scan_indisponivel', 'Scan indisponivel (fail-open ativo): ' . implode(' | ', $output));
        return;
    }

    throw new RuntimeException('Falha na verificacao de seguranca do upload.');
}

/**
 * Retorna a tradução de uma chave no idioma ativo.
 *
 * As traduções são carregadas dos arquivos em lang/ (pt-br.php, en.php).
 * Suporta chaves aninhadas com notação de ponto (ex: 'menu.dashboard').
 *
 * @param string $key     Chave de tradução com notação de ponto (ex: 'login.title', 'empresas.new')
 * @param string $default Valor padrão caso a chave não exista (se vazio, retorna a própria chave)
 * @return string Texto traduzido ou o valor padrão/chave
 */
function __($key, $default = '') {
    $keys = explode('.', $key);
    $value = $GLOBALS['translations'];
    
    foreach ($keys as $k) {
        if (isset($value[$k])) {
            $value = $value[$k];
        } else {
            return $default ?: $key;
        }
    }
    
    return $value;
}

/**
 * Retorna a classe CSS 'active' se a página atual corresponder ao item de menu.
 *
 * Usado na sidebar para destacar visualmente o link do módulo ativo.
 * Compara o parâmetro GET 'page' com o valor informado.
 *
 * @param string $page Nome da página a comparar (ex: 'empresas', 'usuarios')
 * @return string 'active' se a página corresponde, string vazia caso contrário
 */
function selected_menu($page) {
    $currentPage = $_GET['page'] ?? '';
    return ($currentPage === $page) ? 'active' : '';
}

/**
 * Gera campo hidden HTML com token CSRF para proteção de formulários.
 *
 * Se não existir token na sessão, gera um novo de 32 bytes aleatórios (64 chars hex).
 * O token é reutilizado enquanto a sessão estiver ativa.
 * Na submissão, o token enviado deve ser comparado com hash_equals() contra o da sessão.
 *
 * @return string HTML do input hidden com o token CSRF
 */
function csrf_field() {
    $token = $_SESSION['csrf_token'] ?? bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verifica se o token CSRF enviado e valido para a sessao atual.
 */
function has_valid_csrf_token(string $enviado): bool
{
    $sessao = (string) ($_SESSION['csrf_token'] ?? '');
    return $enviado !== '' && $sessao !== '' && hash_equals($sessao, $enviado);
}

/**
 * Exige metodo POST para a acao atual.
 * Retorna false e responde 405 quando o metodo e invalido.
 */
function enforce_post_request(): bool
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
        return true;
    }

    http_response_code(405);
    echo 'Metodo nao permitido.';
    return false;
}

/**
 * Renderiza e consome a mensagem flash armazenada na sessão.
 *
 * Mensagens flash são alertas temporários (sucesso, erro, aviso) exibidos
 * uma única vez após um redirect. Após a exibição, a mensagem é removida
 * da sessão automaticamente.
 *
 * @return string HTML do alerta Bootstrap (alert-success, alert-danger, etc.) ou string vazia
 */
function flash_message() {
    if (isset($_SESSION['flash'])) {
        $type = $_SESSION['flash']['type'] ?? 'info';
        $message = htmlspecialchars($_SESSION['flash']['message'] ?? '', ENT_QUOTES, 'UTF-8');
        unset($_SESSION['flash']);
        return '<div class="alert alert-' . $type . ' alert-dismissible fade show" role="alert">'
             . $message
             . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'
             . '</div>';
    }
    return '';
}

/**
 * Armazena uma mensagem flash na sessão para exibição após redirect.
 *
 * @param string $type    Tipo do alerta Bootstrap: 'success', 'danger', 'warning', 'info'
 * @param string $message Texto da mensagem a ser exibida ao usuário
 * @return void
 */
function set_flash($type, $message) {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/**
 * Redireciona o navegador para a URL informada e encerra a execução.
 *
 * Envia o header HTTP 'Location' e chama exit() para garantir
 * que nenhum código subsequente seja executado.
 *
 * A URL recebida pode estar escapada para HTML (ex: '&amp;'),
 * portanto ela é decodificada antes do envio no header.
 *
 * @param string $url URL de destino (gerada por url() ou absoluta)
 * @return never
 */
function redirect($url) {
    // url() retorna valor escapado para HTML; no header HTTP precisamos da URL crua.
    $redirectUrl = html_entity_decode((string) $url, ENT_QUOTES, 'UTF-8');
    header('Location: ' . $redirectUrl);
    exit;
}

/**
 * Escapa uma string para saída segura em HTML (prevenção de XSS).
 *
 * Converte caracteres especiais (<, >, ", ', &) em entidades HTML.
 * Aceita string, int, float ou null (null é convertido para string vazia).
 *
 * @param string|int|float|null $string Valor a ser escapado
 * @return string Valor seguro para exibição em HTML
 */
function e(string|int|float|null $string): string {
    return htmlspecialchars((string) ($string ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Formata valor monetário para exibição em pt-BR.
 *
 * @param mixed $valor
 * @return string
 */
function format_money_br($valor): string
{
    if ($valor === null || $valor === '') {
        return '';
    }

    return number_format((float) $valor, 2, ',', '.');
}

/**
 * Normaliza valor monetário informado em pt-BR para formato numérico do PHP/SQL.
 *
 * Aceita entradas como "1.234,56", "1234,56" ou "1234.56".
 *
 * @param mixed $valor
 * @return float
 */
function normalize_money_br($valor): float
{
    if ($valor === null) {
        return 0.0;
    }

    $texto = trim((string) $valor);
    if ($texto === '') {
        return 0.0;
    }

    $texto = str_replace(['R$', ' '], '', $texto);

    if (str_contains($texto, ',')) {
        $texto = str_replace('.', '', $texto);
        $texto = str_replace(',', '.', $texto);
    }

    return (float) $texto;
}

// ============================================================
// Funções de Multi-Tenancy (isolamento por empresa)
// ============================================================

/**
 * Retorna o UUID da empresa ativa na sessão do usuário logado.
 *
 * @return string|null UUID da empresa ou null se não houver sessão
 */
function current_empresa_id() {
    return $_SESSION['user']['empresa_id'] ?? null;
}

/**
 * Retorna o nome fantasia da empresa ativa na sessão.
 *
 * @return string Nome da empresa ou string vazia se não houver sessão
 */
function current_empresa_nome() {
    return $_SESSION['user']['empresa_nome'] ?? '';
}

/**
 * Retorna o codigo da empresa ativa na sessao.
 *
 * @return string
 */
function current_empresa_codigo() {
    return $_SESSION['user']['empresa_codigo'] ?? '';
}

/**
 * Verifica se o usuário logado pertence à empresa master (dona do sistema).
 *
 * A empresa master é definida pela constante MASTER_EMPRESA_ID e possui
 * privilégios especiais: gerenciar outras empresas e ver logs de todos.
 *
 * @return bool true se o usuário pertence à empresa master
 */
function is_master_empresa() {
    return current_empresa_id() === MASTER_EMPRESA_ID;
}

/**
 * Verifica se o usuário pode visualizar dados de todas as empresas.
 * Apenas admins da empresa master têm essa permissão.
 */
function can_see_all_empresas() {
    return is_master_empresa();
}

/**
 * Verifica se o usuário logado pode administrar empresas (criar, editar, bloquear, excluir).
 * Apenas usuários da empresa master podem.
 */
function can_manage_empresas() {
    return is_master_empresa();
}

/**
 * Filtra um array de registros pelo isolamento multi-tenant.
 *
 * Se o usuário pertence à empresa master, retorna todos os registros.
 * Caso contrário, retorna apenas os registros cuja chave de empresa
 * corresponde à empresa do usuário logado.
 *
 * @param array  $records Array de registros associativos a filtrar
 * @param string $key     Nome da chave que contém o ID da empresa em cada registro
 * @return array Registros filtrados (preserva chaves originais do array)
 */
function filter_by_empresa($records, $key = 'empresa_id') {
    if (can_see_all_empresas()) {
        return $records;
    }
    $empresaId = current_empresa_id();
    return array_filter($records, function($r) use ($key, $empresaId) {
        return ($r[$key] ?? null) == $empresaId;
    });
}

/**
 * Retorna os dados completos do usuário logado armazenados na sessão.
 *
 * O array contém: id, codigo, nome, email, empresa_id, empresa_codigo,
 * empresa_nome, empresa_logo, perfil_id, perfil_nome.
 *
 * @return array|null Dados do usuário ou null se não estiver logado
 */
function current_user() {
    return $_SESSION['user'] ?? null;
}

/**
 * Retorna os vinculos de empresa disponiveis para o usuario logado.
 *
 * @return array<int, array<string, mixed>>
 */
function user_empresas_disponiveis(): array {
    return $_SESSION['user_empresas'] ?? [];
}

/**
 * Informa se o usuario pode alternar a empresa ativa.
 *
 * @return bool
 */
function can_switch_empresa(): bool {
    return count(user_empresas_disponiveis()) > 1;
}

/**
 * Verifica se o usuario logado possui uma permissao especifica.
 *
 * Usuários da empresa master possuem acesso total. Para os demais,
 * a permissão é verificada na tabela perfil_permissoes pelo perfil ativo.
 *
 * @param string $modulo Módulo do sistema (ex: contas_pagar)
 * @param string $acao Ação do módulo (ex: aprovar)
 * @return bool
 */
function can_user(string $modulo, string $acao): bool
{
    if (!is_logged_in()) {
        return false;
    }

    if (is_master_empresa()) {
        return true;
    }

    $perfilId = (string) (current_user()['perfil_id'] ?? '');
    if ($perfilId === '') {
        return false;
    }

    try {
        $pdo = db();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) FROM perfil_permissoes WHERE perfil_id = :perfil_id AND modulo = :modulo AND acao = :acao'
        );
        $stmt->execute([
            ':perfil_id' => $perfilId,
            ':modulo' => $modulo,
            ':acao' => $acao,
        ]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Exception $ex) {
        return false;
    }
}

/**
 * Resolve permissao exigida para rota sensivel (SEC-004).
 * Retorna null quando a rota nao exige permissao granular.
 *
 * @return array{modulo:string,acao:string}|null
 */
function resolve_sensitive_route_permission(string $page, string $action, array $post = []): ?array
{
    $modulo = trim($page);
    $rota = trim($action);

    if ($modulo === '' || $rota === '' || $modulo === 'login') {
        return null;
    }

    $storeAction = static function (array $payload): string {
        return trim((string) ($payload['id'] ?? '')) !== '' ? 'editar' : 'criar';
    };

    return match ($modulo) {
        'clientes', 'fornecedores', 'empresas', 'perfis', 'usuarios', 'categorias' => match ($rota) {
            'store' => ['modulo' => $modulo, 'acao' => $storeAction($post)],
            'block', 'unblock' => ['modulo' => $modulo, 'acao' => 'bloquear'],
            'delete', 'delete_documento' => ['modulo' => $modulo, 'acao' => 'excluir'],
            default => null,
        },
        'conciliacao' => match ($rota) {
            'importar' => ['modulo' => $modulo, 'acao' => 'criar'],
            'marcar_receber', 'marcar_pagar', 'marcar_interno', 'ignorar' => ['modulo' => $modulo, 'acao' => 'editar'],
            default => null,
        },
        'contas_receber' => match ($rota) {
            'save' => ['modulo' => $modulo, 'acao' => $storeAction($post)],
            'delete' => ['modulo' => $modulo, 'acao' => 'excluir'],
            'baixar' => ['modulo' => $modulo, 'acao' => 'editar'],
            default => null,
        },
        'contas_pagar' => match ($rota) {
            'store' => ['modulo' => $modulo, 'acao' => $storeAction($post)],
            'enviar_aprovacao', 'aprovar', 'cancelar' => ['modulo' => $modulo, 'acao' => 'aprovar'],
            'baixar_parcela' => ['modulo' => $modulo, 'acao' => 'editar'],
            default => null,
        },
        default => null,
    };
}

/**
 * Registra evento antifraude com contexto minimo (usuario, empresa, ip e recurso).
 */
function registrar_evento_antifraude(string $tipo, string $modulo, string $acao, ?string $registroId = null, array $extra = []): void
{
    if (!function_exists('registrar_log')) {
        return;
    }

    $user = current_user() ?? [];
    $dados = array_merge([
        'modulo_alvo' => $modulo,
        'acao_alvo' => $acao,
        'registro_id' => $registroId,
        'usuario_id' => (string) ($user['id'] ?? ''),
        'usuario_codigo' => (string) ($user['codigo'] ?? ''),
        'empresa_id' => (string) ($user['empresa_id'] ?? ''),
        'empresa_codigo' => (string) ($user['empresa_codigo'] ?? ''),
        'ip' => function_exists('get_client_ip') ? get_client_ip() : (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
    ], $extra);

    $descricao = 'Evento antifraude: ' . $tipo . ' em ' . $modulo . '.' . $acao;
    registrar_log('antifraude', $tipo, $descricao, 'warning', $registroId, null, null, $dados);
}

/**
 * Registra alerta antifraude se o usuario exceder limite de acoes sensiveis na janela.
 */
function verificar_alerta_antifraude(string $modulo, string $acao, int $windowSeconds = 900, int $limite = 3): void
{
    if (!is_logged_in() || $windowSeconds <= 0 || $limite <= 0) {
        return;
    }

    $usuarioId = (string) (current_user()['id'] ?? '');
    if ($usuarioId === '') {
        return;
    }

    try {
        $pdo = db();
        $desde = date('Y-m-d H:i:s', time() - $windowSeconds);
        $evento = $modulo . '.' . $acao;

        $stmt = $pdo->prepare(
            "SELECT COUNT(*)
             FROM log_acoes
             WHERE modulo = 'antifraude'
               AND acao = 'acao_sensivel'
               AND usuario_id = :usuario_id
               AND descricao LIKE :evento
               AND criado_em >= :desde"
        );
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':evento' => '%em ' . $evento,
            ':desde' => $desde,
        ]);

        $qtd = (int) $stmt->fetchColumn();
        if ($qtd < $limite) {
            return;
        }

        $alertaDesc = 'Padrao suspeito detectado para ' . $evento;
        $check = $pdo->prepare(
            "SELECT COUNT(*)
             FROM log_acoes
             WHERE modulo = 'antifraude'
               AND acao = 'alerta_padrao_suspeito'
               AND usuario_id = :usuario_id
               AND descricao = :descricao
               AND criado_em >= :desde"
        );
        $check->execute([
            ':usuario_id' => $usuarioId,
            ':descricao' => $alertaDesc,
            ':desde' => $desde,
        ]);

        if ((int) $check->fetchColumn() > 0) {
            return;
        }

        if (function_exists('registrar_log')) {
            registrar_log(
                'antifraude',
                'alerta_padrao_suspeito',
                $alertaDesc,
                'critical',
                null,
                null,
                null,
                [
                    'evento' => $evento,
                    'quantidade' => $qtd,
                    'janela_segundos' => $windowSeconds,
                    'limite' => $limite,
                    'usuario_id' => $usuarioId,
                ]
            );
        }
    } catch (Throwable $ex) {
        // Nao interrompe o fluxo principal em caso de falha na trilha antifraude.
    }
}

/**
 * Verifica se o usuario aceitou a versao atual dos termos.
 *
 * @param string $usuarioId
 * @return bool
 */
function usuario_aceitou_termo_versao_atual(string $usuarioId): bool {
    try {
        $pdo = db();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM usuario_termos WHERE usuario_id = :uid AND versao_termo = :versao');
        $stmt->execute([
            ':uid' => $usuarioId,
            ':versao' => TERMS_CURRENT_VERSION,
        ]);
        return (int) $stmt->fetchColumn() > 0;
    } catch (Exception $ex) {
        // Fail-open para nao bloquear acesso caso migration ainda nao tenha sido aplicada.
        return false;
    }
}

/**
 * Determina se o usuario logado precisa aceitar os termos.
 *
 * @return bool
 */
function usuario_deve_aceitar_termos(): bool {
    if (!is_logged_in()) {
        return false;
    }

    $cacheVersao = $_SESSION['terms_accepted_version'] ?? '';
    if ($cacheVersao === TERMS_CURRENT_VERSION) {
        return false;
    }

    $user = current_user();
    $usuarioId = (string) ($user['id'] ?? '');
    if ($usuarioId === '') {
        return false;
    }

    if (usuario_aceitou_termo_versao_atual($usuarioId)) {
        $_SESSION['terms_accepted_version'] = TERMS_CURRENT_VERSION;
        return false;
    }

    return true;
}

/**
 * Verifica se existe um usuário autenticado na sessão atual.
 *
 * @return bool true se há um usuário logado, false caso contrário
 */
function is_logged_in() {
    return !empty($_SESSION['user']);
}

/**
 * Gera a URL para alternar entre os idiomas disponíveis (pt-br ↔ en).
 *
 * Preserva todos os parâmetros GET atuais e adiciona/substitui o parâmetro 'lang'.
 *
 * @return string URL com o idioma alternativo selecionado
 */
function lang_switch_url() {
    $newLang = (CURRENT_LANG === 'pt-br') ? 'en' : 'pt-br';
    $params = $_GET;
    $params['lang'] = $newLang;
    return BASE_URL . '/index.php?' . http_build_query($params);
}

/**
 * Retorna o label abreviado do idioma ativo para exibição na interface.
 *
 * @return string 'PT-BR' ou 'EN'
 */
function current_lang_label() {
    return (CURRENT_LANG === 'pt-br') ? 'PT-BR' : 'EN';
}
