/**
 * Masked personal values of the Superadmin console (components/superadmin/sensitive.blade.php and the
 * `email_reveal` / `phone_reveal` tokens of the JSON APIs). The eye button asks the server for the real
 * value of its token (POST /superadmin/sensitive/reveal, audited) and shows it in full; pressing it again
 * hides it. Texts come from the [data-sensitive-i18n] JSON on <body>.
 */
import { dfApi, escapeHtml } from './shared';

const i18n = () => {
    try {
        return JSON.parse(document.body?.dataset.sensitiveI18n || '{}');
    } catch {
        return {};
    }
};

/**
 * HTML of a masked value with its reveal button, for page scripts rendering API data.
 *
 * @param {string|null|undefined} masked Masked text from the API.
 * @param {string|null|undefined} token Matching `*_reveal` token.
 * @param {string} [empty] Text when there is no value.
 * @returns {string} HTML.
 */
export function sensitiveHtml(masked, token, empty = '—') {
    if (!masked) return escapeHtml(empty);
    if (!token) return escapeHtml(masked);
    const label = escapeHtml(i18n().reveal || '');

    return `<span class="sa-sensitive" data-sensitive><span data-sensitive-text>${escapeHtml(masked)}</span>`
        + `<button type="button" class="sa-sensitive-toggle" data-sensitive-reveal="${escapeHtml(token)}" title="${label}" aria-label="${label}">`
        + '<span class="material-symbols-outlined" aria-hidden="true">visibility</span></button></span>';
}

export function initSuperadminSensitive() {
    const url = document.body?.dataset.sensitiveRevealUrl;
    if (!url || document.body.dataset.sensitiveBound) return;
    document.body.dataset.sensitiveBound = '1';

    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-sensitive-reveal]');
        if (!button || button.disabled) return;
        event.preventDefault();
        event.stopPropagation();
        const wrapper = button.closest('[data-sensitive]');
        const text = wrapper?.querySelector('[data-sensitive-text]');
        const icon = button.querySelector('.material-symbols-outlined');
        if (!text) return;
        const texts = i18n();

        // Already revealed: hide it again (no request).
        if (wrapper.dataset.revealed === '1') {
            text.textContent = wrapper.dataset.masked;
            wrapper.dataset.revealed = '0';
            if (icon) icon.textContent = 'visibility';
            button.title = texts.reveal || '';
            button.setAttribute('aria-label', button.title);
            return;
        }

        button.disabled = true;
        try {
            const { data } = await dfApi(url, { method: 'POST', body: { token: button.dataset.sensitiveReveal } });
            wrapper.dataset.masked = wrapper.dataset.masked || text.textContent;
            text.textContent = data.value;
            wrapper.dataset.revealed = '1';
            if (icon) icon.textContent = 'visibility_off';
            button.title = texts.hide || '';
            button.setAttribute('aria-label', button.title);
        } catch (error) {
            if (typeof window.notify === 'function') window.notify(error.message || texts.failed || '', 'error');
        } finally {
            button.disabled = false;
        }
    }, true);
}
