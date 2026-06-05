-- Migration Marco M2
-- Data: 2026-06-04

USE financeiro_erp;

-- Categorias financeiras por empresa
CREATE TABLE IF NOT EXISTS categorias (
    id CHAR(36) PRIMARY KEY,
    empresa_id CHAR(36) NOT NULL,
    codigo VARCHAR(20) NOT NULL,
    nome VARCHAR(120) NOT NULL,
    tipo ENUM('receita','despesa') NOT NULL,
    descricao VARCHAR(255) NULL,
    status ENUM('ativo','bloqueado') NOT NULL DEFAULT 'ativo',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_categorias_empresa_nome_tipo (empresa_id, nome, tipo),
    UNIQUE KEY uk_categorias_codigo (codigo),
    KEY idx_categorias_empresa (empresa_id),
    KEY idx_categorias_tipo (tipo),
    KEY idx_categorias_status (status),
    CONSTRAINT fk_categorias_empresa
        FOREIGN KEY (empresa_id) REFERENCES empresas(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Conta a pagar (cabecalho)
CREATE TABLE IF NOT EXISTS contas_pagar (
    id CHAR(36) PRIMARY KEY,
    empresa_id CHAR(36) NOT NULL,
    fornecedor_id CHAR(36) NOT NULL,
    categoria_id CHAR(36) NULL,
    codigo VARCHAR(20) NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    valor_total DECIMAL(15,2) NOT NULL,
    data_emissao DATE NOT NULL,
    data_vencimento DATE NOT NULL,
    forma_pagamento ENUM('boleto','pix','ted','doc','cartao','dinheiro','outro') NOT NULL,
    status ENUM('rascunho','pendente_aprovacao','aprovada','parcialmente_paga','paga','vencida','cancelada') NOT NULL DEFAULT 'rascunho',
    recorrente TINYINT(1) NOT NULL DEFAULT 0,
    periodicidade ENUM('semanal','quinzenal','mensal','bimestral','trimestral','semestral','anual') NULL,
    observacoes TEXT NULL,
    criado_por_usuario_id CHAR(36) NULL,
    aprovado_por_usuario_id CHAR(36) NULL,
    aprovado_em DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_contas_pagar_empresa_codigo (empresa_id, codigo),
    KEY idx_contas_pagar_empresa (empresa_id),
    KEY idx_contas_pagar_fornecedor (fornecedor_id),
    KEY idx_contas_pagar_categoria (categoria_id),
    KEY idx_contas_pagar_status (status),
    KEY idx_contas_pagar_vencimento (data_vencimento),
    CONSTRAINT fk_cp_empresa
        FOREIGN KEY (empresa_id) REFERENCES empresas(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_cp_fornecedor
        FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_cp_categoria
        FOREIGN KEY (categoria_id) REFERENCES categorias(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_cp_criado_por
        FOREIGN KEY (criado_por_usuario_id) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_cp_aprovado_por
        FOREIGN KEY (aprovado_por_usuario_id) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Parcelas de contas a pagar
CREATE TABLE IF NOT EXISTS contas_pagar_parcelas (
    id CHAR(36) PRIMARY KEY,
    conta_pagar_id CHAR(36) NOT NULL,
    numero_parcela INT NOT NULL,
    valor DECIMAL(15,2) NOT NULL,
    data_vencimento DATE NOT NULL,
    data_pagamento DATE NULL,
    valor_pago DECIMAL(15,2) NULL,
    status ENUM('pendente','paga','vencida','cancelada') NOT NULL DEFAULT 'pendente',
    forma_pagamento ENUM('boleto','pix','ted','doc','cartao','dinheiro','outro') NULL,
    comprovante_path VARCHAR(500) NULL,
    observacoes VARCHAR(255) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_cpp_conta_numero (conta_pagar_id, numero_parcela),
    KEY idx_cpp_status (status),
    KEY idx_cpp_vencimento (data_vencimento),
    CONSTRAINT fk_cpp_conta
        FOREIGN KEY (conta_pagar_id) REFERENCES contas_pagar(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Historico operacional de contas a pagar
CREATE TABLE IF NOT EXISTS contas_pagar_historico (
    id CHAR(36) PRIMARY KEY,
    conta_pagar_id CHAR(36) NOT NULL,
    parcela_id CHAR(36) NULL,
    evento VARCHAR(80) NOT NULL,
    descricao VARCHAR(255) NOT NULL,
    usuario_id CHAR(36) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cph_conta (conta_pagar_id),
    KEY idx_cph_parcela (parcela_id),
    KEY idx_cph_usuario (usuario_id),
    KEY idx_cph_evento (evento),
    CONSTRAINT fk_cph_conta
        FOREIGN KEY (conta_pagar_id) REFERENCES contas_pagar(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_cph_parcela
        FOREIGN KEY (parcela_id) REFERENCES contas_pagar_parcelas(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_cph_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
