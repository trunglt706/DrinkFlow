/**
 * User feedback moderation (superadmin/feedbacks.blade.php): approve / deactivate, edit and delete a feedback.
 */
import { dfApi, openSuperadminConfirm, pageNotice, reloadSoon, renderSubmitLoading, resetSubmitButton } from './common';

export default function initFeedbacksPage(_root, i18n) {
    const form = document.querySelector('#edit-feedback-form');
    const errorBox = document.querySelector('#edit-feedback-error');
    let editingId = null;

    const showError = (message) => {
        if (!errorBox) return;
        errorBox.textContent = message || '';
        errorBox.classList.toggle('hidden', !message);
    };

    const openEditor = (button) => {
        if (!form) return;
        let feedback = {};
        try {
            feedback = JSON.parse(button.dataset.feedback || '{}');
        } catch {
            feedback = {};
        }
        editingId = button.dataset.feedbackId;
        form.rating.value = String(feedback.rating ?? 5);
        form.subsystem.value = feedback.subsystem || 'all';
        form.user_display_name.value = feedback.user_display_name || '';
        form.content.value = feedback.content || '';
        showError('');
        window.openSuperadminModal('edit-feedback-modal');
        form.content.focus();
    };

    const setStatus = async (button) => {
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
    };

    const confirmDelete = (button) => openSuperadminConfirm({
        message: i18n.confirm_delete,
        description: i18n.delete_description,
        tone: 'danger',
        irreversible: true,
        confirmLabel: i18n.delete,
        confirmIcon: 'delete',
        onConfirm: async () => {
            try {
                await dfApi(`/superadmin/feedbacks/${button.dataset.feedbackId}`, { method: 'DELETE' });
                pageNotice(i18n.deleted);
                reloadSoon();
            } catch (error) {
                pageNotice(error.message, 'error');
            }
        },
    });

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-action^="feedback-"]');
        if (!button || button.disabled) return;
        if (button.dataset.action === 'feedback-status') setStatus(button);
        else if (button.dataset.action === 'feedback-edit') openEditor(button);
        else if (button.dataset.action === 'feedback-delete') confirmDelete(button);
    });

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!editingId) return;
        const submitButton = form.querySelector('button[type="submit"]');
        showError('');
        try {
            await dfApi(`/superadmin/feedbacks/${editingId}`, {
                method: 'PUT',
                body: {
                    rating: Number(form.rating.value),
                    subsystem: form.subsystem.value,
                    user_display_name: form.user_display_name.value,
                    content: form.content.value,
                },
            });
            window.closeSuperadminModal('edit-feedback-modal');
            pageNotice(i18n.updated);
            reloadSoon();
        } catch (error) {
            showError(error.message);
        } finally {
            resetSubmitButton(submitButton);
        }
    });
}
