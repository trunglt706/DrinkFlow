@php
    // Same rule as the debts page: the order is paid only to its campaign's own active account.
    $campaignAccount = app(\App\Services\Payment\ReceivingAccountResolver::class)->forCampaign($activeOrder?->campaign);
    $paymentAccountConfigured = $campaignAccount !== null;
    $orderCode = $activeOrder?->code ?? '';
    $proxyOrders = $activeOrder?->children ?? collect();
    $orderDisplayCode = $activeOrder?->code ? ('#' . $activeOrder->code) : '';
    $orderCampaign = $activeOrder?->campaign;
    $sponsorAllocations = collect($orderCampaign?->sponsor_allocations ?? []);
    $sponsorPercentage = (float) $sponsorAllocations->sum(static fn(array $allocation): float => (float) ($allocation['percentage'] ?? 0));
    $bankCode = $campaignAccount?->bank_code ?? '';
    $bankName = $campaignAccount?->bank_name ?? '';
    $accountNumber = $campaignAccount?->account_number ?? '';
    $accountName = $campaignAccount?->account_name ?? '';
    $isFullSponsor = $activeOrder?->campaign?->sponsor_type === \App\Models\Campaign::SPONSOR_TYPE_FULL || ((int) ($activeOrder?->sponsor_amount ?? 0) >= (int) ($activeOrder?->subtotal ?? 0) && (int) ($activeOrder?->subtotal ?? 0) > 0);
    $campaignSubtotal = (int) ($orderCampaign?->active_orders_subtotal ?? 0);
    $orderSubtotal = (int) ($activeOrder?->subtotal ?? 0);
    $orderDeliveryAmount = (int) ($activeOrder?->delivery_amount ?? 0);
    $orderDiscountAmount = (int) ($activeOrder?->discount_amount ?? 0);
    if ($activeOrder && $campaignSubtotal > 0) {
        $orderRatio = $orderSubtotal / $campaignSubtotal;
        if ($orderDeliveryAmount === 0) {
            $orderDeliveryAmount = (int) round((int) ($orderCampaign->delivery_fee ?? 0) * $orderRatio);
        }
        if ($orderDiscountAmount === 0) {
            $orderDiscountAmount = (int) round((int) ($orderCampaign->discount ?? 0) * $orderRatio);
        }
    }
    $orderGrossTotal = max(0, $orderSubtotal + $orderDeliveryAmount - $orderDiscountAmount);
    $orderSponsorAmount = $isFullSponsor ? $orderGrossTotal : (int) ($activeOrder?->sponsor_amount ?? 0);
    $orderFinalAmount = max(0, $orderGrossTotal - $orderSponsorAmount);
    $formattedOrderAmount = \App\Support\Helpers\FormatHelper::formatCurrency($orderFinalAmount);
    $orderTransferContent = $orderCode !== ''
        ? app(\App\Services\Payment\VietQrService::class)->transferContent((string) $orderCode, $roomUser->payerName())
        : '';
    $initialQrPayload = $campaignAccount && $accountNumber
        ? app(\App\Services\Payment\VietQrService::class)->generate($campaignAccount, $orderFinalAmount, $orderTransferContent)
        : '';

    $orderSponsorAllocations = collect($orderCampaign?->sponsor_allocations ?? []);
    $orderSponsorUserIds = $orderSponsorAllocations->pluck('room_user_id')->filter()->map(fn($id) => (int) $id);
    // Members sponsoring the order's campaign get a sponsor icon after their name (also while the campaign is live).
    $orderSponsorPercentages = $orderCampaign?->sponsorPercentages() ?? [];
    $orderSponsorRoomUsers = $orderSponsorUserIds->isNotEmpty()
        ? $room->roomUsers()->with('globalUser')->whereIn('id', $orderSponsorUserIds)->get()->keyBy('id')
        : collect();

    $orderSponsorsList = $orderSponsorAllocations->map(function ($alloc) use ($orderSponsorRoomUsers, $orderSponsorAmount) {
        $user = $orderSponsorRoomUsers->get((int) ($alloc['room_user_id'] ?? 0));
        $name = $user?->display_name ?? $user?->globalUser?->name ?? __('admin.sponsor_info');
        $percentage = (float) ($alloc['percentage'] ?? 0);
        $amount = (int) round(($orderSponsorAmount * $percentage) / 100);
        return [
            'name' => $name,
            'percentage' => $percentage,
            'amount' => $amount,
        ];
    })->values();

    if ($isFullSponsor && $orderSponsorsList->isNotEmpty()) {
        $firstSponsor = $orderSponsorsList->first();
        $firstSponsor['amount'] = max(0, $firstSponsor['amount'] + $orderSponsorAmount - (int) $orderSponsorsList->sum('amount'));
        $orderSponsorsList->put(0, $firstSponsor);
    }

    if ($orderSponsorsList->isEmpty() && !empty($orderCampaign?->sponsor_name)) {
        $orderSponsorsList->push([
            'name' => $orderCampaign->sponsor_name,
            'percentage' => (float) ($orderCampaign->sponsor_percentage ?? ($orderCampaign->sponsor_type === \App\Models\Campaign::SPONSOR_TYPE_FULL ? 100 : 0)),
            'amount' => $orderSponsorAmount,
        ]);
    }
@endphp

