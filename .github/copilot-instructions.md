# Copilot Instructions — 
---

## Estilo de código geral

- Comentários apenas onde a intenção não é óbvia
- Evite trabalhar em mais de um arquivo por vez.
- Múltiplas edições simultâneas em um arquivo causarão corrupção.
- Esteja conversando e ensinando sobre o que você está fazendo enquanto codifica.
- Sempre adicionar o número da linha e o nome do arquivo quando você referencia o código
- Sempre atualize a documentação do código para refletir as mudanças feitas.
- Use nomes de variáveis e funções que sejam descritivos do seu propósito.
- Evite usar abreviações que não sejam amplamente reconhecidas.
- Mantenha as funções curtas e focadas em uma única tarefa.
- Use comentários para explicar o "porquê" de uma decisão de código, não o "o quê".
- Evite código morto ou comentado sem uma explicação clara de por que ele está lá.
- Trate as exceções de maneira adequada, evitando `catch` vazio ou genérico demais.
- Em consultas SQL, sempre use `LIMIT` explícito e evite `SELECT *`, listando as colunas explicitamente.
- Use constantes nomeadas em maiúsculo para valores fixos, evitando magic numbers ou strings.
- Inclua `declare(strict_types=1)` no início dos arquivos PHP para garantir a tipagem estrita.
- Declare os tipos de parâmetros e retorno das funções para melhorar a legibilidade e a segurança do código.