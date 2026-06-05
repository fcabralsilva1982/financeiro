-- Migration Marco M1
-- Data: 2026-06-04

USE financeiro_erp;

-- Registro de aceite de termos por usuario e versao
CREATE TABLE IF NOT EXISTS usuario_termos (
    id CHAR(36) PRIMARY KEY,
    usuario_id CHAR(36) NOT NULL,
    versao_termo VARCHAR(20) NOT NULL,
    aceito_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip VARCHAR(45) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_usuario_termo_versao (usuario_id, versao_termo),
    KEY idx_usuario_termos_usuario (usuario_id),
    CONSTRAINT fk_usuario_termos_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
