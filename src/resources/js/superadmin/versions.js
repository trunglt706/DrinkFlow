import { dfApi } from './shared';
import { openSuperadminConfirm } from './modal';
import { renderSubmitLoading } from '../shared/submit-loading';
import { applyFormat } from '../shared/markdown-format';

/**
 * Superadmin release management page (resources/views/superadmin/versions.blade.php).
 *
 * Create/edit releases in #version-modal with a small Markdown editor (toolbar, write/preview
 * tabs, .md upload) and delete them through the shared confirm modal. The preview HTML is rendered
 * server-side by Version::renderMarkdown(), which escapes raw HTML and drops unsafe links.
 */

const UPLOAD_EXTENSIONS = /\.(md|markdown|txt)$/i;

/**
 * Replace :placeholders in a translated string.
 *
 * @param {string} text Translated text.
 * @param {Record<string, string|number>} params Replacements.
 * @returns {string}
 */
const trans = (text, params = {}) => Object.entries(params)
    .reduce((result, [key, value]) => result.replaceAll(`:${key}`, String(value)), String(text ?? ''));

export function initSuperadminVersions() {
    const form = document.getElementById('version-form');
    if (!form) return;

    const i18n = JSON.parse(form.dataset.i18n || '{}');
    const maxLength = Number(form.dataset.maxLength || 0);
    const maxUploadBytes = Number(form.dataset.maxUploadKb || 100) * 1024;
    const modalTitle = document.getElementById('version-modal-title');
    const modalDescription = modalTitle?.nextElementSibling;
    const errorBox = document.getElementById('version-modal-error');
    const textarea = document.getElementById('version-field-changelog');
    const preview = document.getElementById('version-changelog-preview');
    const counter = document.getElementById('version-changelog-count');
    const toolbarActions = form.querySelector('[data-md-actions]');
    const upload = document.getElementById('version-md-upload');
    const submitButton = form.querySelector('button[type="submit"]');
    const submitHtml = submitButton.innerHTML;
    const fields = form.elements;

    let mode = 'create';
    let updateUrl = null;
    let previewedSource = null;

    const notice = (message, type = 'success') => {
        const box = document.getElementById('notice');
        if (!box) return;
        box.textContent = message;
        box.className = `sa-notice ${type} is-visible`;
    };

    const showError = (message) => {
        if (!errorBox) return;
        errorBox.textContent = message;
        errorBox.classList.toggle('hidden', !message);
    };

    const showTemplate = (id) => {
        const template = document.getElementById(id);
        preview.replaceChildren(template ? template.content.cloneNode(true) : '');
    };

    const updateCounter = () => {
        const length = textarea.value.length;
        counter.textContent = `${trans(i18n.characters, { count: length.toLocaleString() })} / ${maxLength.toLocaleString()}`;
        counter.classList.toggle('text-error', maxLength > 0 && length > maxLength);
    };

    const renderPreview = async () => {
        const source = textarea.value;
        if (source.trim() === '') {
            showTemplate('tpl-preview-empty');
            previewedSource = null;
            return;
        }
        if (source === previewedSource) return;

        showTemplate('tpl-preview-loading');
        try {
            const { data } = await dfApi(form.dataset.previewUrl, { method: 'POST', body: { changelog: source } });
            // Server-rendered with raw HTML escaped and unsafe links removed (Version::renderMarkdown()).
            preview.innerHTML = data.html;
            previewedSource = source;
        } catch (error) {
            showTemplate('tpl-preview-failed');
            const description = preview.querySelector('[data-empty-description]');
            if (description) description.textContent = error.message || '';
        }
    };

    const switchTab = (tab) => {
        const isPreview = tab === 'preview';
        form.querySelectorAll('[data-md-tab]').forEach((button) => {
            const active = button.dataset.mdTab === tab;
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });
        textarea.classList.toggle('hidden', isPreview);
        preview.classList.toggle('hidden', !isPreview);
        toolbarActions.classList.toggle('is-hidden', isPreview);
        if (isPreview) renderPreview();
        else textarea.focus();
    };

    const openEditor = (release = null, url = null) => {
        mode = release ? 'edit' : 'create';
        updateUrl = url;
        form.reset();
        fields.version.value = release?.version ?? '';
        fields.title.value = release?.title ?? '';
        fields.release_date.value = release?.release_date ?? new Date().toISOString().slice(0, 10);
        fields.important.checked = Boolean(release?.important);
        fields.force_refresh.checked = Boolean(release?.force_refresh);
        textarea.value = release?.changelog ?? '';
        previewedSource = null;

        if (modalTitle) modalTitle.textContent = release ? i18n.edit_release : i18n.new_release;
        // Header icon follows the dialog kind: create (new release) vs edit (existing release).
        const modalIcon = document.querySelector('#version-modal [data-modal-icon]');
        if (modalIcon) {
            modalIcon.className = `sa-modal-icon is-${release ? 'edit' : 'create'}`;
            modalIcon.querySelector('.material-symbols-outlined').textContent = release ? 'edit_note' : 'new_releases';
        }
        if (modalDescription) modalDescription.textContent = release ? i18n.edit_description : i18n.create_description;
        submitButton.innerHTML = submitHtml;
        submitButton.disabled = false;
        showError('');
        updateCounter();
        switchTab('write');
        window.openSuperadminModal('version-modal');
        fields.version.focus();
    };

    textarea.addEventListener('input', updateCounter);

    form.addEventListener('click', (event) => {
        const tabButton = event.target.closest('[data-md-tab]');
        if (tabButton) {
            switchTab(tabButton.dataset.mdTab);
            return;
        }
        const formatButton = event.target.closest('[data-md-format]');
        if (formatButton) applyFormat(textarea, formatButton.dataset.mdFormat, i18n);
    });

    upload.addEventListener('change', () => {
        const file = upload.files?.[0];
        upload.value = ''; // allow re-selecting the same file
        if (!file) return;

        if (!UPLOAD_EXTENSIONS.test(file.name)) {
            showError(i18n.upload_invalid_type);
            return;
        }
        if (file.size > maxUploadBytes) {
            showError(trans(i18n.upload_too_large, { size: maxUploadBytes / 1024 }));
            return;
        }

        const reader = new FileReader();
        reader.onload = () => {
            textarea.value = String(reader.result ?? '').replace(/\r\n?/g, '\n');
            previewedSource = null;
            showError('');
            updateCounter();
            notice(trans(i18n.upload_loaded, { name: file.name }));
            switchTab('write');
        };
        reader.onerror = () => showError(i18n.upload_failed);
        reader.readAsText(file, 'UTF-8');
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!form.reportValidity()) return;

        showError('');
        submitButton.disabled = true;
        renderSubmitLoading(submitButton);

        const body = {
            version: fields.version.value.trim(),
            title: fields.title.value.trim(),
            changelog: textarea.value,
            release_date: fields.release_date.value || null,
            important: fields.important.checked,
            force_refresh: fields.force_refresh.checked,
        };

        try {
            if (mode === 'edit') {
                await dfApi(updateUrl, { method: 'PUT', body });
            } else {
                await dfApi(form.dataset.storeUrl, { method: 'POST', body });
            }
            window.closeSuperadminModal('version-modal');
            notice(mode === 'edit' ? i18n.updated : i18n.created);
            window.setTimeout(() => window.location.reload(), 500);
        } catch (error) {
            showError(error.message || String(error));
            submitButton.disabled = false;
            submitButton.innerHTML = submitHtml;
        }
    });

    document.addEventListener('click', (event) => {
        if (event.target.closest('[data-action="create-release"]')) {
            openEditor();
            return;
        }

        const editButton = event.target.closest('[data-action="edit-release"]');
        if (editButton) {
            openEditor(JSON.parse(editButton.dataset.release || '{}'), editButton.dataset.updateUrl);
            return;
        }

        const deleteButton = event.target.closest('[data-action="delete-release"]');
        if (deleteButton) {
            openSuperadminConfirm({
                message: trans(i18n.confirm_delete, { version: deleteButton.dataset.version }),
                description: i18n.delete_description,
                tone: 'danger',
                irreversible: true,
                onConfirm: async () => {
                    await dfApi(deleteButton.dataset.deleteUrl, { method: 'DELETE' });
                    notice(i18n.deleted);
                    window.setTimeout(() => window.location.reload(), 500);
                },
            });
        }
    });
}
