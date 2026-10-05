/**
 * System settings (superadmin/system.blade.php): health list, mail / storage overrides (tabs kept in the URL hash),
 * maintenance window, test mail and system reset.
 */
import { dfApi, emptyStateHtml, escapeHtml, openSuperadminConfirm, pageNotice, resetSubmitButton, statusPill, trans } from './common';

const toDateTimeLocal = (value) => {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return value.slice(0, 16);
    const offset = date.getTimezoneOffset();
    return new Date(date.getTime() - offset * 60000).toISOString().slice(0, 16);
};

export default function initSystemPage(_root, i18n, urls) {
    // None of the forms on this page navigate away or reload after submit, so the submit button the shared
    // submit-loading listener disabled and spun must be restored by hand once the async request settles.
    const restoreSubmitButton = (form) => resetSubmitButton(form.querySelector('button[type="submit"]'));

    // Fill a mail/storage settings form: saved values, the .env value as placeholder, and which source is in effect.
    const renderConfigForm = (form, fields) => {
        if (!form || !fields) return;
        Object.entries(fields).forEach(([name, info]) => {
            const input = form.elements[name];
            if (!input) return;
            const envText = info.secret
                ? (info.env_configured ? i18n.config.envSecretSet : i18n.config.envSecretEmpty)
                : (info.env_configured ? trans(i18n.config.envValue, { value: info.env_value }) : i18n.config.envEmpty);
            if (input.tagName === 'SELECT') {
                const envOption = input.querySelector('[data-env-option]');
                if (envOption) envOption.textContent = info.env_configured ? `${i18n.config.useEnv} (${info.env_value})` : i18n.config.useEnv;
                input.value = info.value ?? '';
            } else if (info.secret) {
                input.value = '';
            } else {
                input.value = info.value ?? '';
                input.placeholder = info.env_configured ? String(info.env_value) : '';
            }
            const hint = form.querySelector(`[data-config-hint="${name}"]`);
            if (hint) {
                hint.innerHTML = `<span class="sa-config-source is-${escapeHtml(info.source)}">${escapeHtml(i18n.config[info.source])}</span>${escapeHtml(envText)}`;
            }
            const clear = form.querySelector(`[data-clear-secret="${name}"]`);
            if (clear) {
                clear.classList.toggle('hidden', !info.configured);
                clear.classList.toggle('flex', info.configured);
                clear.querySelector('input').checked = false;
            }
        });
    };

    const renderHealth = (health) => {
        const rows = [
            ['database', statusPill(health.database.status)],
            ['queue', `<small>${escapeHtml(health.queue.connection)} · ${health.queue.failed_jobs}</small>`],
            ['socket', statusPill(health.socket.status), health.socket.reason_message],
            ['mail', statusPill(health.mail.configured ? 'configured' : 'not_configured')],
            ['storage', statusPill(health.storage.status)],
        ];
        if (health.supervisor) rows.push(['supervisor', statusPill(health.supervisor.status)]);
        document.querySelector('#system-health').innerHTML = rows.map(([key, markup, hint]) =>
            `<div class="sa-health-row"><div><strong>${escapeHtml(i18n.health[key] || key)}</strong>${hint ? `<small>${escapeHtml(hint)}</small>` : ''}</div>${markup}</div>`,
        ).join('');
    };

    const load = async () => {
        const { data } = await dfApi(urls.index);
        const maintenance = data.maintenance;
        document.querySelector('#maintenance-enabled').checked = maintenance.enabled;
        document.querySelector('#maintenance-starts').value = toDateTimeLocal(maintenance.starts_at);
        document.querySelector('#maintenance-ends').value = toDateTimeLocal(maintenance.ends_at);
        renderHealth(data.health);
        renderConfigForm(document.querySelector('#mail-config-form'), data.mail_config);
        renderConfigForm(document.querySelector('#storage-config-form'), data.storage_config);
    };

    // Email / storage tabs (arrow keys move between tabs, the hash keeps the choice on reload).
    const configTabs = Array.from(document.querySelectorAll('[data-config-tabs] [role="tab"]'));
    const selectConfigTab = (name, focus = false) => {
        const tab = configTabs.find((candidate) => candidate.dataset.tab === name) || configTabs[0];
        configTabs.forEach((candidate) => {
            const selected = candidate === tab;
            candidate.setAttribute('aria-selected', String(selected));
            candidate.tabIndex = selected ? 0 : -1;
            document.getElementById(candidate.getAttribute('aria-controls')).hidden = !selected;
        });
        if (focus) tab.focus();
        return tab.dataset.tab;
    };
    if (configTabs.length) {
        configTabs.forEach((tab, index) => {
            tab.addEventListener('click', () => window.history.replaceState(null, '', `#${selectConfigTab(tab.dataset.tab)}`));
            tab.addEventListener('keydown', (event) => {
                if (!['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
                event.preventDefault();
                const next = configTabs[(index + (event.key === 'ArrowRight' ? 1 : -1) + configTabs.length) % configTabs.length];
                window.history.replaceState(null, '', `#${selectConfigTab(next.dataset.tab, true)}`);
            });
        });
        selectConfigTab(window.location.hash.slice(1));
        window.addEventListener('hashchange', () => selectConfigTab(window.location.hash.slice(1)));
    }

    document.querySelectorAll('[data-config-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            const errorBox = form.querySelector('[data-config-error]');
            errorBox.classList.add('hidden');
            const body = {};
            Array.from(form.elements).forEach((element) => {
                if (!element.name) return;
                if (element.type === 'checkbox') body[element.name] = element.checked;
                else if (element.type === 'number') body[element.name] = element.value === '' ? null : Number(element.value);
                else body[element.name] = element.value.trim() === '' ? null : element.value.trim();
            });
            try {
                const result = await dfApi(form.dataset.configForm, { method: 'PUT', body });
                pageNotice(result.message);
                await load();
            } catch (error) {
                errorBox.textContent = error.message;
                errorBox.classList.remove('hidden');
            } finally {
                restoreSubmitButton(form);
            }
        });
    });

    document.querySelector('#maintenance-form')?.addEventListener('submit', (event) => {
        event.preventDefault();
        const enabled = document.querySelector('#maintenance-enabled').checked;
        openSuperadminConfirm({
            message: enabled ? i18n.confirm_maintenance_enable : i18n.confirm_maintenance_disable,
            description: enabled ? i18n.maintenance_enable_description : i18n.maintenance_disable_description,
            confirmLabel: i18n.save_maintenance,
            confirmIcon: 'construction',
            tone: enabled ? 'warning' : 'info',
            icon: 'construction',
            onConfirm: async () => {
                await dfApi(urls.maintenance, {
                    method: 'PUT',
                    body: {
                        enabled,
                        starts_at: document.querySelector('#maintenance-starts').value || null,
                        ends_at: document.querySelector('#maintenance-ends').value || null,
                    },
                });
                pageNotice(i18n.maintenance_saved);
            },
        });
    });

    const mailTestForm = document.querySelector('#mail-test-form');
    mailTestForm?.addEventListener('submit', async (event) => {
        event.preventDefault();
        const errorBox = document.querySelector('#mail-test-modal-error');
        errorBox.classList.add('hidden');
        try {
            const result = await dfApi(urls.mail_test, {
                method: 'POST',
                body: { email: mailTestForm.email.value, message: mailTestForm.message.value },
            });
            mailTestForm.message.value = '';
            window.closeSuperadminModal('mail-test-modal');
            pageNotice(result.message);
        } catch (error) {
            // Keep the modal open with what was typed so the address can be corrected and resent.
            errorBox.textContent = error.message;
            errorBox.classList.remove('hidden');
        } finally {
            restoreSubmitButton(mailTestForm);
        }
    });

    const resetForm = document.querySelector('#reset-system-form');
    if (resetForm) {
        const resetSubmit = resetForm.querySelector('button[type="submit"]');
        // Only allow submitting once the phrase is typed exactly; the server re-checks it anyway.
        const syncResetSubmit = () => {
            resetSubmit.disabled = resetForm.phrase.value !== urls.reset_phrase || resetForm.password.value === '';
        };
        resetForm.phrase.addEventListener('input', syncResetSubmit);
        resetForm.password.addEventListener('input', syncResetSubmit);
        resetForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            const errorBox = document.querySelector('#reset-system-modal-error');
            errorBox.classList.add('hidden');
            try {
                await dfApi(urls.reset, {
                    method: 'POST',
                    body: { password: resetForm.password.value, phrase: resetForm.phrase.value },
                });
                resetForm.reset();
                window.closeSuperadminModal('reset-system-modal');
                pageNotice(i18n.reset_complete);
                load().catch(() => {});
            } catch (error) {
                errorBox.textContent = error.message;
                errorBox.classList.remove('hidden');
            } finally {
                restoreSubmitButton(resetForm);
                syncResetSubmit();
            }
        });
    }

    load().catch((error) => {
        pageNotice(error.message, 'error');
        document.querySelector('#system-health').innerHTML = emptyStateHtml('tpl-load-failed', error.message);
    });
}
