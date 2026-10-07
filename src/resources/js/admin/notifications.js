import { renderSubmitLoading } from '../shared/submit-loading';

/** Initialize room notification-channel create, test, and disable interactions. */
export function initAdminNotifications() {
    const form = document.querySelector('#add-channel-form');
    const typeSelect = document.querySelector('#ch-type');
    const notice = document.querySelector('#notice');
    const deleteModal = document.querySelector('#channel-delete-modal');
    const deleteConfirm = document.querySelector('#channel-delete-confirm');
    const deleteCancel = document.querySelector('#channel-delete-cancel');
    const editCancel = document.querySelector('#channel-edit-cancel');
    const formTitle = document.querySelector('#channel-form-title');
    const formIcon = document.querySelector('#channel-form-icon');
    const formSubmit = document.querySelector('#channel-form-submit');
    const testModal = document.querySelector('#channel-test-modal');
    const testClose = document.querySelector('#channel-test-close');
    const testCancel = document.querySelector('#channel-test-cancel');
    const testSubmit = document.querySelector('#channel-test-submit');
    const testTemplateSelect = document.querySelector('#test-template-select');
    const testTargetName = document.querySelector('#test-target-channel-name');
    const testTargetType = document.querySelector('#test-target-channel-type');
    const testPreviewBox = document.querySelector('#test-preview-box');
    const testPreviewFormatLabel = document.querySelector('#test-preview-format-label');

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const roomSlug = document.querySelector('[data-room-slug]')?.dataset.roomSlug || window.__DF_ROOM_SLUG__ || '';
    const translations = notice?.dataset || {};
    let pendingDeleteId = null;
    let editingChannelId = null;
    let activeTestChannelId = null;
    let activeTestChannelType = 'telegram';

    if (!form && !document.querySelector('[data-notification-channel]')) return;

    const showNotify = (message, type = 'success') => {
        if (!notice) return;
        notice.textContent = message;
        notice.className = `mb-4 rounded-xl px-4 py-3 text-xs font-medium ${type === 'error' ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-emerald-50 text-emerald-700 border border-emerald-200'}`;
        notice.classList.remove('hidden');
        window.setTimeout(() => notice.classList.add('hidden'), 5000);
    };

    const setLoading = (button, active) => {
        if (!button) return;
        if (active) {
            button.dataset.originalHtml = button.innerHTML;
            button.disabled = true;
            button.classList.add('opacity-70', 'cursor-not-allowed');
            renderSubmitLoading(button);
            return;
        }
        button.disabled = false;
        button.classList.remove('opacity-70', 'cursor-not-allowed');
        if (button.dataset.originalHtml) button.innerHTML = button.dataset.originalHtml;
    };

    const responseData = async (response) => response.json().catch(() => ({}));
    const reloadAfterNotice = (message) => {
        showNotify(message);
        window.setTimeout(() => window.location.reload(), 700);
    };

    const switchPlatform = (type) => {
        document.querySelectorAll('.platform-config-fields').forEach((element) => element.classList.add('hidden'));
        document.querySelector(`#platform-${type}`)?.classList.remove('hidden');
        document.querySelectorAll('.platform-integration-guide').forEach((element) => element.classList.add('hidden'));
        document.querySelector(`[data-platform-guide="${type}"]`)?.classList.remove('hidden');
    };

    const resetCredentialFields = () => {
        ['#ch-tg-token', '#ch-tg-chat-id', '#ch-slack-url', '#ch-cw-token', '#ch-cw-room-id', '#ch-wh-url', '#ch-wh-secret'].forEach((selector) => {
            const input = document.querySelector(selector);
            if (input) {
                input.value = '';
                input.placeholder = input.dataset.defaultPlaceholder || input.placeholder;
            }
        });
    };

    const resetForm = () => {
        editingChannelId = null;
        form?.reset();
        resetCredentialFields();
        switchPlatform(typeSelect?.value || 'telegram');
        if (formTitle) formTitle.textContent = translations.notificationConnectNew || 'Connect new bot';
        if (formIcon) formIcon.textContent = 'add_link';
        if (formSubmit) formSubmit.querySelector('span:last-child').textContent = translations.notificationSaveWebhook || 'Save webhook';
        editCancel?.classList.add('hidden');
    };

    typeSelect?.addEventListener('change', (event) => switchPlatform(event.target.value));
    if (typeSelect) switchPlatform(typeSelect.value);

    form?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const type = typeSelect?.value || 'webhook';
        const config = type === 'telegram'
            ? { bot_token: document.querySelector('#ch-tg-token')?.value.trim() || '', chat_id: document.querySelector('#ch-tg-chat-id')?.value.trim() || '' }
            : type === 'slack'
                ? { webhook_url: document.querySelector('#ch-slack-url')?.value.trim() || '' }
                : type === 'chatwork'
                    ? { api_token: document.querySelector('#ch-cw-token')?.value.trim() || '', room_id: document.querySelector('#ch-cw-room-id')?.value.trim() || '' }
                    : { webhook_url: document.querySelector('#ch-wh-url')?.value.trim() || '', secret_token: document.querySelector('#ch-wh-secret')?.value.trim() || '' };
        if (editingChannelId !== null) {
            Object.keys(config).forEach((key) => {
                if (config[key] === '') delete config[key];
            });
        }
        const button = form.querySelector('button[type="submit"]');
        setLoading(button, true);
        try {
            const isEditing = editingChannelId !== null;
            const endpoint = isEditing
                ? `/admin/${roomSlug}/notification-channels/${editingChannelId}`
                : `/admin/${roomSlug}/notification-channels`;
            const response = await fetch(endpoint, { method: isEditing ? 'PATCH' : 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' }, body: JSON.stringify({ type, name: document.querySelector('#ch-name')?.value.trim(), status: 'enabled', config }) });
            const data = await responseData(response);
            if (!response.ok) throw new Error(data.message || translations.notificationSaveFailed || 'Could not save webhook.');
            reloadAfterNotice(translations.notificationSaved || 'Saved successfully.');
        } catch (error) {
            showNotify(error.message || translations.notificationServerError || 'Server error.', 'error');
            setLoading(button, false);
        }
    });

    window.editChannel = async (id, channel) => {
        const button = document.querySelector(`[data-channel-edit="${id}"]`);
        setLoading(button, true);
        try {
            const response = await fetch(`/admin/${roomSlug}/notification-channels/${id}`, {
                headers: { Accept: 'application/json' }
            });
            const data = await responseData(response);
            if (!response.ok || !data.data) {
                throw new Error(data.message || translations.notificationServerError || 'Server error.');
            }

            const details = data.data;
            editingChannelId = id;
            const nameInput = document.querySelector('#ch-name');
            if (nameInput) nameInput.value = details.name || channel.name || '';
            if (typeSelect) typeSelect.value = details.type || channel.type || 'webhook';
            resetCredentialFields();
            const config = details.config || {};
            const fieldMap = {
                telegram: { bot_token: '#ch-tg-token', chat_id: '#ch-tg-chat-id' },
                slack: { webhook_url: '#ch-slack-url' },
                chatwork: { api_token: '#ch-cw-token', room_id: '#ch-cw-room-id' },
                webhook: { webhook_url: '#ch-wh-url', secret_token: '#ch-wh-secret' }
            };
            Object.entries(fieldMap[details.type] || {}).forEach(([key, selector]) => {
                const input = document.querySelector(selector);
                if (input) {
                    input.value = config[key] || '';
                    if ((details.secrets_configured || []).includes(key)) {
                        input.dataset.defaultPlaceholder = input.dataset.defaultPlaceholder || input.placeholder;
                        input.placeholder = '••••••••';
                    }
                }
            });
            switchPlatform(typeSelect?.value || 'webhook');
            if (formTitle) formTitle.textContent = translations.notificationEdit || 'Edit';
            if (formIcon) formIcon.textContent = 'edit';
            if (formSubmit) formSubmit.querySelector('span:last-child').textContent = translations.notificationEdit || 'Edit';
            editCancel?.classList.remove('hidden');
            document.querySelector('#ch-name')?.focus();
        } catch (error) {
            showNotify(error.message || translations.notificationServerError || 'Server error.', 'error');
        } finally {
            setLoading(button, false);
        }
    };
    editCancel?.addEventListener('click', resetForm);

    const updateLivePreview = () => {
        if (!testPreviewBox) return;
        const template = testTemplateSelect?.value || 'test_ping';
        const type = activeTestChannelType || 'telegram';

        if (testPreviewFormatLabel) {
            testPreviewFormatLabel.textContent = type === 'telegram' ? 'HTML (Telegram)' : type === 'slack' ? 'mrkdwn (Slack)' : type === 'chatwork' ? 'BBCode (ChatWork)' : 'JSON (Webhook)';
        }

        // Rendered by the server with the same builder and formatters as the real test send (current locale).
        let previews = {};
        try {
            previews = JSON.parse(testPreviewBox.dataset.previews || '{}');
        } catch {
            previews = {};
        }

        const templatePreviews = previews[template] || previews.test_ping || {};
        testPreviewBox.textContent = templatePreviews[type] || templatePreviews.telegram || '';
    };

    testTemplateSelect?.addEventListener('change', updateLivePreview);

    window.openTestModal = (id, channel) => {
        activeTestChannelId = id;
        activeTestChannelType = channel.type || 'telegram';
        if (testTargetName) testTargetName.textContent = channel.name || ucfirst(activeTestChannelType);
        if (testTargetType) testTargetType.textContent = activeTestChannelType;
        if (testTemplateSelect) testTemplateSelect.value = 'test_ping';
        updateLivePreview();
        testModal?.classList.remove('hidden');
        testModal?.classList.add('flex');
    };

    const closeTestModal = () => {
        testModal?.classList.add('hidden');
        testModal?.classList.remove('flex');
        activeTestChannelId = null;
    };

    testClose?.addEventListener('click', closeTestModal);
    testCancel?.addEventListener('click', closeTestModal);
    testModal?.addEventListener('click', (event) => {
        if (event.target === testModal) closeTestModal();
    });

    testSubmit?.addEventListener('click', async () => {
        if (!activeTestChannelId) return;
        const id = activeTestChannelId;
        const template = testTemplateSelect?.value || 'test_ping';
        setLoading(testSubmit, true);
        try {
            const response = await fetch(`/admin/${roomSlug}/notification-channels/${id}/test`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json'
                },
                body: JSON.stringify({ template })
            });
            const data = await responseData(response);
            if (!response.ok) {
                throw new Error(data.message || translations.notificationTestFailed || 'Could not send test ping.');
            }
            closeTestModal();
            showNotify(translations.testNotificationSuccess || translations.notificationTestSent || 'Test notification sent successfully.');
        } catch (error) {
            showNotify(error.message || translations.notificationServerError || 'Server error.', 'error');
        } finally {
            setLoading(testSubmit, false);
        }
    });

    window.testChannel = async (id, template = 'test_ping') => {
        const button = document.querySelector(`[data-channel-test="${id}"]`);
        setLoading(button, true);
        try {
            const response = await fetch(`/admin/${roomSlug}/notification-channels/${id}/test`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json'
                },
                body: JSON.stringify({ template })
            });
            const data = await responseData(response);
            if (!response.ok) throw new Error(data.message || translations.notificationTestFailed || 'Could not send test ping.');
            showNotify(translations.testNotificationSuccess || translations.notificationTestSent || 'Test ping sent successfully.');
        } catch (error) {
            showNotify(error.message || translations.notificationServerError || 'Server error.', 'error');
        } finally {
            setLoading(button, false);
        }
    };

    const closeDeleteModal = () => {
        pendingDeleteId = null;
        deleteModal?.classList.add('hidden');
        deleteModal?.classList.remove('flex');
    };
    window.deleteChannel = (id) => {
        pendingDeleteId = id;
        deleteModal?.classList.remove('hidden');
        deleteModal?.classList.add('flex');
        deleteConfirm?.focus();
    };
    deleteCancel?.addEventListener('click', closeDeleteModal);
    deleteModal?.addEventListener('click', (event) => { if (event.target === deleteModal) closeDeleteModal(); });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            if (pendingDeleteId !== null) closeDeleteModal();
            if (activeTestChannelId !== null) closeTestModal();
        }
    });
    deleteConfirm?.addEventListener('click', async () => {
        if (pendingDeleteId === null) return;
        const id = pendingDeleteId;
        const trigger = document.querySelector(`[data-channel-delete="${id}"]`);
        setLoading(deleteConfirm, true);
        try {
            const response = await fetch(`/admin/${roomSlug}/notification-channels/${id}`, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' } });
            const data = await responseData(response);
            if (!response.ok) throw new Error(data.message || translations.notificationDeleteFailed || 'Could not delete channel.');
            closeDeleteModal();
            setLoading(trigger, true);
            reloadAfterNotice(translations.notificationSaved || 'Saved successfully.');
        } catch (error) {
            showNotify(error.message || translations.notificationDeleteFailed || 'Could not delete channel.', 'error');
            setLoading(deleteConfirm, false);
        }
    });
}
