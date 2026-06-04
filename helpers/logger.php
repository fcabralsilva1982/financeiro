<?php
declare(strict_types=1);
require_once __DIR__ . '/functions.php';

/**
 * Sistema de Log detalhado
 * Grava ações no banco de dados, identificadas pelo código da empresa e do usuário.
 *
 * Níveis: info, warning, error, critical
 * Cada registro inclui: quem fez (usuário+empresa via código), o que fez (módulo+ação),
 * em qual registro, descrição, dados antes/depois (JSON), IP, user agent, URL, método HTTP.
 */

/**
 * Registra uma ação no log do sistema.
 *
 * @param string      $modulo          Módulo: 'auth', 'empresas', 'usuarios', 'perfis', etc.
 * @param string      $acao            Ação: 'login', 'logout', 'criar', 'editar', 'bloquear', 'desbloquear', 'excluir', 'visualizar'
 * @param string      $descricao       Descrição humana e detalhada da ação
 * @param string      $nivel           Nível: 'info', 'warning', 'error', 'critical'
 * @param string|null $registro_id     UUID do registro afetado
 * @param string|null $registro_codigo Código amigável do registro afetado
 * @param array|null  $dados_anteriores Dados antes da alteração
 * @param array|null  $dados_novos      Dados depois da alteração
 * @return bool
 */
function registrar_log(
    $modulo,
    $acao,
    $descricao,
    $nivel = 'info',
    $registro_id = null,
    $registro_codigo = null,
    $dados_anteriores = null,
    $dados_novos = null
) {
    try {
        $pdo = db();
        $user = current_user();

        $sql = "INSERT INTO `log_acoes` (
            `id`, `empresa_id`, `empresa_codigo`, `usuario_id`, `usuario_codigo`, `usuario_nome`,
            `modulo`, `acao`, `nivel`, `registro_id`, `registro_codigo`, `descricao`,
            `dados_anteriores`, `dados_novos`, `ip`, `user_agent`, `url`, `metodo_http`
        ) VALUES (
            :id, :empresa_id, :empresa_codigo, :usuario_id, :usuario_codigo, :usuario_nome,
            :modulo, :acao, :nivel, :registro_id, :registro_codigo, :descricao,
            :dados_anteriores, :dados_novos, :ip, :user_agent, :url, :metodo_http
        )";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':id'                => \generate_uuid(),
            ':empresa_id'        => $user['empresa_id'] ?? null,
            ':empresa_codigo'    => $user['empresa_codigo'] ?? null,
            ':usuario_id'        => $user['id'] ?? null,
            ':usuario_codigo'    => $user['codigo'] ?? null,
            ':usuario_nome'      => $user['nome'] ?? 'Sistema',
            ':modulo'            => $modulo,
            ':acao'              => $acao,
            ':nivel'             => $nivel,
            ':registro_id'       => $registro_id,
            ':registro_codigo'   => $registro_codigo,
            ':descricao'         => $descricao,
            ':dados_anteriores'  => $dados_anteriores ? json_encode($dados_anteriores, JSON_UNESCAPED_UNICODE) : null,
            ':dados_novos'       => $dados_novos ? json_encode($dados_novos, JSON_UNESCAPED_UNICODE) : null,
            ':ip'                => get_client_ip(),
            ':user_agent'        => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            ':url'               => substr(($_SERVER['REQUEST_URI'] ?? ''), 0, 2000),
            ':metodo_http'       => $_SERVER['REQUEST_METHOD'] ?? 'GET',
        ]);

        return true;
    } catch (Exception $ex) {
        // Se o log falhar, não deve interromper o sistema.
        // Em modo debug, exibe o erro.
        if (defined('APP_DEBUG') && APP_DEBUG) {
            error_log('[LOG ERROR] ' . $ex->getMessage());
        }
        return false;
    }
}

/**
 * Atalho para registrar log de nível INFO.
 *
 * Usado para ações normais do sistema: login, logout, criação de registros, etc.
 *
 * @param string      $modulo          Módulo do sistema (ex: 'auth', 'empresas')
 * @param string      $acao            Ação realizada (ex: 'login', 'criar')
 * @param string      $descricao       Descrição humana da ação
 * @param string|null $registro_id     UUID do registro afetado
 * @param string|null $registro_codigo Código amigável do registro (ex: 'EMP-0001')
 * @param array|null  $dados_anteriores Snapshot dos dados antes da alteração
 * @param array|null  $dados_novos      Snapshot dos dados após a alteração
 * @return bool true se o log foi salvo com sucesso
 */
function log_info($modulo, $acao, $descricao, $registro_id = null, $registro_codigo = null, $dados_anteriores = null, $dados_novos = null) {
    return registrar_log($modulo, $acao, $descricao, 'info', $registro_id, $registro_codigo, $dados_anteriores, $dados_novos);
}
/**
 * Atalho para registrar log de nível WARNING.
 *
 * Usado para ações que merecem atenção: bloqueio de usuários, tentativas de brute force, etc.
 *
 * @param string      $modulo          Módulo do sistema
 * @param string      $acao            Ação realizada
 * @param string      $descricao       Descrição da ação
 * @param string|null $registro_id     UUID do registro afetado
 * @param string|null $registro_codigo Código amigável do registro
 * @param array|null  $dados_anteriores Dados antes da alteração
 * @param array|null  $dados_novos      Dados após a alteração
 * @return bool
 */
