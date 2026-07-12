<?php
declare(strict_types=1);

require_once __DIR__ . '/../helpers/database.php';
require_once __DIR__ . '/../helpers/functions.php';

final class CategoriasFinanceiras
{
    public static function listar(string $empresaId, array $filtros = []): array
    {
        $pdo = db();
        $sql = "SELECT id, codigo, nome, tipo, status, criado_em
                FROM categorias
                WHERE empresa_id = :empresa_id";
        $params = [':empresa_id' => $empresaId];

        if (!empty($filtros['tipo'])) {
            $sql .= ' AND tipo = :tipo';
            $params[':tipo'] = $filtros['tipo'];
        }

        if (!empty($filtros['status'])) {
            $sql .= ' AND status = :status';
            $params[':status'] = $filtros['status'];
        }

        if (!empty($filtros['busca'])) {
            $sql .= ' AND (codigo LIKE :busca OR nome LIKE :busca)';
            $params[':busca'] = '%' . $filtros['busca'] . '%';
        }

        $sql .= ' ORDER BY nome ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function buscarPorId(string $id, string $empresaId): ?array
    {
        $pdo = db();
        $stmt = $pdo->prepare(
            'SELECT id, codigo, nome, tipo, descricao, status
             FROM categorias
             WHERE id = :id AND empresa_id = :empresa_id
             LIMIT 1'
        );
        $stmt->execute([':id' => $id, ':empresa_id' => $empresaId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function criar(array $dados, string $empresaId): string
    {
        $pdo = db();
        $id = generate_uuid();

        $stmt = $pdo->prepare(
            'INSERT INTO categorias (id, empresa_id, codigo, nome, tipo, descricao, status)
             VALUES (:id, :empresa_id, :codigo, :nome, :tipo, :descricao, :status)'
        );
        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
            ':codigo' => self::gerarCodigo(),
            ':nome' => trim((string) ($dados['nome'] ?? '')),
            ':tipo' => (string) ($dados['tipo'] ?? 'despesa'),
            ':descricao' => trim((string) ($dados['descricao'] ?? '')) ?: null,
            ':status' => (string) ($dados['status'] ?? 'ativo'),
        ]);

        return $id;
    }

    public static function atualizar(string $id, array $dados, string $empresaId): void
    {
        $pdo = db();
        $stmt = $pdo->prepare(
            'UPDATE categorias
             SET nome = :nome, tipo = :tipo, descricao = :descricao, status = :status
             WHERE id = :id AND empresa_id = :empresa_id'
        );
        $stmt->execute([
            ':id' => $id,
            ':empresa_id' => $empresaId,
            ':nome' => trim((string) ($dados['nome'] ?? '')),
            ':tipo' => (string) ($dados['tipo'] ?? 'despesa'),
            ':descricao' => trim((string) ($dados['descricao'] ?? '')) ?: null,
            ':status' => (string) ($dados['status'] ?? 'ativo'),
        ]);
    }

    public static function excluir(string $id, string $empresaId): void
    {
        $pdo = db();
        $stmt = $pdo->prepare('DELETE FROM categorias WHERE id = :id AND empresa_id = :empresa_id');
        $stmt->execute([':id' => $id, ':empresa_id' => $empresaId]);
    }

    public static function validar(array $dados, string $empresaId, ?string $idIgnorado = null): array
    {
        $erros = [];
        $nome = trim((string) ($dados['nome'] ?? ''));
        $tipo = (string) ($dados['tipo'] ?? '');

        if ($nome === '') {
            $erros[] = 'Nome da categoria e obrigatorio.';
        }

        if (!in_array($tipo, ['receita', 'despesa'], true)) {
            $erros[] = 'Tipo da categoria invalido.';
        }

        if ($nome !== '' && in_array($tipo, ['receita', 'despesa'], true)) {
            $pdo = db();
            $sql = 'SELECT id FROM categorias WHERE empresa_id = :empresa_id AND nome = :nome AND tipo = :tipo';
            $params = [':empresa_id' => $empresaId, ':nome' => $nome, ':tipo' => $tipo];

            if ($idIgnorado !== null) {
                $sql .= ' AND id <> :id';
                $params[':id'] = $idIgnorado;
            }

            $sql .= ' LIMIT 1';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            if ($stmt->fetch()) {
                $erros[] = 'Ja existe categoria com este nome e tipo para esta empresa.';
            }
        }

        return $erros;
    }

    private static function gerarCodigo(): string
    {
        $pdo = db();
        $stmt = $pdo->prepare(
            "SELECT COALESCE(MAX(CAST(SUBSTRING_INDEX(codigo, '-', -1) AS UNSIGNED)), 0)
             FROM categorias
             WHERE codigo REGEXP '^CAT-[0-9]+$'"
        );
        $stmt->execute();
        $seq = ((int) $stmt->fetchColumn()) + 1;

        return generate_codigo('CAT', $seq);
    }
}
