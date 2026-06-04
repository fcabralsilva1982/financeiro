---
name: api-rest
description: 'Projetar, implementar ou revisar APIs REST em PHP. Use para: design de endpoints, versionamento, autenticação via API key ou JWT, respostas padronizadas, tratamento de erros HTTP, validação de entrada, documentação OpenAPI, rate limiting e boas práticas REST.'
argument-hint: 'Descreva o que precisa (ex: "criar endpoint de listagem de usuários", "autenticação JWT", "documentar API de pedidos")'
---

# API REST — PHP

## Princípios REST

- URLs identificam **recursos** (substantivos, plural): `/usuarios`, `/pedidos/42/itens`
- Verbos HTTP definem a **ação**: `GET` ler, `POST` criar, `PUT/PATCH` atualizar, `DELETE` remover
- Stateless: cada request contém todas as informações necessárias
- Respostas consistentes: mesmo formato de sucesso e erro em toda a API

## Design de endpoints

```
GET    /api/v1/usuarios            # listar (com paginação)
GET    /api/v1/usuarios/{id}       # buscar por ID
POST   /api/v1/usuarios            # criar
PUT    /api/v1/usuarios/{id}       # substituir completo
PATCH  /api/v1/usuarios/{id}       # atualizar parcialmente
DELETE /api/v1/usuarios/{id}       # remover

GET    /api/v1/usuarios/{id}/pedidos   # sub-recurso
```

- Versionar na URL: `/api/v1/`
- Kebab-case para recursos compostos: `/itens-pedido`
- Nunca usar verbos na URL: `/api/usuarios/buscar` ❌ → `GET /api/usuarios?q=termo` ✅

## Resposta padronizada

```php
// Sucesso
http_response_code(200);
header('Content-Type: application/json; charset=UTF-8');
echo json_encode([
    'sucesso' => true,
    'dados'   => $resultado,
    'meta'    => ['total' => $total, 'pagina' => $pagina, 'por_pagina' => $porPagina],
]);

// Erro
http_response_code(422);
echo json_encode([
    'sucesso' => false,
    'erro'    => [
        'codigo'  => 'VALIDACAO_FALHOU',
        'mensagem' => 'Dados inválidos.',
        'campos'  => ['email' => 'E-mail já cadastrado.'],
    ],
]);
```

## Códigos HTTP corretos

| Situação | Código |
|----------|--------|
| Sucesso com dados | 200 OK |
| Recurso criado | 201 Created |
| Sem conteúdo | 204 No Content |
| Dados inválidos | 422 Unprocessable Entity |
| Não autenticado | 401 Unauthorized |
| Sem permissão | 403 Forbidden |
| Não encontrado | 404 Not Found |
| Rate limit atingido | 429 Too Many Requests |
| Erro interno | 500 Internal Server Error |

## Autenticação

### API Key (serviço a serviço)

```php
$apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
$hash   = hash('sha256', $apiKey);

$stmt = $pdo->prepare("SELECT id FROM api_keys WHERE hash = :hash AND ativo = 1 LIMIT 1");
$stmt->execute([':hash' => $hash]);
if (!$stmt->fetch()) {
    http_response_code(401);
    echo json_encode(['sucesso' => false, 'erro' => ['codigo' => 'NAO_AUTENTICADO']]);
    exit;
}
```

### JWT (usuário autenticado)

```php
// Validar JWT no header Authorization: Bearer <token>
$header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (!preg_match('/^Bearer\s(.+)$/', $header, $m)) { /* 401 */ }

$payload = validar_jwt($m[1], JWT_SECRET); // verificar assinatura + exp + iss + aud
if (!$payload) { /* 401 */ }
```

- Algoritmo: `RS256` ou `HS256` (nunca `none`)
- Payload mínimo: `sub` (usuario_id), `exp`, `iss`, `aud`
- Expiração curta: 15–60 min; usar refresh token de longa duração
- Nunca armazenar JWT em `localStorage`; preferir cookie `HttpOnly; Secure`

## Validação de entrada

```php
function validar_entrada(array $dados, array $regras): array
{
    $erros = [];
    foreach ($regras as $campo => [$tipo, $obrigatorio, $max]) {
        $valor = $dados[$campo] ?? null;
        if ($obrigatorio && ($valor === null || $valor === '')) {
            $erros[$campo] = 'Campo obrigatório.';
            continue;
        }
        if ($valor !== null && $tipo === 'email' && !filter_var($valor, FILTER_VALIDATE_EMAIL)) {
            $erros[$campo] = 'E-mail inválido.';
        }
        if ($valor !== null && $max && mb_strlen((string)$valor) > $max) {
            $erros[$campo] = "Máximo $max caracteres.";
        }
    }
    return $erros;
}
```

## Paginação

```php
$pagina    = max(1, (int)($_GET['pagina'] ?? 1));
$porPagina = min(100, max(1, (int)($_GET['por_pagina'] ?? 20)));
$offset    = ($pagina - 1) * $porPagina;

// Query com LIMIT/OFFSET
$stmt = $pdo->prepare("SELECT ... FROM tabela LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit',  $porPagina, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset,    PDO::PARAM_INT);
```

## Headers obrigatórios

```php
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');          // para endpoints autenticados
// CORS (se necessário)
header('Access-Control-Allow-Origin: https://meusite.com.br');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');
```

## Rate limiting

```php
$chave     = 'rate:' . hash('sha256', $ip . $rota);
$tentativas = incrementar_contador($chave, ttl: 60); // ex: Redis ou tabela
if ($tentativas > 60) {
    header('Retry-After: 60');
    http_response_code(429);
    exit;
}
```

## Documentação OpenAPI (mínimo)

```yaml
# openapi.yaml
openapi: "3.1.0"
info:
  title: API do Sistema
  version: "1.0.0"
paths:
  /api/v1/usuarios:
    get:
      summary: Listar usuários
      security: [{ bearerAuth: [] }]
      parameters:
        - name: pagina
          in: query
          schema: { type: integer, default: 1 }
      responses:
        "200":
          description: Lista de usuários
        "401":
          description: Não autenticado
```

## Nunca fazer

- Retornar stack trace ou query SQL em respostas de erro
- Usar `GET` para operações que alteram estado
- Retornar `200` com `{"erro": "..."}` no body
- `Access-Control-Allow-Origin: *` em endpoint autenticado
- Expor IDs sequenciais previsíveis sem controle de acesso (IDOR)
- Retornar lista sem paginação em endpoints públicos
