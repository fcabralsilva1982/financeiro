-- Migration M7
-- Data: 2026-07-12

USE financeiro_erp;

ALTER TABLE contas_receber
    ADD COLUMN comprovante_path VARCHAR(500) NULL AFTER forma_recebimento;
