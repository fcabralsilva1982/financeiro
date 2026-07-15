-- Tabela: contas_receber
CREATE TABLE IF NOT EXISTS contas_receber (
  id CHAR(36) PRIMARY KEY,
  empresa_id CHAR(36) NOT NULL,
  cliente_id CHAR(36) NOT NULL,
  codigo VARCHAR(20) NOT NULL,
  descricao VARCHAR(255) NOT NULL,
  valor_total DECIMAL(15,2) NOT NULL,
  data_emissao DATE NOT NULL,
  data_vencimento DATE NOT NULL,
  forma_cobranca VARCHAR(50) NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'emitida',
  observacoes TEXT,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  atualizado_em TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (empresa_id) REFERENCES empresas(id),
  FOREIGN KEY (cliente_id) REFERENCES clientes(id)
);

-- ============================================================
-- 3. CONTAS A RECEBER (FATURAS)
-- ============================================================
CREATE TABLE `contas_receber` (
    `id`                CHAR(36)      NOT NULL COMMENT 'UUID',
    `empresa_id`        CHAR(36)      NOT NULL COMMENT 'Empresa (multi-tenant)',
    `cliente_id`        CHAR(36)      NOT NULL COMMENT 'Cliente vinculado',
    `codigo`            VARCHAR(20)   NOT NULL COMMENT 'Código amigável: CR-0001',
    `descricao`         VARCHAR(255)  NOT NULL COMMENT 'Descrição do serviço ou produto',
    `valor_total`       DECIMAL(15,2) NOT NULL COMMENT 'Valor total da fatura',
    `data_emissao`      DATE          NOT NULL COMMENT 'Data de emissão',
    `data_vencimento`   DATE          NOT NULL COMMENT 'Data de vencimento',
    `forma_cobranca`    ENUM('boleto','pix','nfse','cartao') NOT NULL COMMENT 'Forma de cobrança',
    `status`            ENUM('rascunho','emitida','enviada','visualizada','paga','vencida','cancelada') NOT NULL DEFAULT 'emitida',
    `data_pagamento`    DATE          NULL COMMENT 'Data de pagamento',
    `valor_pago`        DECIMAL(15,2) NULL COMMENT 'Valor efetivamente recebido',
    `forma_recebimento` ENUM('boleto','pix','cartao','dinheiro','transferencia','outro') NULL COMMENT 'Forma de recebimento',
    `comprovante_path`  VARCHAR(500)  NULL COMMENT 'Caminho relativo do comprovante de recebimento',
    `observacoes_baixa` TEXT          NULL COMMENT 'Observações da baixa',
    `observacoes`       TEXT          NULL,
    `criado_em`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_contas_receber_codigo` (`codigo`),
    KEY `idx_contas_receber_empresa` (`empresa_id`),
    KEY `idx_contas_receber_cliente` (`cliente_id`),
    KEY `idx_contas_receber_status` (`status`),
    CONSTRAINT `fk_cr_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas`(`id`),
    CONSTRAINT `fk_cr_cliente` FOREIGN KEY (`cliente_id`) REFERENCES `clientes`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Faturas e cobranças a receber dos clientes';

-- ============================================================
-- 4. PARCELAS DAS CONTAS A RECEBER
-- ============================================================
CREATE TABLE `contas_receber_parcelas` (
    `id`                CHAR(36)      NOT NULL COMMENT 'UUID',
    `conta_receber_id`  CHAR(36)      NOT NULL COMMENT 'Fatura vinculada',
    `numero_parcela`    INT           NOT NULL COMMENT 'Número da parcela',
    `valor`             DECIMAL(15,2) NOT NULL COMMENT 'Valor da parcela',
    `data_vencimento`   DATE          NOT NULL COMMENT 'Vencimento da parcela',
    `data_pagamento`    DATE          NULL COMMENT 'Data de pagamento (baixa)',
    `valor_pago`        DECIMAL(15,2) NULL COMMENT 'Valor efetivamente pago',
    `status`            ENUM('pendente','paga','vencida','cancelada') NOT NULL DEFAULT 'pendente',
    `forma_pagamento`   ENUM('boleto','pix','cartao','outro') NULL COMMENT 'Forma de pagamento utilizada',
    `linha_digitavel`   VARCHAR(255)  NULL COMMENT 'Linha digitável do boleto ou código PIX',
    `url_pagamento`     VARCHAR(500)  NULL COMMENT 'Link para pagamento',
    `criado_em`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_crp_conta` (`conta_receber_id`),
    KEY `idx_crp_status` (`status`),
    CONSTRAINT `fk_crp_conta` FOREIGN KEY (`conta_receber_id`) REFERENCES `contas_receber`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Parcelas das faturas de contas a receber';

-- ============================================================
-- 5. HISTÓRICO DE COBRANÇA (RÉGUA)
-- ============================================================
CREATE TABLE `contas_receber_historico` (
    `id`                CHAR(36)      NOT NULL COMMENT 'UUID',
    `conta_receber_id`  CHAR(36)      NOT NULL COMMENT 'Fatura vinculada',
    `parcela_id`        CHAR(36)      NULL COMMENT 'Parcela vinculada (opcional)',
    `evento`            VARCHAR(100)  NOT NULL COMMENT 'Evento: lembrete, envio, visualizacao, baixa, etc.',
    `descricao`         VARCHAR(255)  NULL COMMENT 'Descrição detalhada do evento',
    `canal`             ENUM('email','whatsapp','sistema','webhook','outro') NOT NULL,
    `data_evento`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `usuario_id`        CHAR(36)      NULL COMMENT 'Usuário responsável (se aplicável)',
    PRIMARY KEY (`id`),
    KEY `idx_crh_conta` (`conta_receber_id`),
    KEY `idx_crh_parcela` (`parcela_id`),
    CONSTRAINT `fk_crh_conta` FOREIGN KEY (`conta_receber_id`) REFERENCES `contas_receber`(`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_crh_parcela` FOREIGN KEY (`parcela_id`) REFERENCES `contas_receber_parcelas`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Histórico de eventos e comunicações da régua de cobrança';

-- ============================================================
-- 6. CONFIGURAÇÃO DA RÉGUA DE COBRANÇA
-- ============================================================
CREATE TABLE `contas_receber_regua` (
    `id`                CHAR(36)      NOT NULL COMMENT 'UUID',
    `empresa_id`        CHAR(36)      NOT NULL COMMENT 'Empresa (multi-tenant)',
    `dias_antes`        INT           NOT NULL COMMENT 'Dias antes do vencimento para disparo',
    `dias_depois`       INT           NOT NULL COMMENT 'Dias após o vencimento para disparo',
    `evento`            VARCHAR(100)  NOT NULL COMMENT 'Evento: lembrete, aviso, cobrança, etc.',
    `canal`             ENUM('email','whatsapp','sistema','webhook','outro') NOT NULL,
    `mensagem_padrao`   TEXT          NULL COMMENT 'Mensagem padrão enviada',
    `ativo`             TINYINT(1)    NOT NULL DEFAULT 1,
    `criado_em`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_crr_empresa` (`empresa_id`),
    CONSTRAINT `fk_crr_empresa` FOREIGN KEY (`empresa_id`) REFERENCES `empresas`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Configuração da régua de cobrança por empresa';
-- IDs: UUID (CHAR(36)) em todas as tabelas
--
-- IMPORTANTE: Executar com --default-character-set=utf8mb4
-- Exemplo: mysql -u root --default-character-set=utf8mb4 < schema.sql
-- ============================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;

CREATE DATABASE IF NOT EXISTS `financeiro_erp`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `financeiro_erp`;

-- ============================================================
-- 1. EMPRESAS
-- ============================================================
CREATE TABLE `empresas` (
    `id`                  CHAR(36)        NOT NULL COMMENT 'UUID',
    `tipo_pessoa`         ENUM('PF','PJ') NOT NULL DEFAULT 'PJ' COMMENT 'Pessoa Fisica ou Juridica',
    `codigo`              VARCHAR(20)     NOT NULL COMMENT 'Código amigável: EMP-0001',
    `cnpj`                VARCHAR(18)     NULL COMMENT 'CNPJ numérico ou alfanumérico formatado',
    `cpf`                 VARCHAR(14)     NULL COMMENT 'CPF formatado',
    `inscricao_estadual`  VARCHAR(30)     NULL     COMMENT 'Inscrição Estadual ou ISENTO',
    `razao_social`        VARCHAR(255)    NOT NULL,
    `nome_fantasia`       VARCHAR(255)    NULL,
    `email`               VARCHAR(255)    NULL,
    `telefone`            VARCHAR(20)     NULL     COMMENT 'Telefone: +55 11 9090-9090',
    `celular`             VARCHAR(20)     NULL     COMMENT 'Celular: +55 11 99999-9999',
    `cep`                 VARCHAR(9)      NULL     COMMENT 'CEP formatado: 00000-000',
    `logradouro`          VARCHAR(255)    NULL,
    `numero`              VARCHAR(20)     NULL,
    `complemento`         VARCHAR(100)    NULL,
    `bairro`              VARCHAR(100)    NULL,
    `cidade`              VARCHAR(100)    NULL,
    `uf`                  CHAR(2)         NULL,
    `logo`                VARCHAR(500)    NULL     COMMENT 'Caminho ou URL do logotipo',
    `observacoes`         TEXT            NULL,
    `status`              ENUM('ativo','bloqueado') NOT NULL DEFAULT 'ativo',
    `criado_em`           DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em`       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_empresas_cnpj` (`cnpj`),
    UNIQUE KEY `uk_empresas_cpf` (`cpf`),
    UNIQUE KEY `uk_empresas_codigo` (`codigo`),
    INDEX `idx_empresas_tipo_pessoa` (`tipo_pessoa`),
    INDEX `idx_empresas_status` (`status`),
    INDEX `idx_empresas_razao_social` (`razao_social`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cadastro de empresas do sistema';

-- ============================================================
-- 2. CONTATOS DA EMPRESA
-- ============================================================
CREATE TABLE `empresa_contatos` (
    `id`            CHAR(36)        NOT NULL COMMENT 'UUID',
    `empresa_id`    CHAR(36)        NOT NULL,
    `nome`          VARCHAR(150)    NOT NULL,
    `email`         VARCHAR(255)    NULL,
    `telefone`      VARCHAR(20)     NULL,
    `cargo`         VARCHAR(100)    NULL,
    `criado_em`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_empresa_contatos_empresa` (`empresa_id`),
    CONSTRAINT `fk_empresa_contatos_empresa`
        FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Contatos vinculados a cada empresa';

-- ============================================================
-- 3. PERFIS DE ACESSO
-- ============================================================
CREATE TABLE `perfis` (
    `id`            CHAR(36)        NOT NULL COMMENT 'UUID',
    `codigo`        VARCHAR(20)     NOT NULL COMMENT 'Código amigável: PRF-0001',
    `nome`          VARCHAR(100)    NOT NULL,
    `descricao`     VARCHAR(500)    NULL,
    `status`        ENUM('ativo','bloqueado') NOT NULL DEFAULT 'ativo',
    `criado_em`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_perfis_nome` (`nome`),
    UNIQUE KEY `uk_perfis_codigo` (`codigo`),
    INDEX `idx_perfis_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Perfis de acesso (níveis de permissão)';

-- ============================================================
-- 4. PERMISSÕES DOS PERFIS
--    Cada linha = 1 permissão (modulo.acao) vinculada a 1 perfil
-- ============================================================
CREATE TABLE `perfil_permissoes` (
    `id`         CHAR(36)        NOT NULL COMMENT 'UUID',
    `perfil_id`  CHAR(36)        NOT NULL,
    `modulo`     VARCHAR(50)     NOT NULL COMMENT 'Ex: empresas, usuarios, perfis, financeiro',
    `acao`       VARCHAR(50)     NOT NULL COMMENT 'Ex: visualizar, criar, editar, bloquear, excluir',
    `criado_em`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_perfil_modulo_acao` (`perfil_id`, `modulo`, `acao`),
    INDEX `idx_perfil_permissoes_perfil` (`perfil_id`),
    CONSTRAINT `fk_perfil_permissoes_perfil`
        FOREIGN KEY (`perfil_id`) REFERENCES `perfis` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Permissões granulares por perfil (módulo + ação)';

-- ============================================================
-- 5. USUÁRIOS
-- ============================================================
CREATE TABLE `usuarios` (
    `id`            CHAR(36)        NOT NULL COMMENT 'UUID',
    `codigo`        VARCHAR(20)     NOT NULL COMMENT 'Código amigável: USR-0001',
    `nome`          VARCHAR(200)    NOT NULL,
    `email`         VARCHAR(255)    NOT NULL,
    `senha`         VARCHAR(255)    NOT NULL COMMENT 'Hash bcrypt',
    `telefone`      VARCHAR(20)     NULL,
    `celular`       VARCHAR(20)     NULL,
    `status`        ENUM('ativo','bloqueado') NOT NULL DEFAULT 'ativo',
    `ultimo_login`  DATETIME        NULL,
    `criado_em`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_usuarios_email` (`email`),
    UNIQUE KEY `uk_usuarios_codigo` (`codigo`),
    INDEX `idx_usuarios_status` (`status`),
    INDEX `idx_usuarios_nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cadastro de usuários do sistema';

-- ============================================================
-- 6. VÍNCULO USUÁRIO ↔ EMPRESA (com perfil por empresa)
--    Um usuário pode pertencer a múltiplas empresas,
--    cada vínculo com um perfil de acesso específico.
-- ============================================================
CREATE TABLE `usuario_empresa` (
    `id`            CHAR(36)        NOT NULL COMMENT 'UUID',
    `usuario_id`    CHAR(36)        NOT NULL,
    `empresa_id`    CHAR(36)        NOT NULL,
    `perfil_id`     CHAR(36)        NOT NULL,
    `status`        ENUM('ativo','bloqueado') NOT NULL DEFAULT 'ativo',
    `criado_em`     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em` DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_usuario_empresa` (`usuario_id`, `empresa_id`),
    INDEX `idx_usuario_empresa_empresa` (`empresa_id`),
    INDEX `idx_usuario_empresa_perfil` (`perfil_id`),
    CONSTRAINT `fk_usuario_empresa_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_usuario_empresa_empresa`
        FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_usuario_empresa_perfil`
        FOREIGN KEY (`perfil_id`) REFERENCES `perfis` (`id`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Vínculo de usuários com empresas e seus respectivos perfis';

-- ============================================================
-- 7. LOG DE AÇÕES (auditoria detalhada)
-- ============================================================
CREATE TABLE `log_acoes` (
    `id`                CHAR(36)        NOT NULL COMMENT 'UUID',
    `empresa_id`        CHAR(36)        NULL     COMMENT 'UUID da empresa',
    `empresa_codigo`    VARCHAR(20)     NULL     COMMENT 'Código amigável da empresa (EMP-0001)',
    `usuario_id`        CHAR(36)        NULL     COMMENT 'UUID do usuário',
    `usuario_codigo`    VARCHAR(20)     NULL     COMMENT 'Código amigável do usuário (USR-0001)',
    `usuario_nome`      VARCHAR(200)    NULL     COMMENT 'Nome do usuário no momento do log',
    `modulo`            VARCHAR(50)     NOT NULL COMMENT 'empresas, usuarios, perfis, auth, etc.',
    `acao`              VARCHAR(50)     NOT NULL COMMENT 'login, logout, criar, editar, bloquear, desbloquear, excluir, visualizar',
    `nivel`             ENUM('info','warning','error','critical') NOT NULL DEFAULT 'info' COMMENT 'Nível de severidade',
    `registro_id`       CHAR(36)        NULL     COMMENT 'UUID do registro afetado',
    `registro_codigo`   VARCHAR(20)     NULL     COMMENT 'Código amigável do registro afetado',
    `descricao`         TEXT            NOT NULL COMMENT 'Descrição detalhada da ação',
    `dados_anteriores`  JSON            NULL     COMMENT 'Snapshot dos dados antes da alteração',
    `dados_novos`       JSON            NULL     COMMENT 'Snapshot dos dados depois da alteração',
    `ip`                VARCHAR(45)     NULL     COMMENT 'IPv4 ou IPv6',
    `user_agent`        VARCHAR(500)    NULL     COMMENT 'Navegador / client',
    `url`               VARCHAR(2000)   NULL     COMMENT 'URL da requisição',
    `metodo_http`       VARCHAR(10)     NULL     COMMENT 'GET, POST, PUT, DELETE',
    `criado_em`         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_log_empresa` (`empresa_id`),
    INDEX `idx_log_empresa_codigo` (`empresa_codigo`),
    INDEX `idx_log_usuario` (`usuario_id`),
    INDEX `idx_log_usuario_codigo` (`usuario_codigo`),
    INDEX `idx_log_modulo_acao` (`modulo`, `acao`),
    INDEX `idx_log_nivel` (`nivel`),
    INDEX `idx_log_data` (`criado_em`),
    INDEX `idx_log_registro` (`registro_id`),
    CONSTRAINT `fk_log_acoes_usuario`
        FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_log_acoes_empresa`
        FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Log de ações para auditoria detalhada do sistema';


-- ============================================================
-- DADOS INICIAIS (UUIDs fixos para referência cruzada)
-- ============================================================

-- UUIDs fixos para os registros iniciais
SET @uuid_empresa_master  = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
SET @uuid_empresa_beta    = 'b2c3d4e5-f6a7-8901-bcde-f12345678901';
SET @uuid_perfil_admin    = 'c3d4e5f6-a7b8-9012-cdef-123456789012';
SET @uuid_perfil_gerente  = 'd4e5f6a7-b8c9-0123-defa-234567890123';
SET @uuid_perfil_operador = 'e5f6a7b8-c9d0-1234-efab-345678901234';
SET @uuid_perfil_visual   = 'f6a7b8c9-d0e1-2345-fabc-456789012345';
SET @uuid_user_joao       = '11111111-1111-1111-1111-111111111111';
SET @uuid_user_maria      = '22222222-2222-2222-2222-222222222222';

-- Empresa master (dona do sistema)
INSERT INTO `empresas` (`id`, `tipo_pessoa`, `codigo`, `cnpj`, `cpf`, `inscricao_estadual`, `razao_social`, `nome_fantasia`, `email`, `telefone`,
    `cep`, `logradouro`, `numero`, `complemento`, `bairro`, `cidade`, `uf`, `logo`, `observacoes`, `status`)
VALUES
(@uuid_empresa_master, 'PJ', 'EMP-0001', '12.345.678/0001-90', NULL, '123.456.789.000', 'Empresa Alpha Ltda', 'Alpha Tech',
 'contato@alphatech.com.br', '(11) 99999-1111',
 '01001-000', 'Rua Augusta', '1500', 'Sala 301', 'Consolação', 'São Paulo', 'SP',
 NULL, 'Empresa principal do grupo. Sede administrativa em São Paulo.', 'ativo');

-- Contatos da empresa master
INSERT INTO `empresa_contatos` (`id`, `empresa_id`, `nome`, `email`, `telefone`, `cargo`) VALUES
(UUID(), @uuid_empresa_master, 'João Silva', 'joao@alphatech.com.br', '(11) 99999-1100', 'Diretor'),
(UUID(), @uuid_empresa_master, 'Carla Souza', 'carla@alphatech.com.br', '(11) 99999-1102', 'Financeiro');

-- Empresa cliente de exemplo
INSERT INTO `empresas` (`id`, `tipo_pessoa`, `codigo`, `cnpj`, `cpf`, `inscricao_estadual`, `razao_social`, `nome_fantasia`, `email`, `telefone`,
    `cep`, `logradouro`, `numero`, `complemento`, `bairro`, `cidade`, `uf`, `logo`, `observacoes`, `status`)
VALUES
(@uuid_empresa_beta, 'PJ', 'EMP-0002', '98.765.432/0001-10', NULL, '987.654.321.000', 'Beta Comércio S/A', 'Beta Store',
 'financeiro@betastore.com.br', '(21) 98888-2222',
 '20040-020', 'Av. Rio Branco', '100', '10º andar', 'Centro', 'Rio de Janeiro', 'RJ',
 NULL, 'Loja virtual e física no Centro do Rio.', 'ativo');

INSERT INTO `empresa_contatos` (`id`, `empresa_id`, `nome`, `email`, `telefone`, `cargo`) VALUES
(UUID(), @uuid_empresa_beta, 'Maria Santos', 'maria@betastore.com.br', '(21) 98888-2200', 'Gerente');

-- Perfis de acesso
INSERT INTO `perfis` (`id`, `codigo`, `nome`, `descricao`, `status`) VALUES
(@uuid_perfil_admin,    'PRF-0001', 'Administrador', 'Acesso total ao sistema, todas as permissões habilitadas.', 'ativo'),
(@uuid_perfil_gerente,  'PRF-0002', 'Gerente',       'Gerencia empresas e usuários, sem acesso a perfis.', 'ativo'),
(@uuid_perfil_operador, 'PRF-0003', 'Operador',      'Visualiza e edita registros, sem poder criar ou excluir.', 'ativo'),
(@uuid_perfil_visual,   'PRF-0004', 'Visualizador',  'Apenas visualização, sem permissão de alteração.', 'ativo');

-- Permissões do perfil Administrador (todas)
INSERT INTO `perfil_permissoes` (`id`, `perfil_id`, `modulo`, `acao`) VALUES
(UUID(), @uuid_perfil_admin, 'empresas',  'visualizar'), (UUID(), @uuid_perfil_admin, 'empresas',  'criar'),
(UUID(), @uuid_perfil_admin, 'empresas',  'editar'),     (UUID(), @uuid_perfil_admin, 'empresas',  'bloquear'),
(UUID(), @uuid_perfil_admin, 'empresas',  'excluir'),
(UUID(), @uuid_perfil_admin, 'usuarios',  'visualizar'), (UUID(), @uuid_perfil_admin, 'usuarios',  'criar'),
(UUID(), @uuid_perfil_admin, 'usuarios',  'editar'),     (UUID(), @uuid_perfil_admin, 'usuarios',  'bloquear'),
(UUID(), @uuid_perfil_admin, 'usuarios',  'excluir'),
(UUID(), @uuid_perfil_admin, 'perfis',    'visualizar'), (UUID(), @uuid_perfil_admin, 'perfis',    'criar'),
(UUID(), @uuid_perfil_admin, 'perfis',    'editar'),     (UUID(), @uuid_perfil_admin, 'perfis',    'bloquear'),
(UUID(), @uuid_perfil_admin, 'perfis',    'excluir'),
(UUID(), @uuid_perfil_admin, 'categorias', 'visualizar'), (UUID(), @uuid_perfil_admin, 'categorias', 'criar'),
(UUID(), @uuid_perfil_admin, 'categorias', 'editar'),     (UUID(), @uuid_perfil_admin, 'categorias', 'bloquear'),
(UUID(), @uuid_perfil_admin, 'categorias', 'excluir');

-- Permissões do perfil Gerente
INSERT INTO `perfil_permissoes` (`id`, `perfil_id`, `modulo`, `acao`) VALUES
(UUID(), @uuid_perfil_gerente, 'empresas',  'visualizar'), (UUID(), @uuid_perfil_gerente, 'empresas',  'criar'),
(UUID(), @uuid_perfil_gerente, 'empresas',  'editar'),
(UUID(), @uuid_perfil_gerente, 'usuarios',  'visualizar'), (UUID(), @uuid_perfil_gerente, 'usuarios',  'criar'),
(UUID(), @uuid_perfil_gerente, 'usuarios',  'editar'),
(UUID(), @uuid_perfil_gerente, 'perfis',    'visualizar');

-- Permissões do perfil Operador
INSERT INTO `perfil_permissoes` (`id`, `perfil_id`, `modulo`, `acao`) VALUES
(UUID(), @uuid_perfil_operador, 'empresas',  'visualizar'), (UUID(), @uuid_perfil_operador, 'empresas',  'editar'),
(UUID(), @uuid_perfil_operador, 'usuarios',  'visualizar'), (UUID(), @uuid_perfil_operador, 'usuarios',  'editar'),
(UUID(), @uuid_perfil_operador, 'perfis',    'visualizar');

-- Permissões do perfil Visualizador
INSERT INTO `perfil_permissoes` (`id`, `perfil_id`, `modulo`, `acao`) VALUES
(UUID(), @uuid_perfil_visual, 'empresas',  'visualizar'),
(UUID(), @uuid_perfil_visual, 'usuarios',  'visualizar'),
(UUID(), @uuid_perfil_visual, 'perfis',    'visualizar');

-- Usuário administrador master (senha: admin123 — bcrypt cost 12)
INSERT INTO `usuarios` (`id`, `codigo`, `nome`, `email`, `senha`, `telefone`, `celular`, `status`) VALUES
(@uuid_user_joao, 'USR-0001', 'João Silva', 'joao.silva@alphatech.com.br',
 '$2y$12$HZarnT6NWxGS0NUXwXlLMOM7x0ZylqLDzeIoW67l3SawzB1/sBqaS',
 '(11) 3333-1111', '(11) 99999-1111', 'ativo');

-- Usuário da empresa cliente (senha: user123 — bcrypt cost 12)
INSERT INTO `usuarios` (`id`, `codigo`, `nome`, `email`, `senha`, `telefone`, `celular`, `status`) VALUES
(@uuid_user_maria, 'USR-0002', 'Maria Santos', 'maria.santos@betastore.com.br',
 '$2y$12$/cvgYZG1AAVCrrCTDbD9sO3hgPQWbmv4f/so9Ol5gWLtgd6SyI.Sa',
 '(21) 3333-2222', '(21) 98888-2222', 'ativo');

-- Vínculos usuário ↔ empresa
INSERT INTO `usuario_empresa` (`id`, `usuario_id`, `empresa_id`, `perfil_id`, `status`) VALUES
(UUID(), @uuid_user_joao,  @uuid_empresa_master, @uuid_perfil_admin,   'ativo'),  -- João → Alpha Tech (Administrador)
(UUID(), @uuid_user_maria, @uuid_empresa_beta,   @uuid_perfil_gerente, 'ativo');  -- Maria → Beta Store (Gerente)


-- ============================================================
-- 8. CLIENTES
-- ============================================================
CREATE TABLE `clientes` (
    `id`                    CHAR(36)        NOT NULL COMMENT 'UUID',
    `codigo`                VARCHAR(20)     NOT NULL COMMENT 'Código amigável: CLI-0001',
    `empresa_id`            CHAR(36)        NOT NULL COMMENT 'Tenant — empresa dona do registro',
    `tipo_pessoa`           ENUM('PF','PJ') NOT NULL DEFAULT 'PJ' COMMENT 'Pessoa Física ou Jurídica',
    `cpf_cnpj`              VARCHAR(18)     NOT NULL COMMENT 'CPF (000.000.000-00) ou CNPJ (00.000.000/0000-00)',
    `razao_social`          VARCHAR(255)    NOT NULL COMMENT 'Nome completo (PF) ou Razão Social (PJ)',
    `nome_fantasia`         VARCHAR(255)    NULL     COMMENT 'Nome fantasia (PJ)',
    `email`                 VARCHAR(255)    NOT NULL COMMENT 'E-mail principal para cobranças e faturas',
    `telefone`              VARCHAR(20)     NULL     COMMENT 'Telefone fixo',
    `celular`               VARCHAR(20)     NULL     COMMENT 'Celular / WhatsApp',
    `cep`                   VARCHAR(9)      NULL     COMMENT 'CEP formatado: 00000-000',
    `logradouro`            VARCHAR(255)    NULL,
    `numero`                VARCHAR(20)     NULL,
    `complemento`           VARCHAR(100)    NULL,
    `bairro`                VARCHAR(100)    NULL,
    `cidade`                VARCHAR(100)    NULL,
    `uf`                    CHAR(2)         NULL,
    `prazo_pagamento`       INT             NULL     COMMENT 'Prazo padrão de pagamento em dias (ex: 30)',
    `forma_pagamento`       ENUM('boleto','pix','cartao') NULL COMMENT 'Método padrão de cobrança',
    `limite_credito`        DECIMAL(15,2)   NULL     COMMENT 'Limite de crédito a prazo',
    `email_fatura`          VARCHAR(500)    NULL     COMMENT 'E-mail(s) para recebimento de faturas, separados por vírgula',
    `preferencia_canal`     ENUM('email','whatsapp','ambos') NULL DEFAULT 'email' COMMENT 'Canal preferido de notificação',
    `opt_out_lembretes`     TINYINT(1)      NOT NULL DEFAULT 0 COMMENT '1 = não receber lembretes automáticos',
    `categoria`             ENUM('recorrente','avulso','parceiro') NULL COMMENT 'Categoria do cliente',
    `tags`                  VARCHAR(500)    NULL     COMMENT 'Tags para segmentação, separadas por vírgula',
    `observacoes`           TEXT            NULL,
    `status`                ENUM('ativo','bloqueado') NOT NULL DEFAULT 'ativo',
    `criado_em`             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em`         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_clientes_codigo` (`codigo`),
    UNIQUE KEY `uk_clientes_cpf_cnpj_empresa` (`cpf_cnpj`, `empresa_id`),
    INDEX `idx_clientes_empresa` (`empresa_id`),
    INDEX `idx_clientes_status` (`status`),
    INDEX `idx_clientes_razao_social` (`razao_social`),
    INDEX `idx_clientes_tipo_pessoa` (`tipo_pessoa`),
    INDEX `idx_clientes_categoria` (`categoria`),
    CONSTRAINT `fk_clientes_empresa`
        FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cadastro de clientes (PF e PJ) com dados de cobrança';

-- ============================================================
-- 8.1 DOCUMENTOS DOS CLIENTES
-- ============================================================
CREATE TABLE `cliente_documentos` (
    `id`                CHAR(36)        NOT NULL COMMENT 'UUID',
    `cliente_id`        CHAR(36)        NOT NULL COMMENT 'Cliente vinculado',
    `empresa_id`        CHAR(36)        NOT NULL COMMENT 'Tenant — empresa dona do cliente',
    `tipo_documento`    ENUM('rg','cpf','cnh','comprovante_residencia','cartao_cnpj','contrato_social','inscricao_estadual','procuracao','foto_frente','foto_verso','outro') NOT NULL DEFAULT 'outro' COMMENT 'Tipo do documento anexado',
    `nome_original`     VARCHAR(255)    NOT NULL COMMENT 'Nome original do arquivo enviado',
    `arquivo_path`      VARCHAR(500)    NOT NULL COMMENT 'Caminho relativo do arquivo no storage externo',
    `mime_type`         VARCHAR(100)    NOT NULL COMMENT 'MIME type validado do arquivo',
    `tamanho_bytes`     BIGINT          NOT NULL COMMENT 'Tamanho do arquivo em bytes',
    `criado_em`         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_cliente_documentos_cliente` (`cliente_id`),
    INDEX `idx_cliente_documentos_empresa` (`empresa_id`),
    INDEX `idx_cliente_documentos_tipo` (`tipo_documento`),
    CONSTRAINT `fk_cliente_documentos_cliente`
        FOREIGN KEY (`cliente_id`) REFERENCES `clientes` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_cliente_documentos_empresa`
        FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Documentos anexados aos clientes';

-- ============================================================
-- 9. FORNECEDORES
-- ============================================================
CREATE TABLE `fornecedores` (
    `id`                        CHAR(36)        NOT NULL COMMENT 'UUID',
    `codigo`                    VARCHAR(20)     NOT NULL COMMENT 'Código amigável: FOR-0001',
    `empresa_id`                CHAR(36)        NOT NULL COMMENT 'Tenant — empresa dona do registro',
    `tipo_pessoa`               ENUM('PF','PJ') NOT NULL DEFAULT 'PJ' COMMENT 'Pessoa Física ou Jurídica',
    `cpf_cnpj`                  VARCHAR(18)     NOT NULL COMMENT 'CPF ou CNPJ',
    `razao_social`              VARCHAR(255)    NOT NULL COMMENT 'Nome completo (PF) ou Razão Social (PJ)',
    `nome_fantasia`             VARCHAR(255)    NULL     COMMENT 'Nome fantasia (PJ)',
    `email`                     VARCHAR(255)    NULL     COMMENT 'E-mail principal',
    `telefone`                  VARCHAR(20)     NULL     COMMENT 'Telefone fixo',
    `celular`                   VARCHAR(20)     NULL     COMMENT 'Celular / WhatsApp',
    `cep`                       VARCHAR(9)      NULL     COMMENT 'CEP formatado: 00000-000',
    `logradouro`                VARCHAR(255)    NULL,
    `numero`                    VARCHAR(20)     NULL,
    `complemento`               VARCHAR(100)    NULL,
    `bairro`                    VARCHAR(100)    NULL,
    `cidade`                    VARCHAR(100)    NULL,
    `uf`                        CHAR(2)         NULL,
    `banco`                     VARCHAR(100)    NULL     COMMENT 'Nome do banco para TED/DOC',
    `agencia`                   VARCHAR(20)     NULL     COMMENT 'Número da agência',
    `conta`                     VARCHAR(30)     NULL     COMMENT 'Número da conta corrente',
    `chave_pix`                 VARCHAR(255)    NULL     COMMENT 'Chave PIX (CPF, e-mail, telefone ou aleatória)',
    `dados_bancarios_adicionais` TEXT           NULL     COMMENT 'Informações bancárias extras (boletos etc.)',
    `categoria`                 VARCHAR(100)    NULL     COMMENT 'Categoria do fornecedor para relatórios',
    `observacoes`               TEXT            NULL,
    `status`                    ENUM('ativo','bloqueado') NOT NULL DEFAULT 'ativo',
    `criado_em`                 DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `atualizado_em`             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_fornecedores_codigo` (`codigo`),
    UNIQUE KEY `uk_fornecedores_cpf_cnpj_empresa` (`cpf_cnpj`, `empresa_id`),
    INDEX `idx_fornecedores_empresa` (`empresa_id`),
    INDEX `idx_fornecedores_status` (`status`),
    INDEX `idx_fornecedores_razao_social` (`razao_social`),
    INDEX `idx_fornecedores_tipo_pessoa` (`tipo_pessoa`),
    CONSTRAINT `fk_fornecedores_empresa`
        FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Cadastro de fornecedores com dados bancários para pagamento';

-- ============================================================
-- 9.1 DOCUMENTOS DOS FORNECEDORES
-- ============================================================
CREATE TABLE `fornecedor_documentos` (
    `id`                CHAR(36)        NOT NULL COMMENT 'UUID',
    `fornecedor_id`     CHAR(36)        NOT NULL COMMENT 'Fornecedor vinculado',
    `empresa_id`        CHAR(36)        NOT NULL COMMENT 'Tenant — empresa dona do fornecedor',
    `tipo_documento`    ENUM('rg','cpf','cnh','comprovante_residencia','cartao_cnpj','contrato_social','inscricao_estadual','procuracao','foto_frente','foto_verso','outro') NOT NULL DEFAULT 'outro' COMMENT 'Tipo do documento anexado',
    `nome_original`     VARCHAR(255)    NOT NULL COMMENT 'Nome original do arquivo enviado',
    `arquivo_path`      VARCHAR(500)    NOT NULL COMMENT 'Caminho relativo do arquivo no storage externo',
    `mime_type`         VARCHAR(100)    NOT NULL COMMENT 'MIME type validado do arquivo',
    `tamanho_bytes`     BIGINT          NOT NULL COMMENT 'Tamanho do arquivo em bytes',
    `criado_em`         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_fornecedor_documentos_fornecedor` (`fornecedor_id`),
    INDEX `idx_fornecedor_documentos_empresa` (`empresa_id`),
    INDEX `idx_fornecedor_documentos_tipo` (`tipo_documento`),
    CONSTRAINT `fk_fornecedor_documentos_fornecedor`
        FOREIGN KEY (`fornecedor_id`) REFERENCES `fornecedores` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_fornecedor_documentos_empresa`
        FOREIGN KEY (`empresa_id`) REFERENCES `empresas` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Documentos anexados aos fornecedores';


-- ============================================================
-- DADOS DE EXEMPLO: CLIENTES
-- ============================================================
INSERT INTO `clientes`
    (`id`, `codigo`, `empresa_id`, `tipo_pessoa`, `cpf_cnpj`, `razao_social`, `nome_fantasia`,
     `email`, `telefone`, `celular`, `cep`, `logradouro`, `numero`, `bairro`, `cidade`, `uf`,
     `prazo_pagamento`, `forma_pagamento`, `limite_credito`, `preferencia_canal`, `categoria`, `status`)
VALUES
-- PJ — Distribuidora Nacional de Alimentos (Alpha Tech)
(UUID(), 'CLI-0001', @uuid_empresa_master, 'PJ', '11.222.333/0001-44',
 'Distribuidora Nacional de Alimentos Ltda', 'DNA Foods',
 'financeiro@dnafoods.com.br', '(11) 3344-5566', '(11) 99887-6543',
 '04544-000', 'Av. Engenheiro Luis Carlos Berrini', '1500', 'Brooklin', 'São Paulo', 'SP',
 30, 'boleto', 50000.00, 'email', 'recorrente', 'ativo'),

-- PF — Carlos Eduardo (Alpha Tech)
(UUID(), 'CLI-0002', @uuid_empresa_master, 'PF', '123.456.789-01',
 'Carlos Eduardo Mendes da Silva', NULL,
 'carlos.mendes@gmail.com', NULL, '(11) 97654-3210',
 '01310-100', 'Av. Paulista', '1000', 'Bela Vista', 'São Paulo', 'SP',
 15, 'pix', NULL, 'whatsapp', 'avulso', 'ativo'),

-- PJ — Tech Solutions (Beta Store)
(UUID(), 'CLI-0003', @uuid_empresa_beta, 'PJ', '55.666.777/0001-88',
 'Tech Solutions Informática S/A', 'TechSol',
 'pagamentos@techsol.com.br', '(21) 2233-4455', '(21) 98765-4321',
 '20040-020', 'Av. Rio Branco', '200', 'Centro', 'Rio de Janeiro', 'RJ',
 45, 'cartao', 120000.00, 'ambos', 'parceiro', 'ativo');

-- ============================================================
-- DADOS DE EXEMPLO: FORNECEDORES
-- ============================================================
INSERT INTO `fornecedores`
    (`id`, `codigo`, `empresa_id`, `tipo_pessoa`, `cpf_cnpj`, `razao_social`, `nome_fantasia`,
     `email`, `telefone`, `celular`, `cep`, `logradouro`, `numero`, `bairro`, `cidade`, `uf`,
     `banco`, `agencia`, `conta`, `chave_pix`, `categoria`, `status`)
VALUES
-- PJ — Papelaria Central (Alpha Tech)
(UUID(), 'FOR-0001', @uuid_empresa_master, 'PJ', '99.888.777/0001-66',
 'Papelaria e Suprimentos Central Ltda', 'Central Papéis',
 'nf@centralpapeis.com.br', '(11) 2211-3344', '(11) 91234-5678',
 '03015-000', 'Rua 25 de Março', '850', 'Centro', 'São Paulo', 'SP',
 'Banco do Brasil', '1234-5', '56789-0', '99888777000166', 'Material de escritório', 'ativo'),

-- PF — Ana Paula consultora (Alpha Tech)
(UUID(), 'FOR-0002', @uuid_empresa_master, 'PF', '987.654.321-00',
 'Ana Paula Ferreira dos Santos', NULL,
 'ana.ferreira@outlook.com', NULL, '(11) 98877-6655',
 '04547-000', 'Rua Funchal', '411', 'Vila Olímpia', 'São Paulo', 'SP',
 'Nubank', '0001', '7654321-0', 'ana.ferreira@outlook.com', 'Consultoria', 'ativo'),

-- PJ — LogExpress (Beta Store)
(UUID(), 'FOR-0003', @uuid_empresa_beta, 'PJ', '44.555.666/0001-99',
 'Logística Expressa Transportes ME', 'LogExpress',
 'financeiro@logexpress.com.br', '(21) 3322-1100', '(21) 97777-8888',
 '20271-020', 'Rua da Alfândega', '75', 'Centro', 'Rio de Janeiro', 'RJ',
 'Itaú', '0987', '12345-6', '44555666000199', 'Transporte e logística', 'ativo');
