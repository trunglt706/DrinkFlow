/**
 * Notification channels (superadmin/notifications.blade.php): create a channel and delete one from the row ⋯ menu.
 * The inbox section is handled by resources/js/superadmin/notifications.js.
 */
import { dfApi, openSuperadminConfirm, pageNotice, reloadSoon, resetSubmitButton } from './common';

export default function initNotificationsPage(_root, i18n, urls) {
    const form = document.querySelector('#create-channel-form');
    const typeSelect = form?.querySelector('[data-channel-type]');

    // Show only the credential fields of the selected channel type; hidden groups are disabled so they are neither
    // validated by the browser nor sent.
    const syncChannelFields = () => {
        form.querySelectorAll('[data-channel-fields]').forEach((group) => {
            const active = group.dataset.channelFields === typeSelect.value;
            group.hidden = !active;
            group.querySelectorAll('input').forEach((input) => {
                input.disabled = !active;
                input.required = active && input.dataset.channelRequired === '1';
            });
        });
    };
    if (form && typeSelect) {
        typeSelect.addEventListener('change', syncChannelFields);
        syncChannelFields();
    }

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const errorBox = document.querySelector('#create-channel-modal-error');
        errorBox?.classList.add('hidden');
        const config = {};
        form.querySelectorAll('[data-channel-fields]:not([hidden]) input[name^="config["]').forEach((input) => {
            const value = input.value.trim();
            if (value !== '') config[input.name.slice(7, -1)] = value;
        });
        try {
            await dfApi(urls.store, {
                method: 'POST',
                body: { name: form.name.value, type: typeSelect.value, config, status: 'active' },
            });
            window.location.reload();
        } catch (error) {
            if (errorBox) {
                errorBox.textContent = error.message;
                errorBox.classList.remove('hidden');
            }
            resetSubmitButton(form.querySelector('button[type="submit"]'));
        }
    });

    document.addEventListener('click', (event) => {
        const deleteBtn = event.target.closest('[data-action="delete-channel"]');
        if (!deleteBtn) return;
        openSuperadminConfirm({
            message: i18n.confirm_delete,
            description: i18n.delete_description,
            tone: 'danger',
            irreversible: true,
            onConfirm: async () => {
                await dfApi(`/superadmin/notifications/${deleteBtn.dataset.channelId}`, { method: 'DELETE' });
                pageNotice(i18n.deleted);
                reloadSoon();
            },
        });
    });
}