<x-room.layout :room="$room" :room-user="$roomUser" :user-rooms="$userRooms"
    :unread-notifications-count="$unreadNotificationsCount" :active-tab="'orders'"
    :title="__('room.orders.page_title')">
    <div class="flex flex-col w-full gap-space-md" x-data="{
        selectedOrder: null,
        qrModalOpen: false,
        paymentConfirmModalOpen: false,
        paymentDetailsModalOpen: false,
        pendingPaymentOrderId: null,
        paymentStatus: '{{ $orders->first() ? ($orders->first()->payment_status instanceof \BackedEnum ? $orders->first()->payment_status->value : (string) ($orders->first()->payment_status ?? 'unpaid')) : 'unpaid' }}',
        paymentDetails: {{ Js::from($paymentConfirmationDetails ?? ['requestedAt' => null, 'content' => null, 'approvedBy' => null, 'approvedAt' => null]) }},
        isSubmittingPayment: false,
        qrData: {
            configured: {{ $paymentAccountConfigured ? 'true' : 'false' }},
            bankCode: {{ Js::from($bankCode) }},
            bankName: {{ Js::from($bankName) }},
            accountNumber: {{ Js::from($accountNumber) }},
            accountName: {{ Js::from($accountName) }},
            amount: {{ $orderFinalAmount }},
            formattedAmount: {{ Js::from($formattedOrderAmount) }},
            transferContent: {{ Js::from($orderTransferContent) }},
            qrPayload: {{ Js::from($initialQrPayload) }},
            qrDataUrl: ''
        },
        async openQr(orderId, amount, formattedAmount, code, bankCode, bankName, accNum, accName) {
            this.qrData.amount = amount;
            this.qrData.formattedAmount = formattedAmount;
            if (code) this.qrData.transferContent = code;
            if (bankCode) this.qrData.bankCode = bankCode;
            if (bankName) this.qrData.bankName = bankName;
            if (accNum !== undefined && accNum !== null) this.qrData.accountNumber = accNum;
            if (accName) this.qrData.accountName = accName;

            try {
                this.qrData.qrDataUrl = this.qrData.qrPayload && window.QRCode
                    ? await QRCode.toDataURL(this.qrData.qrPayload, { width: 220, margin: 1, errorCorrectionLevel: 'M' })
                    : '';
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
            // No notification: the clicked icon turns into a check mark for 2 seconds.
            this.copiedField = field;
            clearTimeout(this.copiedTimer);
            this.copiedTimer = setTimeout(() => { this.copiedField = null; }, 2000);
        },
        openPaymentConfirm(orderId) {
            // No receiving account: there is nowhere to transfer to, so a payment cannot be reported (also enforced server-side).
            if (!this.qrData.configured) return;
            this.pendingPaymentOrderId = orderId;
            if (!this.paymentDetails.requestedAt) {
                this.paymentDetails.requestedAt = new Intl.DateTimeFormat(document.documentElement.lang || 'vi-VN', {
                    day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
                }).format(new Date());
            }
            this.qrModalOpen = false;
            this.paymentConfirmModalOpen = true;
        },
        openPaymentDetails() {
            this.paymentDetailsModalOpen = true;
        },
        campaignModalOpen: false,
        campaignLoading: false,
        campaignData: null,
        async openCampaignDetail(campaignId) {
            if (!campaignId) return;
            this.campaignModalOpen = true;
            this.campaignLoading = true;
            this.campaignData = null;
            try {
                const url = '{{ route('user.campaigns.details', ['room' => $room->slug, 'campaign' => ':id']) }}'.replace(':id', campaignId);
                const res = await fetch(url, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
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
        closePaymentConfirm() {
            if (this.isSubmittingPayment) return;
            this.paymentConfirmModalOpen = false;
            this.pendingPaymentOrderId = null;
        },
        async submitPaymentConfirmation() {
            const orderId = this.pendingPaymentOrderId;
            if (!orderId || this.isSubmittingPayment) return;

            this.isSubmittingPayment = true;
            this.paymentConfirmModalOpen = false;
            window.showGlobalLoading?.({{ Js::from(__('room.orders.confirm_modal_submitting')) }});
            try {
                const response = await fetch('/rooms/{{ $room->slug }}/orders/' + orderId + '/confirm-payment', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    credentials: 'same-origin'
                });
                const result = await response.json();
                if (response.ok && result.success) {
                    this.paymentStatus = 'pending';
                    this.paymentDetails = result.payment_confirmation || this.paymentDetails;
                    window.notify?.(result.message || {{ Js::from(__('room.orders.payment_submitted_success')) }}, 'success');
                } else {
                    window.notify?.(result.message || {{ Js::from(__('room.orders.payment_submit_error')) }}, 'error');
                }
            } catch (err) {
                window.notify?.({{ Js::from(__('room.orders.connection_error')) }}, 'error');
            } finally {
                this.isSubmittingPayment = false;
                this.pendingPaymentOrderId = null;
                window.hideGlobalLoading?.();
            }
        }
    }"
        @realtime-event.window="if ($event.detail?.name === 'debt.payment_approved' || $event.detail?.name === 'order.payment_approved') { paymentStatus = 'paid'; paymentDetails.approvedBy = $event.detail?.payload?.approved_by || paymentDetails.approvedBy; paymentDetails.approvedAt = $event.detail?.payload?.approved_at || paymentDetails.approvedAt; }">
        @if(session('status'))
            <div
                class="p-4 rounded-xl bg-primary-fixed text-on-primary-fixed font-semibold flex items-center gap-2 shadow-sm">
                <span class="material-symbols-outlined text-[20px]">check_circle</span>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div
                class="p-4 rounded-xl bg-error-container text-on-error-container font-semibold flex items-center gap-2 shadow-sm">
                <span class="material-symbols-outlined text-[20px]">error</span>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        @if(!$activeOrder && $orders->isEmpty())
            {{-- Nothing ordered in the live campaign: invite to order. Without a live campaign only the history below is shown. --}}
            @if($hasLiveCampaign ?? true)
            <div
                class="bg-white border border-slate-200/80 rounded-xl p-6 sm:p-7 text-center shadow-2xs flex flex-col items-center justify-center">
                <div
                    class="w-9 h-9 rounded-lg bg-emerald-50 text-[#006948] border border-emerald-100 flex items-center justify-center mb-2">
                    <span class="material-symbols-outlined text-[18px]">receipt_long</span>
                </div>
                <h3 class="text-xs sm:text-sm font-bold text-slate-800 tracking-tight mb-1" data-orders-current-empty>
                    {{ __('room.orders.no_current_order_title') }}
                </h3>
                <p class="text-[11px] sm:text-xs text-slate-500 max-w-sm mb-3.5 leading-relaxed">
                    {{ __('room.orders.no_orders_desc') }}
                </p>
                <a href="{{ route('user.campaigns.index', $room->slug) }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#006948] hover:bg-[#005137] text-white text-xs font-semibold shadow-2xs transition-colors cursor-pointer">
                    <span class="material-symbols-outlined text-[16px]">local_cafe</span>
                    <span>{{ __('room.dashboard.enter_campaign') }}</span>
                </a>
            </div>
            @endif
        @else
            <!-- Active / Most Recent Order Tracking Header -->
            @if($activeOrder)
                @php
                    $status = $activeOrder->status instanceof \BackedEnum ? $activeOrder->status->value : (string) $activeOrder->status;
                    $statusSteps = ['submitted' => 1, 'confirmed' => 2, 'ordering' => 3, 'ordered' => 3, 'delivering' => 3, 'completed' => 4];
                    $currentStep = $statusSteps[$status] ?? 1;
                @endphp

                <!-- Single Active Order Constraint Banner -->
                <div
                    class="w-full bg-secondary-container rounded-xl p-space-md shadow-sm flex items-center justify-between gap-space-md">
                    <div class="flex items-center gap-space-sm min-w-0">
                        <div
                            class="w-8 h-8 rounded-lg bg-surface-container-lowest flex items-center justify-center flex-shrink-0 text-primary">
                            <span class="material-symbols-outlined text-[20px]"
                                style="font-variation-settings: 'FILL' 1;">info</span>
                        </div>
                        <div class="flex flex-col min-w-0">
                            <p class="font-label-md text-label-md text-on-secondary-fixed font-semibold truncate">
                                {{ __('room.orders.page_title') }} {{ $orderDisplayCode }}
                            </p>
                            <p class="font-body-sm text-body-sm text-on-secondary-container">
                                {{ __('room.orders.subtitle', ['name' => $room->name]) }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-space-xs flex-shrink-0">
                        <span
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded bg-surface-container-lowest text-on-surface-variant font-label-sm text-label-sm shadow-sm">
                            <span class="w-1.5 h-1.5 rounded-full bg-primary animate-ping"></span>
                            {{ __('room.orders.live_status') }}
                        </span>
                    </div>
                </div>

                <!-- Order Meta & Profile Summary -->
                <div
                    class="w-full bg-surface-container-lowest rounded-xl p-space-lg shadow-sm border border-outline-variant/30">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-space-md">
                        <div class="flex flex-col gap-space-xs">
                            <div class="flex flex-wrap items-center gap-space-sm">
                                <span
                                    class="font-headline-lg text-headline-lg text-on-surface font-bold tracking-tight">{{ $orderDisplayCode }}</span>
                                <x-room.copy-button :text="$orderCode" />
                                <span
                                    class="px-2.5 py-0.5 rounded bg-surface-container-high text-on-surface-variant font-label-sm text-label-sm uppercase font-semibold">
                                    {{ $room->code ?? $room->slug }}
                                </span>
                                <span
                                    class="px-2.5 py-0.5 rounded bg-primary-fixed text-on-primary-fixed-variant font-label-sm text-label-sm font-semibold capitalize">
                                    {{ __('room.orders.status_' . $status) }}
                                </span>
                            </div>
                            <div
                                class="flex flex-wrap items-center gap-y-1 gap-x-space-md text-on-surface-variant font-body-sm text-body-sm">
                                <span class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[16px] text-secondary">schedule</span>
                                    {{ $activeOrder->created_at?->format('H:i, d/m/Y') }}
                                </span>
                                <span class="text-outline-variant">•</span>
                                <span class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[16px] text-secondary">groups</span>
                                    {{ __('room.orders.room_label') }}: <strong
                                        class="text-on-surface font-semibold ml-0.5">{{ $room->name }}</strong>
                                </span>
                                <span class="text-outline-variant hidden sm:inline">•</span>
                                <span class="flex items-center gap-1 min-w-0 max-w-full w-full sm:w-auto">
                                    <span class="material-symbols-outlined text-[16px] text-secondary shrink-0">storefront</span>
                                    <span class="whitespace-nowrap shrink-0">{{ __('room.orders.campaign_label') }}:</span>
                                    <strong class="text-on-surface font-semibold ml-0.5 truncate min-w-0"
                                        title="{{ $activeOrder->campaign?->name ?? 'Direct' }}">{{ $activeOrder->campaign?->name ?? 'Direct' }}</strong>
                                </span>
                            </div>
                        </div>
                        <div
                            class="flex items-center gap-space-md bg-surface-container-low p-space-sm rounded-xl w-full lg:w-auto lg:self-auto">
                            <x-avatar :user="$user" size="sm" class="flex-shrink-0 border-2 border-white ring-2 ring-[#006948]/30 shadow-xs" :alt="$user->name" />
                            <div class="flex flex-col pr-space-sm min-w-0 flex-1 lg:flex-none">
                                <span
                                    class="font-label-sm text-label-sm text-on-surface-variant">{{ __('room.orders.orderer_label') }}</span>
                                <span class="flex items-center gap-1 min-w-0">
                                    <span class="font-label-md text-label-md text-on-surface font-semibold truncate">{{ $user->name }}</span>
                                    @if (isset($orderSponsorPercentages[(int) $roomUser->id]))
                                        <x-room.sponsor-mark />
                                    @endif
                                </span>
                                <span
                                    class="font-tabular-nums text-tabular-nums text-[11px] text-secondary">{{ $roomUser->room_user_code }}</span>
                            </div>
                            <button
                                class="p-2 rounded-lg bg-surface-container-lowest hover:bg-surface-container-high text-on-surface-variant transition-colors shadow-sm cursor-pointer"
                                @click="copyText(window.location.href, 'link')" title="{{ __('room.orders.copy') }}">
                                <span class="material-symbols-outlined text-[18px]" x-text="copiedField === 'link' ? 'check' : 'share'">share</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- 4-Stage Realtime Tracking Stepper -->
                <div
                    class="w-full bg-surface-container-lowest rounded-xl p-space-lg shadow-sm border border-outline-variant/30">
                    <div class="flex items-center justify-between pb-space-md mb-space-md border-b border-outline-variant/20">
                        <div class="flex items-center gap-space-sm">
                            <span class="material-symbols-outlined text-primary text-[22px]">timeline</span>
                            <h3 class="font-headline-sm text-headline-sm text-on-surface font-semibold">
                                {{ __('room.orders.realtime_progress') }}
                            </h3>
                        </div>
                        <div class="hidden sm:flex items-center gap-space-xs text-on-surface-variant font-body-sm text-body-sm">
                            <span class="w-2 h-2 rounded-full bg-primary animate-pulse"></span>
                            <span>{{ __('room.orders.auto_updating') }}</span>
                        </div>
                    </div>

                    <div class="w-full py-space-xs">
                        <div class="grid grid-cols-4 gap-2 sm:gap-space-sm relative">
                            <!-- Step 1: Đã gửi -->
                            <div class="flex flex-col items-center text-center relative group">
                                <div
                                    class="absolute top-4 left-1/2 w-full h-1 {{ $currentStep > 1 ? 'bg-primary' : 'bg-surface-container-high' }} -z-0">
                                </div>
                                <div
                                    class="w-8 h-8 rounded-full {{ $currentStep >= 1 ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface-variant' }} flex items-center justify-center flex-shrink-0 shadow-sm z-10 mb-space-sm">
                                    <span
                                        class="material-symbols-outlined text-[18px]">{{ $currentStep > 1 ? 'check' : 'send' }}</span>
                                </div>
                                <span
                                    class="font-label-md text-label-md {{ $currentStep >= 1 ? 'text-primary font-bold' : 'text-on-surface-variant font-medium' }}">{{ __('room.orders.step_1_title') }}</span>
                                <span
                                    class="font-body-sm text-[11px] text-on-surface-variant">{{ __('room.orders.step_1_desc') }}</span>
                            </div>

                            <!-- Step 2: Đã xác nhận -->
                            <div class="flex flex-col items-center text-center relative group">
                                <div
                                    class="absolute top-4 left-1/2 w-full h-1 {{ $currentStep > 2 ? 'bg-primary' : 'bg-surface-container-high' }} -z-0">
                                </div>
                                <div
                                    class="w-8 h-8 rounded-full {{ $currentStep >= 2 ? ($currentStep == 2 ? 'bg-primary-container text-on-primary ring-4 ring-primary-fixed' : 'bg-primary text-on-primary') : 'bg-surface-container-high text-on-surface-variant' }} flex items-center justify-center flex-shrink-0 shadow-sm z-10 mb-space-sm">
                                    <span
                                        class="material-symbols-outlined text-[18px]">{{ $currentStep > 2 ? 'check' : 'task_alt' }}</span>
                                </div>
                                <span
                                    class="font-label-md text-[11px] sm:text-label-md leading-tight {{ $currentStep >= 2 ? 'text-primary font-bold' : 'text-on-surface-variant font-medium' }}">{{ __('room.orders.step_confirmed_title') }}</span>
                                <span
                                    class="font-body-sm text-[10px] sm:text-[11px] text-on-surface-variant">{{ __('room.orders.step_confirmed_desc') }}</span>
                            </div>

                            <!-- Step 3: Món đã được giao đến -->
                            <div class="flex flex-col items-center text-center relative group">
                                <div
                                    class="absolute top-4 left-1/2 w-full h-1 {{ $currentStep > 3 ? 'bg-primary' : 'bg-surface-container-high' }} -z-0">
                                </div>
                                <div
                                    class="w-8 h-8 rounded-full {{ $currentStep >= 3 ? ($currentStep == 3 ? 'bg-primary-container text-on-primary ring-4 ring-primary-fixed' : 'bg-primary text-on-primary') : 'bg-surface-container-high text-on-surface-variant' }} flex items-center justify-center flex-shrink-0 shadow-sm z-10 mb-space-sm">
                                    <span
                                        class="material-symbols-outlined text-[18px]">{{ $currentStep > 3 ? 'check' : 'two_wheeler' }}</span>
                                </div>
                                <span
                                    class="font-label-md text-[11px] sm:text-label-md leading-tight {{ $currentStep >= 3 ? 'text-primary font-bold' : 'text-on-surface-variant font-medium' }}">{{ __('room.orders.step_delivered_title') }}</span>
                                <span
                                    class="font-body-sm text-[10px] sm:text-[11px] text-on-surface-variant">{{ __('room.orders.step_delivered_desc') }}</span>
                            </div>

                            <!-- Step 4: Hoàn thành -->
                            <div class="flex flex-col items-center text-center relative group">
                                <div
                                    class="w-8 h-8 rounded-full {{ $currentStep >= 4 ? 'bg-primary text-on-primary' : 'bg-surface-container-high text-on-surface-variant' }} flex items-center justify-center flex-shrink-0 shadow-sm z-10 mb-space-sm">
                                    <span class="material-symbols-outlined text-[18px]">verified</span>
                                </div>
                                <span
                                    class="font-label-md text-label-md {{ $currentStep >= 4 ? 'text-primary font-bold' : 'text-on-surface-variant font-medium' }}">{{ __('room.orders.step_completed_title') }}</span>
                                <span
                                    class="font-body-sm text-[11px] text-on-surface-variant">{{ __('room.orders.step_completed_desc') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Order Details & Financial Settlement Columns -->
                <div class="grid grid-cols-1 lg:grid-cols-12 gap-space-md items-start">
                    <!-- Left: Items Breakdown (7 cols) -->
                    <div class="lg:col-span-7 flex flex-col gap-space-md">
                        <div
                            class="bg-surface-container-lowest rounded-xl p-space-md shadow-sm border border-outline-variant/30 flex flex-col gap-space-sm">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-space-sm">
                                    <span class="material-symbols-outlined text-primary text-[20px]">local_cafe</span>
                                    <h4 class="font-headline-sm text-headline-sm text-on-surface font-semibold">
                                        {{ __('room.orders.items_list') }}
                                    </h4>
                                </div>
                                <div class="flex items-center gap-2">
                                    @if($activeOrder->campaign_id)
                                        <button type="button" @click="openCampaignDetail({{ $activeOrder->campaign_id }})"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-sky-200 bg-sky-50 text-sky-800 text-[11px] font-bold hover:bg-sky-100 transition-colors">
                                            <span class="material-symbols-outlined text-[14px]">receipt_long</span>
                                            <span>{{ __('room.campaign.view_room_orders_button') }}</span>
                                        </button>
                                    @endif
                                    <span
                                        class="px-2 py-0.5 rounded bg-surface-container text-on-surface-variant font-label-sm text-label-sm">
                                        {{ $activeOrder->items->count() }} {{ __('room.orders.items_count_suffix') }}
                                    </span>
                                </div>
                            </div>

                            <!-- Items List -->
                            <div class="flex flex-col gap-3">
                                @forelse($activeOrder->items as $item)
                                    <x-room.order-item-card :item="$item" :rating="$itemRatings[$item->id] ?? null" />
                                @empty
                                    <div class="p-4 text-center text-on-surface-variant font-body-sm">
                                        {{ __('room.orders.empty_items') }}
                                    </div>
                                @endforelse
                            </div>
                        </div>

                        @if($proxyOrders->isNotEmpty())
                            <!-- Món đã order dùm: cùng bố cục với "Món đã chọn", kèm thông tin người được đặt hộ -->
                            <div
                                class="bg-surface-container-lowest rounded-xl p-space-md shadow-sm border border-outline-variant/30 flex flex-col gap-space-sm">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-space-sm">
                                        <span class="material-symbols-outlined text-primary text-[20px]">group</span>
                                        <h4 class="font-headline-sm text-headline-sm text-on-surface font-semibold">
                                            {{ __('room.orders.proxy_items_title') }}
                                        </h4>
                                    </div>
                                    <span
                                        class="px-2 py-0.5 rounded bg-surface-container text-on-surface-variant font-label-sm text-label-sm">
                                        {{ $proxyOrders->sum(static fn ($proxyOrder): int => $proxyOrder->items->count()) }} {{ __('room.orders.items_count_suffix') }}
                                    </span>
                                </div>

                                <div class="flex flex-col gap-3">
                                    @foreach($proxyOrders as $proxyOrder)
                                        @php
                                            $proxyGlobalUser = $proxyOrder->roomUser?->globalUser;
                                            $proxyName = $proxyOrder->roomUser?->display_name ?? $proxyGlobalUser?->name ?? __('room.orders.member_unknown');
                                        @endphp
                                        @foreach($proxyOrder->items as $proxyItem)
                                            <x-room.order-item-card :item="$proxyItem">
                                                <span class="font-semibold text-on-surface">{{ $proxyName }}</span>
                                                @if (isset($orderSponsorPercentages[(int) $proxyOrder->room_user_id]))
                                                    <x-room.sponsor-mark />
                                                @endif
                                                @if($proxyGlobalUser?->email)
                                                    <span>({{ $proxyGlobalUser->email }})</span>
                                                @endif
                                                <span>- {{ __('room.orders.proxy_order_code_is') }}</span>
                                                <span class="inline-flex items-center gap-0.5 font-mono text-outline">{{ $proxyOrder->code }}<x-room.copy-button :text="$proxyOrder->code" align="right" /></span>
                                            </x-room.order-item-card>
                                        @endforeach
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- Right: Financial Settlement (5 cols) -->
                    <div class="lg:col-span-5 flex flex-col gap-space-md">
                        <div
                            class="bg-surface-container-lowest rounded-xl p-space-lg shadow-sm border border-outline-variant/30 flex flex-col gap-space-md">
                            <div class="flex items-center justify-between pb-space-xs border-b border-outline-variant/20">
                                <div class="flex items-center gap-space-sm">
                                    <span
                                        class="material-symbols-outlined text-primary text-[20px]">account_balance_wallet</span>
                                    <h4 class="font-headline-sm text-headline-sm text-on-surface font-semibold">
                                        {{ __('room.debts.page_title') }}
                                    </h4>
                                </div>
                                @if($orderFinalAmount > 0)
                                    <template x-if="paymentStatus !== 'paid' && paymentStatus !== 'pending'">
                                        <span
                                            class="px-2.5 py-1 rounded bg-tertiary-fixed text-on-tertiary-fixed-variant font-label-sm text-label-sm font-semibold flex items-center gap-1">
                                            <span class="w-1.5 h-1.5 rounded-full bg-tertiary"></span>
                                            <span>{{ __('room.orders.payment_status_unpaid') }}</span>
                                        </span>
                                    </template>
                                @endif
                            </div>

                            <!-- Ledger Calculation List -->
                            <div class="flex flex-col gap-space-sm pt-space-xs">
                                <div
                                    class="flex items-center justify-between font-body-md text-body-md text-on-surface-variant">
                                    <span>{{ __('room.orders.subtotal') }}:</span>
                                    <span
                                        class="font-tabular-nums text-tabular-nums text-on-surface font-medium">{{ \App\Support\Helpers\FormatHelper::formatCurrency($activeOrder->subtotal) }}</span>
                                </div>
                                @if($orderSponsorAmount > 0)
                                    <div class="space-y-2 bg-emerald-50/60 p-2.5 rounded-xl border border-emerald-200/60">
                                        <div
                                            class="flex items-center justify-between font-body-md text-body-md text-primary font-medium">
                                            <span class="flex items-center gap-1.5">
                                                <span class="material-symbols-outlined text-[16px] text-emerald-600">redeem</span>
                                                <span
                                                    class="font-bold text-emerald-950">{{ __('room.orders.sponsor_discount') }}:</span>
                                            </span>
                                            <span
                                                class="font-tabular-nums text-tabular-nums font-bold text-emerald-700 font-mono">-{{ \App\Support\Helpers\FormatHelper::formatCurrency($orderSponsorAmount) }}</span>
                                        </div>
                                        @if($orderSponsorsList->isNotEmpty())
                                            <div class="flex flex-wrap items-center gap-1.5 pt-0.5">
                                                @foreach ($orderSponsorsList as $sp)
                                                    <span
                                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-50 border border-emerald-200/80 text-emerald-900 text-xs font-medium shadow-2xs">
                                                        <span
                                                            class="material-symbols-outlined text-[14px] text-emerald-600">volunteer_activism</span>
                                                        <span class="font-bold text-emerald-950">{{ $sp['name'] }}</span>
                                                        @if (($sp['percentage'] ?? 0) > 0)
                                                            <span
                                                                class="font-mono bg-emerald-600 text-white text-[10px] px-1.5 py-0.2 rounded-full font-bold">
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
                                        @endif
                                    </div>
                                @endif
                                @if($orderDiscountAmount > 0)
                                    <div
                                        class="flex items-center justify-between font-body-md text-body-md text-emerald-700 font-medium bg-emerald-50 p-2 rounded-lg border border-emerald-100">
                                        <span class="flex items-center gap-1">
                                            <span class="material-symbols-outlined text-[16px]">local_offer</span>
                                            {{ __('room.orders.discount_label') }}:
                                        </span>
                                        <span
                                            class="font-tabular-nums text-tabular-nums font-bold">-{{ \App\Support\Helpers\FormatHelper::formatCurrency($orderDiscountAmount) }}</span>
                                    </div>
                                @endif
                                @if($orderDeliveryAmount > 0)
                                    <div
                                        class="flex items-center justify-between font-body-md text-body-md text-on-surface-variant">
                                        <span>{{ __('room.orders.delivery_share') }}:</span>
                                        <span
                                            class="font-tabular-nums text-tabular-nums text-on-surface font-medium">+{{ \App\Support\Helpers\FormatHelper::formatCurrency($orderDeliveryAmount) }}</span>
                                    </div>
                                @endif

                                <div class="h-px w-full bg-surface-container-high my-space-xs"></div>

                                <!-- Total Payable -->
                                <div class="flex items-baseline justify-between p-space-sm rounded-xl bg-surface-container-low">
                                    <div class="flex flex-col">
                                        <span
                                            class="font-label-md text-label-md text-on-surface font-bold">{{ __('room.orders.final_amount') }}</span>
                                        <span
                                            class="font-body-sm text-[11px] text-on-surface-variant">{{ __('room.orders.deducted_fund') }}</span>
                                    </div>
                                    <div class="flex items-baseline gap-0.5">
                                        <span
                                            class="font-display-lg text-display-lg font-bold text-error tracking-tight font-tabular-nums">
                                            {{ \App\Support\Helpers\FormatHelper::formatCurrency($orderFinalAmount) }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            @if($orderFinalAmount > 0)
                                <!-- Prominent Notice about possible price changes when campaign closes -->
                                <div
                                    class="flex items-start gap-2.5 p-3 rounded-xl bg-amber-50/90 border border-amber-200 text-amber-900 shadow-2xs">
                                    <span class="material-symbols-outlined text-amber-700 text-[18px] shrink-0 mt-0.5">info</span>
                                    <div class="flex flex-col text-xs leading-relaxed">
                                        <span class="font-bold text-amber-950">{{ __('room.orders.payment_note_title') }}</span>
                                        <span class="text-amber-800 text-[11px]">{{ __('room.orders.payment_note_desc') }}</span>
                                    </div>
                                </div>

                                <!-- VietQR Payment Quick Action Box (When not fully paid and amount > 0) -->
                                <div x-show="paymentStatus !== 'paid'"
                                    class="mt-space-xs bg-surface-container-low p-space-md rounded-xl flex flex-col gap-space-sm border border-outline-variant/40">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-space-sm">
                                            <span class="material-symbols-outlined text-primary text-[20px]">qr_code_scanner</span>
                                            <span
                                                class="font-label-md text-label-md text-on-surface font-semibold">{{ __('room.orders.vietqr_pay_title') }}</span>
                                        </div>
                                    </div>

                                    <p class="font-body-sm text-body-sm text-on-surface-variant">
                                        {{ __('room.orders.vietqr_pay_desc') }}
                                    </p>

                                    <!-- Notice when pending admin approval -->
                                    <template x-if="paymentStatus === 'pending'">
                                        <button type="button" @click="openPaymentDetails()"
                                            class="w-full text-left p-2.5 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-xs flex items-start gap-2 hover:bg-amber-100 transition-colors cursor-pointer">
                                            <span
                                                class="material-symbols-outlined text-amber-700 text-[18px] shrink-0 mt-0.5">hourglass_top</span>
                                            <div class="flex-1">
                                                <span class="font-bold block">{{ __('room.orders.payment_pending_badge') }}</span>
                                                <span class="text-[11px]">{{ __('room.orders.payment_pending_notice') }}</span>
                                            </div>
                                            <span class="material-symbols-outlined text-[16px]">chevron_right</span>
                                        </button>
                                    </template>

                                    <!-- Action Buttons -->
                                    <div class="flex flex-col sm:flex-row items-center gap-2 pt-1">
                                        <!-- Button Quét mã VietQR với text-white -->
                                        <button
                                            class="w-full flex-1 py-2.5 px-space-md rounded-xl bg-blue-600 hover:bg-blue-700 text-white font-label-md text-label-md font-bold shadow-sm transition-all flex items-center justify-center gap-space-sm active:scale-[0.99] cursor-pointer"
                                            @click="openQr(
                                                                                                                                                                            {{ $activeOrder->id }},
                                                                                                                                                                            {{ (int) $orderFinalAmount }},
                                                                                                                                                                            '{{ $formattedOrderAmount }}',
                                                                                                                                                                            {{ Js::from($orderTransferContent) }},
                                                                                                                                                                            {{ Js::from($bankCode) }},
                                                                                                                                                                            {{ Js::from($bankName) }},
                                                                                                                                                                            {{ Js::from($accountNumber) }},
                                                                                                                                                                            {{ Js::from($accountName) }}
                                                                                                                                                                        )"
                                            type="button">
                                            <span class="material-symbols-outlined text-[20px] text-white">qr_code_2</span>
                                            <span class="text-white font-bold">{{ __('room.orders.pay_now_vietqr') }}</span>
                                        </button>

                                        <!-- Button Đã thanh toán -->
                                        <template x-if="paymentStatus !== 'pending' && qrData.configured">
                                            <button
                                                class="w-full sm:w-auto py-2.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-label-md text-label-md font-bold shadow-sm transition-all flex items-center justify-center gap-1.5 active:scale-[0.99] cursor-pointer shrink-0 disabled:opacity-50"
                                                :disabled="isSubmittingPayment" @click="openPaymentConfirm({{ $activeOrder->id }})"
                                                type="button">
                                                <span class="material-symbols-outlined text-[18px] text-white"
                                                    x-show="!isSubmittingPayment">check_circle</span>
                                                <span class="material-symbols-outlined text-[18px] text-white animate-spin"
                                                    x-show="isSubmittingPayment" x-cloak>progress_activity</span>
                                                <span class="text-white font-bold">{{ __('room.orders.mark_as_paid') }}</span>
                                            </button>
                                        </template>
                                    </div>
                                </div>

                                <!-- Settled Notice Box (When fully paid) -->
                                <button type="button" @click="openPaymentDetails()" x-show="paymentStatus === 'paid'" x-cloak
                                    class="w-full text-left mt-space-xs bg-emerald-50 border border-emerald-200 p-space-md rounded-xl flex items-center gap-space-sm text-emerald-900 shadow-2xs hover:bg-emerald-100 transition-colors cursor-pointer p-2">
                                    <span class="material-symbols-outlined text-emerald-600 text-[26px]">verified</span>
                                    <div class="flex flex-1 flex-col">
                                        <span
                                            class="font-bold text-sm text-emerald-800">{{ __('room.orders.payment_status_paid') }}</span>
                                        <span class="text-xs text-emerald-700">{{ __('room.orders.payment_settled_notice') }}</span>
                                    </div>
                                    <span class="material-symbols-outlined text-emerald-700 text-[18px]">chevron_right</span>
                                </button>
                            @else
                                <!-- Free / Fully Sponsored Order Notice -->
                                <div
                                    class="mt-space-xs bg-emerald-50 border border-emerald-200 p-space-md rounded-xl flex items-center gap-space-sm text-emerald-900 shadow-2xs">
                                    <span class="material-symbols-outlined text-emerald-600 text-[26px]">verified</span>
                                    <div class="flex flex-1 flex-col">
                                        <span
                                            class="font-bold text-sm text-emerald-800">{{ __('room.orders.no_payment_needed_title') }}</span>
                                        <span class="text-xs text-emerald-700">{{ __('room.orders.no_payment_needed_desc') }}</span>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

        @endif

        @isset($historyOrders)
            @include('user.partials.order-history', ['historyOrders' => $historyOrders, 'historyFilters' => $historyFilters ?? []])
        @endisset

        @if($activeOrder)
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
                            class="p-space-md border-b border-outline-variant flex items-center justify-between bg-surface-container-low">
                            <div class="flex items-center gap-space-sm">
                                <div
                                    class="w-9 h-9 rounded-lg bg-primary-fixed flex items-center justify-center text-on-primary-fixed-variant">
                                    <span class="material-symbols-outlined text-[20px]">qr_code_2</span>
                                </div>
                                <div>
                                    <h3 class="font-headline-sm text-headline-sm text-on-surface font-bold">
                                        {{ __('room.orders.pay_now_vietqr') }}
                                    </h3>
                                    <p class="font-body-sm text-body-sm text-on-surface-variant">{{ $room->name }}</p>
                                </div>
                            </div>
                            <button
                                class="w-8 h-8 rounded-lg flex items-center justify-center text-on-surface-variant hover:bg-surface-container-high transition-colors cursor-pointer"
                                @click="qrModalOpen = false">
                                <span class="material-symbols-outlined text-[20px]">close</span>
                            </button>
                        </div>
                        <div class="p-space-md flex flex-col gap-space-md">
                            <div
                                class="flex flex-col items-center justify-center p-space-md bg-surface-container-low rounded-xl border border-outline-variant/60">
                                <div x-show="!qrData.configured" data-qr-not-configured
                                    class="flex w-full flex-col items-center gap-2 rounded-xl border border-dashed border-amber-300 bg-amber-50 px-4 py-6 text-center">
                                    <span class="material-symbols-outlined text-[32px] text-amber-500">account_balance</span>
                                    <p class="text-sm font-semibold text-amber-800">{{ __('room.debts.payment_account_not_configured') }}</p>
                                    <p class="text-[11px] leading-relaxed text-amber-700">{{ __('room.debts.payment_account_not_configured_hint') }}</p>
                                </div>
                                <div x-show="qrData.configured"
                                    class="vietqr-snake-box relative bg-surface-container-lowest p-3 rounded-2xl shadow-sm border border-outline-variant flex flex-col items-center overflow-hidden">
                                    <!-- SVG Snake Border Animation -->
                                    <svg class="vietqr-snake-svg" viewBox="0 0 100 100" preserveAspectRatio="none"
                                        aria-hidden="true">
                                        <rect class="vietqr-snake-track" x="1.5" y="1.5" width="97" height="97" rx="8"
                                            ry="8" pathLength="100" />
                                        <rect class="vietqr-snake-line" x="1.5" y="1.5" width="97" height="97" rx="8" ry="8"
                                            pathLength="100" />
                                    </svg>
                                    <img :src="qrData.qrDataUrl" alt="VietQR"
                                        class="w-48 h-48 object-contain rounded-lg relative z-0" loading="lazy" />
                                </div>
                                <div class="mt-3 text-center">
                                    <span
                                        class="font-body-sm text-body-sm text-on-surface-variant">{{ __('room.orders.payment_amount_label') }}:</span>
                                    <div class="font-display-lg text-display-lg font-bold text-error tracking-tight font-tabular-nums"
                                        x-text="qrData.formattedAmount"></div>
                                </div>
                            </div>
                            <div x-show="qrData.configured"
                                class="flex flex-col gap-space-xs bg-surface-container-lowest border border-outline-variant rounded-xl p-space-sm">
                                <div
                                    class="flex items-center justify-between py-1 border-b border-surface-container-high text-body-sm">
                                    <span class="text-on-surface-variant">{{ __('room.orders.bank_label') }}:</span>
                                    <span class="font-semibold text-on-surface flex items-center gap-1">
                                        <span
                                            class="px-1.5 py-0.5 rounded bg-surface-container-high text-[11px] font-bold text-primary"
                                            x-text="qrData.bankName"></span>
                                    </span>
                                </div>
                                <div
                                    class="flex items-center justify-between py-1 border-b border-surface-container-high text-body-sm">
                                    <span
                                        class="text-on-surface-variant">{{ __('room.orders.account_number_label') }}:</span>
                                    <div class="flex items-center gap-1">
                                        <span class="font-tabular-nums font-bold text-on-surface"
                                            x-text="qrData.accountNumber"></span>
                                        <button
                                            class="p-1 rounded hover:bg-surface-container-high text-primary transition-colors flex items-center cursor-pointer"
                                            @click="copyText(qrData.accountNumber, 'account')">
                                            <span class="material-symbols-outlined text-[16px]" x-text="copiedField === 'account' ? 'check' : 'content_copy'">content_copy</span>
                                        </button>
                                    </div>
                                </div>
                                <div class="flex items-center justify-between py-1 border-b border-surface-container-high text-body-sm"
                                    x-show="qrData.accountName">
                                    <span class="text-on-surface-variant">{{ __('room.orders.account_name_label') }}:</span>
                                    <span class="font-bold text-on-surface text-xs uppercase"
                                        x-text="qrData.accountName"></span>
                                </div>
                                <div
                                    class="flex items-center justify-between py-1.5 bg-primary-fixed/20 px-2 rounded-lg mt-1 text-body-sm">
                                    <div class="flex flex-col">
                                        <span
                                            class="font-label-sm text-[11px] text-on-primary-fixed-variant font-medium">{{ __('room.orders.transfer_content_label') }}:</span>
                                        <span class="font-tabular-nums font-bold text-primary tracking-wide select-all"
                                            x-text="qrData.transferContent"></span>
                                    </div>
                                    <button
                                        class="p-1 rounded hover:bg-primary-fixed text-primary transition-colors flex items-center cursor-pointer"
                                        @click="copyText(qrData.transferContent, 'content')">
                                        <span class="material-symbols-outlined text-[16px]" x-text="copiedField === 'content' ? 'check' : 'content_copy'">content_copy</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Footer Actions in QR Modal -->
                            <div class="pt-2 border-t border-outline-variant/60 flex items-center justify-between gap-2">
                                <button type="button" @click="qrModalOpen = false"
                                    class="px-4 py-2 rounded-xl border border-outline-variant bg-surface-container-low text-on-surface font-semibold text-xs hover:bg-surface-container-high transition-colors cursor-pointer">
                                    {{ __('room.orders.close') }}
                                </button>
                                <template x-if="paymentStatus !== 'paid' && paymentStatus !== 'pending' && qrData.configured">
                                    <button type="button" :disabled="isSubmittingPayment"
                                        @click="openPaymentConfirm({{ $activeOrder->id }})"
                                        class="px-5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs flex items-center gap-1.5 shadow-sm transition-all cursor-pointer disabled:opacity-50">
                                        <span class="material-symbols-outlined text-[16px] text-white"
                                            x-show="!isSubmittingPayment">check_circle</span>
                                        <span class="material-symbols-outlined text-[16px] text-white animate-spin"
                                            x-show="isSubmittingPayment" x-cloak>progress_activity</span>
                                        <span class="text-white">{{ __('room.orders.mark_as_paid') }}</span>
                                    </button>
                                </template>
                                <template x-if="paymentStatus === 'pending'">
                                    <span
                                        class="text-xs font-bold text-amber-700 flex items-center gap-1 bg-amber-50 px-3 py-1.5 rounded-lg border border-amber-200">
                                        <span class="material-symbols-outlined text-[16px]">hourglass_top</span>
                                        <span>{{ __('room.orders.payment_pending_badge') }}</span>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <!-- Payment Confirmation Modal -->
            <template x-teleport="body">
                <div x-show="paymentConfirmModalOpen" x-cloak @keydown.escape.window="closePaymentConfirm()"
                    class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md"
                    role="dialog" aria-modal="true" aria-labelledby="payment-confirm-title">
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
                            <h3 id="payment-confirm-title" class="text-lg font-bold text-slate-900">
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
                                    <span class="text-slate-500">{{ __('room.orders.payment_request_content') }}</span>
                                    <span class="text-right font-mono font-bold text-[#006948]"
                                        x-text="paymentDetails.content || '{{ __('room.orders.not_available') }}'"></span>
                                </div>
                                <div class="border-t border-slate-200 pt-3">
                                    <div class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">
                                        {{ __('room.orders.payment_approval_info') }}
                                    </div>
                                    <div class="flex items-start justify-between gap-4">
                                        <span class="text-slate-500">{{ __('room.orders.payment_approved_by') }}</span>
                                        <span class="text-right font-semibold text-slate-900"
                                            x-text="paymentDetails.approvedBy || '{{ __('room.orders.payment_not_approved') }}'"></span>
                                    </div>
                                    <div class="mt-2 flex items-start justify-between gap-4">
                                        <span class="text-slate-500">{{ __('room.orders.payment_approved_at') }}</span>
                                        <span class="text-right font-semibold text-slate-900"
                                            x-text="paymentDetails.approvedAt || '{{ __('room.orders.not_available') }}'"></span>
                                    </div>
                                </div>
                            </div>
                            <div
                                class="mt-4 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs leading-5 text-amber-900">
                                <span
                                    class="material-symbols-outlined mt-0.5 shrink-0 text-[17px] text-amber-700">info</span>
                                <span>{{ __('room.orders.confirm_modal_note') }}</span>
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

            <!-- Payment Request Details Modal -->
            <template x-teleport="body">
                <div x-show="paymentDetailsModalOpen" x-cloak @keydown.escape.window="paymentDetailsModalOpen = false"
                    class="fixed inset-0 z-[110] flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-md"
                    role="dialog" aria-modal="true" aria-labelledby="payment-details-title">
                    <div x-show="paymentDetailsModalOpen" x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
                        @click.outside="paymentDetailsModalOpen = false"
                        class="w-full max-w-md overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xl">
                        <div class="p-5 sm:p-6">
                            <div
                                class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-[#006948]">
                                <span class="material-symbols-outlined text-[24px]">fact_check</span>
                            </div>
                            <h3 id="payment-details-title" class="text-lg font-bold text-slate-900">
                                {{ __('room.orders.payment_request_details_title') }}
                            </h3>
                            <div class="mt-4 space-y-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm">
                                <div class="flex items-start justify-between gap-4">
                                    <span class="text-slate-500">{{ __('room.orders.payment_request_time') }}</span>
                                    <span class="text-right font-semibold text-slate-900"
                                        x-text="paymentDetails.requestedAt || '{{ __('room.orders.not_available') }}'"></span>
                                </div>
                                <div class="flex items-start justify-between gap-4">
                                    <span class="text-slate-500">{{ __('room.orders.payment_request_content') }}</span>
                                    <span class="text-right font-mono font-bold text-[#006948]"
                                        x-text="paymentDetails.content || '{{ __('room.orders.not_available') }}'"></span>
                                </div>
                                <div class="border-t border-slate-200 pt-3">
                                    <div class="mb-2 text-xs font-bold uppercase tracking-wide text-slate-500">
                                        {{ __('room.orders.payment_approval_info') }}
                                    </div>
                                    <div class="flex items-start justify-between gap-4">
                                        <span class="text-slate-500">{{ __('room.orders.payment_approved_by') }}</span>
                                        <span class="text-right font-semibold text-slate-900"
                                            x-text="paymentDetails.approvedBy || '{{ __('room.orders.payment_not_approved') }}'"></span>
                                    </div>
                                    <div class="mt-2 flex items-start justify-between gap-4">
                                        <span class="text-slate-500">{{ __('room.orders.payment_approved_at') }}</span>
                                        <span class="text-right font-semibold text-slate-900"
                                            x-text="paymentDetails.approvedAt || '{{ __('room.orders.not_available') }}'"></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex justify-end border-t border-slate-100 bg-slate-50 px-5 py-4 sm:px-6">
                            <button type="button" @click="paymentDetailsModalOpen = false"
                                class="rounded-xl bg-[#006948] px-4 py-2.5 text-sm font-bold text-white hover:bg-[#005137] cursor-pointer">
                                {{ __('room.orders.close') }}
                            </button>
                        </div>
                    </div>
                </div>
            </template>

        @endif

        <!-- Room Orders Modal: xem đơn của tất cả thành viên trong phòng cho chiến dịch này (mở từ đơn hiện tại hoặc lịch sử đơn) -->
        <x-room.campaign-orders-modal />
    </div>
</x-room.layout>
