-- Migration M6
-- Data: 2026-07-12

USE financeiro_erp;

CREATE TABLE IF NOT EXISTS contas_receber_historico (
    id CHAR(36) NOT NULL COMMENT 'UUID',
    conta_receber_id CHAR(36) NOT NULL COMMENT 'Fatura vinculada',
    parcela_id CHAR(36) NULL COMMENT 'Parcela vinculada (opcional)',
    evento VARCHAR(100) NOT NULL COMMENT 'Evento: lembrete, envio, visualizacao, baixa, etc.',
    descricao VARCHAR(255) NULL COMMENT 'Descrição detalhada do evento',
    canal ENUM('email','whatsapp','sistema','webhook','outro') NOT NULL,
    data_evento DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    usuario_id CHAR(36) NULL COMMENT 'Usuário responsável (se aplicável)',
    PRIMARY KEY (id),
    KEY idx_crh_conta (conta_receber_id),
    KEY idx_crh_parcela (parcela_id),
    CONSTRAINT fk_crh_conta FOREIGN KEY (conta_receber_id) REFERENCES contas_receber(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Histórico de eventos e comunicações da régua de cobrança';
