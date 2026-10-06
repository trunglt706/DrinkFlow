/**
 * Global user profile (superadmin/user-detail.blade.php): identity, room memberships, block / unblock, delete and
 * membership removal.
 */
import { dfApi, emptyStateHtml, escapeHtml, openSuperadminConfirm, pageNotice, sensitiveHtml, statusPill, trans } from './common';

export default function initUserDetailPage(_root, i18n, urls) {
    const userId = urls.user_id;
    let currentUser = null;
    const formatDateTime = (value) => (value ? new Date(value).toLocaleString(document.documentElement.lang || undefined) : i18n.never_logged_in);
    const emptyRow = (html) => `<tr><td colspan="3">${html}</td></tr>`;
    const detailRow = (label, value) => `<div class="sa-detail-row"><span>${escapeHtml(label)}</span><strong>${value}</strong></div>`;

    const load = async () => {
        const { data: user } = await dfApi(`/superadmin/global-users/${userId}`);
        currentUser = user;
        document.querySelector('#user-name').textContent = user.name;
        document.querySelector('#user-meta').innerHTML = `<span class="font-mono">${sensitiveHtml(user.email, user.email_reveal)}</span> · ${statusPill(user.status)}`;
        document.querySelector('#identity-list').innerHTML = [
            detailRow(i18n.name, escapeHtml(user.name)),
            detailRow(i18n.email, sensitiveHtml(user.email, user.email_reveal)),
            detailRow(i18n.status, statusPill(user.status)),
            detailRow(i18n.created_at, formatDateTime(user.created_at)),
            detailRow(i18n.last_login, user.last_login_at ? formatDateTime(user.last_login_at) : escapeHtml(i18n.never_logged_in)),
            detailRow(i18n.rooms_joined, user.room_users_count ?? 0),
            detailRow(i18n.total_orders, user.orders_count ?? 0),
            ...(user.oauth_identities || []).map((identity) => detailRow(identity.provider,
                `${escapeHtml(identity.provider_email || i18n.linked_identity)}<small>${escapeHtml(identity.last_login_at || i18n.no_login_metadata)}</small>`)),
        ].join('');
        document.querySelector('#membership-list').innerHTML = (user.room_users || []).map((membership) => `
            <tr>
                <td class="col-primary">
                    <div class="flex items-center gap-3">
                        <span class="superadmin-avatar shrink-0">${escapeHtml((membership.room?.name || 'R').slice(0, 1).toUpperCase())}</span>
                        <span class="min-w-0">
                            <strong class="block truncate">${escapeHtml(membership.room?.name || '—')}</strong>
                            <small class="block truncate text-outline">${escapeHtml(membership.room?.slug || '')}</small>
                        </span>
                    </div>
                </td>
                <td class="col-status">${statusPill(membership.status)}</td>
                <td class="col-actions">
                    <div class="sa-row-actions">
                        <button class="sa-button secondary" type="button" data-action="remove-membership" data-room-user-id="${membership.id}"><span class="material-symbols-outlined text-[16px]">person_remove</span>${escapeHtml(i18n.remove)}</button>
                    </div>
                </td>
            </tr>
        `).join('') || emptyRow(emptyStateHtml('tpl-no-memberships'));
        // A soft-deleted account is read-only: no status change, delete or membership removal.
        const isDeleted = user.status === 'deleted';
        document.querySelector('#user-delete-action').hidden = isDeleted;
        document.querySelectorAll('[data-action="remove-membership"]').forEach((button) => { button.hidden = isDeleted; });
        const statusBtn = document.querySelector('#user-status-action');
        statusBtn.hidden = isDeleted;
        const isBlocked = user.status === 'blocked';
        statusBtn.innerHTML = `<span class="material-symbols-outlined text-[16px]">${isBlocked ? 'lock_open' : 'lock'}</span>${escapeHtml(isBlocked ? i18n.unblock : i18n.block)}`;
        statusBtn.className = `sa-button ${isBlocked ? 'secondary' : 'warning'}`;
        statusBtn.disabled = false;
        statusBtn.classList.remove('opacity-75', 'cursor-wait');
        delete statusBtn.dataset.originalHtml;
    };

    document.querySelector('#user-status-action')?.addEventListener('click', () => {
        if (!currentUser) return;
        const isBlocked = currentUser.status === 'blocked';
        openSuperadminConfirm({
            message: trans(isBlocked ? i18n.confirm_unblock : i18n.confirm_block, { name: currentUser.name }),
            tone: isBlocked ? 'success' : 'warning',
            description: isBlocked ? i18n.unblock_description : i18n.block_description,
            confirmIcon: isBlocked ? 'lock_open' : 'lock',
            confirmLabel: isBlocked ? i18n.unblock : i18n.block,
            onConfirm: async () => {
                await dfApi(`/superadmin/global-users/${userId}/status`, { method: 'PATCH', body: { status: isBlocked ? 'active' : 'blocked' } });
                pageNotice(i18n.status_updated);
                await load();
            },
        });
    });

    document.addEventListener('click', (event) => {
        const removeBtn = event.target.closest('[data-action="remove-membership"]');
        if (!removeBtn) return;
        openSuperadminConfirm({
            message: i18n.confirm_remove_membership,
            description: i18n.membership_remove_description,
            tone: 'warning',
            confirmIcon: 'person_remove',
            confirmLabel: i18n.remove,
            onConfirm: async () => {
                await dfApi(`/superadmin/global-users/${userId}/memberships/${removeBtn.dataset.roomUserId}`, { method: 'DELETE' });
                pageNotice(i18n.membership_removed);
                await load();
            },
        });
    });

    document.querySelector('#user-delete-action')?.addEventListener('click', () => {
        openSuperadminConfirm({
            message: i18n.confirm_delete,
            description: i18n.delete_description,
            tone: 'danger',
            confirmLabel: i18n.delete,
            onConfirm: async () => {
                await dfApi(`/superadmin/global-users/${userId}`, { method: 'DELETE' });
                pageNotice(i18n.deleted);
                window.setTimeout(() => { window.location.href = urls.list; }, 500);
            },
        });
    });

    load().catch((error) => {
        pageNotice(error.message, 'error');
        const failed = emptyStateHtml('tpl-load-failed', error.message);
        document.querySelector('#identity-list').innerHTML = failed;
        document.querySelector('#membership-list').innerHTML = emptyRow(failed);
    });
}
