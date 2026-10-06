/**
 * Agent page actions (superadmin/agents/show.blade.php, rendered for Superadmins who manage the Agent):
 * room access, password reset and account deletion, through the Agent JSON API (/superadmin/admins/*).
 * The page itself is rendered by the server, so it reloads after a change.
 */
import {
    dfApi, escapeHtml, fetchAllPages, openSuperadminConfirm, pageNotice, renderEmpty, resetSubmitButton, trans,
} from './common';

export default function initAgentDetailPage(_root, i18n, urls) {
    const showModalError = (modalId, message) => {
        const errorBox = document.querySelector(`#${modalId}-error`);
        if (!errorBox) return;
        errorBox.textContent = message;
        errorBox.classList.remove('hidden');
    };
    const hideModalError = (modalId) => document.querySelector(`#${modalId}-error`)?.classList.add('hidden');

    const openRoomAssignment = async () => {
        const container = document.querySelector('#room-options');
        hideModalError('room-access-modal');
        renderEmpty(container, 'tpl-rooms-loading');
        window.openSuperadminModal('room-access-modal');
        try {
            const [{ data: admin }, rooms] = await Promise.all([dfApi(urls.admin), fetchAllPages(urls.rooms)]);
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

    document.querySelector('#edit-rooms')?.addEventListener('click', openRoomAssignment);
    document.querySelector('#room-access-form')?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const form = event.target;
        hideModalError('room-access-modal');
        const roomIds = [...form.querySelectorAll('input[name="room_ids[]"]:checked')].map((input) => Number(input.value));
        try {
            await dfApi(urls.assign_rooms, { method: 'PUT', body: { room_ids: roomIds } });
            window.closeSuperadminModal('room-access-modal');
            pageNotice(i18n.assignment_updated);
            window.setTimeout(() => window.location.reload(), 400);
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
                await dfApi(urls.reset_password, { method: 'POST', body: { password: resetForm.password.value } });
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

    document.querySelector('#agent-delete')?.addEventListener('click', () => {
        openSuperadminConfirm({
            message: trans(i18n.confirm_delete, { name: urls.name || '' }),
            description: i18n.delete_description,
            tone: 'danger',
            irreversible: true,
            confirmLabel: i18n.delete_account,
            onConfirm: async () => {
                await dfApi(urls.destroy, { method: 'DELETE' });
                pageNotice(i18n.deleted);
                window.setTimeout(() => { window.location.href = urls.list; }, 500);
            },
        });
    });
}
