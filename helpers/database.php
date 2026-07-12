<?php
declare(strict_types=1);

/**
 * Retorna a instância única (singleton) de conexão PDO com o MySQL.
 *
 * Na primeira chamada, cria a conexão usando as constantes DB_HOST, DB_PORT,
 * DB_NAME e DB_CHARSET definidas em config.php, e DB_USER/DB_PASS via env().
 * Chamadas subsequentes reutilizam a mesma instância, evitando múltiplas conexões.
 *
 * Configurações de segurança:
 *   - ERRMODE_EXCEPTION: erros de SQL lançam exceções PDOException
 *   - EMULATE_PREPARES=false: prepared statements nativos do MySQL (proteção contra SQL injection)
 *   - FETCH_ASSOC: resultados retornam como arrays associativos por padrão
 *
 * @return \PDO Instância de conexão com o banco de dados
 * @throws \PDOException Se a conexão falhar (credenciais, host, banco inexistente)
 */
function db() {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST
             . ';port=' . DB_PORT
             . ';dbname=' . DB_NAME
             . ';charset=' . DB_CHARSET;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $pdo = new PDO($dsn, env('DB_USER', 'root'), env('DB_PASS', ''), $options);
    }

    return $pdo;
}
