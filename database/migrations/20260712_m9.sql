-- Migration M9
-- Data: 2026-07-12

USE financeiro_erp;

ALTER TABLE cliente_documentos
    MODIFY COLUMN tipo_documento ENUM('rg','cpf','cnh','comprovante_residencia','cartao_cnpj','contrato_social','inscricao_estadual','procuracao','foto_frente','foto_verso','outro') NOT NULL DEFAULT 'outro';
