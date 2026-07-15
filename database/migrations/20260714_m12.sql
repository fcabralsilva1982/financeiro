-- Migration M12
-- Data: 2026-07-14
-- Objetivo: conceder permissoes do modulo de categorias ao perfil Administrador existente.

USE financeiro_erp;

INSERT IGNORE INTO perfil_permissoes (id, perfil_id, modulo, acao)
SELECT UUID(), p.id, 'categorias', a.acao
FROM perfis p
JOIN (
    SELECT 'visualizar' AS acao
    UNION ALL SELECT 'criar'
    UNION ALL SELECT 'editar'
    UNION ALL SELECT 'bloquear'
    UNION ALL SELECT 'excluir'
) a
WHERE p.nome = 'Administrador';
