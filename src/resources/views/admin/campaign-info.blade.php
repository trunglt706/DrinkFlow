@php
    $campaignStatusValue =
        $campaign->status instanceof \BackedEnum ? $campaign->status->value : (string) $campaign->status;
    $isDraft = $campaignStatusValue === 'draft';
    $isCampaignClosed = $campaign->isLocked();
    $isCampaignLive = $campaignStatusValue === 'active';
@endphp

<x-admin.layout :title="__('admin.brand_title') . ' · ' . $campaign->name . ' · ' . __('admin.campaign_nav_info')" active="campaigns" :room="$room">
    <div id="campaign-app" class="space-y-6">
        <!-- TOP SUB-NAVIGATION BAR -->
        <div class="flex flex-col sm:flex-row sm:flex-wrap sm:items-center justify-between gap-4 pb-2 border-b border-outline-variant/60">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.campaigns.page', $room) }}"
                    class="p-2 rounded-xl border border-outline-variant hover:bg-surface-container text-outline hover:text-on-surface transition-colors flex items-center justify-center shrink-0"
                    title="{{ __('admin.back_to_campaigns') }}">
                    <span class="material-symbols-outlined text-[20px]">arrow_back</span>
                </a>
                @unless ($isDraft || $isCampaignClosed)
                    {{-- Member-facing campaign page, to share in the team chat (copy handled by [data-copy] in ui-enhancements.js). --}}
                    <button type="button" data-copy="{{ route('user.campaigns.show', [$room, $campaign]) }}"
                        data-copied-message="{{ __('admin.join_link_copied') }}"
                        data-copy-failed-message="{{ __('admin.copy_failed') }}"
                        data-tooltip="{{ __('admin.copy_join_link_hint') }}"
                        class="inline-flex items-center gap-1.5 px-3 py-2 rounded-xl border border-outline-variant bg-surface-container-lowest hover:bg-primary/5 text-xs font-semibold text-primary transition-colors cursor-pointer">
                        <span class="material-symbols-outlined text-[18px]">link</span>
                        <span>{{ __('admin.copy_join_link') }}</span>
                    </button>
                @endunless
            </div>

            <!-- Sub-navigation Tabs -->
            <div class="flex items-center gap-1.5 bg-surface-container-low p-1.5 rounded-2xl border border-outline-variant/60 self-start sm:self-auto overflow-x-auto no-scrollbar max-w-full">
                <a href="{{ route('admin.campaigns.info', [$room, $campaign]) }}"
                    class="px-3.5 py-2 rounded-xl text-xs font-bold flex items-center gap-2 transition-all no-underline whitespace-nowrap shrink-0 bg-surface-container-lowest text-primary shadow-xs border border-outline-variant/50">
                    <span class="material-symbols-outlined text-[18px]">info</span>
                    <span>{{ __('admin.campaign_nav_info') }}</span>
                </a>

                <a href="{{ route('admin.campaigns.orders', [$room, $campaign]) }}"
                    class="px-3.5 py-2 rounded-xl text-xs font-semibold flex items-center gap-2 transition-all no-underline whitespace-nowrap shrink-0 text-outline hover:text-on-surface hover:bg-surface-container/60">
                    <span class="material-symbols-outlined text-[18px]">receipt_long</span>
                    <span>{{ __('admin.campaign_nav_orders') }}</span>
                    @if ($orders->count() > 0)
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold bg-primary/10 text-primary">
                            {{ $orders->count() }}
                        </span>
                    @endif
                </a>

                @unless ($campaign->isLocked())
                    <a href="{{ route('admin.campaigns.menu', [$room, $campaign]) }}"
                        class="px-3.5 py-2 rounded-xl text-xs font-semibold flex items-center gap-2 transition-all no-underline whitespace-nowrap shrink-0 text-outline hover:text-on-surface hover:bg-surface-container/60">
                        <span class="material-symbols-outlined text-[18px]">restaurant_menu</span>
                        <span>{{ __('admin.campaign_nav_menu') }}</span>
                    </a>
                @endunless

            </div>
        </div>

        <script>
        window.__campaignOrderCheckUrl = {{ Js::from($orderCheckUrl) }};
        window.__campaignGrossSubtotal = {{ (int) ($grossSubtotal ?? 0) }};
        window.__campaignDeliveryFee = {{ (int) ($campaign->delivery_fee ?? 0) }};
        window.__campaignDiscount = {{ (int) ($campaign->discount ?? 0) }};
        window.__campaignCode = {{ Js::from($campaign->code) }};
        window.__campaignRestaurant = {{ Js::from($campaign->restaurant) }};

        (function () {
            let deliveryFee = window.__campaignDeliveryFee;
            let discount = window.__campaignDiscount;

            function formatCurrency(val) {
                const num = Number(val) || 0;
                return new Intl.NumberFormat('vi-VN').format(num) + 'đ';
            }
            function formatInput(val) {
                if (val === null || val === undefined || val === '') return '';
                const num = Number(val);
                if (isNaN(num)) return '';
                return new Intl.NumberFormat('vi-VN').format(num);
            }
            function parseInput(val) {
                const raw = String(val).replace(/[^\d]/g, '');
                return raw ? parseInt(raw, 10) : 0;
            }
            window.formatMoneyInput = function (event) {
                const input = event.target;
                const digitsBeforeCaret = input.value.slice(0, input.selectionStart ?? input.value.length).replace(/\D/g, '').length;
                const digits = input.value.replace(/\D/g, '');
                input.value = digits ? formatInput(parseInput(digits)) : '';
                let caret = 0;
                let seenDigits = 0;
                while (caret < input.value.length && seenDigits < digitsBeforeCaret) {
                    if (/\d/.test(input.value[caret])) seenDigits++;
                    caret++;
                }
                input.setSelectionRange(caret, caret);
            };
            function filterNumberInput(e) {
                if (['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Enter', 'Home', 'End'].includes(e.key)) {
                    return;
                }
                if (e.ctrlKey || e.metaKey) {
                    return;
                }
                if (!/^[0-9.,]$/.test(e.key)) {
                    e.preventDefault();
                }
            }
            window.filterNumberInput = filterNumberInput;

            // ----- Generic modal open/close helpers -----
            function openModal(id) {
                const el = document.getElementById(id);
                if (id === 'adjust-fee-modal') {
                    const notifyCheckbox = document.getElementById('adjust-notify-members');
                    if (notifyCheckbox) notifyCheckbox.checked = false;
                }
                if (el) el.style.display = 'flex';
            }
            function closeModal(id) {
                const el = document.getElementById(id);
                if (el) el.style.display = 'none';
            }
            window.openModal = openModal;
            window.closeModal = closeModal;
            window.closeModalOnBackdrop = function (event, id, canClose) {
                if (event.target !== event.currentTarget) return;
                if (typeof canClose === 'function' && !canClose()) return;
                closeModal(id);
            };

            // ----- QR Modal -----
            window.openQrModal = function (url) {
                const img = document.getElementById('qr-modal-image');
                if (img) img.src = url;
                openModal('qr-modal');
            };

            // ----- Cancel Campaign -----
            let isCancelling = false;
            window.cancelCampaign = async function () {
                if (isCancelling) return;
                isCancelling = true;
                const btn = document.getElementById('confirm-cancel-submit-btn');
                const backBtn = document.getElementById('cancel-campaign-back-btn');
                const normalEl = document.getElementById('cancel-campaign-btn-normal');
                const loadingEl = document.getElementById('cancel-campaign-btn-loading');
                if (btn) btn.disabled = true;
                if (backBtn) backBtn.disabled = true;
                if (normalEl) normalEl.style.display = 'none';
                if (loadingEl) loadingEl.style.display = 'flex';

                try {
                    const response = await fetch('{{ route('admin.campaigns.cancel', [$room, $campaign]) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        }
                    });
                    const res = await response.json();
                    if (!response.ok) throw new Error(res.message || '{{ __('admin.cancel_campaign_failed') }}');

                    if (window.notify) window.notify('{{ __('admin.campaign_cancelled_success') }}', 'success');
                    closeModal('confirm-cancel-modal');
                    window.location.href = '{{ route('admin.campaigns.page', $room) }}';
                } catch (err) {
                    if (window.notify) window.notify(err.message, 'error');
                    else alert(err.message);
                    isCancelling = false;
                    if (btn) btn.disabled = false;
                    if (backBtn) backBtn.disabled = false;
                    if (normalEl) normalEl.style.display = 'flex';
                    if (loadingEl) loadingEl.style.display = 'none';
                }
            };

            // ----- Duplicate Campaign -----
            let isDuplicating = false;
            window.executeDuplicateCampaign = async function () {
                if (isDuplicating) return;
                isDuplicating = true;
                const btn = document.getElementById('execute-duplicate-btn');
                const cancelBtn = document.getElementById('cancel-duplicate-btn');
                const normalEl = document.getElementById('duplicate-btn-normal');
                const loadingEl = document.getElementById('duplicate-btn-loading');
                if (btn) btn.disabled = true;
                if (cancelBtn) cancelBtn.disabled = true;
                if (normalEl) normalEl.style.display = 'none';
                if (loadingEl) loadingEl.style.display = 'flex';

                try {
                    const response = await fetch('{{ route('admin.campaigns.duplicate', [$room, $campaign]) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        }
                    });
                    const res = await response.json().catch(() => ({}));
                    if (!response.ok || !res.data || !res.data.id) throw new Error(res.message || '{{ __('admin.duplicate_campaign_failed') }}');

                    if (window.notify) window.notify('{{ __('admin.duplicate_campaign_success') }}', 'success');
                    window.location.href = '{{ route('admin.campaigns.edit', [$room, 0]) }}'.replace(/\/0\/edit$/, '/' + res.data.id + '/edit');
                } catch (err) {
                    if (window.notify) window.notify(err.message, 'error');
                    else alert(err.message);
                    isDuplicating = false;
                    if (btn) btn.disabled = false;
                    if (cancelBtn) cancelBtn.disabled = false;
                    if (normalEl) normalEl.style.display = 'flex';
                    if (loadingEl) loadingEl.style.display = 'none';
                    closeModal('confirm-duplicate-modal');
                }
            };

            // ----- Extend Deadline -----
            let isExtending = false;
            // Once the deadline has passed the lock controls are not rendered, so reopening ordering needs a reload.
            const deadlineWasOpen = {{ $campaign->isOpenForOrders() ? 'true' : 'false' }};
            window.extendDeadline = async function (minutes) {
                if (isExtending) return;
                isExtending = true;
                const buttons = document.querySelectorAll('[data-extend-deadline-btn]');
                buttons.forEach(function (btn) { btn.disabled = true; });

                try {
                    const response = await fetch('{{ route('admin.campaigns.extend-deadline', [$room, $campaign]) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ minutes: minutes })
                    });
                    const res = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(res.message || '{{ __('admin.extend_deadline_failed') }}');

                    if (window.notify) window.notify(res.message, 'success');
                    if (!deadlineWasOpen) {
                        window.location.reload();
                        return;
                    }
                    // Update the deadline shown on the page instead of reloading it.
                    document.querySelectorAll('[data-campaign-deadline]').forEach(function (el) {
                        el.textContent = res.deadline_display || el.textContent;
                        el.classList.add('bg-amber-100');
                        setTimeout(function () { el.classList.remove('bg-amber-100'); }, 1500);
                    });
                    document.querySelectorAll('[data-campaign-deadline-time]').forEach(function (el) {
                        el.textContent = res.deadline_time || el.textContent;
                    });
                } catch (err) {
                    if (window.notify) window.notify(err.message, 'error');
                    else alert(err.message);
                } finally {
                    isExtending = false;
                    buttons.forEach(function (btn) { btn.disabled = false; });
                }
            };

            // ----- Adjust receiving payment account (closed campaigns) -----
            window.executeUpdatePaymentAccount = async function () {
                const select = document.getElementById('adjust-payment-account-select');
                const btn = document.getElementById('execute-payment-account-btn');
                const cancelBtn = document.getElementById('cancel-payment-account-btn');
                const icon = btn?.querySelector('[data-payment-account-icon]');
                if (!select || !btn || btn.disabled) return;
                btn.disabled = true;
                if (cancelBtn) cancelBtn.disabled = true;
                if (icon) { icon.textContent = 'progress_activity'; icon.classList.add('animate-spin'); }

                try {
                    const response = await fetch(@js(route('admin.campaigns.payment-account', [$room, $campaign])), {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({ payment_account_id: Number(select.value) })
                    });
                    const res = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(res.message || @js(__('admin.invalid_payment_account')));

                    if (window.notify) window.notify(res.message, 'success');
                    window.location.reload();
                } catch (err) {
                    if (window.notify) window.notify(err.message, 'error');
                    btn.disabled = false;
                    if (cancelBtn) cancelBtn.disabled = false;
                    if (icon) { icon.textContent = 'save'; icon.classList.remove('animate-spin'); }
                }
            };

            // ----- Resend Notification -----
            let isResendingNotification = false;
            window.executeResendNotification = async function () {
                if (isResendingNotification) return;
                isResendingNotification = true;
                const btn = document.getElementById('execute-resend-notification-btn');
                const cancelBtn = document.getElementById('cancel-resend-notification-btn');
                const normalEl = document.getElementById('resend-notification-normal');
                const loadingEl = document.getElementById('resend-notification-loading');
                if (btn) btn.disabled = true;
                if (cancelBtn) cancelBtn.disabled = true;
                if (normalEl) normalEl.style.display = 'none';
                if (loadingEl) loadingEl.style.display = 'flex';

                try {
                    const response = await fetch('{{ route('admin.campaigns.resend-notification', [$room, $campaign]) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        }
                    });
                    const res = await response.json().catch(() => ({}));
                    if (!response.ok) throw new Error(res.message || '{{ __('admin.resend_notification_failed') }}');

                    if (window.notify) window.notify(res.message, 'success');
                } catch (err) {
                    if (window.notify) window.notify(err.message, 'error');
                    else alert(err.message);
                } finally {
                    isResendingNotification = false;
                    if (btn) btn.disabled = false;
                    if (cancelBtn) cancelBtn.disabled = false;
                    if (normalEl) normalEl.style.display = 'flex';
                    if (loadingEl) loadingEl.style.display = 'none';
                    closeModal('confirm-resend-notification-modal');
                }
            };

            // ----- Mark Delivered -----
            let isDeliveringLoading = false;
            window.executeMarkDelivering = async function () {
                if (isDeliveringLoading) return;
                isDeliveringLoading = true;
                const btn = document.getElementById('execute-mark-delivering-btn');
                if (btn) btn.disabled = true;

                try {
                    const response = await fetch('{{ route('admin.campaigns.mark-delivering', [$room, $campaign]) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        }
                    });
                    const res = await response.json();
                    if (!response.ok) throw new Error(res.message || '{{ __('admin.mark_delivering_failed') }}');

                    if (window.notify) window.notify('{{ __('admin.mark_delivering_success') }}', 'success');
                    closeModal('confirm-delivery-modal');
                    window.location.reload();
                } catch (err) {
                    if (window.notify) window.notify(err.message, 'error');
                    else alert(err.message);
                } finally {
                    isDeliveringLoading = false;
                    if (btn) btn.disabled = false;
                }
            };

            // ----- Adjust Fee & Discount -----
            let isAdjusting = false;
            window.saveAdjustments = async function (e) {
                if (e) e.preventDefault();
                if (isAdjusting) return;

                const feeInput = document.getElementById('adjust-fee-input');
                const discountInput = document.getElementById('adjust-discount-input');
                const notifyCheckbox = document.getElementById('adjust-notify-members');
                const feeVal = feeInput ? parseInput(feeInput.value) : deliveryFee;
                const discountVal = discountInput ? parseInput(discountInput.value) : discount;

                isAdjusting = true;
                const btn = document.getElementById('adjust-submit-btn');
                const normalContent = document.getElementById('adjust-submit-normal');
                const loadingContent = document.getElementById('adjust-submit-loading');
                if (btn) {
                    btn.disabled = true;
                    btn.setAttribute('aria-busy', 'true');
                }
                if (normalContent) normalContent.style.display = 'none';
                if (loadingContent) loadingContent.style.display = 'inline-flex';

                try {
                    const response = await fetch('{{ route('admin.campaigns.update', [$room, $campaign]) }}', {
                        method: 'PATCH',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            delivery_fee: feeVal,
                            discount: discountVal,
                            notify_members: Boolean(notifyCheckbox && notifyCheckbox.checked)
                        })
                    });
                    const res = await response.json();
                    if (!response.ok) throw new Error(res.message || '{{ __('admin.update_campaign_failed') }}');

                    if (window.notify) window.notify('{{ __('admin.update_campaign_success') }}', 'success');
                    closeModal('adjust-fee-modal');
                    window.location.reload();
                } catch (err) {
                    if (window.notify) window.notify(err.message, 'error');
                    else alert(err.message);
                } finally {
                    isAdjusting = false;
                    if (btn) {
                        btn.disabled = false;
                        btn.removeAttribute('aria-busy');
                    }
                    if (normalContent) normalContent.style.display = 'inline-flex';
                    if (loadingContent) loadingContent.style.display = 'none';
                }
            };
        })();
        </script>

        @if (!$isDraft)
            <!-- SECTION 1: STORE & CAMPAIGN BANNER (2/3 & 1/3 SPLIT LAYOUT) -->
            <section class="bg-surface-container-lowest border border-outline-variant rounded-2xl p-5 sm:p-6 shadow-xs">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
                    <!-- Left Column: 2/3 Width - Campaign Info, Tags & Participation -->
                    <div class="lg:col-span-2 space-y-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-admin.campaign-status-badge :campaign="$campaign" class="text-xs px-3 py-1" />
                            <span class="text-xs text-outline bg-surface-container-low border border-outline-variant px-2.5 py-1 rounded-lg font-mono font-medium">
                                #{{ $campaign->code ?? 'N/A' }}
                            </span>
                        </div>

                        <div>
                            <h2 class="text-2xl lg:text-3xl font-bold text-on-surface tracking-tight">
                                {{ $campaign->name }} · {{ $campaign->restaurant }}
                            </h2>
                            @if (!empty($campaign->description))
                                <p class="mt-1.5 text-sm italic text-outline whitespace-pre-line leading-relaxed">{{ $campaign->description }}</p>
                            @endif
                        </div>

                        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-outline">
                            <span class="flex items-center gap-1.5 bg-surface-container-low px-3 py-1.5 rounded-lg border border-outline-variant/50">
                                <span class="material-symbols-outlined text-[16px] text-amber-600">schedule</span>
                                <span>{{ __('admin.deadline_label') }}</span>
                                <strong class="text-on-surface font-mono font-semibold rounded transition-colors duration-700" data-campaign-deadline>{{ $campaign->deadline?->format('H:i d/m/Y') ?? '—' }}</strong>
                            </span>
                            @if (!empty($campaign->max_budget))
                                <span class="flex items-center gap-1.5 bg-surface-container-low px-3 py-1.5 rounded-lg border border-outline-variant/50">
                                    <span class="material-symbols-outlined text-[16px] text-primary">payments</span>
                                    <span>{{ __('admin.max_product_budget_ceiling') }}:</span>
                                    <strong class="text-primary font-mono font-semibold">{{ \App\Support\Helpers\FormatHelper::formatCurrency((int) $campaign->max_budget) }}</strong>
                                </span>
                            @endif

                            @if ($campaign->paymentAccount)
                                <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-surface-container-low border border-outline-variant/60 text-xs text-on-surface shadow-2xs">
                                    <span class="material-symbols-outlined text-[16px] text-primary">account_balance</span>
                                    <span class="font-bold">{{ $campaign->paymentAccount->bank_code }}</span>
                                    <span class="font-mono text-outline font-semibold">{{ $campaign->paymentAccount->account_number }}</span>
                                    <span class="text-[11px] text-on-surface-variant font-medium">({{ $campaign->paymentAccount->account_name }})</span>
                                </div>
                            @endif
                        </div>

                        @if (in_array($campaignStatusValue, ['active', 'scheduled'], true) && $campaign->deadline)
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <span class="flex items-center gap-1.5 font-semibold text-on-surface-variant">
                                    <span class="material-symbols-outlined text-[16px] text-amber-600">more_time</span>
                                    {{ __('admin.extend_deadline_label') }}:
                                </span>
                                @foreach (\App\Models\Campaign::EXTEND_DEADLINE_MINUTES as $extendMinutes)
                                    <button type="button" data-extend-deadline-btn onclick="extendDeadline({{ $extendMinutes }})"
                                        class="px-3 py-1 rounded-full border border-primary/30 bg-primary/5 hover:bg-primary/10 text-primary font-mono font-semibold transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                                        {{ __('admin.extend_minutes_btn', ['minutes' => $extendMinutes]) }}
                                    </button>
                                @endforeach
                            </div>
                        @endif

                        <!-- Room Participation Progress Banner -->
                        @php
                            $totalUsers = max(1, $totalUsersCount ?? 1);
                            $orderedCount = $orderedUsersCount ?? $orders->count();
                            $declinedCount = $declinedUsersCount ?? 0;
                            $pendingCount = max(0, $totalUsers - $orderedCount - $declinedCount);
                            $orderedPercent = round(($orderedCount / $totalUsers) * 100, 1);
                            $declinedPercent = round(($declinedCount / $totalUsers) * 100, 1);
                            $pendingPercent = max(0, 100 - $orderedPercent - $declinedPercent);
                        @endphp
                        <div class="pt-3 border-t border-outline-variant/40 bg-surface-container-low p-4 rounded-xl border border-outline-variant/60 flex flex-col gap-3">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-lg bg-emerald-50 border border-emerald-200 flex items-center justify-center text-primary shrink-0">
                                        <span class="material-symbols-outlined text-[18px]">pie_chart</span>
                                    </div>
                                    <div>
                                        <span class="font-bold text-on-surface text-xs sm:text-sm">{{ __('admin.room_participation_rate') }}</span>
                                        <span class="text-xs text-outline ml-2 font-mono">({{ $orderedCount }}/{{ $totalUsers }} {{ __('admin.members') }})</span>
                                    </div>
                                </div>
                                <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 border border-emerald-200 text-primary text-xs font-bold font-mono">
                                    <span class="w-2 h-2 rounded-full bg-primary"></span>
                                    {{ __('admin.participating') }}: {{ $orderedPercent }}%
                                </span>
                            </div>

                            <div class="space-y-2">
                                <div class="h-3 w-full bg-surface-container-highest rounded-full overflow-hidden flex shadow-inner">
                                    <div class="bg-primary h-full transition-all duration-300"
                                        style="width: {{ $orderedPercent }}%;"
                                        title="{{ __('admin.ordered') }}: {{ $orderedCount }} ({{ $orderedPercent }}%)">
                                    </div>
                                    <div class="bg-slate-400 h-full transition-all duration-300"
                                        style="width: {{ $declinedPercent }}%;"
                                        title="{{ __('admin.declined') }}: {{ $declinedCount }} ({{ $declinedPercent }}%)">
                                    </div>
                                    <div class="bg-amber-400 h-full transition-all duration-300"
                                        style="width: {{ $pendingPercent }}%;"
                                        title="{{ __('admin.no_response') }}: {{ $pendingCount }} ({{ $pendingPercent }}%)">
                                    </div>
                                </div>
                                <div class="flex flex-wrap items-center justify-between gap-2 text-xs pt-0.5 font-mono">
                                    <div class="flex flex-wrap items-center gap-3">
                                        <span class="flex items-center gap-1.5 text-primary font-semibold">
                                            <span class="w-2 h-2 rounded-full bg-primary"></span>
                                            {{ $orderedCount }} {{ __('admin.ordered') }} ({{ $orderedPercent }}%)
                                        </span>
                                        <span class="flex items-center gap-1.5 text-outline">
                                            <span class="w-2 h-2 rounded-full bg-slate-400"></span>
                                            {{ $declinedCount }} {{ __('admin.declined') }} ({{ $declinedPercent }}%)
                                        </span>
                                        <span class="flex items-center gap-1.5 text-amber-700 font-medium">
                                            <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                                            {{ $pendingCount }} {{ __('admin.no_response') }} ({{ $pendingPercent }}%)
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: 1/3 Width - Action Buttons & Payment Card -->
                    <div class="lg:col-span-1 flex flex-col gap-3 justify-center lg:border-l lg:border-outline-variant/40 lg:pl-6">
                        <!-- Action Buttons (vertical stack) -->
                        <div class="flex flex-col gap-2 w-full">
                            @if ($isCampaignLive)
                                <button type="button" id="resend-notification-btn" onclick="openModal('confirm-resend-notification-modal')"
                                    class="w-full h-10 px-4 rounded-xl border border-primary/30 bg-primary/5 hover:bg-primary/10 text-primary text-xs font-semibold flex items-center gap-2.5 transition-colors cursor-pointer">
                                    <span class="material-symbols-outlined text-[18px]">campaign</span>
                                    <span>{{ __('admin.resend_notification') }}</span>
                                </button>
                                @if (! $campaign->isOpenForOrders())
                                    {{-- Past the deadline: ordering can no longer be locked or unlocked. --}}
                                @elseif ($campaign->isOrderingLocked())
                                    <button type="button" data-ordering-lock-toggle data-mode="unlock" data-url="{{ route('admin.campaigns.unlock-ordering', [$room, $campaign]) }}"
                                        class="w-full h-10 px-4 rounded-xl border border-emerald-300 bg-emerald-50/60 hover:bg-emerald-50 text-emerald-700 text-xs font-semibold flex items-center gap-2.5 transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-wait">
                                        <span class="material-symbols-outlined text-[18px]">lock_open</span>
                                        <span>{{ __('admin.campaign_unlock_action') }}</span>
                                    </button>
                                @else
                                    <button type="button" data-ordering-lock-toggle data-mode="lock" data-url="{{ route('admin.campaigns.lock-ordering', [$room, $campaign]) }}"
                                        title="{{ __('admin.campaign_ordering_locked_hint') }}"
                                        class="w-full h-10 px-4 rounded-xl border border-outline-variant bg-surface-container-lowest hover:bg-surface-container text-on-surface text-xs font-semibold flex items-center gap-2.5 transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-wait">
                                        <span class="material-symbols-outlined text-[18px] text-amber-600">lock</span>
                                        <span>{{ __('admin.campaign_lock_action') }}</span>
                                    </button>
                                @endif
                                <button type="button" data-close-campaign-open data-campaign-id="{{ $campaign->id }}"
                                    class="w-full h-10 px-4 rounded-xl border border-amber-300 bg-amber-50/60 hover:bg-amber-50 text-amber-700 text-xs font-semibold flex items-center gap-2.5 transition-colors cursor-pointer">
                                    <span class="material-symbols-outlined text-[18px]">lock_clock</span>
                                    <span>{{ __('admin.close_campaign_action') }}</span>
                                </button>
                            @endif

                            @unless ($isCampaignClosed)
                                <a href="{{ route('admin.campaigns.edit', [$room, $campaign]) }}"
                                    class="w-full h-10 px-4 rounded-xl border border-outline-variant bg-surface-container-lowest hover:bg-surface-container text-on-surface text-xs font-semibold flex items-center gap-2.5 transition-colors no-underline">
                                    <span class="material-symbols-outlined text-[18px] text-outline">edit</span>
                                    <span>{{ __('admin.edit_campaign') }}</span>
                                </a>
                            @endunless

                            @if ($isCampaignClosed)
                                <button type="button" data-adjust-payment-account-open onclick="openModal('adjust-payment-account-modal');"
                                    class="w-full h-10 px-4 rounded-xl border border-primary/30 bg-primary/5 hover:bg-primary/10 text-primary text-xs font-semibold flex items-center gap-2.5 transition-colors cursor-pointer">
                                    <span class="material-symbols-outlined text-[18px]">account_balance</span>
                                    <span>{{ __('admin.campaign_payment_account_adjust') }}</span>
                                </button>
                            @endif

                            <button type="button" onclick="openModal('confirm-duplicate-modal');"
                                class="w-full h-10 px-4 rounded-xl border border-outline-variant bg-surface-container-lowest hover:bg-surface-container text-on-surface text-xs font-semibold flex items-center gap-2.5 transition-colors cursor-pointer">
                                <span class="material-symbols-outlined text-[18px] text-outline">content_copy</span>
                                <span>{{ __('admin.confirm_duplicate') }}</span>
                            </button>

                            @unless ($isCampaignClosed)
                                <button type="button" onclick="openModal('confirm-cancel-modal');"
                                    class="w-full h-10 px-4 rounded-xl border border-error/30 bg-error/5 hover:bg-error/10 text-error text-xs font-semibold flex items-center gap-2.5 transition-colors cursor-pointer">
                                    <span class="material-symbols-outlined text-[18px]">cancel</span>
                                    <span>{{ __('admin.cancel_campaign_action') }}</span>
                                </button>
                            @endunless
                        </div>

                        <!-- Payment QR Card Preview (if available) -->
                        @if ($campaign->paymentAccount && !empty($campaign->paymentAccount->qr_code_url))
                            <div class="p-3.5 bg-surface-container-low border border-outline-variant/60 rounded-xl flex items-center gap-3">
                                <img src="{{ $campaign->paymentAccount->qr_code_url }}" alt="Payment QR" loading="lazy"
                                    class="w-14 h-14 rounded-lg object-contain bg-white border border-outline-variant/50 p-1 cursor-pointer hover:scale-105 transition-transform"
                                    onclick="openQrModal(@js($campaign->paymentAccount->qr_code_url))"
                                    title="{{ __('admin.click_to_view_large_qr') }}">
                                <div class="flex-1 min-w-0">
                                    <span class="text-[10px] uppercase font-bold tracking-wider text-outline block">{{ __('admin.payment_qr_code') }}</span>
                                    <span class="text-xs font-bold text-on-surface block truncate">{{ $campaign->paymentAccount->bank_code }} · {{ $campaign->paymentAccount->account_number }}</span>
                                    <button type="button" onclick="openQrModal(@js($campaign->paymentAccount->qr_code_url))"
                                        class="text-[11px] text-primary hover:underline font-medium inline-flex items-center gap-0.5 mt-0.5">
                                        <span class="material-symbols-outlined text-[13px]">zoom_in</span>
                                        <span>{{ __('admin.view_qr_code') }}</span>
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </section>

            <!-- SECTION 2: 2-COLUMN GRID (FINANCIAL SETTLEMENT + DELIVERY & SETTLEMENT STATUS) -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-stretch">
                <!-- COLUMN 1: FINANCIAL SETTLEMENT SUMMARY -->
                <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl shadow-xs overflow-hidden flex flex-col justify-between">
                    <div>
                        <div class="p-4 border-b border-outline-variant/60 bg-surface-container-low flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-[20px]">calculate</span>
                                <h3 class="text-sm font-bold text-on-surface">
                                    {{ __('admin.financial_settlement_summary') }}
                                </h3>
                            </div>
                            <button type="button" onclick="openModal('adjust-fee-modal')" @disabled($isCampaignClosed)
                                class="px-3 py-1.5 rounded-lg bg-primary/10 hover:bg-primary/20 text-primary border border-primary/20 text-xs font-semibold flex items-center gap-1.5 transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                                <span class="material-symbols-outlined text-[16px]">tune</span>
                                <span>{{ __('admin.adjust_fees_discount') }}</span>
                            </button>
                        </div>

                        <div class="p-5 space-y-3.5 text-xs">
                            <!-- Subtotal section -->
                            <div class="space-y-2">
                                <div class="flex justify-between items-center text-outline">
                                    <span>{{ __('admin.original_subtotal') }}:</span>
                                    <span class="font-mono text-on-surface font-semibold text-sm">{{ \App\Support\Helpers\FormatHelper::formatCurrency($grossSubtotal ?? 0) }}</span>
                                </div>
                                <div class="flex justify-between items-center text-outline">
                                    <span>{{ __('admin.delivery_fee_extra') }}:</span>
                                    <span class="font-mono text-amber-700 font-semibold">+{{ \App\Support\Helpers\FormatHelper::formatCurrency($campaign->delivery_fee ?? 0) }}</span>
                                </div>
                                @if ((int) ($campaign->discount ?? 0) > 0)
                                    <div class="flex justify-between items-center text-outline">
                                        <span>{{ __('admin.discount_input') }}:</span>
                                        <span class="font-mono text-emerald-700 font-semibold">-{{ \App\Support\Helpers\FormatHelper::formatCurrency($campaign->discount ?? 0) }}</span>
                                    </div>
                                @endif
                                <div class="flex justify-between items-center pt-2 font-bold text-on-surface border-t border-outline-variant/40">
                                    <span class="text-sm">{{ __('admin.gross_total') }}:</span>
                                    <span class="font-mono text-base text-primary font-bold">
                                        {{ \App\Support\Helpers\FormatHelper::formatCurrency($grossTotal) }}
                                    </span>
                                </div>
                            </div>

                            <!-- Subsidy section (if applicable) -->
                            @php
                                $sponsorTypeValue = (string) ($campaign->sponsor_type ?? \App\Models\Campaign::SPONSOR_TYPE_NONE);
                            @endphp
                            @if (
                                $sponsorSubsidy > 0 ||
                                    !empty($campaign->sponsor_name) ||
                                    $sponsorTypeValue !== \App\Models\Campaign::SPONSOR_TYPE_NONE ||
                                    !empty($campaign->sponsor_description) ||
                                    (!empty($campaign->sponsor_allocations) && count($campaign->sponsor_allocations) > 0))
                                <div class="space-y-2.5 bg-surface-container-low p-3.5 rounded-xl border border-outline-variant/60">
                                    <div class="flex justify-between items-center font-bold text-primary">
                                        <span class="flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-[16px]">redeem</span>
                                            <span>{{ __('admin.multi_sponsor_subsidy') }}</span>
                                        </span>
                                        <span class="font-mono text-emerald-700">-{{ \App\Support\Helpers\FormatHelper::formatCurrency($sponsorSubsidy ?? 0) }}</span>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2 text-[11px]">
                                        <span class="font-semibold text-outline">{{ __('admin.sponsor_type_label') }}:</span>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-emerald-600 text-white font-semibold">{{ __('admin.sponsor_type_' . $sponsorTypeValue) }}</span>
                                    </div>
                                    @if (!empty($campaign->sponsor_description))
                                        <p class="text-[11px] italic text-outline whitespace-pre-line leading-relaxed">{{ $campaign->sponsor_description }}</p>
                                    @endif
                                    @if (!empty($sponsorsList) && $sponsorsList->isNotEmpty())
                                        <div class="flex flex-wrap items-center gap-1.5 pt-1">
                                            @foreach ($sponsorsList as $sp)
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 border border-emerald-200/80 text-emerald-900 text-xs font-medium shadow-2xs">
                                                    <span class="material-symbols-outlined text-[14px] text-emerald-600">volunteer_activism</span>
                                                    <span class="font-bold text-emerald-950">{{ $sp['name'] }}</span>
                                                    @if (($sp['percentage'] ?? 0) > 0)
                                                        <span class="font-mono bg-emerald-600 text-white text-[10px] px-1.5 py-0.2 rounded-full font-bold">
                                                            {{ $sp['percentage'] }}%
                                                        </span>
                                                    @endif
                                                    @if (($sp['amount'] ?? 0) > 0)
                                                        <span class="font-mono text-emerald-800 text-[11px] font-semibold">
                                                            {{ \App\Support\Helpers\FormatHelper::formatCurrency($sp['amount']) }}
                                                        </span>
                                                    @endif
                                                </span>
                                            @endforeach
                                        </div>
                                    @elseif(!empty($campaign->sponsor_name))
                                        <div class="flex flex-wrap items-center gap-1.5 pt-1">
                                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 border border-emerald-200/80 text-emerald-900 text-xs font-medium shadow-2xs">
                                                <span class="material-symbols-outlined text-[14px] text-emerald-600">volunteer_activism</span>
                                                <span class="font-bold text-emerald-950">{{ $campaign->sponsor_name }}</span>
                                                <span class="font-mono text-emerald-800 text-[11px] font-semibold">
                                                    {{ \App\Support\Helpers\FormatHelper::formatCurrency($sponsorSubsidy ?? 0) }}
                                                </span>
                                            </span>
                                        </div>
                                    @endif
                                </div>
                            @endif

                            <!-- Self-paid ("trả riêng") total and per-member amounts -->
                            @if (($selfPaidTotal ?? 0) > 0)
                                <div class="space-y-2.5 bg-surface-container-low p-3.5 rounded-xl border border-outline-variant/60">
                                    <div class="flex justify-between items-center font-bold text-amber-800">
                                        <span class="flex items-center gap-1.5">
                                            <span class="material-symbols-outlined text-[16px] text-amber-600">person_pin</span>
                                            <span>{{ __('admin.self_paid_total_label') }}</span>
                                        </span>
                                        <span class="font-mono">{{ \App\Support\Helpers\FormatHelper::formatCurrency($selfPaidTotal) }}</span>
                                    </div>
                                    <p class="text-[11px] text-outline">{{ __('admin.self_paid_members_hint', ['count' => $selfPaidMembers->count()]) }}</p>
                                    <ul class="max-h-48 overflow-y-auto divide-y divide-outline-variant/40 rounded-lg border border-outline-variant/40 bg-surface-container-lowest">
                                        @foreach ($selfPaidMembers as $selfPaidMember)
                                            <li class="flex items-center justify-between gap-3 px-3 py-1.5">
                                                <span class="truncate text-on-surface font-medium" title="{{ $selfPaidMember['name'] }}">{{ $selfPaidMember['name'] }}</span>
                                                <span class="shrink-0 font-mono font-semibold text-amber-800">{{ \App\Support\Helpers\FormatHelper::formatCurrency($selfPaidMember['amount']) }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- COLUMN 2: DELIVERY STATUS & NOTIFICATION -->
                <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl shadow-xs overflow-hidden flex flex-col justify-between">
                    <div>
                        <div class="p-4 border-b border-outline-variant/60 bg-surface-container-low flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-primary text-[20px]">local_shipping</span>
                                <h3 class="text-sm font-bold text-on-surface">
                                    {{ __('admin.delivery_status_title') }}
                                </h3>
                            </div>
                            <x-admin.campaign-status-badge :campaign="$campaign" />
                        </div>

                        <div class="p-5 space-y-4 text-xs">
                            <p class="text-on-surface-variant leading-relaxed">
                                {{ __('admin.delivery_status_desc') }}
                            </p>

                            <div class="grid grid-cols-2 gap-3 pt-1">
                                <div class="p-3 bg-surface-container-low rounded-xl border border-outline-variant/60">
                                    <span class="text-[11px] text-outline block">{{ __('admin.delivering_orders_count') }}</span>
                                    <span class="text-base font-bold text-primary font-mono mt-0.5 block">
                                        {{ $orders->where('status', \App\Enums\OrderStatus::Delivering)->count() }} / {{ $orders->count() }}
                                    </span>
                                </div>
                                <div class="p-3 bg-surface-container-low rounded-xl border border-outline-variant/60">
                                    <span class="text-[11px] text-outline block">{{ __('admin.expected_delivery_time') }}</span>
                                    <span class="text-base font-bold text-on-surface font-mono mt-0.5 block" data-campaign-deadline-time>
                                        {{ $campaign->deadline?->format('H:i') ?? '--:--' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Self-paid debt calculation basis -->
                            @php
                                $selfPaidBasisValue = (string) ($campaign->self_paid_price_basis ?? \App\Models\Campaign::SELF_PAID_PRICE_BASIS_ORIGINAL);
                            @endphp
                            <div class="space-y-1.5 bg-surface-container-low p-3.5 rounded-xl border border-outline-variant/60">
                                <div class="flex items-center gap-1.5 font-bold text-on-surface text-[11px]">
                                    <span class="material-symbols-outlined text-[16px] text-amber-600">payments</span>
                                    <span>{{ __('admin.self_paid_price_basis_label') }}</span>
                                </div>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 border border-amber-300 text-[11px] font-semibold">
                                    {{ __('admin.self_paid_price_basis_' . $selfPaidBasisValue) }}
                                </span>
                                <p class="text-[11px] text-outline leading-relaxed">
                                    {{ __('admin.self_paid_price_basis_hint') }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="p-4 bg-surface-container-low border-t border-outline-variant/60">
                        <button type="button" onclick="openModal('confirm-delivery-modal')"
                            @disabled($isCampaignClosed)
                            class="w-full py-2.5 px-4 bg-primary hover:bg-primary-container text-on-primary rounded-xl text-xs font-bold flex items-center justify-center gap-2 shadow-xs transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                            <span class="material-symbols-outlined text-[18px]">delivery_dining</span>
                            <span>{{ __('admin.mark_items_delivered_btn') }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- SECTION 3: ORDER & COLLECTION STATS -->
            <div class="space-y-4">
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="p-4 bg-surface-container-low rounded-xl border border-outline-variant/60 space-y-1">
                        <span class="text-[11px] text-outline uppercase font-mono font-bold tracking-wider block">{{ __('admin.aggregated_items_list') }}</span>
                        <span class="text-xl font-bold font-mono text-primary">{{ count($aggregatedItems ?? []) }}</span>
                        <span class="text-[11px] text-outline block">{{ __('admin.total_portions_ordered') }}</span>
                    </div>
                    <div class="p-4 bg-surface-container-low rounded-xl border border-outline-variant/60 space-y-1">
                        <span class="text-[11px] text-outline uppercase font-mono font-bold tracking-wider block">{{ __('admin.orders_list_tab') }}</span>
                        <span class="text-xl font-bold font-mono text-on-surface">{{ $orders->count() }}</span>
                        <span class="text-[11px] text-outline block">{{ __('admin.orders_placed') }}</span>
                    </div>
                    <div class="p-4 bg-surface-container-low rounded-xl border border-outline-variant/60 space-y-1">
                        <span class="text-[11px] text-outline uppercase font-mono font-bold tracking-wider block">{{ __('admin.total_collected') }}</span>
                        <span class="text-xl font-bold font-mono text-emerald-700">{{ \App\Support\Helpers\FormatHelper::formatCurrency($totalPaid ?? 0) }}</span>
                        <span class="text-[11px] text-emerald-600 block">{{ $collectedRatio ?? 0 }}% {{ __('admin.collected_rate') }}</span>
                    </div>
                    <div class="p-4 bg-surface-container-low rounded-xl border border-outline-variant/60 space-y-1">
                        <span class="text-[11px] text-outline uppercase font-mono font-bold tracking-wider block">{{ __('admin.remaining_debt') }}</span>
                        <span class="text-xl font-bold font-mono text-amber-700">{{ \App\Support\Helpers\FormatHelper::formatCurrency($totalDebt ?? 0) }}</span>
                        <span class="text-[11px] text-outline block">{{ $campaign->debts->where('status', 'unpaid')->count() }} {{ __('admin.unpaid_debts') }}</span>
                    </div>
                </div>
            </div>
        @else
            <!-- DRAFT STATUS NOTICE -->
            <div class="bg-surface-container-lowest border border-dashed border-outline-variant rounded-2xl p-8 sm:p-12 text-center space-y-4">
                <div class="w-14 h-14 rounded-full bg-primary/10 text-primary flex items-center justify-center mx-auto">
                    <span class="material-symbols-outlined text-[30px]">edit_note</span>
                </div>
                <div class="max-w-md mx-auto space-y-1.5">
                    <h3 class="text-base font-bold text-on-surface">{{ __('admin.draft_campaign_notice_title') }}</h3>
                    <p class="text-xs text-outline leading-relaxed">{{ __('admin.draft_campaign_notice_desc') }}</p>
                </div>
                <div class="pt-2 flex justify-center gap-3">
                    <a href="{{ route('admin.campaigns.edit', [$room, $campaign]) }}"
                        class="px-5 py-2.5 rounded-xl bg-primary hover:bg-primary-container text-on-primary text-xs font-bold flex items-center gap-2 transition-colors no-underline shadow-xs">
                        <span class="material-symbols-outlined text-[18px]">edit</span>
                        <span>{{ __('admin.edit_campaign') }}</span>
                    </a>
                </div>
            </div>
        @endif

        <!-- MODAL: ADJUST FEES & DISCOUNT -->
        {{-- Modals reset their margin: their container uses space-y-*, which (Tailwind v4) adds a bottom margin to every
             child but the last, and that margin would leave a gap under a fixed full-screen backdrop. --}}
        <div id="adjust-fee-modal" class="fixed inset-0 z-50 !m-0 items-center justify-center p-4 bg-black/50 backdrop-blur-xs" style="display: none;" onclick="closeModalOnBackdrop(event, 'adjust-fee-modal')">
            <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col">
                <div class="p-5 border-b border-outline-variant/60 flex items-center justify-between bg-surface-container-low">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[22px]">tune</span>
                        <h3 class="text-base font-bold text-on-surface">{{ __('admin.adjust_fees_discount_title') }}</h3>
                    </div>
                    <button type="button" onclick="closeModal('adjust-fee-modal')" class="text-outline hover:text-on-surface cursor-pointer">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <form onsubmit="saveAdjustments(event)" class="p-5 space-y-4">
                    <p class="text-xs text-outline leading-relaxed">{{ __('admin.adjust_fees_discount_desc') }}</p>

                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold text-on-surface">{{ __('admin.delivery_fee_input') }}</label>
                        <div class="relative">
                            <input type="text" id="adjust-fee-input" inputmode="numeric" value="{{ number_format((int) ($campaign->delivery_fee ?? 0), 0, ',', '.') }}"
                                onkeydown="filterNumberInput(event)"
                                oninput="formatMoneyInput(event)"
                                class="w-full h-10 px-3 pr-8 bg-surface border border-outline-variant rounded-lg text-sm font-mono focus:border-primary focus:ring-1 focus:ring-primary outline-hidden"
                                placeholder="0">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-mono text-outline">đ</span>
                        </div>
                    </div>

                    <div class="space-y-1.5">
                        <label class="block text-xs font-semibold text-on-surface">{{ __('admin.discount_input') }}</label>
                        <div class="relative">
                            <input type="text" id="adjust-discount-input" inputmode="numeric" value="{{ number_format((int) ($campaign->discount ?? 0), 0, ',', '.') }}"
                                onkeydown="filterNumberInput(event)"
                                oninput="formatMoneyInput(event)"
                                class="w-full h-10 px-3 pr-8 bg-surface border border-outline-variant rounded-lg text-sm font-mono focus:border-primary focus:ring-1 focus:ring-primary outline-hidden"
                                placeholder="0">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-mono text-outline">đ</span>
                        </div>
                    </div>

                    <label class="flex items-center gap-2.5 cursor-pointer text-xs text-on-surface-variant select-none">
                        <input type="checkbox" id="adjust-notify-members" class="rounded border-outline-variant text-primary focus:ring-primary w-4 h-4">
                        <span class="font-medium">{{ __('admin.notify_members_checkbox_label') }}</span>
                    </label>

                    <div class="pt-3 border-t border-outline-variant/60 flex items-center justify-end gap-2">
                        <button type="button" onclick="closeModal('adjust-fee-modal')"
                            class="px-4 py-2 rounded-lg border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors cursor-pointer">
                            {{ __('admin.cancel') }}
                        </button>
                        <button type="submit" id="adjust-submit-btn"
                            class="px-4 py-2 rounded-lg bg-primary hover:bg-primary-container text-on-primary text-xs font-semibold flex items-center gap-1.5 transition-colors cursor-pointer shadow-xs">
                            <span id="adjust-submit-normal" class="inline-flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[16px]">save</span>
                                <span>{{ __('admin.save_adjustment') }}</span>
                            </span>
                            <span id="adjust-submit-loading" class="items-center gap-1.5" style="display: none;" role="status">
                                <span class="material-symbols-outlined animate-spin text-[16px]" aria-hidden="true">progress_activity</span>
                                <span>{{ __('admin.processing') }}</span>
                            </span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- MODAL: CONFIRM CANCEL CAMPAIGN -->
        <div id="confirm-cancel-modal" class="fixed inset-0 z-50 !m-0 items-center justify-center p-4 bg-black/50 backdrop-blur-xs" style="display: none;" onclick="closeModalOnBackdrop(event, 'confirm-cancel-modal')">
            <div class="bg-surface-container-lowest border border-error/30 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col">
                <div class="p-5 border-b border-error/20 flex items-center gap-3 bg-error-container/20">
                    <span class="w-10 h-10 rounded-full bg-error/10 text-error flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[24px]">warning</span>
                    </span>
                    <div>
                        <h3 class="text-base font-bold text-on-surface">{{ __('admin.confirm_cancel_campaign_title') }}</h3>
                        <p class="text-xs text-error/80 font-medium">#{{ $campaign->code }} · {{ $campaign->name }}</p>
                    </div>
                </div>

                <div class="p-5 space-y-4 text-xs">
                    <p class="text-on-surface leading-relaxed">{{ __('admin.confirm_cancel_campaign_desc') }}</p>
                    <div class="p-3 bg-surface-container-low border border-outline-variant/60 rounded-lg text-outline">
                        {{ __('admin.confirm_delete_temporary_campaign_message') }}
                    </div>

                    <div class="pt-3 border-t border-outline-variant/60 flex items-center justify-end gap-2">
                        <button type="button" id="cancel-campaign-back-btn" onclick="closeModal('confirm-cancel-modal')"
                            class="px-4 py-2 rounded-lg border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed">
                            {{ __('admin.cancel') }}
                        </button>
                        <button type="button" id="confirm-cancel-submit-btn" onclick="cancelCampaign()"
                            class="px-4 py-2 rounded-lg bg-error hover:bg-error/90 text-white text-xs font-semibold flex items-center gap-1.5 transition-colors cursor-pointer shadow-xs disabled:opacity-60 disabled:cursor-not-allowed">
                            <span id="cancel-campaign-btn-normal" class="flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[16px]">delete_forever</span>
                                <span>{{ __('admin.confirm_cancel_campaign_btn') }}</span>
                            </span>
                            <span id="cancel-campaign-btn-loading" class="items-center gap-1.5" style="display: none;">
                                <svg class="animate-spin h-4 w-4 text-white" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span>{{ __('admin.cancelling_status') }}</span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL: CLOSE CAMPAIGN SUMMARY & CONFIRM -->
        @if ($isCampaignLive)
            <x-admin.close-campaign-modal :room="$room" />
            @if ($campaign->isOpenForOrders())
                <x-admin.campaign-ordering-lock-modal />
            @endif
        @endif

        <!-- MODAL: CONFIRM ITEMS ARRIVED -->
        <div id="confirm-delivery-modal" class="fixed inset-0 z-50 !m-0 items-center justify-center p-4 bg-black/50 backdrop-blur-xs" style="display: none;" onclick="closeModalOnBackdrop(event, 'confirm-delivery-modal')">
            <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl shadow-2xl max-w-md w-full overflow-hidden">
                <div class="p-4 border-b border-outline-variant/60 flex items-center justify-between bg-surface-container-low">
                    <div class="flex items-center gap-2.5">
                        <span class="w-9 h-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-[22px]">delivery_dining</span>
                        </span>
                        <div>
                            <h3 class="text-sm font-bold text-on-surface">{{ __('admin.confirm_delivery_title') }}</h3>
                            <p class="text-[11px] text-outline font-mono font-code">#{{ $campaign->code }} · {{ $campaign->name }}</p>
                        </div>
                    </div>
                    <button type="button" onclick="closeModal('confirm-delivery-modal')" class="text-outline hover:text-on-surface cursor-pointer">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <div class="p-5 space-y-4 text-xs">
                    <p class="text-xs text-on-surface leading-relaxed">{{ __('admin.confirm_mark_delivering_prompt') }}</p>

                    <div class="pt-2 border-t border-outline-variant/60 flex items-center justify-end gap-2">
                        <button type="button" onclick="closeModal('confirm-delivery-modal')"
                            class="px-4 py-2.5 rounded-lg border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors cursor-pointer">
                            {{ __('admin.cancel') }}
                        </button>
                        <button type="button" id="execute-mark-delivering-btn" onclick="executeMarkDelivering()"
                            class="px-4 py-2.5 rounded-lg bg-primary hover:bg-primary-container text-on-primary text-xs font-semibold flex items-center gap-2 transition-colors cursor-pointer shadow-xs">
                            <span class="material-symbols-outlined text-[18px]">check_circle</span>
                            <span>{{ __('admin.confirm_delivery_btn') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- MODAL: CONFIRM RESEND NOTIFICATION -->
        @if ($isCampaignLive)
        <div id="confirm-resend-notification-modal" class="fixed inset-0 z-50 !m-0 items-center justify-center p-4 bg-black/50 backdrop-blur-xs" style="display: none;" onclick="closeModalOnBackdrop(event, 'confirm-resend-notification-modal', () => !document.getElementById('execute-resend-notification-btn')?.disabled)">
            <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col">
                <div class="p-5 border-b border-outline-variant/60 flex items-center gap-3 bg-surface-container-low">
                    <span class="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[22px]">campaign</span>
                    </span>
                    <div>
                        <h3 class="text-base font-bold text-on-surface">{{ __('admin.resend_notification') }}</h3>
                        <p class="text-xs text-outline font-mono font-code">#{{ $campaign->code }} · {{ $campaign->name }}</p>
                    </div>
                </div>

                <div class="p-5 space-y-4 text-xs">
                    <p class="text-on-surface leading-relaxed">{{ __('admin.resend_notification_confirm_message') }}</p>

                    <div class="pt-3 border-t border-outline-variant/60 flex items-center justify-end gap-2">
                        <button type="button" id="cancel-resend-notification-btn" onclick="closeModal('confirm-resend-notification-modal')"
                            class="px-4 py-2 rounded-lg border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                            {{ __('admin.cancel') }}
                        </button>
                        <button type="button" id="execute-resend-notification-btn" onclick="executeResendNotification()"
                            class="px-4 py-2 rounded-lg bg-primary hover:bg-primary-container text-on-primary text-xs font-semibold transition-colors cursor-pointer shadow-xs disabled:opacity-70 disabled:cursor-not-allowed">
                            <span id="resend-notification-normal" class="flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[16px]">send</span>
                                <span>{{ __('admin.resend_notification') }}</span>
                            </span>
                            <span id="resend-notification-loading" class="items-center gap-1.5" style="display: none;">
                                <svg class="animate-spin h-4 w-4 text-white" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                <span>{{ __('admin.processing') }}</span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- MODAL: CONFIRM DUPLICATE CAMPAIGN -->
        <div id="confirm-duplicate-modal" class="fixed inset-0 z-50 !m-0 items-center justify-center p-4 bg-black/50 backdrop-blur-xs" style="display: none;" onclick="closeModalOnBackdrop(event, 'confirm-duplicate-modal', () => !document.getElementById('execute-duplicate-btn')?.disabled)">
            <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col">
                <div class="p-5 border-b border-outline-variant/60 flex items-center gap-3 bg-surface-container-low">
                    <span class="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[22px]">content_copy</span>
                    </span>
                    <div>
                        <h3 class="text-base font-bold text-on-surface">{{ __('admin.duplicate_campaign') }}</h3>
                        <p class="text-xs text-outline font-mono font-code">#{{ $campaign->code }} · {{ $campaign->name }}</p>
                    </div>
                </div>

                <div class="p-5 space-y-4 text-xs">
                    <p class="text-on-surface leading-relaxed">{{ __('admin.confirm_duplicate_campaign') }}</p>

                    <div class="pt-3 border-t border-outline-variant/60 flex items-center justify-end gap-2">
                        <button type="button" id="cancel-duplicate-btn" onclick="closeModal('confirm-duplicate-modal')"
                            class="px-4 py-2 rounded-lg border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                            {{ __('admin.cancel') }}
                        </button>
                        <button type="button" id="execute-duplicate-btn" onclick="executeDuplicateCampaign()"
                            class="px-4 py-2 rounded-lg bg-primary hover:bg-primary-container text-on-primary text-xs font-semibold transition-colors cursor-pointer shadow-xs disabled:opacity-70 disabled:cursor-not-allowed">
                            <span id="duplicate-btn-normal" class="flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-[16px]">check_circle</span>
                                <span>{{ __('admin.confirm_duplicate') }}</span>
                            </span>
                            <span id="duplicate-btn-loading" class="items-center gap-1.5" style="display: none;">
                                <svg class="animate-spin h-4 w-4 text-white" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                <span>{{ __('admin.processing') }}</span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        @if ($isCampaignClosed)
            <!-- MODAL: ADJUST RECEIVING PAYMENT ACCOUNT (closed campaigns) -->
            <div id="adjust-payment-account-modal" class="fixed inset-0 z-50 !m-0 items-center justify-center p-4 bg-black/50 backdrop-blur-xs" style="display: none;" onclick="closeModalOnBackdrop(event, 'adjust-payment-account-modal', () => !document.getElementById('execute-payment-account-btn')?.disabled)">
                <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl shadow-2xl w-full max-w-md overflow-hidden flex flex-col">
                    <div class="p-5 border-b border-outline-variant/60 flex items-center gap-3 bg-surface-container-low">
                        <span class="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-[22px]">account_balance</span>
                        </span>
                        <div class="min-w-0">
                            <h3 class="text-base font-bold text-on-surface">{{ __('admin.campaign_payment_account_adjust') }}</h3>
                            <p class="text-xs text-outline font-mono font-code truncate">#{{ $campaign->code }} · {{ $campaign->name }}</p>
                        </div>
                    </div>

                    <div class="p-5 space-y-4 text-xs">
                        <p class="text-on-surface-variant leading-relaxed">{{ __('admin.campaign_payment_account_adjust_desc') }}</p>
                        @if ($adjustablePaymentAccounts->isEmpty())
                            <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-amber-800">{{ __('admin.no_payment_accounts') }}</p>
                        @else
                            <div>
                                <label for="adjust-payment-account-select" class="mb-1.5 block font-semibold text-on-surface">{{ __('admin.campaign_payment_account_label') }}</label>
                                <select id="adjust-payment-account-select"
                                    class="w-full px-3 py-2 bg-surface border border-outline-variant rounded-lg text-xs text-on-surface focus:outline-none focus:border-primary">
                                    @foreach ($adjustablePaymentAccounts as $account)
                                        <option value="{{ $account->id }}" @selected($account->id === $campaign->payment_account_id)>{{ $account->bank_code }} · {{ $account->account_number }} ({{ $account->account_name }})</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="pt-3 border-t border-outline-variant/60 flex items-center justify-end gap-2">
                            <button type="button" id="cancel-payment-account-btn" onclick="closeModal('adjust-payment-account-modal')"
                                class="px-4 py-2 rounded-lg border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                                {{ __('admin.cancel') }}
                            </button>
                            @if ($adjustablePaymentAccounts->isNotEmpty())
                                <button type="button" id="execute-payment-account-btn" onclick="executeUpdatePaymentAccount()"
                                    class="px-4 py-2 rounded-lg bg-primary hover:bg-primary-container text-on-primary text-xs font-semibold transition-colors cursor-pointer shadow-xs disabled:opacity-70 disabled:cursor-not-allowed inline-flex items-center gap-1.5">
                                    <span data-payment-account-icon class="material-symbols-outlined text-[16px]">save</span>
                                    <span>{{ __('admin.save_changes') }}</span>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

        <!-- MODAL: QR ENLARGE -->
        <div id="qr-modal" class="fixed inset-0 z-50 !m-0 items-center justify-center p-4 bg-black/60 backdrop-blur-xs" style="display: none;" onclick="closeModalOnBackdrop(event, 'qr-modal')">
            <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl shadow-2xl p-6 max-w-sm w-full text-center space-y-4">
                <div class="flex items-center justify-between pb-2 border-b border-outline-variant/60">
                    <h3 class="text-sm font-bold text-on-surface">{{ __('admin.payment_qr_code') }}</h3>
                    <button type="button" onclick="closeModal('qr-modal')" class="text-outline hover:text-on-surface cursor-pointer">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>
                <img id="qr-modal-image" src="" alt="Payment QR Large" loading="lazy" class="w-64 h-64 mx-auto object-contain bg-white p-2 rounded-xl border border-outline-variant">
                <button type="button" onclick="closeModal('qr-modal')" class="w-full py-2 bg-surface-container hover:bg-surface-container-high rounded-lg text-xs font-semibold text-on-surface transition-colors cursor-pointer">
                    {{ __('admin.close') }}
                </button>
            </div>
        </div>
    </div>
</x-admin.layout>
