---
name: html-css
description: 'Escrever ou revisar HTML e CSS para sistemas web. Use para: semântica HTML5, acessibilidade WCAG 2.2, BEM, layout responsivo, Flexbox, Grid, formulários acessíveis, dark mode, variáveis CSS, performance de carregamento e boas práticas de marcação.'
argument-hint: 'Descreva o que criar ou revisar (ex: "formulário de login acessível", "layout de listagem responsivo", "revisar semântica desta página")'
---

# HTML / CSS — Boas Práticas

## HTML semântico

- Usar elementos pelo **significado**, não pela aparência
- Estrutura mínima de página:

```html
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Descrição da página">
    <title>Título da Página — Nome do Sistema</title>
    <link rel="stylesheet" href="/public/assets/css/app.css">
</head>
<body>
    <header>...</header>
    <nav aria-label="Menu principal">...</nav>
    <main id="conteudo-principal">...</main>
    <footer>...</footer>
</body>
</html>
```

- `<main>` único por página; `<header>` e `<footer>` dentro de `<article>`/`<section>` são permitidos
- Headings hierárquicos: `h1` → `h2` → `h3` (nunca pular nível)
- Botões de ação: `<button>`; links de navegação: `<a href>`; nunca inverter
- Listas de navegação dentro de `<nav><ul><li>`

## Acessibilidade (WCAG 2.2)

- `alt` descritivo em todas as `<img>` (decorativas: `alt=""`)
- Labels associados a inputs: `<label for="campo-email">` + `<input id="campo-email">`
- Não usar cor como único indicador de estado
- Contraste mínimo: 4.5:1 (texto normal), 3:1 (texto grande/UI)
- Foco visível em todos os elementos interativos (não remover `outline` sem substituto)
- Ordem de foco lógica (seguir ordem do DOM)
- `role` e `aria-*` apenas quando semântica nativa não for suficiente

```html
<!-- Formulário acessível -->
<div class="campo">
    <label for="email">E-mail <span aria-hidden="true">*</span></label>
    <input type="email" id="email" name="email"
           required
           aria-required="true"
           aria-describedby="email-erro"
           autocomplete="email">
    <span id="email-erro" class="erro" role="alert" hidden>E-mail inválido.</span>
</div>
```

## BEM — nomenclatura de classes

```
bloco__elemento--modificador
```

```html
<div class="cartao">
    <h2 class="cartao__titulo">Nome</h2>
    <p class="cartao__descricao">Texto</p>
    <button class="cartao__botao cartao__botao--destaque">Ver mais</button>
</div>
```

- Bloco: componente independente (`cartao`, `modal`, `nav-principal`)
- Elemento: parte do bloco (`cartao__titulo`) — nunca aninhar BEM (`cartao__header__titulo` ❌)
- Modificador: variação de estado (`botao--destaque`, `campo--erro`, `menu--aberto`)

## CSS — estrutura e variáveis

```css
/* Variáveis no :root */
:root {
    --cor-primaria: #1a73e8;
    --cor-texto: #212121;
    --cor-fundo: #ffffff;
    --fonte-base: 1rem;
    --raio-borda: 4px;
    --espacamento: 1rem;
}

/* Dark mode automático */
@media (prefers-color-scheme: dark) {
    :root {
        --cor-texto: #e8eaed;
        --cor-fundo: #1e1e1e;
    }
}
```

## Layout responsivo

- Mobile-first: estilos base para mobile, expandir com `min-width`
- Breakpoints sugeridos: `480px`, `768px`, `1024px`, `1280px`
- Preferir Flexbox para componentes, Grid para layouts de página

```css
/* Grid de página */
.layout {
    display: grid;
    grid-template-columns: 1fr;
    gap: var(--espacamento);
}
@media (min-width: 768px) {
    .layout { grid-template-columns: 240px 1fr; }
}

/* Flexbox para componente */
.cartao-lista {
    display: flex;
    flex-wrap: wrap;
    gap: var(--espacamento);
}
.cartao-lista__item {
    flex: 1 1 280px; /* cresce, encolhe, base 280px */
}
```

## Formulários

- `type` correto: `email`, `tel`, `number`, `date`, `password`, `search`
- `autocomplete` em campos comuns: `name`, `email`, `current-password`, `new-password`
- `novalidate` no `<form>` quando usando validação customizada JS
- Mensagens de erro próximas ao campo, associadas via `aria-describedby`
- Estado de erro visível: borda colorida + ícone + texto (nunca só cor)

## Performance

- CSS crítico inline (above-the-fold); resto com `<link rel="stylesheet">`
- Evitar `@import` em CSS (bloqueia renderização)
- Imagens: `loading="lazy"` para imagens abaixo da dobra; `width` e `height` explícitos
- Fontes: `font-display: swap`; subconjunto de caracteres quando possível
- Preferir SVG para ícones (escalável, sem requisição extra)

## Nunca fazer

- `style` inline para estilização (apenas para valores dinâmicos via JS)
- Classes sem significado semântico (`.vermelho`, `.grande`, `.col-left`)
- `!important` exceto para overrides de bibliotecas externas
- `<table>` para layout (apenas para dados tabulares)
- `<br>` para espaçamento (usar margin/padding)
- Remover `outline` sem substituto visível de foco
