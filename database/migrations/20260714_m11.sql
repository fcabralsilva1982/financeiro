-- Migration M11
-- Data: 2026-07-14
-- Objetivo: suportar tenants PF e profissional liberal sem CNPJ.

USE financeiro_erp;

ALTER TABLE empresas
    ADD COLUMN tipo_pessoa ENUM('PF','PJ') NOT NULL DEFAULT 'PJ' COMMENT 'Pessoa Fisica ou Juridica' AFTER codigo,
    MODIFY COLUMN cnpj VARCHAR(18) NULL COMMENT 'CNPJ numerico ou alfanumerico formatado',
    ADD COLUMN cpf VARCHAR(14) NULL COMMENT 'CPF formatado' AFTER cnpj;

UPDATE empresas
SET tipo_pessoa = 'PJ'
WHERE tipo_pessoa IS NULL;

ALTER TABLE empresas
    DROP INDEX uk_empresas_cnpj,
    ADD UNIQUE KEY uk_empresas_cnpj (cnpj),
    ADD UNIQUE KEY uk_empresas_cpf (cpf),
    ADD INDEX idx_empresas_tipo_pessoa (tipo_pessoa);
