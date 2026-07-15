-- Migration M13
-- Data: 2026-07-15
-- Objetivo: criar estrutura inicial do modulo de conciliacao bancaria e conceder permissoes ao Administrador.

USE financeiro_erp;

CREATE TABLE IF NOT EXISTS conciliacao_bancaria (
    id CHAR(36) PRIMARY KEY,
    empresa_id CHAR(36) NOT NULL,
    usuario_id CHAR(36) NULL,
    nome_arquivo VARCHAR(255) NOT NULL,
    data_importacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    quantidade_transacoes INT NOT NULL DEFAULT 0,
    quantidade_duplicadas INT NOT NULL DEFAULT 0,
    quantidade_conciliadas_auto INT NOT NULL DEFAULT 0,
    quantidade_pendentes INT NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_cb_empresa_data (empresa_id, data_importacao),
    KEY idx_cb_usuario (usuario_id),
    CONSTRAINT fk_cb_empresa
        FOREIGN KEY (empresa_id) REFERENCES empresas(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_cb_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conciliacao_transacoes (
    id CHAR(36) PRIMARY KEY,
    conciliacao_bancaria_id CHAR(36) NOT NULL,
    empresa_id CHAR(36) NOT NULL,
    fitid VARCHAR(100) NOT NULL,
    data_movimento DATE NOT NULL,
    valor DECIMAL(15,2) NOT NULL,
    tipo_movimento ENUM('credito','debito') NOT NULL,
    descricao_banco VARCHAR(255) NULL,
    memo TEXT NULL,
    status_conciliacao ENUM('pendente','conciliado_auto','conciliado_manual','ignorado','movimento_interno') NOT NULL DEFAULT 'pendente',
    origem_match ENUM('conta_receber','conta_pagar','novo_recebimento','nova_despesa','movimento_interno','nenhum') NOT NULL DEFAULT 'nenhum',
    conta_receber_id CHAR(36) NULL,
    conta_pagar_parcela_id CHAR(36) NULL,
    confianca_match TINYINT UNSIGNED NULL,
    acao_confirmada_por_usuario_id CHAR(36) NULL,
    acao_confirmada_em DATETIME NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_ct_empresa_fitid (empresa_id, fitid),
    KEY idx_ct_importacao (conciliacao_bancaria_id),
    KEY idx_ct_empresa_status (empresa_id, status_conciliacao),
    KEY idx_ct_empresa_data (empresa_id, data_movimento),
    KEY idx_ct_empresa_tipo (empresa_id, tipo_movimento),
    KEY idx_ct_conta_receber (conta_receber_id),
    KEY idx_ct_parcela_pagar (conta_pagar_parcela_id),
    CONSTRAINT fk_ct_importacao
        FOREIGN KEY (conciliacao_bancaria_id) REFERENCES conciliacao_bancaria(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ct_empresa
        FOREIGN KEY (empresa_id) REFERENCES empresas(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ct_conta_receber
        FOREIGN KEY (conta_receber_id) REFERENCES contas_receber(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_ct_parcela_pagar
        FOREIGN KEY (conta_pagar_parcela_id) REFERENCES contas_pagar_parcelas(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_ct_confirmado_por
        FOREIGN KEY (acao_confirmada_por_usuario_id) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO perfil_permissoes (id, perfil_id, modulo, acao)
SELECT UUID(), p.id, 'conciliacao', a.acao
FROM perfis p
JOIN (
    SELECT 'visualizar' AS acao
    UNION ALL SELECT 'criar'
    UNION ALL SELECT 'editar'
    UNION ALL SELECT 'bloquear'
    UNION ALL SELECT 'excluir'
) a
WHERE p.nome = 'Administrador';