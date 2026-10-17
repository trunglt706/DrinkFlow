import { renderSubmitLoading } from '../shared/submit-loading';
import { formatMoney } from '../shared/money';

/**
 * Admin Debts & Split Billing Controller
 */
export function initAdminDebts() {
    const searchInput = document.querySelector('#debt-search');
    const filterForm = document.querySelector('#debts-filter-form');
    const modal = document.querySelector('#debt-modal');
    const modalBackdrop = document.querySelector('#debt-backdrop');
    const modalBody = document.querySelector('#debt-modal-body');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const roomSlug = document.querySelector('[data-room-slug]')?.dataset.roomSlug || window.__DF_ROOM_SLUG__ || '';
    const formatMoneyInput = (input) => {
        if (!input) return;
        const digits = String(input.value || '').replace(/[^0-9]/g, '');
        input.value = digits ? Number(digits).toLocaleString('vi-VN') : '';
    };
    const numericValue = (selector) => Number(String(document.querySelector(selector)?.value || '').replace(/[^0-9]/g, '')) || 0;

    if (!filterForm && !modal) return;

    const i18n = JSON.parse(modal?.dataset.i18n || '{}');
    const t = (key, replacements = {}) => Object.entries(replacements).reduce(
        (text, [name, value]) => text.replaceAll(`:${name}`, value),
        i18n[key] || '',
    );
    const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (char) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    })[char]);

    const closeDebtModal = () => {
        modal?.classList.add('hidden');
        modal?.classList.remove('flex');
    };
    window.closeDebtModal = closeDebtModal;
    modalBackdrop?.addEventListener('click', closeDebtModal);

    searchInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            filterForm?.requestSubmit();
        }
    });
    searchInput?.addEventListener('admin:search-cleared', () => filterForm?.requestSubmit());

    // ── Record payment ("Thu tiền") & adjust ("Điều chỉnh") modal ─────────
    const modalTitle = document.querySelector('#debt-modal-title');
    const modalSubtitle = document.querySelector('#debt-modal-subtitle');
    const modalIcon = document.querySelector('#debt-modal-icon');
    const modalIconWrap = document.querySelector('#debt-modal-icon-wrap');
    const ICON_TONES = {
        payment: 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20',
        adjust: 'bg-primary/10 text-primary border-primary/20',
    };

    /**
     * Fill the modal header and show it.
     *
     * @param {'payment'|'adjust'} kind Which form is shown.
     * @param {string} memberName Debtor's name.
     * @param {string} campaignName Origin campaign name.
     * @returns {void}
     */
    const showDebtModal = (kind, memberName, campaignName) => {
        if (modalTitle) modalTitle.textContent = t(kind === 'payment' ? 'confirmTitle' : 'adjustTitle');
        if (modalSubtitle) modalSubtitle.textContent = [memberName, campaignName].filter(Boolean).join(' · ');
        if (modalIcon) modalIcon.textContent = kind === 'payment' ? 'payments' : 'tune';
        if (modalIconWrap) modalIconWrap.className = `w-10 h-10 shrink-0 rounded-xl flex items-center justify-center border ${ICON_TONES[kind]}`;
        modal?.classList.remove('hidden');
        modal?.classList.add('flex');
    };

    const inputClass = 'w-full h-10 px-3 bg-surface border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-colors';

    /** Summary card with the current outstanding amount and the amount left after the action. */
    const summaryCard = (remaining, afterLabel) => `
        <div class="grid grid-cols-2 gap-2">
            <div class="rounded-xl border border-amber-200 bg-amber-50/70 px-3.5 py-3 dark:border-amber-800 dark:bg-amber-950/30">
                <div class="text-[10px] font-semibold uppercase tracking-wider text-amber-700 dark:text-amber-300">${escapeHtml(t('remainingLabel'))}</div>
                <div class="mt-0.5 font-mono text-lg font-bold text-amber-700 dark:text-amber-300">${escapeHtml(formatMoney(remaining))}</div>
            </div>
            <div class="rounded-xl border border-outline-variant/70 bg-surface-container-low px-3.5 py-3">
                <div class="text-[10px] font-semibold uppercase tracking-wider text-outline">${escapeHtml(afterLabel)}</div>
                <div class="mt-0.5 font-mono text-lg font-bold text-on-surface" data-debt-after>—</div>
            </div>
        </div>`;

    /** Amount field with the currency suffix and optional quick-fill button. */
    const amountField = (id, label, value, quickLabel = '') => `
        <div data-amount-field>
            <div class="flex items-center justify-between mb-1.5">
                <label for="${id}" class="font-semibold text-on-surface">${escapeHtml(label)} <span class="text-error">*</span></label>
                ${quickLabel ? `<button type="button" data-fill-amount class="text-[11px] font-semibold text-primary hover:underline">${escapeHtml(quickLabel)}</button>` : ''}
            </div>
            <div class="relative">
                <input type="text" inputmode="numeric" id="${id}" value="${Number(value).toLocaleString('vi-VN')}" autocomplete="off"
                    class="${inputClass} pr-9 font-mono font-bold text-base text-primary" required>
                <span class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 font-mono font-bold text-outline">đ</span>
            </div>
        </div>`;

    /** Card-style radio group (payment method / adjustment type). */
    const choiceGroup = (name, legend, options, columns) => `
        <fieldset>
            <legend class="font-semibold text-on-surface mb-1.5">${escapeHtml(legend)} <span class="text-error">*</span></legend>
            <div class="grid ${columns} gap-2">
                ${options.map((option, index) => `
                    <label class="relative flex cursor-pointer items-start gap-2.5 rounded-xl border border-outline-variant bg-surface px-3 py-2.5 transition-colors hover:border-primary/50 has-[:checked]:border-primary has-[:checked]:bg-primary/5 has-[:checked]:ring-2 has-[:checked]:ring-primary/15">
                        <input type="radio" name="${name}" value="${option.value}" class="sr-only peer" ${index === 0 ? 'checked' : ''}>
                        <span class="material-symbols-outlined text-[20px] text-outline peer-checked:text-primary" aria-hidden="true">${option.icon}</span>
                        <span class="min-w-0">
                            <span class="block font-semibold text-on-surface">${escapeHtml(option.label)}</span>
                            ${option.hint ? `<span class="block text-[11px] text-outline leading-snug mt-0.5">${escapeHtml(option.hint)}</span>` : ''}
                        </span>
                    </label>`).join('')}
            </div>
        </fieldset>`;

    const errorBox = '<p data-debt-form-error role="alert" class="hidden rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-rose-700 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300"></p>';

    const footer = (submitIcon, submitLabel, submitClass) => `
        <div class="flex items-center justify-end gap-2 border-t border-outline-variant/60 bg-surface-container-low/60 px-5 py-3.5">
            <button type="button" onclick="closeDebtModal()" class="px-4 py-2 rounded-xl border border-outline-variant text-on-surface font-semibold hover:bg-surface-container transition-colors">${escapeHtml(t('cancel'))}</button>
            <button type="submit" class="px-4 py-2 rounded-xl text-white font-semibold inline-flex items-center gap-1.5 shadow-xs transition-colors disabled:opacity-60 ${submitClass}">
                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">${submitIcon}</span>${escapeHtml(submitLabel)}
            </button>
        </div>`;

    /**
     * Submit a debt form as JSON, showing errors inline and keeping the loading state until reload.
     *
     * @param {HTMLFormElement} form Submitted form.
     * @param {string} url Endpoint.
     * @param {object} body JSON payload.
     * @param {string} fallbackError Message when the server gives none.
     * @returns {Promise<void>}
     */
    const submitDebtForm = async (form, url, body, fallbackError) => {
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalHtml = submitBtn?.innerHTML || '';
        const showError = (message) => {
            const box = form.querySelector('[data-debt-form-error]');
            if (!box) return;
            box.textContent = message || '';
            box.classList.toggle('hidden', !message);
        };
        showError('');
        if (submitBtn) {
            submitBtn.disabled = true;
            renderSubmitLoading(submitBtn);
        }
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify(body),
            });
            if (res.ok) {
                window.location.reload();
                return;
            }
            const payload = await res.json().catch(() => ({}));
            showError(Object.values(payload.errors || {}).flat()[0] || payload.message || fallbackError);
        } catch (error) {
            console.error(error);
            showError(t('serverError'));
        }
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalHtml;
        }
    };

    const formError = (form, message) => {
        const box = form.querySelector('[data-debt-form-error]');
        if (!box) return;
        box.textContent = message;
        box.classList.remove('hidden');
    };

    window.openRecordPaymentModal = function(debtId, remaining, memberName, campaignName = '') {
        remaining = Number(remaining) || 0;
        if (modalBody) {
            modalBody.innerHTML = `
                <form id="record-pay-form" class="text-xs" novalidate>
                    <div class="space-y-4 p-5">
                        ${summaryCard(remaining, t('afterPaymentLabel'))}
                        ${amountField('pay-amount', t('paymentAmount'), remaining, t('payFull'))}
                        ${choiceGroup('pay-method', t('paymentMethod'), [
                            { value: 'vietqr', icon: 'qr_code_2', label: t('methodVietqr') },
                            { value: 'cash', icon: 'payments', label: t('methodCash') },
                            { value: 'room_fund', icon: 'savings', label: t('methodRoomFund') },
                        ], 'grid-cols-3')}
                        <div>
                            <label for="pay-ref" class="block font-semibold text-on-surface mb-1.5">${escapeHtml(t('reference'))}</label>
                            <input type="text" id="pay-ref" maxlength="120" placeholder="${escapeHtml(t('referencePlaceholder'))}" class="${inputClass}">
                        </div>
                        ${errorBox}
                    </div>
                    ${footer('payments', t('confirmPayment'), 'bg-emerald-600 hover:bg-emerald-700')}
                </form>`;
        }
        showDebtModal('payment', memberName, campaignName);

        const form = document.querySelector('#record-pay-form');
        const amountInput = form?.querySelector('#pay-amount');
        const afterEl = form?.querySelector('[data-debt-after]');
        const refresh = () => {
            const amount = numericValue('#pay-amount');
            if (afterEl) afterEl.textContent = formatMoney(Math.max(0, remaining - amount));
        };
        amountInput?.addEventListener('input', (event) => {
            formatMoneyInput(event.target);
            refresh();
        });
        form?.querySelector('[data-fill-amount]')?.addEventListener('click', () => {
            amountInput.value = remaining.toLocaleString('vi-VN');
            refresh();
        });
        refresh();
        amountInput?.focus();
        amountInput?.select();

        form?.addEventListener('submit', (event) => {
            event.preventDefault();
            const amount = numericValue('#pay-amount');
            if (amount <= 0) return formError(form, t('invalidAmount'));
            if (amount > remaining) return formError(form, t('amountExceeds', { max: remaining.toLocaleString('vi-VN') }));
            submitDebtForm(form, `/admin/${roomSlug}/debts/${debtId}/payments`, {
                amount,
                payment_method: form.querySelector('input[name="pay-method"]:checked')?.value,
                reference: form.querySelector('#pay-ref')?.value.trim() || null,
            }, t('paymentError'));
        });
    };

    window.openAdjustDebtModal = function(debtId, remaining, memberName, campaignName = '') {
        remaining = Number(remaining) || 0;
        if (modalBody) {
            modalBody.innerHTML = `
                <form id="adjust-debt-form" class="text-xs" novalidate>
                    <div class="space-y-4 p-5">
                        ${summaryCard(remaining, t('afterAdjustLabel'))}
                        ${choiceGroup('adj-type', t('adjustType'), [
                            { value: 'decrease', icon: 'trending_down', label: t('adjustDecrease'), hint: t('adjustDecreaseHint') },
                            { value: 'increase', icon: 'trending_up', label: t('adjustIncrease'), hint: t('adjustIncreaseHint') },
                            { value: 'waive', icon: 'volunteer_activism', label: t('adjustWaive'), hint: t('adjustWaiveHint') },
                            { value: 'correction', icon: 'edit_note', label: t('adjustCorrection'), hint: t('adjustCorrectionHint') },
                        ], 'grid-cols-2')}
                        ${amountField('adj-amount', t('amount'), 0)}
                        <div>
                            <label for="adj-reason" class="block font-semibold text-on-surface mb-1.5">${escapeHtml(t('reason'))} <span class="text-error">*</span></label>
                            <textarea id="adj-reason" rows="2" maxlength="255" placeholder="${escapeHtml(t('reasonPlaceholder'))}" class="w-full p-3 bg-surface border border-outline-variant rounded-xl text-on-surface focus:outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 transition-colors" required></textarea>
                        </div>
                        ${errorBox}
                    </div>
                    ${footer('save', t('saveAdjustment'), 'bg-primary hover:bg-primary/90')}
                </form>`;
        }
        showDebtModal('adjust', memberName, campaignName);

        const form = document.querySelector('#adjust-debt-form');
        const amountWrap = form?.querySelector('[data-amount-field]');
        const afterEl = form?.querySelector('[data-debt-after]');
        const selectedType = () => form?.querySelector('input[name="adj-type"]:checked')?.value || 'decrease';
        // Mirrors AdjustDebtAction: the new outstanding amount for each adjustment type.
        const afterAmount = (type, amount) => ({
            decrease: remaining - amount,
            increase: remaining + amount,
            waive: 0,
            correction: amount,
        })[type];
        const refresh = () => {
            const type = selectedType();
            // Waiving clears the whole remaining amount, so no amount is entered.
            amountWrap?.classList.toggle('hidden', type === 'waive');
            const after = afterAmount(type, numericValue('#adj-amount'));
            if (afterEl) {
                afterEl.textContent = after < 0 ? '—' : formatMoney(after);
                afterEl.classList.toggle('text-rose-600', after < 0);
            }
        };
        form?.querySelectorAll('input[name="adj-type"]').forEach((input) => input.addEventListener('change', refresh));
        form?.querySelector('#adj-amount')?.addEventListener('input', (event) => {
            formatMoneyInput(event.target);
            refresh();
        });
        refresh();

        form?.addEventListener('submit', (event) => {
            event.preventDefault();
            const type = selectedType();
            const amount = type === 'waive' ? 0 : numericValue('#adj-amount');
            const reason = form.querySelector('#adj-reason')?.value.trim() || '';
            if (type !== 'waive' && type !== 'correction' && amount <= 0) return formError(form, t('invalidAmount'));
            if (afterAmount(type, amount) < 0) return formError(form, t('adjustNegative'));
            if (!reason) return formError(form, t('reasonRequired'));
            submitDebtForm(form, `/admin/${roomSlug}/debts/${debtId}/adjust`, { type, amount, reason }, t('adjustError'));
        });
    };

    // ── Debt Detail Modal (read-only) ─────────────────────────────────────
    const detailModal = document.querySelector('#debt-detail-modal');
    const closeDetailModal = () => {
        detailModal?.classList.add('hidden');
        detailModal?.classList.remove('flex');
    };
    const renderDetailList = (name, rows, renderRow, emptyText) => {
        const container = detailModal?.querySelector(`[data-debt-detail-list="${name}"]`);
        if (!container) return;
        container.innerHTML = rows.length
            ? rows.map(renderRow).join('')
            : `<div class="px-3 py-3 text-[11px] text-outline">${escapeHtml(emptyText)}</div>`;
    };
    /** Show one tab of the detail modal ("basic" info or payment/adjustment "history"). */
    const activeTabClasses = ['border-primary', 'text-primary'];
    const inactiveTabClasses = ['border-transparent', 'text-outline', 'hover:text-on-surface'];
    const switchDetailTab = (name) => {
        detailModal?.querySelectorAll('[data-debt-detail-tab]').forEach((tab) => {
            const active = tab.dataset.debtDetailTab === name;
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
            tab.classList.remove(...(active ? inactiveTabClasses : activeTabClasses));
            tab.classList.add(...(active ? activeTabClasses : inactiveTabClasses));
        });
        detailModal?.querySelectorAll('[data-debt-detail-panel]').forEach((panel) => {
            panel.classList.toggle('hidden', panel.dataset.debtDetailPanel !== name);
        });
    };
    detailModal?.querySelectorAll('[data-debt-detail-tab]').forEach((tab) => {
        tab.addEventListener('click', () => switchDetailTab(tab.dataset.debtDetailTab));
    });
    const openDebtDetail = (detail) => {
        if (!detailModal) return;
        switchDetailTab('basic');
        const historyCount = detailModal.querySelector('[data-debt-detail-history-count]');
        if (historyCount) historyCount.textContent = String((detail.payments || []).length + (detail.adjustments || []).length);
        detailModal.querySelectorAll('[data-debt-detail-field]').forEach((el) => {
            el.textContent = detail[el.dataset.debtDetailField] || (el.dataset.debtDetailField === 'sponsor_type' ? '' : '—');
        });
        // Optional blocks are hidden when they carry no value.
        detailModal.querySelectorAll('[data-debt-detail-row]').forEach((el) => {
            el.classList.toggle('hidden', !detail[el.dataset.debtDetailRow]);
        });
        renderDetailList('payments', detail.payments || [], (payment) => `
            <div class="px-3 py-2.5 flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="font-semibold text-on-surface">${escapeHtml(payment.method)}</div>
                    <div class="text-[11px] text-outline">${escapeHtml(payment.paid_at)}${payment.reference ? ' · ' + escapeHtml(payment.reference) : ''}</div>
                </div>
                <div class="font-mono font-bold text-emerald-700 shrink-0">${escapeHtml(payment.amount)}</div>
            </div>`, i18n.noPayments || '');
        renderDetailList('adjustments', detail.adjustments || [], (adjustment) => `
            <div class="px-3 py-2.5 flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <div class="font-semibold text-on-surface">${escapeHtml(adjustment.type)}</div>
                    <div class="text-[11px] text-outline">${escapeHtml(adjustment.created_at)}${adjustment.reason ? ' · ' + escapeHtml(adjustment.reason) : ''}</div>
                </div>
                <div class="text-right shrink-0">
                    <div class="font-mono font-bold text-on-surface">${escapeHtml(adjustment.amount)}</div>
                    <div class="text-[11px] font-mono text-outline">${escapeHtml(adjustment.before)} → ${escapeHtml(adjustment.after)}</div>
                </div>
            </div>`, i18n.noAdjustments || '');
        detailModal.classList.remove('hidden');
        detailModal.classList.add('flex');
    };
    document.addEventListener('click', (event) => {
        const openButton = event.target instanceof Element ? event.target.closest('[data-open-debt-detail]') : null;
        if (openButton) {
            try {
                openDebtDetail(JSON.parse(openButton.dataset.debtDetail || '{}'));
            } catch (error) {
                console.error(error);
            }
            return;
        }
        if (event.target instanceof Element && (event.target.closest('[data-debt-detail-close]') || event.target.id === 'debt-detail-backdrop')) {
            closeDetailModal();
        }
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && detailModal && !detailModal.classList.contains('hidden')) closeDetailModal();
    });
    // Applying a date range submits the filter form (same behavior as the audit log page).
    document.addEventListener('admin:daterange-change', (event) => {
        if (event.target?.id !== 'debt-date-range') return;
        filterForm?.requestSubmit();
    });
    document.querySelector('#debt-user-filter')?.addEventListener('change', () => filterForm?.requestSubmit());

    // ── Approve Payment Detail Modal ──────────────────────────────────────
    const approveModal    = document.querySelector('#approve-debt-modal');
    const approveBackdrop = document.querySelector('#approve-debt-backdrop');
    const approveClose    = document.querySelector('#approve-modal-close');
    const approveCancel   = document.querySelector('#approve-modal-cancel');
    const approveConfirm  = document.querySelector('#approve-modal-confirm');
    const approveConfirmText = document.querySelector('#approve-modal-confirm-text');

    /**
     * Open the Approve Payment detail modal and fill it with debt data.
     *
     * @param {Object} data – Debt info passed from Blade inline call.
     */
    window.openApproveDebtModal = function(data) {
        if (!approveModal) return;

        const isPayAll = Boolean(data.isPayAll);
        const titleEl = document.querySelector('#approve-modal-title');
        const subtitleEl = document.querySelector('#approve-modal-subtitle');
        const badgeTextEl = document.querySelector('#approve-modal-badge-text');
        const statusBadgeEl = document.querySelector('#approve-modal-status-badge');
        const breakdownContainer = document.querySelector('#approve-modal-breakdown-container');
        const breakdownList = document.querySelector('#approve-modal-breakdown-list');
        const breakdownCount = document.querySelector('#approve-modal-breakdown-count');
        const amountLabelEl = document.querySelector('#approve-modal-amount-label');
        const singleInfoGrid = document.querySelector('#approve-modal-single-info');

        // Fill member info
        const memberEl = document.querySelector('#approve-modal-member');
        const memberMetaEl = document.querySelector('#approve-modal-member-meta');
        if (memberEl) memberEl.textContent = data.member || '—';
        if (memberMetaEl) {
            const parts = [];
            if (data.memberEmail) parts.push(data.memberEmail);
            if (data.memberCode) parts.push(data.memberCode);
            memberMetaEl.textContent = parts.join(' · ') || '—';
        }

        // Fill time – prefer updatedAt (time member submitted request), fallback to createdAt
        const timeEl = document.querySelector('#approve-modal-time');
        if (timeEl) timeEl.textContent = data.updatedAt || data.createdAt || '—';

        // Fill campaign
        const campaignEl = document.querySelector('#approve-modal-campaign');
        if (campaignEl) campaignEl.textContent = data.campaign || '—';

        // Fill transfer content/note
        const contentEl = document.querySelector('#approve-modal-content');
        if (contentEl) contentEl.textContent = data.transferContent || '—';

        // Fill amount
        const amountEl = document.querySelector('#approve-modal-amount');
        if (amountEl) {
            amountEl.textContent = formatMoney(data.amount);
        }

        // Bind debt id to confirm button
        if (approveConfirm) {
            approveConfirm.dataset.debtId = data.id;
            approveConfirm.dataset.isPayAll = isPayAll ? '1' : '0';
        }

        if (isPayAll) {
            if (titleEl) titleEl.textContent = t('approveAllTitle');
            if (subtitleEl) subtitleEl.textContent = t('approveAllSubtitle');
            if (badgeTextEl) badgeTextEl.textContent = t('approveAllBadge');
            if (statusBadgeEl) {
                statusBadgeEl.className = 'inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold border bg-purple-100 text-purple-900 border-purple-300 shrink-0';
            }
            if (amountLabelEl) amountLabelEl.textContent = t('approveAllAmountLabel');
            if (singleInfoGrid) singleInfoGrid.classList.add('hidden');

            // Render debts breakdown
            if (breakdownContainer && breakdownList) {
                breakdownContainer.classList.remove('hidden');
                const debts = data.pendingDebts || [];
                if (breakdownCount) breakdownCount.textContent = t('debtsCount', { count: debts.length });
                breakdownList.innerHTML = debts.map(d => `
                    <div class="p-2.5 flex items-center justify-between text-xs hover:bg-surface-container transition-colors">
                        <div class="flex flex-col min-w-0 pr-2">
                            <div class="flex items-center gap-1.5 font-semibold text-on-surface">
                                <span class="font-mono text-[11px] text-primary font-bold">${escapeHtml(d.code || 'N/A')}</span>
                                <span class="text-outline-variant">•</span>
                                <span class="truncate">${escapeHtml(d.campaign || 'N/A')}</span>
                            </div>
                            ${d.note ? `<div class="text-[11px] text-outline truncate italic mt-0.5">${escapeHtml(d.note)}</div>` : ''}
                        </div>
                        <span class="font-mono font-bold text-amber-600 shrink-0 text-sm">${formatMoney(d.amount)}</span>
                    </div>
                `).join('') || `<div class="p-3 text-center text-outline">${escapeHtml(t('noDebts'))}</div>`;
            }

            if (approveConfirmText) {
                const count = (data.pendingDebts || []).length;
                approveConfirmText.textContent = t('approveAllConfirm', { count });
            }
        } else {
            if (titleEl) titleEl.textContent = t('approveTitle');
            if (subtitleEl) subtitleEl.textContent = t('approveSubtitle');
            if (badgeTextEl) badgeTextEl.textContent = t('approveBadge');
            if (statusBadgeEl) {
                statusBadgeEl.className = 'inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-bold border bg-amber-100 text-amber-900 border-amber-300 shrink-0';
            }
            if (amountLabelEl) amountLabelEl.textContent = t('approveAmountLabel');
            if (singleInfoGrid) singleInfoGrid.classList.remove('hidden');
            if (breakdownContainer) breakdownContainer.classList.add('hidden');
            if (approveConfirmText) approveConfirmText.textContent = t('approveConfirm');
        }

        // Show modal
        approveModal.classList.remove('hidden');
        approveModal.classList.add('flex');
    };

    function closeApproveModal() {
        approveModal?.classList.add('hidden');
        approveModal?.classList.remove('flex');
    }

    approveBackdrop?.addEventListener('click', closeApproveModal);
    approveClose?.addEventListener('click', closeApproveModal);
    approveCancel?.addEventListener('click', closeApproveModal);

    // ESC key closes approve modal
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') closeApproveModal();
    });

    approveConfirm?.addEventListener('click', async () => {
        const debtId = approveConfirm.dataset.debtId;
        const isPayAll = approveConfirm.dataset.isPayAll === '1';
        if (!debtId) return;

        // Loading state
        const origText = approveConfirmText ? approveConfirmText.textContent : '';
        if (approveConfirmText) approveConfirmText.textContent = '...';
        approveConfirm.disabled = true;
        approveConfirm.classList.add('opacity-75', 'cursor-not-allowed');

        try {
            const res = await fetch(`/admin/${roomSlug}/debts/${debtId}/approve`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
            });
            const data = await res.json();

            if (res.ok) {
                closeApproveModal();
                if (isPayAll) {
                    // For full debt settlement, refresh to update all counters and table rows
                    window.location.reload();
                } else {
                    // Optimistically update the single row on the page
                    const row = document.querySelector(`[data-debt-id="${debtId}"]`);
                    if (row) {
                        // Update status badge cell
                        const statusCell = row.querySelector('td:nth-child(3) span');
                        if (statusCell) {
                            statusCell.className = 'inline-flex items-center px-2.5 py-0.5 rounded-full whitespace-nowrap text-[11px] font-semibold border bg-emerald-50 text-emerald-700 border-emerald-200';
                            statusCell.textContent = `✓ ${t('statusPaid')}`;
                        }
                        // Update remaining amount cell
                        const amtCell = row.querySelector('td:nth-child(5)');
                        if (amtCell) {
                            amtCell.className = 'py-3.5 px-4 text-right font-mono font-bold text-sm text-emerald-600';
                            amtCell.textContent = formatMoney(0);
                        }
                        // Update action cell
                        const actCell = row.querySelector('td:nth-child(6) div');
                        if (actCell) {
                            actCell.innerHTML = `<span class="text-[11px] text-emerald-700 font-semibold flex items-center gap-0.5">
                                <span class="material-symbols-outlined text-[14px]">verified</span>
                                <span>${escapeHtml(t('settled'))}</span>
                            </span>`;
                        }
                        row.dataset.status = 'paid';
                    } else {
                        // Fallback reload
                        window.location.reload();
                    }
                }
            } else {
                alert(data.message || t('paymentError'));
                // Restore button
                if (approveConfirmText) approveConfirmText.textContent = origText;
                approveConfirm.disabled = false;
                approveConfirm.classList.remove('opacity-75', 'cursor-not-allowed');
            }
        } catch (e) {
            console.error(e);
            alert(t('serverError'));
            if (approveConfirmText) approveConfirmText.textContent = origText;
            approveConfirm.disabled = false;
            approveConfirm.classList.remove('opacity-75', 'cursor-not-allowed');
        }
    });

    initPaymentRequestModal(csrfToken, escapeHtml);

    window.exportDebtCSV = function() {
        window.location.assign(`/admin/${roomSlug}/debts/export`);
    };
}

