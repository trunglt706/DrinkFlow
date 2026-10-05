/**
 * Failed jobs (superadmin/queue.blade.php): retry or delete a failed job.
 */
import { dfApi, openSuperadminConfirm, pageNotice, reloadSoon } from './common';

export default function initQueuePage(_root, i18n) {
    document.addEventListener('click', (event) => {
        const retryBtn = event.target.closest('[data-action="retry-job"]');
        if (retryBtn) {
            openSuperadminConfirm({
                message: i18n.confirm_retry,
                description: i18n.retry_description,
                confirmLabel: i18n.retry,
                confirmIcon: 'replay',
                tone: 'info',
                icon: 'replay',
                onConfirm: async () => {
                    await dfApi(`/superadmin/queue/failed/${retryBtn.dataset.jobId}/retry`, { method: 'POST' });
                    pageNotice(i18n.retried);
                    reloadSoon();
                },
            });
            return;
        }

        const deleteBtn = event.target.closest('[data-action="delete-job"]');
        if (!deleteBtn) return;
        openSuperadminConfirm({
            message: i18n.confirm_delete,
            description: i18n.delete_description,
            tone: 'danger',
            irreversible: true,
            confirmLabel: i18n.delete,
            onConfirm: async () => {
                await dfApi(`/superadmin/queue/failed/${deleteBtn.dataset.jobId}`, { method: 'DELETE' });
                pageNotice(i18n.deleted);
                reloadSoon();
            },
        });
    });
}
