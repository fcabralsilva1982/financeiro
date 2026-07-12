-- Migration M4
-- Data: 2026-07-12

USE financeiro_erp;

ALTER TABLE contas_receber
    MODIFY COLUMN status ENUM('rascunho','emitida','enviada','visualizada','paga','vencida','cancelada') NOT NULL DEFAULT 'emitida';
