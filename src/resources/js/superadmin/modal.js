import { renderSubmitLoading } from '../shared/submit-loading';

/**
 * Show or mask the password input a [data-password-toggle] button points to.
 *
 * @param {HTMLElement} button Toggle button (see components/superadmin/password-input.blade.php).
 * @param {boolean} visible Whether the password should be shown as plain text.
 */
const setPasswordVisible = (button, visible) => {
    const input = document.getElementById(button.dataset.passwordToggle);
    if (!input) return;
    input.type = visible ? 'text' : 'password';
    button.setAttribute('aria-pressed', visible ? 'true' : 'false');
    const icon = button.querySelector('.material-symbols-outlined');
    if (icon) icon.textContent = visible ? 'visibility_off' : 'visibility';
};

/**
 * Generic superadmin modal open/close controller + a shared confirm() replacement.
 *
 * Any element with [data-modal-open="modal-id"] opens that modal; any element inside a modal
 * with [data-modal-close] (including the backdrop) closes it, as does the Escape key.
 */
export function initSuperadminModals() {
    const openModal = (modal) => {
        if (modal.classList.contains('hidden')) modal.saReturnFocus = document.activeElement;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.classList.add('sa-no-scroll');
        modal.querySelector('.sa-modal-body input:not([type="hidden"]), .sa-modal-body textarea, .sa-modal-body select, button[type="submit"]')?.focus();
    };

    const closeModal = (modal) => {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        if (!document.querySelector('[data-modal]:not(.hidden)')) document.body.classList.remove('sa-no-scroll');
        // Give focus back to the control that opened the dialog.
        modal.saReturnFocus?.focus?.();
        modal.saReturnFocus = null;
        // Never leave a typed password visible for the next time the modal opens.
        modal.querySelectorAll('[data-password-toggle]').forEach((button) => setPasswordVisible(button, false));
    };

    document.addEventListener('click', (event) => {
        const passwordToggle = event.target.closest('[data-password-toggle]');
        if (passwordToggle) {
            setPasswordVisible(passwordToggle, passwordToggle.getAttribute('aria-pressed') !== 'true');
            return;
        }

        const opener = event.target.closest('[data-modal-open]');
        if (opener) {
            const modal = document.getElementById(opener.dataset.modalOpen);
            if (modal) openModal(modal);
            return;
        }

        const closer = event.target.closest('[data-modal-close]');
        if (closer) {
            const modal = closer.closest('[data-modal]');
            if (modal) closeModal(modal);
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('[data-modal]:not(.hidden)').forEach((modal) => closeModal(modal));
    });

    // Server-rendered forms with data-confirm="message" (and optional data-confirm-tone, data-confirm-title,
    // data-confirm-description, data-confirm-label, data-confirm-icon, data-confirm-irreversible) ask through the
    // shared confirm modal before submitting.
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed === '1') return;
        event.preventDefault();
        event.stopImmediatePropagation();
        openSuperadminConfirm({
            message: form.dataset.confirm,
            tone: form.dataset.confirmTone,
            title: form.dataset.confirmTitle,
            description: form.dataset.confirmDescription,
            confirmLabel: form.dataset.confirmLabel,
            confirmIcon: form.dataset.confirmIcon,
            irreversible: form.dataset.confirmIrreversible === '1',
            onConfirm: () => {
                form.dataset.confirmed = '1';
                form.requestSubmit();
            },
        });
    }, true);

    window.openSuperadminModal = (id) => {
        const modal = document.getElementById(id);
        if (modal) openModal(modal);
    };
    window.closeSuperadminModal = (id) => {
        const modal = document.getElementById(id);
        if (modal) closeModal(modal);
    };
}

const CONFIRM_BUTTON_CLASS = { danger: 'sa-button danger', warning: 'sa-button warning-solid', info: 'sa-button', success: 'sa-button success' };

