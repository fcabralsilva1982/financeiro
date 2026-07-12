/**
 * FinanceiroApp — Custom JavaScript
 * @author FinanceiroApp Team
 */

// --- Global error handler para rejeições de Promise não tratadas ---
window.addEventListener('unhandledrejection', (event) => {
    console.error('[FinanceiroApp] Unhandled promise rejection:', event.reason);
});

function hidePageLoader() {
    const pageLoader = document.getElementById('pageLoader');
    if (!pageLoader) {
        return;
    }

    pageLoader.classList.add('is-hidden');
    document.body.classList.remove('page-loading');
}

function showPageLoader() {
    const pageLoader = document.getElementById('pageLoader');
    if (!pageLoader) {
        return;
    }

    pageLoader.classList.remove('is-hidden');
    document.body.classList.add('page-loading');
}

document.addEventListener('DOMContentLoaded', () => {
    hidePageLoader();

    function getActionPayload(rawUrl) {
        if (!rawUrl) {
            return null;
        }

        const parsed = new URL(rawUrl, window.location.origin);
        return {
            page: parsed.searchParams.get('page') || '',
            action: parsed.searchParams.get('action') || '',
            id: parsed.searchParams.get('id') || '',
            vinculoId: parsed.searchParams.get('vinculo_id') || '',
        };
    }

    // Exibe loader em submissões de formulário
    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', () => {
            if (form.hasAttribute('data-no-loader')) {
                return;
            }
            showPageLoader();
        });
    });

    // Exibe loader em navegações por link
    document.querySelectorAll('a[href]').forEach((link) => {
        link.addEventListener('click', (event) => {
            if (event.defaultPrevented || event.button !== 0) {
                return;
            }
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }

            const href = (link.getAttribute('href') || '').trim();
            if (href === '' || href.startsWith('#') || href.toLowerCase().startsWith('javascript:')) {
                return;
            }

            if (link.hasAttribute('download')) {
                return;
            }

            const target = (link.getAttribute('target') || '').toLowerCase();
            if (target === '_blank') {
                return;
            }

            if (link.hasAttribute('data-bs-toggle') || link.hasAttribute('data-no-loader')) {
                return;
            }

            showPageLoader();
        });
    });

    // =========================================================
    // MÁSCARAS DE TELEFONE
    // Formata automaticamente conforme o usuário digita
    // data-mask="phone"  => +55 11 9090-9090  (fixo: 8 dígitos)
    // data-mask="mobile" => +55 11 99999-9999 (celular: 9 dígitos)
    // =========================================================

    /**
     * Aplica máscara de telefone a um valor baseado apenas nos dígitos digitados.
     * Constrói progressivamente: +XX XX XXXXX-XXXX ou +XX XX XXXX-XXXX
     * @param {string} digits - Apenas os dígitos do número
     * @param {string} type - Tipo da máscara: 'phone' ou 'mobile'
     * @returns {string} Valor formatado com a máscara aplicada
     */
    function applyPhoneMask(digits, type) {
        const d = digits;
        let result = '';

        // +XX
        if (d.length > 0) {
            result = '+' + d.substring(0, Math.min(d.length, 2));
        }
        // Espaço + DD
        if (d.length > 2) {
            result += ' ' + d.substring(2, Math.min(d.length, 4));
        }
        // Espaço + número local
        if (d.length > 4) {
            if (type === 'phone') {
                // Fixo: XXXX-XXXX (8 dígitos)
                const local = d.substring(4, Math.min(d.length, 12));
                if (local.length <= 4) {
                    result += ' ' + local;
                } else {
                    result += ' ' + local.substring(0, 4) + '-' + local.substring(4);
                }
            } else {
                // Celular: XXXXX-XXXX (9 dígitos)
                const local = d.substring(4, Math.min(d.length, 13));
                if (local.length <= 5) {
                    result += ' ' + local;
                } else {
                    result += ' ' + local.substring(0, 5) + '-' + local.substring(5);
                }
            }
        }

        return result;
    }

    /**
     * Extrai apenas dígitos de uma string.
     * @param {string} str - String de entrada
     * @returns {string} Apenas os dígitos
     */
    function onlyDigits(str) {
        return str.replace(/\D/g, '');
    }

    /**
     * Handler de input para campos com máscara de telefone.
     * @param {InputEvent} e - Evento de input
     */
    function handlePhoneMask(e) {
        const input = e.target;
        const type = input.getAttribute('data-mask');
        const maxDigits = (type === 'phone') ? 12 : 13;

        const digits = onlyDigits(input.value).substring(0, maxDigits);
        const formatted = applyPhoneMask(digits, type);

        // Preservar posição do cursor
        const cursorPos = input.selectionStart;
        const prevLen = input.value.length;

        input.value = formatted;

        // Ajustar cursor
        const diff = formatted.length - prevLen;
        let newPos = cursorPos + diff;
        if (newPos < 0) newPos = 0;
        if (newPos > formatted.length) newPos = formatted.length;
        input.setSelectionRange(newPos, newPos);
    }

    /**
     * Inicializa máscaras em todos os [data-mask] dentro de um container.
     * @param {HTMLElement|Document} container - Elemento pai para buscar inputs
     */
    function initPhoneMasks(container) {
        const inputs = container.querySelectorAll('[data-mask="phone"], [data-mask="mobile"]');
        inputs.forEach((input) => {
            if (input._maskInit) return;
            input._maskInit = true;
            input.addEventListener('input', handlePhoneMask);
            // Formatar valor inicial se existir
            if (input.value) {
                const type = input.getAttribute('data-mask');
                const maxDigits = (type === 'phone') ? 12 : 13;
                const digits = onlyDigits(input.value).substring(0, maxDigits);
                if (digits.length > 0) {
                    input.value = applyPhoneMask(digits, type);
                }
            }
        });
    }

    // Inicializar todas as máscaras na página
    initPhoneMasks(document);

    // Observer para aplicar máscara em contatos adicionados dinamicamente
    const contatosContainer = document.getElementById('contatos-container');
    if (contatosContainer) {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((m) => {
                m.addedNodes.forEach((node) => {
                    if (node.nodeType === 1) {
                        initPhoneMasks(node);
                    }
                });
            });
        });
        observer.observe(contatosContainer, { childList: true, subtree: true });
    }

    // --- Sidebar Toggle (mobile) ---
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('show');
            if (overlay) overlay.classList.toggle('show');
        });
    }

    if (overlay) {
        overlay.addEventListener('click', () => {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
        });
    }

    // --- Modal de Exclusão ---
    const modalExcluir = document.getElementById('modalExcluir');
    if (modalExcluir) {
        modalExcluir.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const url = button.getAttribute('data-url');
            const payload = getActionPayload(url);

            const pageEl = document.getElementById('excluirPage');
            const actionEl = document.getElementById('excluirAction');
            const idEl = document.getElementById('excluirId');
            const vinculoEl = document.getElementById('excluirVinculoId');

            if (payload && pageEl && actionEl && idEl && vinculoEl) {
                pageEl.value = payload.page;
                actionEl.value = payload.action;
                idEl.value = payload.id;
                vinculoEl.value = payload.vinculoId;
            }
        });
    }

    // --- Modal de Bloqueio/Desbloqueio ---
    const modalBloquear = document.getElementById('modalBloquear');
    if (modalBloquear) {
        modalBloquear.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const url = button.getAttribute('data-url');
            const title = button.getAttribute('data-title') || '';
            const msg = button.getAttribute('data-msg') || '';
            const payload = getActionPayload(url);

            const titleEl = document.getElementById('modalBloquearTitle');
            const msgEl = document.getElementById('modalBloquearMsg');
            const pageEl = document.getElementById('bloquearPage');
            const actionEl = document.getElementById('bloquearAction');
            const idEl = document.getElementById('bloquearId');
            const vinculoEl = document.getElementById('bloquearVinculoId');

            if (payload && pageEl && actionEl && idEl && vinculoEl) {
                pageEl.value = payload.page;
                actionEl.value = payload.action;
                idEl.value = payload.id;
                vinculoEl.value = payload.vinculoId;
            }
            if (titleEl && title) titleEl.textContent = title;
            if (msgEl && msg) msgEl.textContent = msg;
        });
    }

    // --- Tooltips ---
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipTriggerList.forEach((el) => {
        new bootstrap.Tooltip(el);
    });

});

window.addEventListener('load', hidePageLoader);
window.addEventListener('beforeunload', showPageLoader);
