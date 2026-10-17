<x-room.layout :room="$room" :room-user="$roomUser" :user-rooms="$userRooms" :unread-notifications-count="$unreadNotificationsCount" :active-tab="'debts'"
    :title="__('room.debts.page_title')" :description="__('room.debts.subtitle')" :og-image="asset('images/og-debt-payment.jpg')">
    <div class="flex flex-col w-full gap-space-lg" x-data="{
        qrModalOpen: false,
        paymentConfirmModalOpen: false,
        isSubmittingPayment: false,
        currentDebtId: null,
        currentDebtPending: false,
        campaignModalOpen: false,
        campaignLoading: false,
        campaignData: null,
        init() {
            // Notification links carry ?debt={debt code} (payment reminder) or ?campaign={campaign code} (campaign closed):
            // open the matching debt's payment modal.
            const params = new URLSearchParams(window.location.search);
            const isCode = (value) => /^[A-Za-z0-9-]+$/.test(value || '');
            const debtCode = params.get('debt');
            const campaignCode = params.get('campaign');
            const selector = isCode(debtCode) ?
                `[data-pay-debt-code='${debtCode}']` :
                (isCode(campaignCode) ? `[data-pay-debt-campaign='${campaignCode}']` : null);
            if (!selector) return;
            this.$nextTick(() => {
                const payButton = this.$root.querySelector(selector);
                payButton?.scrollIntoView({ block: 'center' });
                payButton?.click();
            });
        },
        async openCampaignDetail(campaignId) {
            if (!campaignId) return;
            this.campaignModalOpen = true;
            this.campaignLoading = true;
            this.campaignData = null;
            try {
                const url = '{{ route('user.campaigns.details', ['room' => $room->slug, 'campaign' => ':id']) }}'.replace(':id', campaignId);
                const res = await fetch(url, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });
                if (res.ok) {
                    const data = await res.json();
                    this.campaignData = data.data;
                } else {
                    window.notify?.('{{ __('global.common.error') }}', 'error');
                    this.campaignModalOpen = false;
                }
            } catch (e) {
                window.notify?.('{{ __('room.orders.connection_error') }}', 'error');
                this.campaignModalOpen = false;
            } finally {
                this.campaignLoading = false;
            }
        },
        paymentDetails: {
            requestedAt: '',
            content: '',
            amount: ''
        },
        qrData: {
            configured: {{ $vietqrData ? 'true' : 'false' }},
            bankName: '{{ $vietqrData['bank_name'] ?? '' }}',
            accountNumber: '{{ $vietqrData['account_number'] ?? '' }}',
            accountName: '{{ $vietqrData['account_name'] ?? '' }}',
            amount: {{ $totalPayableAmount }},
            formattedAmount: '{{ \App\Support\Helpers\FormatHelper::formatCurrency($totalPayableAmount) }}',
            transferContent: {{ Js::from($payAllContent) }},
            debtCode: '',
            qrPayload: {{ Js::from($vietqrData['payload'] ?? '') }},
            qrDataUrl: ''
        },
        async openQr(amount, formattedAmount, content, customQrUrl, debtId = null, isPending = false, payload = '', account = null, debtCode = '') {
            this.qrData.debtCode = debtCode || '';
            this.currentDebtId = debtId || null;
            this.currentDebtPending = Boolean(isPending);
            this.qrData.amount = amount;
            this.qrData.formattedAmount = formattedAmount;
            this.qrData.transferContent = content;
            // Each QR belongs to one receiving account; never reuse another debt's payload or bank details.
            this.qrData.configured = Boolean(account && account.account_number);
            this.qrData.bankName = account?.bank_name || '';
            this.qrData.accountNumber = account?.account_number || '';
            this.qrData.accountName = account?.account_name || '';
            this.qrData.qrPayload = this.qrData.configured ? (payload || '') : '';
            try {
                this.qrData.qrDataUrl = this.qrData.qrPayload && window.QRCode ?
                    await QRCode.toDataURL(this.qrData.qrPayload, { width: 220, margin: 1, errorCorrectionLevel: 'M' }) :
                    '';
            } catch (error) {
                console.error('QR render error:', error);
                this.qrData.qrDataUrl = '';
            }
            this.qrModalOpen = true;
        },
        copiedField: null,
        copiedTimer: null,
        copyText(text, field) {
            navigator.clipboard?.writeText(text);
            // No toast: the copy icon turns into a check mark for 2 seconds.
            this.copiedField = field;
            clearTimeout(this.copiedTimer);
            this.copiedTimer = setTimeout(() => { this.copiedField = null; }, 2000);
        },
        openPaymentConfirm() {
            // No receiving account: there is nowhere to transfer to, so a payment cannot be reported (also enforced server-side).
            if (this.currentDebtPending || !this.qrData.configured) return;
            this.paymentDetails.requestedAt = new Intl.DateTimeFormat(document.documentElement.lang || 'vi-VN', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            }).format(new Date());
            this.paymentDetails.content = this.qrData.transferContent;
            this.paymentDetails.amount = this.qrData.formattedAmount;
            this.qrModalOpen = false;
            this.paymentConfirmModalOpen = true;
        },
        closePaymentConfirm() {
            if (this.isSubmittingPayment) return;
            this.paymentConfirmModalOpen = false;
        },
        async submitPaymentConfirmation() {
            if (this.isSubmittingPayment) return;
            this.isSubmittingPayment = true;
            this.paymentConfirmModalOpen = false;
            window.showGlobalLoading?.({{ Js::from(__('room.orders.confirm_modal_submitting', ['default' => 'Đang gửi xác nhận...'])) }});
    
            try {
                const response = await fetch('{{ route('user.debts.confirm-payment', $room) }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        debt_id: this.currentDebtId,
                        transfer_content: this.qrData.transferContent
                    }),
                    credentials: 'same-origin'
                });
                const result = await response.json();
                if (response.ok && result.success) {
                    window.notify?.(result.message || '{{ __('room.orders.payment_submitted_success') }}', 'success');
                    // Opened from a campaign-closed notification (?campaign=code): land on the plain ledger
                    // instead of reloading, which would auto-open the QR of the debt just reported.
                    const hasCampaignParam = new URLSearchParams(window.location.search).has('campaign');
                    setTimeout(() => hasCampaignParam
                        ? window.location.assign({{ Js::from(route('user.debts.index', $room)) }})
                        : window.location.reload(), 800);
                } else {
                    window.notify?.(result.message || '{{ __('room.orders.payment_submit_error') }}', 'error');
                }
            } catch (err) {
                window.notify?.('{{ __('room.orders.connection_error') }}', 'error');
            } finally {
                this.isSubmittingPayment = false;
                window.hideGlobalLoading?.();
            }
        }
    }">
        <!-- Header Banner -->
        <div
            class="flex flex-col md:flex-row md:items-center justify-between gap-3 bg-surface-container-lowest p-3 sm:p-3.5 rounded-xl shadow-2xs border border-outline-variant/30">
            <div class="flex items-center gap-3">
                <div
                    class="w-9 h-9 rounded-lg bg-surface-container-high flex items-center justify-center text-primary shrink-0">
                    <span class="material-symbols-outlined text-[20px]">account_balance_wallet</span>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <span
                            class="text-sm sm:text-base text-on-surface font-bold">{{ __('room.debts.page_title') }}</span>
                        @if ($totalUnpaidAmount > 0)
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-error-container text-on-error-container text-[11px] font-semibold">
                                <span class="w-1.5 h-1.5 rounded-full bg-error animate-ping"></span>
                                {{ __('room.debts.unpaid_count', ['count' => $unpaidDebts->count()]) }}
                            </span>
                        @else
                            <span
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-primary-fixed text-on-primary-fixed text-[11px] font-semibold">
                                <span class="material-symbols-outlined text-[13px]">check</span>
                                {{ __('room.debts.all_paid') }}
                            </span>
                        @endif
                    </div>
                    <p class="text-xs text-on-surface-variant mt-0.5">{{ __('room.debts.subtitle') }}</p>
                </div>
            </div>
            {{-- Pay-all bundles every debt not yet in a request into one consolidated request (2+ debts). --}}
            @if ($paymentSummary['can_submit'])
                <button
                    class="px-3.5 py-1.5 rounded-lg bg-[#006948] hover:bg-[#005137] text-white text-xs font-semibold shadow-2xs transition-all inline-flex items-center gap-1.5 self-start md:self-auto cursor-pointer"
                    @click="openQr({{ $totalPayableAmount }}, '{{ \App\Support\Helpers\FormatHelper::formatCurrency($totalPayableAmount) }}', {{ Js::from($payAllContent) }}, '', null, false, {{ Js::from($vietqrData['payload'] ?? '') }}, {{ Js::from($vietqrData ? ['bank_name' => $vietqrData['bank_name'], 'account_number' => $vietqrData['account_number'], 'account_name' => $vietqrData['account_name']] : null) }})">
                    <span class="material-symbols-outlined text-[17px] text-white">qr_code_2</span>
                    <span
                        class="text-white">{{ __('room.debts.pay_all', ['amount' => \App\Support\Helpers\FormatHelper::formatCurrency($paymentSummary['submittable'])]) }}</span>
                </button>
            @endif
        </div>

        <!-- 3 Metrics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <!-- Card 1: Unpaid Debt -->
            <div
                class="bg-surface-container-lowest rounded-xl p-3.5 sm:p-4 shadow-2xs border border-outline-variant/30 relative overflow-hidden flex flex-col justify-between">
                <div class="flex justify-between items-start">
                    <span
                        class="text-xs text-on-surface-variant font-semibold">{{ __('room.debts.unpaid_title') }}</span>
                    <span
                        class="w-7 h-7 rounded-lg bg-error-container text-on-error-container flex items-center justify-center">
                        <span class="material-symbols-outlined text-[17px]">pending_actions</span>
                    </span>
                </div>
                <div class="mt-2.5">
                    <div class="flex items-baseline gap-2">
                        <span class="text-lg sm:text-xl text-error tracking-tight font-bold font-tabular-nums">
                            {{ \App\Support\Helpers\FormatHelper::formatCurrency($totalUnpaidAmount) }}
                        </span>
                        <span class="text-[11px] text-error bg-error-container px-1.5 py-0.5 rounded font-medium">
                            {{ __('room.debts.unpaid_count', ['count' => $unpaidDebts->count()]) }}
                        </span>
                    </div>
                    <p class="mt-1 text-[11px] text-on-surface-variant leading-snug">{{ __('room.debts.unpaid_hint') }}
                    </p>
                    <ul class="mt-1.5 space-y-0.5 text-[11px] leading-snug" data-debt-summary>
                        @if ($paymentSummary['pending'] > 0)
                            <li class="flex items-center gap-1 text-amber-700">
                                <span class="material-symbols-outlined text-[13px]"
                                    aria-hidden="true">hourglass_top</span>
                                {{ __('room.debts.summary_pending', ['amount' => \App\Support\Helpers\FormatHelper::formatCurrency($paymentSummary['pending'])]) }}
                            </li>
                        @endif
                        @if ($paymentSummary['submittable'] > 0)
                            <li class="flex items-center gap-1 text-on-surface-variant">
                                <span class="material-symbols-outlined text-[13px]" aria-hidden="true">send_money</span>
                                {{ __('room.debts.summary_submittable', ['amount' => \App\Support\Helpers\FormatHelper::formatCurrency($paymentSummary['submittable']), 'count' => $paymentSummary['submittable_count']]) }}
                            </li>
                        @endif
                        @if ($paymentSummary['credit_enabled'])
                            <li class="flex items-center gap-1 text-on-surface-variant">
                                <span class="material-symbols-outlined text-[13px]"
                                    aria-hidden="true">credit_score</span>
                                {{ __('room.debts.credit_available', ['amount' => \App\Support\Helpers\FormatHelper::formatCurrency($paymentSummary['credit_available']), 'limit' => \App\Support\Helpers\FormatHelper::formatCurrency($paymentSummary['credit_limit'])]) }}
                            </li>
                        @endif
                    </ul>
                </div>
                <div
                    class="mt-3 pt-2.5 flex items-center justify-between text-on-surface-variant text-[11px] border-t border-outline-variant/20">
                    <span class="flex items-center gap-1 {{ $totalUnpaidAmount > 0 ? 'text-error' : 'text-primary' }}">
                        <span
                            class="material-symbols-outlined text-[14px]">{{ $totalUnpaidAmount > 0 ? 'error' : 'check_circle' }}</span>
                        {{ $totalUnpaidAmount > 0 ? __('room.debts.transfer_needed') : __('room.debts.no_debt') }}
                    </span>
                    <a class="text-primary hover:underline font-medium"
                        href="#unpaid-bills">{{ __('room.debts.debt_details') }}</a>
                </div>
            </div>

            <!-- Card 2: Paid this month -->
            <div
                class="bg-surface-container-lowest rounded-xl p-3.5 sm:p-4 shadow-2xs border border-outline-variant/30 relative overflow-hidden flex flex-col justify-between">
                <div class="flex justify-between items-start">
                    <span
                        class="text-xs text-on-surface-variant font-semibold">{{ __('room.debts.paid_this_month') }}</span>
                    <span
                        class="w-7 h-7 rounded-lg bg-primary-fixed text-on-primary-fixed-variant flex items-center justify-center">
                        <span class="material-symbols-outlined text-[17px]">check_circle</span>
                    </span>
                </div>
                <div class="mt-2.5">
                    <div class="flex items-baseline gap-2">
                        <span class="text-lg sm:text-xl text-primary tracking-tight font-bold font-tabular-nums">
                            {{ \App\Support\Helpers\FormatHelper::formatCurrency($totalPaidMonthAmount) }}
                        </span>
                        <span class="text-[11px] text-primary bg-primary-fixed px-1.5 py-0.5 rounded font-medium">
                            {{ __('room.debts.paid_count', ['count' => $totalPaidMonthCount]) }}
                        </span>
                    </div>
                    <p class="mt-1 text-[11px] text-on-surface-variant leading-snug">
                        {{ __('room.debts.paid_reconciliation_hint') }}
                    </p>
                </div>
                <div
                    class="mt-3 pt-2.5 flex items-center justify-between text-[11px] text-on-surface-variant border-t border-outline-variant/20">
                    <span class="flex items-center gap-1 text-primary">
                        <span class="material-symbols-outlined text-[14px]">verified</span>
                        {{ __('room.debts.matched_percent') }}
                    </span>
                    <span class="text-on-surface-variant font-tabular-nums">{{ now()->format('m/Y') }}</span>
                </div>
            </div>

            <!-- Card 3: Sponsor Saved -->
            <div
                class="bg-surface-container-lowest rounded-xl p-3.5 sm:p-4 shadow-2xs border border-outline-variant/30 relative overflow-hidden flex flex-col justify-between">
                <div class="flex justify-between items-start">
                    <span
                        class="text-xs text-on-surface-variant font-semibold">{{ __('room.debts.sponsor_saved_title') }}</span>
                    <span
                        class="w-7 h-7 rounded-lg bg-secondary-container text-on-secondary-container flex items-center justify-center">
                        <span class="material-symbols-outlined text-[17px]">redeem</span>
                    </span>
                </div>
                <div class="mt-2.5">
                    <div class="flex items-baseline gap-2">
                        <span class="text-lg sm:text-xl text-secondary tracking-tight font-bold font-tabular-nums">
                            {{ \App\Support\Helpers\FormatHelper::formatCurrency($totalSponsorAmount ?? 0) }}
                        </span>
                        <span
                            class="text-[11px] text-secondary bg-secondary-container px-1.5 py-0.5 rounded font-medium">{{ __('room.debts.sponsored_label') }}</span>
                    </div>
                    <p class="mt-1 text-[11px] text-on-surface-variant leading-snug">
                        {{ __('room.debts.sponsor_saved_desc') }}
                    </p>
                </div>
                <div
                    class="mt-3 pt-2.5 flex items-center justify-between text-[11px] text-on-surface-variant border-t border-outline-variant/20">
                    <span class="flex items-center gap-1 text-on-surface-variant">
                        <span class="material-symbols-outlined text-[14px]">savings</span>
                        {{ __('room.debts.welfare_label') }}
                    </span>
                    <span class="text-secondary font-medium">{{ __('room.debts.saved_percent') }}</span>
                </div>
            </div>
        </div>

        <!-- Debt Ledger Table -->
        <x-room.debt-ledger :debts="$ledgerDebts" :payment-requests="$paymentRequests" :payment-request-details="$paymentRequestDetails" :transfer-contents="$transferContents" :qr-payloads="$qrPayloads"
            :qr-accounts="$qrAccounts" />

        <!-- VietQR Modal -->
        <style>
            .vietqr-snake-box {
                position: relative;
            }

            .vietqr-snake-svg {
                position: absolute;
                inset: 0;
                width: 100%;
                height: 100%;
                pointer-events: none;
                z-index: 10;
                overflow: visible;
            }

            .vietqr-snake-track {
                fill: none;
                stroke: #e2e8f0;
                stroke-width: 2.5;
                stroke-linecap: round;
                stroke-linejoin: round;
            }

            .vietqr-snake-line {
                fill: none;
                stroke: #006948;
                stroke-width: 3;
                stroke-linecap: round;
                stroke-linejoin: round;
                stroke-dasharray: 20 80;
                stroke-dashoffset: 0;
                animation: vietqr-snake-run 6s linear infinite;
                filter: drop-shadow(0 0 3px rgba(0, 105, 72, 0.5));
            }

            @keyframes vietqr-snake-run {
                0% {
                    stroke-dashoffset: 0;
                }

                100% {
                    stroke-dashoffset: -100;
                }
            }

            @media (prefers-reduced-motion: reduce) {
                .vietqr-snake-line {
                    animation: none;
                    stroke-dasharray: 100 0;
                }
            }
        </style>
        <template x-teleport="body">
            <div x-show="qrModalOpen" x-cloak
                class="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md transition-opacity duration-200"
                role="dialog">
                <div class="bg-surface-container-lowest w-full max-w-md rounded-2xl shadow-2xl border border-outline-variant overflow-hidden flex flex-col"
                    @click.outside="qrModalOpen = false">
                    <div
                        class="p-3 sm:p-3.5 border-b border-outline-variant/30 flex items-center justify-between bg-surface-container-low">
                        <div class="flex items-center gap-2.5">
                            <div
                                class="w-8 h-8 rounded-lg bg-primary-fixed flex items-center justify-center text-on-primary-fixed-variant">
                                <span class="material-symbols-outlined text-[18px]">qr_code_2</span>
                            </div>
                            <div>
                                <h3 class="text-xs sm:text-sm text-on-surface font-bold">
                                    {{ __('room.debts.vietqr_modal_title') }}
                                </h3>
                                <p class="text-[11px] text-on-surface-variant">{{ $room->name }}</p>
                            </div>
                        </div>
                        <button
                            class="w-7 h-7 rounded-lg flex items-center justify-center text-on-surface-variant hover:bg-surface-container-high transition-colors cursor-pointer"
                            @click="qrModalOpen = false">
                            <span class="material-symbols-outlined text-[18px]">close</span>
                        </button>
                    </div>
                    <div class="p-3.5 sm:p-4 flex flex-col gap-3">
                        <div
                            class="flex flex-col items-center justify-center p-3 bg-surface-container-low rounded-xl border border-outline-variant/40">
                            <div x-show="!qrData.configured" data-qr-not-configured
                                class="flex w-full flex-col items-center gap-2 rounded-xl border border-dashed border-amber-300 bg-amber-50 px-4 py-6 text-center">
                                <span
                                    class="material-symbols-outlined text-[32px] text-amber-500">account_balance</span>
                                <p class="text-sm font-semibold text-amber-800">
                                    {{ __('room.debts.payment_account_not_configured') }}</p>
                                <p class="text-[11px] leading-relaxed text-amber-700">
                                    {{ __('room.debts.payment_account_not_configured_hint') }}</p>
                            </div>
                            <div x-show="qrData.configured"
                                class="vietqr-snake-box relative bg-surface-container-lowest p-3 rounded-2xl shadow-sm border border-outline-variant flex flex-col items-center overflow-hidden">
                                <!-- SVG Snake Border Animation -->
                                <svg class="vietqr-snake-svg" viewBox="0 0 100 100" preserveAspectRatio="none"
                                    aria-hidden="true">
                                    <rect class="vietqr-snake-track" x="1.5" y="1.5" width="97" height="97"
                                        rx="8" ry="8" pathLength="100" />
                                    <rect class="vietqr-snake-line" x="1.5" y="1.5" width="97" height="97"
                                        rx="8" ry="8" pathLength="100" />
                                </svg>
                                <img :src="qrData.qrDataUrl" alt="VietQR"
                                    class="w-48 h-48 object-contain rounded-lg relative z-0" loading="lazy" />
                            </div>
                            <div class="mt-2.5 text-center">
                                <span
                                    class="text-[11px] text-on-surface-variant">{{ __('room.debts.vietqr_amount_label') }}</span>
                                <div class="text-lg sm:text-xl font-bold text-error tracking-tight font-tabular-nums"
                                    x-text="qrData.formattedAmount"></div>
                                <div x-show="qrData.debtCode" x-cloak data-qr-debt-code
                                    class="mt-1 inline-flex items-center gap-1 text-[11px] text-on-surface-variant">
                                    <span>{{ __('room.debts.vietqr_debt_code') }}</span>
                                    <span class="font-mono font-bold text-primary" x-text="qrData.debtCode"></span>
                                </div>
                            </div>
                        </div>
                        <div x-show="qrData.configured"
                            class="flex flex-col gap-1.5 bg-surface-container-lowest border border-outline-variant/40 rounded-xl p-2.5 text-xs">
                            <div
                                class="flex items-center justify-between py-1 border-b border-surface-container-high text-xs">
                                <span class="text-on-surface-variant">{{ __('room.debts.vietqr_bank_label') }}</span>
                                <span class="font-semibold text-on-surface flex items-center gap-1">
                                    <span
                                        class="px-1.5 py-0.5 rounded bg-surface-container-high text-[10px] font-bold text-primary"
                                        x-text="qrData.bankName"></span>
                                </span>
                            </div>
                            <div
                                class="flex items-center justify-between py-1 border-b border-surface-container-high text-xs">
                                <span
                                    class="text-on-surface-variant">{{ __('room.debts.vietqr_account_number') }}</span>
                                <div class="flex items-center gap-1">
                                    <span class="font-tabular-nums font-bold text-on-surface text-xs"
                                        x-text="qrData.accountNumber"></span>
                                    <button
                                        class="p-0.5 rounded hover:bg-surface-container-high text-primary transition-colors flex items-center cursor-pointer"
                                        @click="copyText(qrData.accountNumber, 'account')">
                                        <span class="material-symbols-outlined text-[14px]"
                                            x-text="copiedField === 'account' ? 'check' : 'content_copy'">content_copy</span>
                                    </button>
                                </div>
                            </div>
                            <div
                                class="flex items-center justify-between py-1 border-b border-surface-container-high text-xs">
                                <span
                                    class="text-on-surface-variant">{{ __('room.debts.vietqr_account_name') }}</span>
                                <span class="font-semibold text-on-surface uppercase text-xs"
                                    x-text="qrData.accountName"></span>
                            </div>
                            <div
                                class="flex items-center justify-between py-1.5 bg-primary-fixed/20 px-2 rounded-lg mt-0.5 text-xs">
                                <div class="flex flex-col">
                                    <span
                                        class="text-[10px] text-on-primary-fixed-variant font-medium">{{ __('room.debts.vietqr_transfer_content') }}</span>
                                    <span class="font-tabular-nums font-bold text-primary tracking-wide text-xs"
                                        x-text="qrData.transferContent"></span>
                                </div>
                                <button
                                    class="p-0.5 rounded hover:bg-primary-fixed text-primary transition-colors flex items-center cursor-pointer"
                                    @click="copyText(qrData.transferContent, 'content')">
                                    <span class="material-symbols-outlined text-[14px]"
                                        x-text="copiedField === 'content' ? 'check' : 'content_copy'">content_copy</span>
                                </button>
                            </div>
                        </div>

                        <!-- Footer Actions in QR Modal -->
                        <div class="pt-2 border-t border-outline-variant/60 flex items-center justify-between gap-2">
                            <button type="button" @click="qrModalOpen = false"
                                class="px-4 py-2 rounded-xl border border-outline-variant bg-surface-container-low text-on-surface font-semibold text-xs hover:bg-surface-container-high transition-colors cursor-pointer">
                                {{ __('global.common.close') }}
                            </button>
                            <button type="button" x-show="!currentDebtPending && qrData.configured"
                                :disabled="isSubmittingPayment" @click="openPaymentConfirm()"
                                class="px-4 py-2 rounded-xl bg-[#006948] hover:bg-[#005137] text-white font-bold text-xs flex items-center gap-1.5 shadow-sm transition-all cursor-pointer disabled:opacity-50">
                                <span class="material-symbols-outlined text-[16px] text-white"
                                    x-show="!isSubmittingPayment">check_circle</span>
                                <span class="material-symbols-outlined text-[16px] text-white animate-spin"
                                    x-show="isSubmittingPayment" x-cloak>progress_activity</span>
                                <span class="text-white">{{ __('room.orders.mark_as_paid') }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- Payment Confirmation Modal -->
        <template x-teleport="body">
            <div x-show="paymentConfirmModalOpen" x-cloak @keydown.escape.window="closePaymentConfirm()"
                class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md"
                role="dialog" aria-modal="true" aria-labelledby="debt-payment-confirm-title">
                <div x-show="paymentConfirmModalOpen" x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 scale-100" x-transition:leave-end="opacity-0 scale-95"
                    @click.outside="closePaymentConfirm()"
                    class="w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                    <div class="p-5 sm:p-6">
                        <div
                            class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-[#006948]">
                            <span class="material-symbols-outlined text-[24px]">payments</span>
                        </div>
                        <h3 id="debt-payment-confirm-title" class="text-lg font-bold text-slate-900">
                            {{ __('room.orders.confirm_modal_title') }}
                        </h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">
                            {{ __('room.orders.confirm_modal_desc') }}
                        </p>
                        <div class="mt-4 space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
                            <div class="flex items-start justify-between gap-4">
                                <span class="text-slate-500">{{ __('room.orders.payment_request_time') }}</span>
                                <span class="text-right font-semibold text-slate-900"
                                    x-text="paymentDetails.requestedAt || '{{ __('room.orders.not_available') }}'"></span>
                            </div>
                            <div class="flex items-start justify-between gap-4">
                                <span class="text-slate-500">{{ __('room.orders.payment_amount_label') }}</span>
                                <span class="text-right font-mono font-bold text-rose-600"
                                    x-text="paymentDetails.amount"></span>
                            </div>
                            <div class="flex items-start justify-between gap-4">
                                <span class="text-slate-500">{{ __('room.orders.payment_request_content') }}</span>
                                <span class="text-right font-mono font-bold text-[#006948]"
                                    x-text="paymentDetails.content || '{{ __('room.orders.not_available') }}'"></span>
                            </div>
                        </div>
                        <div
                            class="mt-4 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-900">
                            <span
                                class="material-symbols-outlined mt-0.5 shrink-0 text-[17px] text-amber-700">info</span>
                            <span x-show="currentDebtId">{{ __('room.orders.confirm_modal_note') }}</span>
                            <span x-show="!currentDebtId"
                                x-cloak>{{ __('room.debts.pay_all_confirm_note', ['count' => $paymentSummary['submittable_count']]) }}</span>
                        </div>
                    </div>
                    <div
                        class="flex items-center justify-end gap-2 border-t border-slate-100 bg-slate-50 px-5 py-4 sm:px-6">
                        <button type="button" @click="closePaymentConfirm()"
                            class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-200/70 cursor-pointer">
                            {{ __('room.orders.confirm_modal_cancel') }}
                        </button>
                        <button type="button" @click="submitPaymentConfirmation()" :disabled="isSubmittingPayment"
                            class="inline-flex items-center justify-center gap-2 rounded-xl bg-[#006948] px-4 py-2.5 text-sm font-bold text-white shadow-sm transition-colors hover:bg-[#005137] disabled:cursor-not-allowed disabled:opacity-60 cursor-pointer">
                            <span class="material-symbols-outlined text-[18px]">check_circle</span>
                            <span>{{ __('room.orders.confirm_modal_submit') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- Campaign Details Modal -->
        <x-room.campaign-orders-modal />
</x-room.layout>
