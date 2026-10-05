/**
 * Helpers shared by the superadmin page modules (resources/js/superadmin/pages/*.js).
 */
import { dfApi } from '../shared';

export { dfApi, escapeHtml, money, statusPill } from '../shared';
export { openSuperadminConfirm } from '../modal';
export { renderSubmitLoading } from '../../shared/submit-loading';

/**
 * Show a message in the page-level #notice box (success messages are also shown as a toast by shell.js).
 *
 * @param {string} message Already translated text.
 * @param {'success'|'error'} [type='success'] Visual tone.
 */
export function pageNotice(message, type = 'success') {
    const notice = document.querySelector('#notice');
    if (!notice) return;
    notice.textContent = message;
    notice.className = `sa-notice ${type} is-visible`;
}

/** Reload the page shortly after an action, leaving time for its success message to be seen. */
export function reloadSoon(delay = 500) {
    window.setTimeout(() => window.location.reload(), delay);
}

/**
 * Restore a submit button that the shared submit-loading handler (loading.js) swapped for a spinner, when the form stays
 * open (e.g. validation error).
 *
 * @param {HTMLButtonElement|null} button Submit button.
 */
export function resetSubmitButton(button) {
    if (!button) return;
    button.disabled = false;
    button.classList.remove('opacity-75', 'cursor-wait');
    if (button.dataset.originalHtml) button.innerHTML = button.dataset.originalHtml;
}

/**
 * Replace `container`'s content with the empty state from a <template>, optionally overriding its description.
 *
 * @param {Element} container Element to fill.
 * @param {string} templateId Id of the <template> holding a components/superadmin/empty-state.
 * @param {string|null} [description=null] Replacement description text.
 */
export function renderEmpty(container, templateId, description = null) {
    container.innerHTML = emptyStateHtml(templateId, description);
}

/**
 * HTML of the empty state stored in a <template>, optionally with another description.
 *
 * @param {string} templateId Id of the <template>.
 * @param {string|null} [description=null] Replacement description text.
 * @returns {string}
 */
export function emptyStateHtml(templateId, description = null) {
    const wrapper = document.createElement('div');
    wrapper.innerHTML = document.getElementById(templateId)?.innerHTML || '';
    if (description !== null) {
        const target = wrapper.querySelector('[data-empty-description]');
        if (target) target.textContent = description;
    }
    return wrapper.innerHTML;
}

/** Replace `:name`-style placeholders in a translated string. */
export function trans(text, replacements = {}) {
    return Object.entries(replacements).reduce((result, [key, value]) => result.replaceAll(`:${key}`, String(value)), String(text ?? ''));
}

/** Load every page of a paginated JSON index (`{ data: { data: [], next_page_url } }`). */
export async function fetchAllPages(url) {
    const items = [];
    let nextPageUrl = url;
    while (nextPageUrl) {
        const { data: page } = await dfApi(nextPageUrl);
        items.push(...page.data);
        nextPageUrl = page.next_page_url;
    }
    return items;
}
