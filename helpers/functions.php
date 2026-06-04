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
 * @param string $url URL de destino (gerada por url() ou absoluta)
 * @return never
 */
function redirect($url) {
    header('Location: ' . $url);
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
