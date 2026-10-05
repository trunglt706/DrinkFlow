/**
 * Room admin profile (superadmin/admin-detail.blade.php): account summary, room assignment, password reset,
 * block / unblock and delete.
 */
import {
    dfApi, escapeHtml, fetchAllPages, openSuperadminConfirm, pageNotice, renderEmpty, renderSubmitLoading, resetSubmitButton,
    statusPill, trans,
} from './common';

export default function initAdminDetailPage(_root, i18n, urls) {
    const adminId = urls.admin_id;
    let currentAdmin = null;
    const showModalError = (modalId, message) => {
        const errorBox = document.querySelector(`#${modalId}-error`);
        errorBox.textContent = message;
        errorBox.classList.remove('hidden');
    };
    const hideModalError = (modalId) => document.querySelector(`#${modalId}-error`)?.classList.add('hidden');
    const dateTimeFormat = new Intl.DateTimeFormat(document.documentElement.lang || undefined, { dateStyle: 'medium', timeStyle: 'short' });
    const formatDateTime = (value, fallback) => (value ? dateTimeFormat.format(new Date(value)) : fallback);
    const detailRow = (label, valueHtml) => `<div class="sa-detail-row"><span>${escapeHtml(label)}</span><strong>${valueHtml}</strong></div>`;

    const updateStatus = async (status, rethrow = false) => {
        try {
            await dfApi(`/superadmin/admins/${adminId}/status`, { method: 'PATCH', body: { status } });
            pageNotice(i18n.updated);
            await load();
        } catch (error) {
            if (rethrow) throw error;
            pageNotice(error.message, 'error');
        }
    };
    const confirmBlock = () => openSuperadminConfirm({
        message: trans(i18n.confirm_block, { name: currentAdmin?.name || '' }),
        description: i18n.block_description,
        tone: 'warning',
        confirmIcon: 'lock',
        confirmLabel: i18n.block_account,
        onConfirm: () => updateStatus('suspended', true),
    });
    const unblock = async (button) => {
        const originalHtml = button.innerHTML;
        button.disabled = true;
        renderSubmitLoading(button);
        await updateStatus('active');
        // On success load() already re-rendered the button; only restore it after a failure.
        if (button.disabled) {
            button.disabled = false;
            button.innerHTML = originalHtml;
        }
    };

    async function load() {
        const { data: admin } = await dfApi(`/superadmin/admins/${adminId}`);
        currentAdmin = admin;
        document.querySelector('#admin-name').textContent = admin.name;
        document.querySelector('#admin-meta').innerHTML = `${escapeHtml(admin.email)} · ${statusPill(admin.status)}`;
        document.querySelector('#admin-summary').innerHTML = [
            detailRow(i18n.name, escapeHtml(admin.name)),
            detailRow(i18n.email, escapeHtml(admin.email)),
            detailRow(i18n.phone, escapeHtml(admin.phone || i18n.not_provided)),
            detailRow(i18n.status, statusPill(admin.status)),
            detailRow(i18n.created_at, escapeHtml(formatDateTime(admin.created_at, '—'))),
            detailRow(i18n.last_login, escapeHtml(formatDateTime(admin.last_login_at, i18n.never_logged_in))),
        ].join('');
        const roomsContainer = document.querySelector('#admin-rooms');
        if ((admin.rooms || []).length === 0) {
            renderEmpty(roomsContainer, 'tpl-no-assigned-rooms');
        } else {
            roomsContainer.innerHTML = admin.rooms.map((room) =>
                `<div class="sa-health-row"><div><strong>${escapeHtml(room.name)}</strong><small>${escapeHtml(room.slug)}</small></div><span class="status-pill status-active"><span class="status-dot"></span>${escapeHtml(i18n.assigned)}</span></div>`,
            ).join('');
        }
        const statusButton = document.querySelector('#admin-status');
        const isActive = admin.status === 'active';
        statusButton.className = `sa-button ${isActive ? 'warning' : 'secondary'}`;
        statusButton.innerHTML = `<span class="material-symbols-outlined text-[16px]">${isActive ? 'lock' : 'lock_open'}</span>${escapeHtml(isActive ? i18n.block_account : i18n.unblock_account)}`;
        statusButton.disabled = false;
        statusButton.onclick = () => (isActive ? confirmBlock() : unblock(statusButton));
        document.querySelector('#reset-password-description').textContent = trans(i18n.reset_password_description, { name: admin.name });
    }

    const openRoomAssignment = async () => {
        const container = document.querySelector('#room-options');
        hideModalError('room-access-modal');
        renderEmpty(container, 'tpl-rooms-loading');
        window.openSuperadminModal('room-access-modal');
        try {
            const [{ data: admin }, rooms] = await Promise.all([dfApi(`/superadmin/admins/${adminId}`), fetchAllPages(urls.rooms)]);
            const assigned = new Set((admin.rooms || []).map((room) => Number(room.id)));
            if (rooms.length === 0) {
                renderEmpty(container, 'tpl-no-rooms');
                return;
            }
            const statusLabels = JSON.parse(document.body.dataset.statusLabels || '{}');
            container.innerHTML = rooms.map((room) => `
                <label class="flex items-center justify-between gap-3 px-2 py-2 rounded-lg text-[13px] text-on-surface cursor-pointer hover:bg-surface-container">
                    <span class="min-w-0"><strong class="block truncate">${escapeHtml(room.name)}</strong><small class="block truncate text-outline">${escapeHtml(room.slug)} · ${escapeHtml(statusLabels[room.status] || room.status)}</small></span>
                    <input type="checkbox" name="room_ids[]" value="${room.id}" ${assigned.has(Number(room.id)) ? 'checked' : ''}>
                </label>
            `).join('');
        } catch (error) {
            container.innerHTML = '';
            showModalError('room-access-modal', error.message);
        }
    };

    document.querySelector('#room-access-form')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.target;
        hideModalError('room-access-modal');
        const roomIds = [...form.querySelectorAll('input[name="room_ids[]"]:checked')].map((input) => Number(input.value));
        try {
            await dfApi(`/superadmin/admins/${adminId}/rooms`, { method: 'PUT', body: { room_ids: roomIds } });
            window.closeSuperadminModal('room-access-modal');
            pageNotice(i18n.assignment_updated);
            await load();
        } catch (error) {
            showModalError('room-access-modal', error.message);
        } finally {
            resetSubmitButton(form.querySelector('button[type="submit"]'));
        }
    });

    // Let the browser block a mismatched confirmation before submit (and before the loading spinner).
    const resetForm = document.querySelector('#reset-password-form');
    if (resetForm) {
        const check = () => resetForm.password_confirmation.setCustomValidity(
            resetForm.password.value === resetForm.password_confirmation.value ? '' : i18n.password_mismatch);
        resetForm.password.addEventListener('input', check);
        resetForm.password_confirmation.addEventListener('input', check);
        resetForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            hideModalError('reset-password-modal');
            try {
                await dfApi(`/superadmin/admins/${adminId}/reset-password`, { method: 'POST', body: { password: resetForm.password.value } });
                resetForm.reset();
                window.closeSuperadminModal('reset-password-modal');
                pageNotice(i18n.password_reset);
            } catch (error) {
                showModalError('reset-password-modal', error.message);
            } finally {
                resetSubmitButton(resetForm.querySelector('button[type="submit"]'));
            }
        });
    }

    document.querySelector('#admin-delete')?.addEventListener('click', () => {
        const name = document.querySelector('#admin-name')?.textContent.trim() || '';
        openSuperadminConfirm({
            message: trans(i18n.confirm_delete, { name }),
            description: i18n.delete_description,
            tone: 'danger',
            irreversible: true,
            confirmLabel: i18n.delete_account,
            onConfirm: async () => {
                await dfApi(`/superadmin/admins/${adminId}`, { method: 'DELETE' });
                pageNotice(i18n.deleted);
                window.setTimeout(() => { window.location.href = urls.list; }, 500);
            },
        });
    });

    document.querySelector('#edit-rooms')?.addEventListener('click', openRoomAssignment);
    load().catch((error) => {
        pageNotice(error.message, 'error');
        document.querySelectorAll('#admin-summary, #admin-rooms').forEach((container) => renderEmpty(container, 'tpl-load-failed', error.message));
    });
}