/**
 * Show the shared #confirm-modal (see components/superadmin/confirm-modal.blade.php) in place of
 * a native confirm(), and run `onConfirm` when the user confirms.
 *
 * `tone` (danger | warning | info | success, default danger) sets the header icon, default title and button colour;
 * `description` adds a highlighted note explaining the consequences; `irreversible` adds a "cannot be undone" line;
 * `title` / `icon` / `confirmLabel` / `confirmIcon` override the defaults for this call only.
 *
 * @param {{message: string, description?: string, tone?: 'danger'|'warning'|'info'|'success', title?: string, icon?: string,
 *     confirmLabel?: string, confirmIcon?: string, irreversible?: boolean, onConfirm: () => Promise<void>|void}} options
 */
export function openSuperadminConfirm({ message, description, tone, title, icon: headerIcon, confirmLabel, confirmIcon, irreversible = false, onConfirm }) {
    const modal = document.getElementById('confirm-modal');
    if (!modal) return;

    let tones = {};
    try {
        tones = JSON.parse(modal.querySelector('[data-confirm-tones]')?.dataset.confirmTones || '{}');
    } catch {
        tones = {};
    }
    const activeTone = tones[tone] ? tone : 'danger';
    const toneInfo = tones[activeTone] || {};
    modal.dataset.modalTone = activeTone;
    const iconTile = modal.querySelector('[data-modal-icon]');
    if (iconTile) {
        iconTile.className = `sa-modal-icon is-${activeTone}`;
        iconTile.querySelector('.material-symbols-outlined').textContent = headerIcon || toneInfo.icon || 'help';
    }
    const heading = modal.querySelector('#confirm-modal-title');
    if (heading) heading.textContent = title || toneInfo.title || heading.textContent;

    modal.querySelector('#confirm-modal-message').textContent = message;
    const descriptionBox = modal.querySelector('#confirm-modal-description');
    if (descriptionBox) {
        descriptionBox.querySelector('#confirm-modal-description-text').textContent = description || '';
        descriptionBox.classList.toggle('hidden', !description);
        const alert = descriptionBox.querySelector('[data-confirm-description-box]');
        if (alert) alert.className = `sa-modal-alert is-${activeTone === 'success' ? 'info' : activeTone}`;
        const alertIcon = descriptionBox.querySelector('[data-confirm-description-icon]');
        if (alertIcon) alertIcon.textContent = activeTone === 'danger' ? 'report' : (activeTone === 'warning' ? 'warning' : 'info');
    }
    modal.querySelector('#confirm-modal-irreversible')?.classList.toggle('hidden', !irreversible);
    modal.querySelector('#confirm-modal-error')?.classList.add('hidden');
    const label = modal.querySelector('#confirm-modal-submit-label');
    label.textContent = confirmLabel || toneInfo.label || label.dataset.default;
    const icon = modal.querySelector('#confirm-modal-submit-icon');
    if (icon) icon.textContent = confirmIcon || toneInfo.button_icon || icon.dataset.default;
    modal.querySelector('#confirm-modal-submit').className = CONFIRM_BUTTON_CLASS[activeTone];

    const submitButton = modal.querySelector('#confirm-modal-submit');
    const freshButton = submitButton.cloneNode(true); // drop any previous page's listener
    submitButton.replaceWith(freshButton);

    freshButton.addEventListener('click', async () => {
        const originalHtml = freshButton.innerHTML;
        freshButton.disabled = true;
        freshButton.classList.add('opacity-70', 'cursor-wait');
        renderSubmitLoading(freshButton);
        try {
            await onConfirm();
            window.closeSuperadminModal('confirm-modal');
        } catch (error) {
            const errorBox = modal.querySelector('#confirm-modal-error');
            if (errorBox) {
                errorBox.textContent = error.message || String(error);
                errorBox.classList.remove('hidden');
            }
        } finally {
            freshButton.disabled = false;
            freshButton.classList.remove('opacity-70', 'cursor-wait');
            freshButton.innerHTML = originalHtml;
        }
    });

    window.openSuperadminModal('confirm-modal');
    // Destructive dialogs start on "Cancel" so a stray Enter never confirms them.
    modal.querySelector(activeTone === 'danger' ? '[data-modal-close].sa-button' : '#confirm-modal-submit')?.focus();
}
