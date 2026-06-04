---
name: javascript-frontend
description: 'Escrever ou revisar JavaScript vanilla para front-end de sistemas web. Use para: fetch/AJAX seguro, validação de formulários no cliente, manipulação DOM segura, CSRF em requisições JS, tratamento de erros, módulos ES6, eventos, localStorage seguro e boas práticas sem framework.'
argument-hint: 'Descreva o que criar ou revisar (ex: "enviar formulário via fetch com CSRF", "validar CPF no cliente", "carregar lista com paginação")'
---

# JavaScript — Front-end Seguro (Vanilla)

## Princípios

- JS no cliente é **auxiliar** — nunca depender dele para segurança ou autorização
- Toda validação do cliente deve ter equivalente server-side
- Nunca inserir dados do usuário no DOM sem sanitização
- Nunca armazenar tokens de sessão em `localStorage` (usar cookie `HttpOnly`)

## Fetch seguro com CSRF

```js
// Ler token CSRF de meta tag (nunca de cookie via JS em produção)
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

async function postSeguro(url, dados) {
    const resposta = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': csrfToken,
        },
        credentials: 'same-origin', // envia cookies de sessão
        body: JSON.stringify(dados),
    });

    if (!resposta.ok) {
        const erro = await resposta.json().catch(() => ({}));
        throw new Error(erro.mensagem ?? `Erro ${resposta.status}`);
    }

    return resposta.json();
}
```

```html
<!-- No <head> do PHP -->
<meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
```

## Manipulação DOM segura

```js
// ❌ — XSS se `nome` vier de entrada do usuário
elemento.innerHTML = `<span>${nome}</span>`;

// ✅ — seguro
const span = document.createElement('span');
span.textContent = nome;
elemento.appendChild(span);

// ✅ — sanitizar se HTML for necessário (usar biblioteca)
// import DOMPurify from 'dompurify';
// elemento.innerHTML = DOMPurify.sanitize(htmlExterno);
```

## Validação de formulário

```js
// Validação antes de enviar — complemento à validação server-side
document.getElementById('form-login').addEventListener('submit', async (e) => {
    e.preventDefault();
    limparErros();

    const email = document.getElementById('email').value.trim();
    const senha = document.getElementById('senha').value;

    if (!validarEmail(email)) {
        mostrarErro('email', 'E-mail inválido.');
        return;
    }
    if (senha.length < 8) {
        mostrarErro('senha', 'Senha deve ter ao menos 8 caracteres.');
        return;
    }

    try {
        await postSeguro('/login', { email, senha });
        window.location.href = '/dashboard';
    } catch (err) {
        mostrarErro('form', err.message);
    }
});

function validarEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}

function mostrarErro(campo, mensagem) {
    const el = document.getElementById(`${campo}-erro`);
    if (el) { el.textContent = mensagem; el.hidden = false; }
}

function limparErros() {
    document.querySelectorAll('[id$="-erro"]').forEach(el => {
        el.textContent = ''; el.hidden = true;
    });
}
```

## Módulos ES6

```js
// utils/dom.js
export function criarElemento(tag, texto, classes = []) {
    const el = document.createElement(tag);
    el.textContent = texto;
    classes.forEach(c => el.classList.add(c));
    return el;
}

// main.js
import { criarElemento } from './utils/dom.js';
```

```html
<script type="module" src="/public/assets/js/main.js"></script>
```

## Eventos — boas práticas

```js
// Preferir addEventListener; nunca atributos onclick inline
botao.addEventListener('click', handler);

// Delegação de eventos para listas dinâmicas
document.getElementById('lista-usuarios').addEventListener('click', (e) => {
    const botao = e.target.closest('[data-acao="excluir"]');
    if (!botao) return;
    const id = botao.dataset.id;
    confirmarExclusao(id);
});

// Remover listener quando não for mais necessário
const controller = new AbortController();
elemento.addEventListener('click', handler, { signal: controller.signal });
// Para remover: controller.abort();
```

## Feedback de UX em requisições

```js
async function enviarComFeedback(botao, fn) {
    botao.disabled = true;
    botao.setAttribute('aria-busy', 'true');
    const textoOriginal = botao.textContent;
    botao.textContent = 'Aguarde...';

    try {
        await fn();
    } finally {
        botao.disabled = false;
        botao.removeAttribute('aria-busy');
        botao.textContent = textoOriginal;
    }
}
```

## Armazenamento no cliente

| Dado | Onde guardar |
|------|-------------|
| Token de sessão | Cookie `HttpOnly; Secure` (nunca JS) |
| Preferências de UI (tema, idioma) | `localStorage` |
| Estado de formulário temporário | `sessionStorage` |
| Dados sensíveis | Nunca no cliente |

## Nunca fazer

- `eval()`, `new Function(string)`, `setTimeout(string)`
- `innerHTML` com dados não sanitizados
- Lógica de autorização ou validação de segurança apenas no cliente
- `document.write()`
- Tokens de sessão em `localStorage`
- Ignorar erros de `fetch` sem tratamento (`await fetch(...)` sem try/catch)
- Expor chaves de API no JS do front-end
