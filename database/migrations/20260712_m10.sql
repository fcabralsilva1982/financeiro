-- Migration M10
-- Data: 2026-07-12

USE financeiro_erp;

CREATE TABLE IF NOT EXISTS fornecedor_documentos (
    id CHAR(36) NOT NULL,
    fornecedor_id CHAR(36) NOT NULL,
    empresa_id CHAR(36) NOT NULL,
    tipo_documento ENUM('rg','cpf','cnh','comprovante_residencia','cartao_cnpj','contrato_social','inscricao_estadual','procuracao','foto_frente','foto_verso','outro') NOT NULL DEFAULT 'outro',
    nome_original VARCHAR(255) NOT NULL,
    arquivo_path VARCHAR(500) NOT NULL,
    mime_type VARCHAR(100) NOT NULL,
    tamanho_bytes BIGINT NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_fornecedor_documentos_fornecedor (fornecedor_id),
    KEY idx_fornecedor_documentos_empresa (empresa_id),
    KEY idx_fornecedor_documentos_tipo (tipo_documento),
    CONSTRAINT fk_fornecedor_documentos_fornecedor
        FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_fornecedor_documentos_empresa
        FOREIGN KEY (empresa_id) REFERENCES empresas(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