/**
 * Review modal for consolidated payment requests: shows the captured debts and lets the admin
 * approve or reject a pending request. The server re-checks status and balances on every call.
 *
 * @param {string} csrfToken CSRF token for the POST requests.
 * @param {(value: unknown) => string} escapeHtml HTML escaper.
 * @returns {void}
 */
function initPaymentRequestModal(csrfToken, escapeHtml) {
    const modal = document.querySelector('#payment-request-modal');
    if (!modal) return;

    const i18n = JSON.parse(modal.dataset.i18n || '{}');
    const t = (key, replacements = {}) => Object.entries(replacements).reduce(
        (text, [name, value]) => text.replaceAll(`:${name}`, value),
        i18n[key] || '',
    );
    const field = (name) => modal.querySelector(`[data-payment-request-field="${name}"]`);
    const approveBtn = modal.querySelector('[data-payment-request-action="approve"]');
    const rejectBtn = modal.querySelector('[data-payment-request-action="reject"]');
    const rejectLabel = modal.querySelector('[data-reject-label]');
    const rejectForm = modal.querySelector('[data-payment-request-reject-form]');
    const reasonInput = modal.querySelector('#payment-request-reason');
    const errorBox = modal.querySelector('[data-payment-request-error]');
    const conflictBox = modal.querySelector('[data-payment-request-conflict]');
    const reviewBox = modal.querySelector('[data-payment-request-review]');
    let current = null;
    let busy = false;

    const showMessage = (box, message) => {
        if (!box) return;
        box.textContent = message || '';
        box.classList.toggle('hidden', !message);
    };
    // `hidden` and `inline-flex` are both display utilities, so swap them instead of stacking them.
    const setActionsVisible = (visible) => [approveBtn, rejectBtn].forEach((button) => {
        button?.classList.toggle('hidden', !visible);
        button?.classList.toggle('inline-flex', visible);
    });
    const setLoading = (button, loading) => {
        const icon = button?.querySelector('[data-action-icon]');
        if (!icon) return;
        icon.textContent = loading ? 'progress_activity' : icon.dataset.actionIcon;
        icon.classList.toggle('animate-spin', loading);
    };
    const setRejecting = (rejecting) => {
        rejectForm?.classList.toggle('hidden', !rejecting);
        if (rejectLabel) rejectLabel.textContent = rejecting ? rejectLabel.dataset.labelConfirm : rejectLabel.dataset.labelStart;
        if (rejecting) reasonInput?.focus();
    };
    const close = () => {
        if (busy) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    };

    const open = (request) => {
        current = request;
        const isPending = request.status === 'pending';
        ['code', 'member', 'member_meta', 'status_label', 'requested_at', 'transfer_content'].forEach((name) => {
            const el = field(name);
            if (el) el.textContent = request[name] || '—';
        });
        const amountEl = field('amount');
        if (amountEl) amountEl.textContent = formatMoney(request.amount);
        const countEl = field('debt_count');
        if (countEl) countEl.textContent = String((request.debts || []).length);
        const list = modal.querySelector('[data-payment-request-debts]');
        if (list) {
            list.innerHTML = (request.debts || []).map((debt) => `
                <div class="p-2.5 flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5 font-semibold text-on-surface">
                            <span class="font-mono text-[11px] text-primary font-bold">${escapeHtml(debt.code)}</span>
                            <span class="text-outline-variant">•</span>
                            <span class="truncate">${escapeHtml(debt.campaign)}</span>
                        </div>
                        ${debt.note ? `<div class="text-[11px] text-outline truncate italic mt-0.5">${escapeHtml(debt.note)}</div>` : ''}
                    </div>
                    <span class="font-mono font-bold text-amber-600 shrink-0">${escapeHtml(formatMoney(debt.amount))}</span>
                </div>`).join('');
        }

        showMessage(conflictBox, request.conflict
            ? t('conflict', { expected: formatMoney(request.amount), actual: formatMoney(request.current_total) })
            : '');
        const reviewParts = [];
        if (!isPending && request.reviewed_at) reviewParts.push(t('reviewed', { name: request.reviewer || '—', time: request.reviewed_at }));
        if (request.review_reason) reviewParts.push(t('reason', { reason: request.review_reason }));
        showMessage(reviewBox, reviewParts.join(' · '));
        showMessage(errorBox, '');
        if (reasonInput) reasonInput.value = '';
        setRejecting(false);

        setActionsVisible(isPending);
        if (approveBtn) approveBtn.disabled = Boolean(request.conflict);

        modal.classList.remove('hidden');
        modal.classList.add('flex');
    };

    /**
     * Post a review decision and reload the page on success.
     *
     * @param {'approve'|'reject'} action Decision.
     * @param {HTMLButtonElement} button Clicked button, disabled while the request runs.
     * @param {object} body JSON payload.
     * @returns {Promise<void>}
     */
    const submit = async (action, button, body = {}) => {
        if (!current || busy) return;
        busy = true;
        // Lock both decisions while one is in flight, with a spinner on the clicked button.
        if (approveBtn) approveBtn.disabled = true;
        if (rejectBtn) rejectBtn.disabled = true;
        setLoading(button, true);
        window.showAdminLoading?.();
        showMessage(errorBox, '');
        const url = modal.dataset[action === 'approve' ? 'approveUrl' : 'rejectUrl'].replace('__ID__', String(current.id));
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                body: JSON.stringify(body),
            });
            const payload = await res.json().catch(() => ({}));
            if (res.ok) {
                // The request is reviewed: the decision buttons go away right now, not only after the reload.
                setActionsVisible(false);
                setRejecting(false);
                if (payload.message) window.notify?.(payload.message, 'success');
                window.location.reload();
                return;
            }
            showMessage(errorBox, Object.values(payload.errors || {}).flat()[0] || payload.message || t('serverError'));
        } catch (error) {
            console.error(error);
            showMessage(errorBox, t('serverError'));
        }
        busy = false;
        window.hideAdminLoading?.();
        setLoading(button, false);
        if (rejectBtn) rejectBtn.disabled = false;
        if (approveBtn) approveBtn.disabled = Boolean(current?.conflict);
    };

    approveBtn?.addEventListener('click', () => submit('approve', approveBtn));
    rejectBtn?.addEventListener('click', () => {
        if (rejectForm?.classList.contains('hidden')) {
            setRejecting(true);
            return;
        }
        const reason = reasonInput?.value.trim() || '';
        if (!reason) {
            showMessage(errorBox, t('reasonRequired'));
            reasonInput?.focus();
            return;
        }
        submit('reject', rejectBtn, { reason });
    });

    document.addEventListener('click', (event) => {
        const target = event.target instanceof Element ? event.target : null;
        const openButton = target?.closest('[data-open-payment-request]');
        if (openButton) {
            try {
                open(JSON.parse(openButton.dataset.paymentRequest || '{}'));
            } catch (error) {
                console.error(error);
            }
            return;
        }
        if (target?.closest('[data-payment-request-close]') && modal.contains(target)) close();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.classList.contains('hidden')) close();
    });
}
