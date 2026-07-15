<?php
declare(strict_types=1);

namespace Tests;

use PDO;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Classe base para testes de integração.
 * Cria o banco de teste, executa migrations e limpa dados entre testes.
 */
abstract class DatabaseTestCase extends BaseTestCase
{
    protected static ?PDO $pdo = null;

    /**
     * Cria o banco de teste e as tabelas antes de todos os testes da classe.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $dsn = sprintf(
            'mysql:host=%s;port=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_CHARSET
        );

        $root = new PDO($dsn, env('DB_USER', 'root'), env('DB_PASS', ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);

        $dbName = DB_NAME; // financeiro_erp_test
        $root->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $root = null;

        // Conectar ao banco de teste
        static::$pdo = new PDO(
            "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname={$dbName};charset=" . DB_CHARSET,
            env('DB_USER', 'root'),
            env('DB_PASS', ''),
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]
        );

        static::createTables(static::$pdo);
    }

    /**
     * Limpa dados das tabelas entre cada teste (mantém estrutura).
     */
    protected function setUp(): void
    {
        parent::setUp();
        static::truncateTables(static::$pdo);

        // Resetar sessão
        $_SESSION = [];
    }

    /**
     * Retorna a conexão PDO de teste.
     */
    protected function db(): PDO
    {
        return static::$pdo;
    }

    /**
     * Simula sessão de um usuário master logado.
     */
    protected function loginAsMaster(): void
    {
        $_SESSION['user'] = [
            'id'              => 'a1a1a1a1-a1a1-4a1a-a1a1-a1a1a1a1a1a1',
            'codigo'          => 'USR-0001',
            'nome'            => 'Admin Master',
            'email'           => 'admin@master.com',
            'empresa_id'      => MASTER_EMPRESA_ID,
            'empresa_codigo'  => 'EMP-0001',
            'empresa_nome'    => 'Empresa Master',
            'empresa_logo'    => '',
            'perfil_id'       => 'e5e5e5e5-e5e5-4e5e-e5e5-e5e5e5e5e5e5',
            'perfil_nome'     => 'Administrador',
        ];
        $_SESSION['ultima_atividade'] = time();
    }

    /**
     * Simula sessão de um usuário comum (não master).
     */
    protected function loginAsRegular(): void
    {
        $_SESSION['user'] = [
            'id'              => 'b2b2b2b2-b2b2-4b2b-b2b2-b2b2b2b2b2b2',
            'codigo'          => 'USR-0002',
            'nome'            => 'Usuário Comum',
            'email'           => 'user@empresa.com',
            'empresa_id'      => 'c3c3c3c3-c3c3-4c3c-c3c3-c3c3c3c3c3c3',
            'empresa_codigo'  => 'EMP-0002',
            'empresa_nome'    => 'Empresa Filial',
            'empresa_logo'    => '',
            'perfil_id'       => 'd4d4d4d4-d4d4-4d4d-d4d4-d4d4d4d4d4d4',
            'perfil_nome'     => 'Operador',
        ];
        $_SESSION['ultima_atividade'] = time();
    }

