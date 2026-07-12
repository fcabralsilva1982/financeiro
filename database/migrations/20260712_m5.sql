-- Migration M5
-- Data: 2026-07-12

USE financeiro_erp;

ALTER TABLE contas_receber
    ADD COLUMN data_pagamento DATE NULL AFTER status,
    ADD COLUMN valor_pago DECIMAL(15,2) NULL AFTER data_pagamento,
    ADD COLUMN forma_recebimento ENUM('boleto','pix','cartao','dinheiro','transferencia','outro') NULL AFTER valor_pago,
    ADD COLUMN observacoes_baixa TEXT NULL AFTER forma_recebimento;
