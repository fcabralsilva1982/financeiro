<?php
declare(strict_types=1);

/**
 * Bootstrap dos testes — carrega configurações mínimas para rodar
 * sem precisar de um servidor web ou sessão ativa.
 */

// Autoload do Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Simular superglobais mínimas (evitar warnings)
$_SERVER['HTTP_HOST']       = 'localhost';
$_SERVER['HTTPS']           = '';
$_SERVER['REQUEST_URI']     = '/financeiro/index.php';
$_SERVER['REQUEST_METHOD']  = 'GET';
$_SERVER['REMOTE_ADDR']     = '127.0.0.1';
$_SERVER['HTTP_USER_AGENT'] = 'PHPUnit';

// Iniciar sessão para testes (antes do config.php)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Definir constantes antes que config.php tente ler .env
$_ENV['APP_URL']            = 'http://localhost/financeiro';
$_ENV['APP_DEBUG']          = 'true';
$_ENV['APP_LANG']           = 'pt-br';
$_ENV['BCRYPT_COST']        = '4'; // custo baixo = testes rápidos
$_ENV['SESSION_TIMEOUT']    = '300';
$_ENV['MASTER_EMPRESA_ID']  = 'f0f0f0f0-f0f0-4f0f-f0f0-f0f0f0f0f0f0';
$_ENV['DB_HOST']            = 'localhost';
$_ENV['DB_PORT']            = '3306';
$_ENV['DB_NAME']            = 'financeiro_erp_test';
$_ENV['DB_USER']            = 'root';
$_ENV['DB_PASS']            = '';

// Capturar headers para evitar erro "headers already sent"
ob_start();

// Carregar configurações (define constantes BASE_URL, BCRYPT_COST, etc.)
require_once __DIR__ . '/../config/config.php';

// Carregar helpers
require_once __DIR__ . '/../helpers/functions.php';
require_once __DIR__ . '/../helpers/database.php';
require_once __DIR__ . '/../helpers/logger.php';

ob_end_clean();
