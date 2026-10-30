/**
 * Helpers shared by the game pages of the /me portal (global/games.js) and the public play page (game-play.js).
 */
import { renderSubmitLoading } from './submit-loading';

export const GAME_TOAST_KEY = 'drinkflow.games.toast';

/** Parse the data-i18n JSON of an element. */
export const readI18n = (element) => {
    try {
        return JSON.parse(element?.dataset.i18n || '{}');
    } catch {
        return {};
    }
};

export const notify = (message, type = 'success') => {
    if (typeof window.notify === 'function') window.notify(message, type);
};

/** Random UUID v4, also outside secure contexts (plain http on a LAN) where crypto.randomUUID is missing. */
export const uuid = () => {
    if (window.crypto?.randomUUID) return window.crypto.randomUUID();
    const bytes = window.crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = [...bytes].map((byte) => byte.toString(16).padStart(2, '0')).join('');
    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
};

/** Show the shared spinner + "Loading…" label in a button and lock it (prevents a double submit). */
export function startButtonLoading(button) {
    if (!button || button.dataset.loading) return;
    button.dataset.loading = '1';
    button.dataset.originalHtml = button.innerHTML;
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    renderSubmitLoading(button);
}

/** Restore a button put in the loading state by startButtonLoading(). */
export function stopButtonLoading(button) {
    if (!button?.dataset.loading) return;
    button.innerHTML = button.dataset.originalHtml || button.innerHTML;
    delete button.dataset.loading;
    delete button.dataset.originalHtml;
    button.disabled = false;
    button.removeAttribute('aria-busy');
}

/**
 * Forms carrying data-confirm="message" ask for confirmation in the [data-confirm-dialog] dialog first
 * (resources/views/user/global/games/partials/confirm-dialog.blade.php).
 */
export function initConfirmForms() {
    const dialog = document.querySelector('[data-confirm-dialog]');
    if (!dialog || dialog.dataset.bound) return;
    dialog.dataset.bound = '1';
    let pending = null;

    // Capture phase: stop the submit before the global loading overlay (resources/js/global/loading.js) reacts to it.
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        pending = form;
        dialog.querySelector('[data-confirm-message]').textContent = form.dataset.confirm;
        dialog.showModal();
    }, true);
    const ok = dialog.querySelector('[data-confirm-ok]');
    const cancel = dialog.querySelector('[data-confirm-cancel]');
    cancel?.addEventListener('click', () => {
        pending = null;
        dialog.close();
    });
    ok?.addEventListener('click', () => {
        if (!pending) {
            dialog.close();
            return;
        }
        // Keep the dialog open with a spinner on "Confirm" until the page navigates.
        startButtonLoading(ok);
        if (cancel) cancel.disabled = true;
        pending.dataset.confirmed = '1';
        pending.requestSubmit();
    });
    // Back from the browser cache (bfcache): give the buttons back.
    window.addEventListener('pageshow', () => {
        stopButtonLoading(ok);
        if (cancel) cancel.disabled = false;
    });
}

/**
 * POST a result as JSON. Resolves with { ok, data } (ok false for a refusal); rejects on a network error so the
 * caller can keep the same idempotency key for the retry.
 */
export async function postResult(url, payload) {
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const response = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify(payload),
    });
    const data = await response.json().catch(() => ({}));

    return { ok: response.ok, data };
}

/** First validation message of a 422 body, or its message. */
export const firstError = (data) => (data?.errors ? Object.values(data.errors)[0]?.[0] : null) || data?.message || '';

/** Reload the page and show the toast once it is rendered again. */
export function reloadWithToast(message) {
    try {
        sessionStorage.setItem(GAME_TOAST_KEY, message || '');
    } catch {
        // Storage unavailable: the reloaded page simply shows no toast.
    }
    window.location.reload();
}

export function showPendingToast() {
    try {
        const message = sessionStorage.getItem(GAME_TOAST_KEY);
        if (message) {
            sessionStorage.removeItem(GAME_TOAST_KEY);
            notify(message);
        }
    } catch {
        // Storage unavailable.
    }
}