    /**
     * Insere empresa diretamente no banco para testes.
     */
    protected function insertEmpresa(array $override = []): array
    {
        $data = array_merge([
            'id'             => generate_uuid(),
            'codigo'         => 'EMP-' . str_pad((string) rand(1, 9999), 4, '0', STR_PAD_LEFT),
            'tipo_pessoa'    => 'PJ',
            'cnpj'           => (string) rand(10000000000000, 99999999999999),
            'cpf'            => null,
            'razao_social'   => 'Empresa Teste ' . rand(1, 1000),
            'nome_fantasia'  => 'Teste',
            'email'          => 'test' . rand(1, 9999) . '@test.com',
            'status'         => 'ativo',
        ], $override);

        $stmt = static::$pdo->prepare("
            INSERT INTO empresas (id, codigo, tipo_pessoa, cnpj, cpf, razao_social, nome_fantasia, email, status)
            VALUES (:id, :codigo, :tipo_pessoa, :cnpj, :cpf, :razao_social, :nome_fantasia, :email, :status)
        ");
        $stmt->execute([
            ':id'             => $data['id'],
            ':codigo'         => $data['codigo'],
            ':tipo_pessoa'    => $data['tipo_pessoa'],
            ':cnpj'           => $data['cnpj'],
            ':cpf'            => $data['cpf'],
            ':razao_social'   => $data['razao_social'],
            ':nome_fantasia'  => $data['nome_fantasia'],
            ':email'          => $data['email'],
            ':status'         => $data['status'],
        ]);

        return $data;
    }

    /**
     * Insere perfil diretamente no banco para testes.
     */
    protected function insertPerfil(array $override = []): array
    {
        $data = array_merge([
            'id'        => generate_uuid(),
            'codigo'    => 'PRF-' . str_pad((string) rand(1, 9999), 4, '0', STR_PAD_LEFT),
            'nome'      => 'Perfil Teste ' . rand(1, 1000),
            'descricao' => 'Descrição do perfil',
            'status'    => 'ativo',
        ], $override);

        $stmt = static::$pdo->prepare("
            INSERT INTO perfis (id, codigo, nome, descricao, status)
            VALUES (:id, :codigo, :nome, :descricao, :status)
        ");
        $stmt->execute([
            ':id'        => $data['id'],
            ':codigo'    => $data['codigo'],
            ':nome'      => $data['nome'],
            ':descricao' => $data['descricao'],
            ':status'    => $data['status'],
        ]);

        return $data;
    }

    /**
     * Insere usuário + vínculo empresa diretamente no banco.
     */
    protected function insertUsuario(string $empresaId, string $perfilId, array $override = []): array
    {
        $data = array_merge([
            'id'     => generate_uuid(),
            'codigo' => 'USR-' . str_pad((string) rand(1, 9999), 4, '0', STR_PAD_LEFT),
            'nome'   => 'Usuário Teste ' . rand(1, 1000),
            'email'  => 'user' . rand(1, 99999) . '@test.com',
            'senha'  => hash_senha('Senha@123'),
            'status' => 'ativo',
        ], $override);

        $stmt = static::$pdo->prepare("
            INSERT INTO usuarios (id, codigo, nome, email, senha, status)
            VALUES (:id, :codigo, :nome, :email, :senha, :status)
        ");
        $stmt->execute([
            ':id'     => $data['id'],
            ':codigo' => $data['codigo'],
            ':nome'   => $data['nome'],
            ':email'  => $data['email'],
            ':senha'  => $data['senha'],
            ':status' => $data['status'],
        ]);

        // Vínculo empresa
        $vinculoId = generate_uuid();
        $stmt = static::$pdo->prepare("
            INSERT INTO usuario_empresa (id, usuario_id, empresa_id, perfil_id, status)
            VALUES (:id, :uid, :eid, :pid, 'ativo')
        ");
        $stmt->execute([
            ':id'  => $vinculoId,
            ':uid' => $data['id'],
            ':eid' => $empresaId,
            ':pid' => $perfilId,
        ]);

        $data['vinculo_id'] = $vinculoId;
        return $data;
    }

    /**
     * Cria as tabelas necessárias no banco de teste.
     */
    private static function createTables(PDO $pdo): void
    {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $pdo->exec("DROP TABLE IF EXISTS `usuario_termos`");
        $pdo->exec("DROP TABLE IF EXISTS `log_acoes`");
        $pdo->exec("DROP TABLE IF EXISTS `usuario_empresa`");
        $pdo->exec("DROP TABLE IF EXISTS `perfil_permissoes`");
        $pdo->exec("DROP TABLE IF EXISTS `empresa_contatos`");
        $pdo->exec("DROP TABLE IF EXISTS `usuarios`");
        $pdo->exec("DROP TABLE IF EXISTS `perfis`");
        $pdo->exec("DROP TABLE IF EXISTS `empresas`");

        // Empresas
        $pdo->exec("CREATE TABLE IF NOT EXISTS `empresas` (
            `id`                  CHAR(36)        NOT NULL,
            `codigo`              VARCHAR(20)     NOT NULL,
            `tipo_pessoa`         ENUM('PF','PJ') NOT NULL DEFAULT 'PJ',
            `cnpj`                VARCHAR(18)     NULL,
            `cpf`                 VARCHAR(14)     NULL,
            `inscricao_estadual`  VARCHAR(30)     NULL,
            `razao_social`        VARCHAR(255)    NOT NULL,
            `nome_fantasia`       VARCHAR(255)    NULL,
            `email`               VARCHAR(255)    NULL,
            `telefone`            VARCHAR(20)     NULL,
            `celular`             VARCHAR(20)     NULL,
            `cep`                 VARCHAR(9)      NULL,
            `logradouro`          VARCHAR(255)    NULL,
            `numero`              VARCHAR(20)     NULL,
            `complemento`         VARCHAR(100)    NULL,
            `bairro`              VARCHAR(100)    NULL,
            `cidade`              VARCHAR(100)    NULL,
            `uf`                  CHAR(2)         NULL,
            `logo`                VARCHAR(500)    NULL,
            `observacoes`         TEXT            NULL,
            `status`              ENUM('ativo','bloqueado') NOT NULL DEFAULT 'ativo',
            `criado_em`           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `atualizado_em`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_empresas_cnpj` (`cnpj`),
            UNIQUE KEY `uk_empresas_cpf` (`cpf`),
            UNIQUE KEY `uk_empresas_codigo` (`codigo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Contatos
        $pdo->exec("CREATE TABLE IF NOT EXISTS `empresa_contatos` (
            `id`            CHAR(36)        NOT NULL,
            `empresa_id`    CHAR(36)        NOT NULL,
            `nome`          VARCHAR(150)    NOT NULL,
            `email`         VARCHAR(255)    NULL,
            `telefone`      VARCHAR(20)     NULL,
            `cargo`         VARCHAR(100)    NULL,
            `criado_em`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `atualizado_em` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            CONSTRAINT `fk_test_contatos_empresa`
                FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Perfis
        $pdo->exec("CREATE TABLE IF NOT EXISTS `perfis` (
            `id`            CHAR(36)        NOT NULL,
            `codigo`        VARCHAR(20)     NOT NULL,
            `nome`          VARCHAR(100)    NOT NULL,
            `descricao`     VARCHAR(500)    NULL,
            `status`        ENUM('ativo','bloqueado') NOT NULL DEFAULT 'ativo',
            `criado_em`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `atualizado_em` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_perfis_nome` (`nome`),
            UNIQUE KEY `uk_perfis_codigo` (`codigo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Permissões
        $pdo->exec("CREATE TABLE IF NOT EXISTS `perfil_permissoes` (
            `id`         CHAR(36)        NOT NULL,
            `perfil_id`  CHAR(36)        NOT NULL,
            `modulo`     VARCHAR(50)     NOT NULL,
            `acao`       VARCHAR(50)     NOT NULL,
            `criado_em`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_perfil_modulo_acao` (`perfil_id`, `modulo`, `acao`),
            CONSTRAINT `fk_test_permissoes_perfil`
                FOREIGN KEY (`perfil_id`) REFERENCES `perfis` (`id`)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Usuários
        $pdo->exec("CREATE TABLE IF NOT EXISTS `usuarios` (
            `id`            CHAR(36)        NOT NULL,
            `codigo`        VARCHAR(20)     NOT NULL,
            `nome`          VARCHAR(200)    NOT NULL,
            `email`         VARCHAR(255)    NOT NULL,
            `senha`         VARCHAR(255)    NOT NULL,
            `telefone`      VARCHAR(20)     NULL,
            `celular`       VARCHAR(20)     NULL,
            `status`        ENUM('ativo','bloqueado') NOT NULL DEFAULT 'ativo',
            `ultimo_login`  DATETIME        NULL,
            `criado_em`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `atualizado_em` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_usuarios_email` (`email`),
            UNIQUE KEY `uk_usuarios_codigo` (`codigo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Vínculo Usuário-Empresa
        $pdo->exec("CREATE TABLE IF NOT EXISTS `usuario_empresa` (
            `id`            CHAR(36)        NOT NULL,
            `usuario_id`    CHAR(36)        NOT NULL,
            `empresa_id`    CHAR(36)        NOT NULL,
            `perfil_id`     CHAR(36)        NOT NULL,
            `status`        ENUM('ativo','bloqueado') NOT NULL DEFAULT 'ativo',
            `criado_em`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `atualizado_em` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_usuario_empresa` (`usuario_id`, `empresa_id`),
            CONSTRAINT `fk_test_ue_usuario`
                FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk_test_ue_empresa`
                FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`)
                ON DELETE CASCADE ON UPDATE CASCADE,
            CONSTRAINT `fk_test_ue_perfil`
                FOREIGN KEY (`perfil_id`) REFERENCES `perfis` (`id`)
                ON DELETE RESTRICT ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Log de Ações
        $pdo->exec("CREATE TABLE IF NOT EXISTS `log_acoes` (
            `id`                CHAR(36)        NOT NULL,
            `empresa_id`        CHAR(36)        NULL,
            `empresa_codigo`    VARCHAR(20)     NULL,
            `usuario_id`        CHAR(36)        NULL,
            `usuario_codigo`    VARCHAR(20)     NULL,
            `usuario_nome`      VARCHAR(200)    NULL,
            `modulo`            VARCHAR(50)     NOT NULL,
            `acao`              VARCHAR(50)     NOT NULL,
            `nivel`             ENUM('info','warning','error','critical') NOT NULL DEFAULT 'info',
            `registro_id`       CHAR(36)        NULL,
            `registro_codigo`   VARCHAR(20)     NULL,
            `descricao`         TEXT            NOT NULL,
            `dados_anteriores`  JSON            NULL,
            `dados_novos`       JSON            NULL,
            `ip`                VARCHAR(45)     NULL,
            `user_agent`        VARCHAR(500)    NULL,
            `url`               VARCHAR(2000)   NULL,
            `metodo_http`       VARCHAR(10)     NULL,
            `criado_em`         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("CREATE TABLE IF NOT EXISTS `usuario_termos` (
            `id`            CHAR(36)    NOT NULL,
            `usuario_id`    CHAR(36)    NOT NULL,
            `versao_termo`  VARCHAR(20) NOT NULL,
            `aceito_em`     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `ip`            VARCHAR(45) NULL,
            `criado_em`     DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `atualizado_em` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_test_usuario_termo_versao` (`usuario_id`, `versao_termo`),
            KEY `idx_test_usuario_termos_usuario` (`usuario_id`),
            CONSTRAINT `fk_test_usuario_termos_usuario`
                FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
                ON DELETE CASCADE ON UPDATE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    }

    /**
     * Limpa todas as tabelas na ordem correta (respeita FK).
     */
    private static function truncateTables(PDO $pdo): void
    {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $pdo->exec("TRUNCATE TABLE `usuario_termos`");
        $pdo->exec("TRUNCATE TABLE `log_acoes`");
        $pdo->exec("TRUNCATE TABLE `usuario_empresa`");
        $pdo->exec("TRUNCATE TABLE `perfil_permissoes`");
        $pdo->exec("TRUNCATE TABLE `empresa_contatos`");
        $pdo->exec("TRUNCATE TABLE `usuarios`");
        $pdo->exec("TRUNCATE TABLE `perfis`");
        $pdo->exec("TRUNCATE TABLE `empresas`");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    }
}
