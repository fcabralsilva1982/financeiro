-- Migration Marco M3
-- Data: 2026-06-04

USE financeiro_erp;

-- Índices de apoio para consultas agregadas do Dashboard

-- Contas a Receber
CREATE INDEX idx_cr_empresa_status_vencimento ON contas_receber (empresa_id, status, data_vencimento);
CREATE INDEX idx_cr_empresa_cliente_status ON contas_receber (empresa_id, cliente_id, status);

-- Contas a Pagar
CREATE INDEX idx_cp_empresa_status_vencimento ON contas_pagar (empresa_id, status, data_vencimento);
CREATE INDEX idx_cp_empresa_fornecedor_status ON contas_pagar (empresa_id, fornecedor_id, status);

-- Parcelas (job e consolidacao de status)
CREATE INDEX idx_crp_status_vencimento ON contas_receber_parcelas (status, data_vencimento);
CREATE INDEX idx_cpp_status_vencimento ON contas_pagar_parcelas (status, data_vencimento);
