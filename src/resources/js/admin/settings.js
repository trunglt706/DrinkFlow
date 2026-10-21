import { formatMoney } from '../shared/money';
import { collectTagsInputs, initTagsInputs } from './tags-input';

/**
 * Admin Room Settings & Payment Accounts Controller
 */
export function initAdminSettings() {
    const $ = id => document.getElementById(id);
    initTagsInputs();
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const roomSlug = document.querySelector('meta[name="room-slug"]')?.content
        || document.querySelector('[data-room-slug]')?.dataset.roomSlug
        || window.__DF_ROOM_SLUG__
        || '';

    const settingsForm = $('room-settings-form');
    const paymentForm = $('payment-account-form');
    if (!settingsForm && !paymentForm) return;

    function showNotice(msg, type = 'success') {
        const el = $('notice');
        if (!el) return;
        el.textContent = msg;
        el.className = type === 'success'
            ? 'mb-4 rounded-xl px-4 py-3 text-xs font-medium bg-emerald-50 text-emerald-800 border border-emerald-200'
            : 'mb-4 rounded-xl px-4 py-3 text-xs font-medium bg-rose-50 text-rose-800 border border-rose-200';
        el.classList.remove('hidden');
        setTimeout(() => el.classList.add('hidden'), 4000);
    }

    function openModal(id) {
        const m = $(id);
        if (!m) return;
        m.classList.remove('hidden');
        m.classList.add('flex');
    }

    function closeModal(id) {
        const m = $(id);
        if (!m) return;
        m.classList.add('hidden');
        m.classList.remove('flex');
    }

    /* ── Template Tag Inserter ────────────────────────────────────────── */
    window.insertTag = function (tag) {
        const input = $('set-template');
        if (input) {
            input.value += ' ' + tag;
            input.focus();
        }
    };

    /* ── Currency Formatter ───────────────────────────────────────────── */
    function formatNumberWithDots(input) {
        if (!input) return;
        const raw = String(input.value || '').replace(/\D/g, '');
        if (!raw) {
            input.value = '';
            return;
        }
        input.value = raw.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    const budgetInput = $('set-max-budget');
    const debtInput = $('set-debt-ceiling');

    [budgetInput, debtInput].forEach(input => {
        if (input) {
            formatNumberWithDots(input);
            input.addEventListener('input', () => formatNumberWithDots(input));
            input.addEventListener('change', () => formatNumberWithDots(input));
        }
    });

    /* ── Numeric-Only Filter for Account Number (0-9) ─────────────────── */
    const accNumberInput = $('acc-number');
    if (accNumberInput) {
        accNumberInput.addEventListener('input', function () {
            this.value = this.value.replace(/\D/g, '');
        });
        accNumberInput.addEventListener('paste', function (e) {
            e.preventDefault();
            const paste = (e.clipboardData || window.clipboardData).getData('text');
            this.value = paste.replace(/\D/g, '');
        });
    }

    /* ── Submit Campaign Settings Form ────────────────────────────────── */
    settingsForm?.addEventListener('submit', async function (e) {
        e.preventDefault();

        const cleanNumber = (val) => {
            const raw = String(val || '').replace(/\D/g, '');
            return raw ? parseInt(raw, 10) : 0;
        };

        const payload = {
            campaign_title_template: $('set-template')?.value || '',
            max_campaign_budget: cleanNumber(budgetInput?.value),
            personal_debt_ceiling: cleanNumber(debtInput?.value),
            auto_lock_on_debt_limit: $('set-autolock-debt')?.checked ?? true,
            auto_debt_reminders: $('set-auto-debt-reminders')?.checked ?? true,
            is_public: $('set-room-public')?.checked ?? true,
            // Access rules (allowed email domains, allowed / blocked IPs); empty lists = no restriction.
            ...collectTagsInputs(settingsForm),
        };

        const submitBtn = $('save-campaign-settings-btn') || document.querySelector('button[type="submit"][form="room-settings-form"]');
        const originalSubmitContent = submitBtn ? submitBtn.innerHTML : '';
        const loadingText = settingsForm.dataset.loadingText || 'Loading...';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-70', 'cursor-not-allowed');
            submitBtn.innerHTML = `<span class="material-symbols-outlined animate-spin text-[16px]">progress_activity</span><span>${loadingText}</span>`;
        }

        try {
            const res = await fetch(`/admin/${roomSlug}/settings`, {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify(payload),
            });
            if (res.ok) {
                const okMsg = settingsForm.dataset.msgSuccess || 'Saved successfully';
                showNotice(okMsg);
            } else {
                // Show the first validation message (e.g. an invalid IP) instead of a generic error.
                const data = await res.json().catch(() => ({}));
                const firstError = data?.errors ? Object.values(data.errors).flat()[0] : null;
                const errMsg = firstError || data?.message || settingsForm.dataset.msgError || 'An error occurred';
                showNotice(errMsg, 'error');
            }
        } catch (err) {
            console.error(err);
            const errMsg = settingsForm.dataset.msgError || 'An error occurred';
            showNotice(errMsg, 'error');
        } finally {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-70', 'cursor-not-allowed');
                submitBtn.innerHTML = originalSubmitContent;
            }
        }
    });

    $('copy-room-join-link')?.addEventListener('click', async () => {
        const copyBtn = $('copy-room-join-link');
        const value = $('room-join-link')?.textContent?.trim() || '';
        try {
            await navigator.clipboard.writeText(value);
            const copiedMessage = copyBtn?.dataset.msgCopied || 'Copied!';
            if (window.notify) window.notify(copiedMessage, 'success');
            else showNotice(copiedMessage);
        } catch {
            const failedMessage = copyBtn?.dataset.msgFailed || 'Copy failed';
            if (window.notify) window.notify(failedMessage, 'error');
            else showNotice(failedMessage, 'error');
        }
    });

    $('set-room-public')?.addEventListener('change', (event) => {
        const hint = $('room-public-hint');
        if (hint) {
            hint.textContent = event.target.checked
                ? (hint.dataset.hintPublic || '')
                : (hint.dataset.hintPrivate || '');
        }
    });

    /* ── Payment Account Create / Edit Modals ─────────────────────────── */
    window.openCreateAccountModal = function () {
        paymentForm?.reset();
        const idInput = $('payment-account-id');
        if (idInput) idInput.value = '';
        const modalTitle = $('payment-modal-title');
        if (modalTitle) modalTitle.textContent = modalTitle.dataset.titleCreate || 'Add New Account';
        const bankSelect = $('acc-bank-code');
        if (bankSelect) {
            bankSelect.selectedIndex = 0;
            bankSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }
        openModal('payment-account-modal');
    };

    window.openEditAccountModal = function (id, bankCode, bankName, accountNumber, accountName, isDefault) {
        const idInput = $('payment-account-id');
        if (idInput) idInput.value = id;
        const bankSelect = $('acc-bank-code');
        if (bankSelect) {
            bankSelect.value = bankCode;
            bankSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }
        const numInput = $('acc-number');
        if (numInput) numInput.value = accountNumber;
        const nameInput = $('acc-name');
        if (nameInput) nameInput.value = accountName;
        const defInput = $('acc-default');
        if (defInput) defInput.checked = isDefault;
        const modalTitle = $('payment-modal-title');
        if (modalTitle) modalTitle.textContent = modalTitle.dataset.titleEdit || 'Edit Account';
        openModal('payment-account-modal');
    };

    window.closeAccountModal = function () {
        closeModal('payment-account-modal');
    };

    /* Form submit: Create or Update Payment Account with Submit Loading */
    paymentForm?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const accountId = $('payment-account-id')?.value;
        const bankSelect = $('acc-bank-code');
        const bankCode = bankSelect?.value;
        const bankName = bankSelect?.options[bankSelect.selectedIndex]?.dataset.bankName ?? bankCode;
        const body = JSON.stringify({
            bank_code: bankCode,
            bank_name: bankName,
            account_number: $('acc-number')?.value.trim(),
            account_name: $('acc-name')?.value.trim().toUpperCase(),
            is_default: $('acc-default')?.checked ?? false,
        });

        const url = `/admin/${roomSlug}/payment-accounts` + (accountId ? '/' + accountId : '');
        const method = accountId ? 'PATCH' : 'POST';

        const submitBtn = $('payment-submit-btn');
        const originalSubmitContent = submitBtn ? submitBtn.innerHTML : '';
        const loadingText = paymentForm.dataset.loadingText || 'Loading...';
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.classList.add('opacity-70', 'cursor-not-allowed');
            submitBtn.innerHTML = `<span class="material-symbols-outlined animate-spin text-[16px]">progress_activity</span><span>${loadingText}</span>`;
        }

        let isSuccess = false;
        try {
            const res = await fetch(url, {
                method,
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                body,
            });
            const json = await res.json();
            if (!res.ok) {
                const msgs = json.errors ? Object.values(json.errors).flat().join(' ') : (json.message ?? (paymentForm.dataset.msgError || 'Error'));
                showNotice(msgs, 'error');
                return;
            }
            isSuccess = true;
            window.closeAccountModal();
            const successMsg = accountId
                ? (paymentForm.dataset.msgUpdated || 'Account updated')
                : (paymentForm.dataset.msgCreated || 'Account created');
            showNotice(successMsg);
            setTimeout(() => location.reload(), 700);
        } catch {
            showNotice(paymentForm.dataset.msgError || 'Error', 'error');
        } finally {
            if (submitBtn && !isSuccess) {
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-70', 'cursor-not-allowed');
                submitBtn.innerHTML = originalSubmitContent;
            }
        }
    });

    /* ── QR Modal (Snake Border 4.8s) ─────────────────────────────────── */
    function setQrState(state) {
        $('qr-loading')?.classList.toggle('hidden', state !== 'loading');
        $('qr-canvas-wrap')?.classList.toggle('hidden', state !== 'ready');
        $('qr-error')?.classList.toggle('hidden', state !== 'error');
        if (state === 'ready') {
            $('qr-canvas-wrap')?.classList.add('flex');
        }
    }

    function stopSnake() {
        $('qr-snake-rect')?.classList.remove('running');
    }
    function startSnake() {
        $('qr-snake-rect')?.classList.add('running');
    }

    function loadQrLibrary() {
        if (window.QRCode?.toCanvas) return Promise.resolve(window.QRCode);
        if (window.qrcode?.toCanvas) return Promise.resolve(window.qrcode);

        return new Promise((resolve) => {
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/qrcode@1.4.4/build/qrcode.min.js';
            script.onload = () => {
                const library = window.QRCode?.toCanvas ? window.QRCode : window.qrcode;
                resolve(library || null);
            };
            script.onerror = () => resolve(null);
            document.head.appendChild(script);
        });
    }

    async function openQrModal(accountId, endpointUrl) {
        openModal('payment-qr-modal');
        setQrState('loading');
        stopSnake();

        try {
            const res = await fetch(endpointUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } });
            const json = await res.json();
            const d = json.data;

            if (!d) throw new Error('no_data');

            const badge = $('qr-bank-badge');
            if (badge) badge.textContent = d.bank_name || d.bank_code;
            const accName = $('qr-acc-name');
            if (accName) accName.textContent = d.account_name || '';
            const accNum = $('qr-acc-number');
            if (accNum) accNum.textContent = d.account_number || '';

            const amountLabel = $('qr-amount-label');
            if (d.amount && d.amount > 0) {
                if (amountLabel) {
                    amountLabel.textContent = formatMoney(d.amount);
                    amountLabel.classList.remove('hidden');
                }
            } else {
                amountLabel?.classList.add('hidden');
            }

            const descWrap = $('qr-desc-wrap');
            const descVal = $('qr-desc-value');
            if (d.description) {
                if (descVal) descVal.textContent = d.description;
                descWrap?.classList.remove('hidden');
            } else {
                descWrap?.classList.add('hidden');
            }

            setQrState('ready');
            startSnake();

            if (d.payload) {
                const qrLibrary = await loadQrLibrary();
                const canvas = $('qr-canvas');
                if (!qrLibrary || !canvas) throw new Error('qr_library_unavailable');

                await new Promise((resolve, reject) => {
                    qrLibrary.toCanvas(canvas, d.payload, {
                        width: 220,
                        margin: 1,
                        errorCorrectionLevel: 'M',
                        color: { dark: '#000000', light: '#ffffff' },
                    }, (error) => (error ? reject(error) : resolve()));
                });
                canvas.classList.remove('hidden');
            }
        } catch (err) {
            console.error('QR fetch error:', err);
            setQrState('error');
        }
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-qr-btn]');
        if (btn) {
            openQrModal(btn.dataset.accountId, btn.dataset.qrEndpoint);
        }
    });

    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-close-qr]') || (e.target.id === 'payment-qr-modal')) {
            closeModal('payment-qr-modal');
            stopSnake();
        }
    });

    /* ── Delete Confirmation Modal ────────────────────────────────────── */
    let pendingDeleteId = null;

    window.openDeleteAccountModal = function (id) {
        pendingDeleteId = id;
        openModal('payment-delete-modal');
    };

    document.addEventListener('click', function (e) {
        if (e.target.closest('[data-close-delete]') || e.target.id === 'payment-delete-modal') {
            closeModal('payment-delete-modal');
            pendingDeleteId = null;
        }
    });

    $('confirm-payment-delete')?.addEventListener('click', async function () {
        if (!pendingDeleteId) return;
        const targetId = pendingDeleteId;
        closeModal('payment-delete-modal');
        pendingDeleteId = null;

        const deleteModal = $('payment-delete-modal');
        try {
            const res = await fetch(
                `/admin/${roomSlug}/payment-accounts/${targetId}`,
                { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' } }
            );
            const json = await res.json();
            if (json.data?.deleted) {
                showNotice(deleteModal?.dataset.msgDeleted || 'Account deleted');
                setTimeout(() => location.reload(), 700);
            } else {
                showNotice(json.message ?? (deleteModal?.dataset.msgError || 'Error'), 'error');
            }
        } catch {
            showNotice(deleteModal?.dataset.msgError || 'Error', 'error');
        }
    });
}
