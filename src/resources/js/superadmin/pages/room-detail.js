/**
 * Room profile (superadmin/room-detail.blade.php): KPIs, assigned admins, edit modal, enable / disable and delete.
 */
import {
    dfApi, emptyStateHtml, escapeHtml, fetchAllPages, openSuperadminConfirm, pageNotice, renderSubmitLoading, resetSubmitButton,
    statusPill,
} from './common';

export default function initRoomDetailPage(_root, i18n, urls) {
    const roomId = urls.room_id;
    const emptyRow = (html) => `<tr><td colspan="2">${html}</td></tr>`;

    const loadAdminOptions = async (selectedIds) => {
        const container = document.querySelector('#edit-room-admin-options');
        if (!container) return;
        try {
            const admins = await fetchAllPages(urls.admins);
            const selected = new Set(selectedIds.map(Number));
            container.innerHTML = admins.map((admin) => `
                <label class="flex items-center gap-2 px-1 py-1 text-[13px] text-on-surface cursor-pointer">
                    <input type="checkbox" name="admin_ids[]" value="${admin.id}" ${selected.has(Number(admin.id)) ? 'checked' : ''}>
                    <span>${escapeHtml(admin.name)} <span class="text-outline">(${escapeHtml(admin.email)})</span></span>
                </label>
            `).join('') || `<p class="sa-empty">${escapeHtml(i18n.no_admin_options)}</p>`;
        } catch (error) {
            container.innerHTML = `<p class="sa-empty">${escapeHtml(error.message)}</p>`;
        }
    };

    const populateEditForm = (room) => {
        const form = document.querySelector('#edit-room-form');
        if (!form) return;
        form.name.value = room.name;
        form.slug.value = room.slug;
        form.status.value = room.status;
        loadAdminOptions((room.admins || []).map((admin) => admin.id));
    };

    const updateStatus = async (status) => {
        const statusBtn = document.querySelector('#room-status-action');
        if (statusBtn) {
            statusBtn.dataset.originalHtml = statusBtn.dataset.originalHtml || statusBtn.innerHTML;
            statusBtn.disabled = true;
            statusBtn.classList.add('opacity-75', 'cursor-wait');
            renderSubmitLoading(statusBtn);
        }
        try {
            await dfApi(`/superadmin/rooms/${roomId}/status`, { method: 'PATCH', body: { status } });
            pageNotice(i18n.status_updated);
            await load();
        } catch (error) {
            pageNotice(error.message, 'error');
            resetSubmitButton(statusBtn);
        }
    };

    async function load() {
        const { data: room } = await dfApi(`/superadmin/rooms/${roomId}`);
        document.querySelector('#room-name').textContent = room.name;
        document.querySelector('#room-meta').innerHTML = `<span class="font-mono">${escapeHtml(room.slug)}</span> · ${statusPill(room.status)}`;
        document.querySelector('#room-members').textContent = room.room_users_count ?? 0;
        document.querySelector('#room-active-members').textContent = (room.room_users || []).filter((user) => user.status === 'active').length;
        document.querySelector('#room-campaigns').textContent = room.campaigns_count ?? (room.campaigns || []).length;
        document.querySelector('#room-admins').textContent = room.admins_count ?? (room.admins || []).length;
        document.querySelector('#room-admin-list').innerHTML = (room.admins || []).map((admin) => `
            <tr>
                <td class="col-primary">
                    <div class="flex items-center gap-3">
                        <span class="superadmin-avatar shrink-0">${escapeHtml(admin.name.slice(0, 1).toUpperCase())}</span>
                        <span class="min-w-0">
                            <strong class="block truncate">${escapeHtml(admin.name)}</strong>
                            <small class="block truncate text-outline">${escapeHtml(admin.email)}</small>
                        </span>
                    </div>
                </td>
                <td class="col-status">${statusPill(admin.status)}</td>
            </tr>
        `).join('') || emptyRow(emptyStateHtml('tpl-no-admins'));
        const statusBtn = document.querySelector('#room-status-action');
        const willEnable = room.status !== 'active';
        statusBtn.innerHTML = `<span class="material-symbols-outlined text-[16px]">${willEnable ? 'toggle_on' : 'toggle_off'}</span>${escapeHtml(willEnable ? i18n.enable_room : i18n.disable_room)}`;
        statusBtn.disabled = false;
        statusBtn.classList.remove('opacity-75', 'cursor-wait');
        delete statusBtn.dataset.originalHtml;
        statusBtn.onclick = () => updateStatus(willEnable ? 'active' : 'inactive');
        populateEditForm(room);
    }

    document.querySelector('#edit-room-form')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.target;
        const submitButton = form.querySelector('button[type="submit"]');
        const errorBox = document.querySelector('#edit-room-modal-error');
        errorBox?.classList.add('hidden');
        const adminIds = [...form.querySelectorAll('input[name="admin_ids[]"]:checked')].map((input) => Number(input.value));
        try {
            await dfApi(`/superadmin/rooms/${roomId}`, {
                method: 'PATCH',
                body: { name: form.name.value, slug: form.slug.value, status: form.status.value, admin_ids: adminIds },
            });
            pageNotice(i18n.updated);
            window.closeSuperadminModal('edit-room-modal');
            await load();
        } catch (error) {
            if (errorBox) {
                errorBox.textContent = error.message;
                errorBox.classList.remove('hidden');
            } else {
                pageNotice(error.message, 'error');
            }
        } finally {
            resetSubmitButton(submitButton);
        }
    });

    document.querySelector('[data-action="delete-room-profile"]')?.addEventListener('click', () => {
        openSuperadminConfirm({
            message: i18n.confirm_delete,
            description: i18n.delete_description,
            tone: 'danger',
            irreversible: true,
            onConfirm: async () => {
                await dfApi(`/superadmin/rooms/${roomId}`, { method: 'DELETE' });
                pageNotice(i18n.deleted);
                window.setTimeout(() => { window.location.href = urls.list; }, 500);
            },
        });
    });

    load().catch((error) => {
        pageNotice(error.message, 'error');
        document.querySelector('#room-admin-list').innerHTML = emptyRow(emptyStateHtml('tpl-load-failed', error.message));
    });
}
