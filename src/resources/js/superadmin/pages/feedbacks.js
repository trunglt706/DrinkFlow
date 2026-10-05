/**
 * User feedback moderation (superadmin/feedbacks.blade.php): approve / deactivate a feedback.
 */
import { dfApi, pageNotice, reloadSoon, renderSubmitLoading } from './common';

export default function initFeedbacksPage(_root, i18n) {
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-action="feedback-status"]');
        if (!button || button.disabled) return;
        const originalHtml = button.innerHTML;
        button.disabled = true;
        renderSubmitLoading(button);
        try {
            await dfApi(`/superadmin/feedbacks/${button.dataset.feedbackId}/status`, { method: 'PATCH', body: { status: button.dataset.status } });
            pageNotice(i18n.status_updated);
            reloadSoon();
        } catch (error) {
            pageNotice(error.message, 'error');
            button.disabled = false;
            button.innerHTML = originalHtml;
        }
    });
}
