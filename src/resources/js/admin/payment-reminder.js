/**
 * Bottom payment reminder of the admin layout ([data-payment-reminder]): collapse it to a pill and back.
 *
 * The choice is kept for the browser tab (sessionStorage) and tied to the reminder key (invoice, count,
 * amount), so a new or changed invoice shows the full banner again. All texts are rendered by Blade.
 */
const STORAGE_KEY = 'df_admin_payment_reminder_collapsed';

const readCollapsedKey = () => {
    try {
        return window.sessionStorage.getItem(STORAGE_KEY);
    } catch {
        return null;
    }
};

const writeCollapsedKey = (value) => {
    try {
        if (value) {
            window.sessionStorage.setItem(STORAGE_KEY, value);
        } else {
            window.sessionStorage.removeItem(STORAGE_KEY);
        }
    } catch {
        // Storage unavailable (private mode): the banner simply opens expanded on the next page.
    }
};

export function initAdminPaymentReminder() {
    const reminder = document.querySelector('[data-payment-reminder]');
    if (!reminder || reminder.dataset.bound) return;
    reminder.dataset.bound = 'true';

    const key = reminder.dataset.reminderKey || '';
    const setCollapsed = (collapsed) => {
        reminder.dataset.collapsed = collapsed ? 'true' : 'false';
        writeCollapsedKey(collapsed ? key : null);
    };

    if (key !== '' && readCollapsedKey() === key) {
        reminder.dataset.collapsed = 'true';
    }

    reminder.querySelectorAll('[data-payment-reminder-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const collapse = reminder.dataset.collapsed !== 'true';
            setCollapsed(collapse);
            // Keep keyboard focus on the control that is now visible.
            const next = [...reminder.querySelectorAll('[data-payment-reminder-toggle]')].find((el) => el !== button);
            next?.focus();
        });
    });
}
