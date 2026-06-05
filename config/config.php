<?php
declare(strict_types=1);

/**
 * Configurações gerais do sistema
 * Valores sensíveis são lidos do arquivo .env na raiz do projeto.
 */

// --- Carrega variáveis do .env ---
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $pos = strpos($line, '=');
        if ($pos === false) {
            continue;
        }
        $key   = trim(substr($line, 0, $pos));
        $value = trim(substr($line, $pos + 1));
        if (!array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
        }
    }
}

/**
 * Retorna valor de variável de ambiente com fallback padrão
 */
function env($key, $default = null) {
    return $_ENV[$key] ?? $default;
}

// --- Aplicação ---
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
define('BASE_URL', env('APP_URL', $protocol . '://' . $host . '/financeiro'));

define('APP_NAME',    env('APP_NAME', 'FinanceiroApp'));
define('APP_VERSION', env('APP_VERSION', '1.0.0'));
define('APP_DEBUG',   env('APP_DEBUG', 'false') === 'true');

// Custo do bcrypt para password_hash (10-12 recomendado, 12 = mais seguro)
define('BCRYPT_COST', (int) env('BCRYPT_COST', '12'));

// Tempo de inatividade da sessão em segundos (300 = 5 minutos)
define('SESSION_TIMEOUT', (int) env('SESSION_TIMEOUT', '300'));

// Empresa master (dona do sistema) — UUID fixo
define('MASTER_EMPRESA_ID', env('MASTER_EMPRESA_ID', 'a1b2c3d4-e5f6-7890-abcd-ef1234567890'));

// Idioma padrão
define('DEFAULT_LANG', env('APP_LANG', 'pt-br'));

// Idiomas disponíveis
define('AVAILABLE_LANGS', ['pt-br', 'en']);
define('TERMS_CURRENT_VERSION', env('TERMS_CURRENT_VERSION', '1.0.0'));

// Mapa de modulos/acoes para evolucao de controle de permissao
define('PERMISSION_MODULES', [
    'empresas',
    'usuarios',
    'perfis',
    'contas_receber',
    'contas_pagar',
    'dashboard',
    'relatorios',
    'conciliacao',
    'importacao',
    'categorias',
]);

// --- Banco de Dados ---
define('DB_HOST',    env('DB_HOST', 'localhost'));
define('DB_PORT',    env('DB_PORT', '3306'));
define('DB_NAME',    env('DB_NAME', 'financeiro_erp'));
define('DB_USER',    env('DB_USER', 'root'));
define('DB_PASS',    env('DB_PASS', ''));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

// Timezone
date_default_timezone_set(env('APP_TIMEZONE', 'America/Sao_Paulo'));

// --- Erros: nunca expor em produção ---
if (!APP_DEBUG) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
}
ini_set('log_errors', '1');

// --- Headers de Segurança ---
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cross-Origin-Opener-Policy: same-origin');
header('Cross-Origin-Resource-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net; font-src 'self' https://cdn.jsdelivr.net; img-src 'self' data: https://ui-avatars.com; connect-src 'self' https://viacep.com.br; object-src 'none'; base-uri 'self'; form-action 'self';");
if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) {
    header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
}

header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()');
header('Cache-Control: no-store, no-cache, must-revalidate, proxy-revalidate');
header('Pragma: no-cache');
header('X-Permitted-Cross-Domain-Policies: none');

// --- Sessão segura ---
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly'  => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

// Define o idioma ativo
if (isset($_GET['lang']) && in_array($_GET['lang'], AVAILABLE_LANGS)) {
    $_SESSION['lang'] = $_GET['lang'];
}
$currentLang = $_SESSION['lang'] ?? DEFAULT_LANG;
define('CURRENT_LANG', $currentLang);