function log_warning($modulo, $acao, $descricao, $registro_id = null, $registro_codigo = null, $dados_anteriores = null, $dados_novos = null) {
    return registrar_log($modulo, $acao, $descricao, 'warning', $registro_id, $registro_codigo, $dados_anteriores, $dados_novos);
}
/**
 * Atalho para registrar log de nível ERROR.
 *
 * Usado para erros recuperáveis: falha ao salvar, exception capturada, etc.
 *
 * @param string      $modulo          Módulo do sistema
 * @param string      $acao            Ação que falhou
 * @param string      $descricao       Descrição do erro
 * @param string|null $registro_id     UUID do registro afetado
 * @param string|null $registro_codigo Código amigável do registro
 * @param array|null  $dados_anteriores Dados antes da alteração
 * @param array|null  $dados_novos      Dados após a alteração
 * @return bool
 */
function log_error($modulo, $acao, $descricao, $registro_id = null, $registro_codigo = null, $dados_anteriores = null, $dados_novos = null) {
    return registrar_log($modulo, $acao, $descricao, 'error', $registro_id, $registro_codigo, $dados_anteriores, $dados_novos);
}
/**
 * Atalho para registrar log de nível CRITICAL.
 *
 * Usado para falhas graves que comprometem o sistema: corrupção de dados, falha de segurança, etc.
 *
 * @param string      $modulo          Módulo do sistema
 * @param string      $acao            Ação que causou a falha crítica
 * @param string      $descricao       Descrição detalhada da falha
 * @param string|null $registro_id     UUID do registro afetado
 * @param string|null $registro_codigo Código amigável do registro
 * @param array|null  $dados_anteriores Dados antes da alteração
 * @param array|null  $dados_novos      Dados após a alteração
 * @return bool
 */
function log_critical($modulo, $acao, $descricao, $registro_id = null, $registro_codigo = null, $dados_anteriores = null, $dados_novos = null) {
    return registrar_log($modulo, $acao, $descricao, 'critical', $registro_id, $registro_codigo, $dados_anteriores, $dados_novos);
}

/**
 * Obtém o endereço IP real do cliente.
 *
 * Verifica os headers de proxy (X-Forwarded-For, X-Real-IP, Client-IP)
 * na ordem de prioridade antes de usar REMOTE_ADDR.
 * Valida se o IP é válido com filter_var(FILTER_VALIDATE_IP).
 *
 * @return string Endereço IPv4 ou IPv6 do cliente, ou '0.0.0.0' como fallback
 */
function get_client_ip() {
    $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'HTTP_CLIENT_IP', 'REMOTE_ADDR'];
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = trim(explode(',', $_SERVER[$header])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

/**
 * Busca logs do banco com filtros.
 * Master vê tudo; empresa normal vê apenas seus logs.
 *
 * @param array $filtros Filtros opcionais: empresa_codigo, usuario_codigo, modulo, acao, nivel, data_inicio, data_fim
 * @param int   $limit   Limite de registros
 * @param int   $offset  Offset para paginação
 * @return array ['logs' => [...], 'total' => int]
 */
function buscar_logs($filtros = [], $limit = 50, $offset = 0) {
    try {
        $pdo = db();
        $where = [];
        $params = [];

        // Multi-tenancy: se não é master, filtra pela empresa do usuário
        if (!can_see_all_empresas()) {
            $where[] = '`empresa_id` = :tenant_empresa_id';
            $params[':tenant_empresa_id'] = current_empresa_id();
        }

        // Filtro por código da empresa
        if (!empty($filtros['empresa_codigo'])) {
            $where[] = '`empresa_codigo` = :empresa_codigo';
            $params[':empresa_codigo'] = $filtros['empresa_codigo'];
        }

        // Filtro por código do usuário
        if (!empty($filtros['usuario_codigo'])) {
            $where[] = '`usuario_codigo` = :usuario_codigo';
            $params[':usuario_codigo'] = $filtros['usuario_codigo'];
        }

        // Filtro por módulo
        if (!empty($filtros['modulo'])) {
            $where[] = '`modulo` = :modulo';
            $params[':modulo'] = $filtros['modulo'];
        }

        // Filtro por ação
        if (!empty($filtros['acao'])) {
            $where[] = '`acao` = :acao';
            $params[':acao'] = $filtros['acao'];
        }

        // Filtro por nível
        if (!empty($filtros['nivel'])) {
            $where[] = '`nivel` = :nivel';
            $params[':nivel'] = $filtros['nivel'];
        }

        // Filtro por data início
        if (!empty($filtros['data_inicio'])) {
            $where[] = '`criado_em` >= :data_inicio';
            $params[':data_inicio'] = $filtros['data_inicio'] . ' 00:00:00';
        }

        // Filtro por data fim
        if (!empty($filtros['data_fim'])) {
            $where[] = '`criado_em` <= :data_fim';
            $params[':data_fim'] = $filtros['data_fim'] . ' 23:59:59';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Total de registros (para paginação)
        $countSql = "SELECT COUNT(*) FROM `log_acoes` {$whereClause}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Busca registros com paginação
        $limit = max(1, min(200, (int) $limit));
        $offset = max(0, (int) $offset);

        $sql = "SELECT `id`, `empresa_id`, `empresa_codigo`, `usuario_id`, `usuario_codigo`,
                       `usuario_nome`, `modulo`, `acao`, `nivel`, `registro_id`, `registro_codigo`,
                       `descricao`, `dados_anteriores`, `dados_novos`, `ip`, `user_agent`, `url`,
                       `metodo_http`, `criado_em`
                FROM `log_acoes` {$whereClause} ORDER BY `criado_em` DESC LIMIT :lim OFFSET :off";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $logs = $stmt->fetchAll();

        return ['logs' => $logs, 'total' => $total];
    } catch (Exception $ex) {
        if (defined('APP_DEBUG') && APP_DEBUG) {
            error_log('[LOG QUERY ERROR] ' . $ex->getMessage());
        }
        return ['logs' => [], 'total' => 0];
    }
}
