/**
 * Global users list (superadmin/users.blade.php): block / unblock and delete from the row ⋯ menu.
 */
import { dfApi, openSuperadminConfirm, pageNotice, reloadSoon, trans } from './common';

export default function initUsersPage(_root, i18n) {
    document.addEventListener('click', (event) => {
        const toggleBtn = event.target.closest('[data-action="toggle-user-status"]');
        if (toggleBtn) {
            const isBlocked = toggleBtn.dataset.currentStatus === 'blocked';
            const nextStatus = isBlocked ? 'active' : 'blocked';
            openSuperadminConfirm({
                message: trans(isBlocked ? i18n.confirm_unblock : i18n.confirm_block, { name: toggleBtn.dataset.userName }),
                tone: isBlocked ? 'success' : 'warning',
                description: isBlocked ? i18n.unblock_description : i18n.block_description,
                confirmIcon: isBlocked ? 'lock_open' : 'lock',
                confirmLabel: isBlocked ? i18n.unblock : i18n.block,
                onConfirm: async () => {
                    await dfApi(`/superadmin/global-users/${toggleBtn.dataset.userId}/status`, { method: 'PATCH', body: { status: nextStatus } });
                    pageNotice(i18n.status_updated);
                    reloadSoon();
                },
            });
            return;
        }

        const deleteBtn = event.target.closest('[data-action="delete-user"]');
        if (!deleteBtn) return;
        openSuperadminConfirm({
            message: i18n.confirm_delete,
            description: i18n.delete_description,
            tone: 'danger',
            confirmLabel: i18n.delete,
            onConfirm: async () => {
                await dfApi(`/superadmin/global-users/${deleteBtn.dataset.userId}`, { method: 'DELETE' });
                pageNotice(i18n.deleted);
                reloadSoon();
            },
        });
    });
}
