/**
 * Rooms list (superadmin/rooms.blade.php): create-room modal (with admin assignment) and delete from the row ⋯ menu.
 */
import { dfApi, escapeHtml, fetchAllPages, openSuperadminConfirm, pageNotice, reloadSoon, resetSubmitButton } from './common';

export default function initRoomsPage(_root, i18n, urls) {
    const loadAdminOptions = async () => {
        const container = document.querySelector('#create-room-admin-options');
        if (!container) return;
        try {
            const admins = await fetchAllPages(urls.admins);
            container.innerHTML = admins.map((admin) => `
                <label class="flex items-center gap-2 px-1 py-1 text-[13px] text-on-surface cursor-pointer">
                    <input type="checkbox" name="admin_ids[]" value="${admin.id}">
                    <span>${escapeHtml(admin.name)} <span class="text-outline">(${escapeHtml(admin.email)})</span></span>
                </label>
            `).join('') || `<p class="sa-empty">${escapeHtml(i18n.no_admin_options)}</p>`;
        } catch (error) {
            container.innerHTML = `<p class="sa-empty">${escapeHtml(error.message)}</p>`;
        }
    };
    loadAdminOptions();

    document.querySelector('#create-room-form')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.target;
        const errorBox = document.querySelector('#create-room-modal-error');
        errorBox?.classList.add('hidden');
        const adminIds = [...form.querySelectorAll('input[name="admin_ids[]"]:checked')].map((input) => Number(input.value));
        try {
            await dfApi(urls.store, {
                method: 'POST',
                body: { name: form.name.value, slug: form.slug.value, status: form.status.value, admin_ids: adminIds },
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
        const deleteBtn = event.target.closest('[data-action="delete-room"]');
        if (!deleteBtn) return;
        openSuperadminConfirm({
            message: i18n.confirm_delete,
            description: i18n.delete_description,
            tone: 'danger',
            irreversible: true,
            onConfirm: async () => {
                await dfApi(`/superadmin/rooms/${deleteBtn.dataset.roomId}`, { method: 'DELETE' });
                pageNotice(i18n.deleted);
                reloadSoon();
            },
        });
    });
}
