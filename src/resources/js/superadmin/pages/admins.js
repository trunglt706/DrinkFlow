/**
 * Room admins list (superadmin/admins.blade.php): create-admin modal (with room assignment), block and delete from the
 * row ⋯ menu.
 */
import { dfApi, escapeHtml, fetchAllPages, openSuperadminConfirm, pageNotice, reloadSoon, resetSubmitButton, trans } from './common';

export default function initAdminsPage(_root, i18n, urls) {
    const loadRoomOptions = async () => {
        const container = document.querySelector('#create-admin-room-options');
        if (!container) return;
        try {
            const rooms = await fetchAllPages(urls.rooms);
            if (rooms.length === 0) {
                container.innerHTML = document.getElementById('tpl-no-rooms').innerHTML;
                return;
            }
            container.innerHTML = rooms.map((room) => `
                <label class="flex items-center gap-2 px-1 py-1 text-[13px] text-on-surface cursor-pointer">
                    <input type="checkbox" name="room_ids[]" value="${room.id}">
                    <span>${escapeHtml(room.name)} <span class="text-outline">(${escapeHtml(room.slug)})</span></span>
                </label>
            `).join('');
        } catch (error) {
            container.innerHTML = document.getElementById('tpl-load-failed').innerHTML;
            container.querySelector('[data-empty-description]').textContent = error.message;
        }
    };
    loadRoomOptions();

    // Let the browser block a mismatched confirmation before submit (and before the loading spinner).
    const form = document.querySelector('#create-admin-form');
    if (form) {
        const checkPasswordConfirmation = () => form.password_confirmation.setCustomValidity(
            form.password.value === form.password_confirmation.value ? '' : i18n.password_mismatch);
        form.password.addEventListener('input', checkPasswordConfirmation);
        form.password_confirmation.addEventListener('input', checkPasswordConfirmation);

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const errorBox = document.querySelector('#create-admin-modal-error');
            errorBox?.classList.add('hidden');
            const roomIds = [...form.querySelectorAll('input[name="room_ids[]"]:checked')].map((input) => Number(input.value));
            try {
                await dfApi(urls.store, {
                    method: 'POST',
                    body: { name: form.name.value, email: form.email.value, password: form.password.value, room_ids: roomIds },
                });
                window.location.reload();
            } catch (error) {
                errorBox.textContent = error.message;
                errorBox.classList.remove('hidden');
                resetSubmitButton(form.querySelector('button[type="submit"]'));
            }
        });
    }

    document.addEventListener('click', (event) => {
        const deleteBtn = event.target.closest('[data-action="delete-admin"]');
        if (deleteBtn) {
            openSuperadminConfirm({
                message: trans(i18n.confirm_delete, { name: deleteBtn.dataset.adminName }),
                description: i18n.delete_description,
                tone: 'danger',
                irreversible: true,
                confirmLabel: i18n.delete_account,
                onConfirm: async () => {
                    await dfApi(`/superadmin/admins/${deleteBtn.dataset.adminId}`, { method: 'DELETE' });
                    pageNotice(i18n.deleted);
                    reloadSoon();
                },
            });
            return;
        }

        const blockBtn = event.target.closest('[data-action="block-admin"]');
        if (!blockBtn) return;
        openSuperadminConfirm({
            message: trans(i18n.confirm_block, { name: blockBtn.dataset.adminName }),
            description: i18n.block_description,
            tone: 'warning',
            confirmIcon: 'lock',
            confirmLabel: i18n.block_account,
            onConfirm: async () => {
                await dfApi(`/superadmin/admins/${blockBtn.dataset.adminId}/status`, { method: 'PATCH', body: { status: 'suspended' } });
                pageNotice(i18n.updated);
                reloadSoon();
            },
        });
    });
}
