<x-global.layout
  :title="'DrinkFlow - ' . __('global.payments.page_title')"
  :user="$user"
  :active-tab="'payments'"
  :breadcrumbs="$breadcrumbs"
  :unread-notifications-count="$unreadNotificationsCount"
  :notifications="$notifications"
>
  <div class="space-y-8"
    x-data="{
      showQrModal: false,
      toastMessage: '',
      showToast: false,
      qrData: {
        order_id: '',
        order_code: '',
        amount: {{ $totalUnpaidAmount ?: 0 }},
        bank_name: '{{ $defaultBank['bank_name'] ?? '' }}',
        bank_code: '{{ $defaultBank['bank_code'] ?? '' }}',
        account_number: '{{ $defaultBank['account_number'] ?? '' }}',
        account_name: '{{ $defaultBank['account_name'] ?? '' }}',
        has_bank: {{ !empty($defaultBank['account_number']) ? 'true' : 'false' }},
        transfer_content: '{{ $user->code }}',
        payload: '',
        qrDataUrl: '',
        room_name: 'DrinkFlow Room',
      },
      async openQr(customData) {
        if (customData) {
          this.qrData = Object.assign({}, this.qrData, customData);
        }
        try {
          this.qrData.qrDataUrl = this.qrData.payload && window.QRCode
            ? await QRCode.toDataURL(this.qrData.payload, { width: 200, margin: 1, errorCorrectionLevel: 'M' })
            : '';
        } catch (error) {
          console.error('QR render error:', error);
          this.qrData.qrDataUrl = '';
        }
        this.showQrModal = true;
      },
      closeQr() {
        this.showQrModal = false;
      },
      copyToClipboard(text, label) {
        navigator.clipboard.writeText(text).then(() => {
          this.triggerToast('{{ __('global.common.copied') }} ' + label + ': ' + text);
        }).catch(() => {
          this.triggerToast('{{ __('global.common.copied') }} ' + label);
        });
      },
      triggerToast(msg) {
        this.toastMessage = msg;
        this.showToast = true;
        setTimeout(() => { this.showToast = false; }, 3000);
      },
      formatMoney(amount) {
        return new Intl.NumberFormat('vi-VN').format(amount || 0) + '{{ __('global.common.money_suffix') }}';
      },
    }"
    @keydown.escape.window="closeQr()"
  >

    <!-- Toast Notification -->
    <div
      x-show="showToast"
      x-cloak
      x-transition:enter="transition ease-out duration-200"
      x-transition:enter-start="opacity-0 translate-y-2"
      x-transition:enter-end="opacity-100 translate-y-0"
      x-transition:leave="transition ease-in duration-150"
      x-transition:leave-start="opacity-100 translate-y-0"
      x-transition:leave-end="opacity-0 translate-y-2"
      class="fixed bottom-6 right-6 z-50 bg-slate-900 text-white text-xs font-semibold px-4 py-3 rounded-xl shadow-xl flex items-center gap-2"
    >
      <span class="material-symbols-outlined text-emerald-400 text-[18px]">check_circle</span>
      <span x-text="toastMessage"></span>
    </div>

    <!-- Breadcrumb & Title Area -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 text-xs text-slate-400 mb-1">
          <a href="{{ route('user.me.dashboard') }}" class="hover:text-slate-700 transition-colors">{{ __('global.payments.breadcrumb_portal') }}</a>
          <span class="material-symbols-outlined text-[13px]">chevron_right</span>
          <a href="{{ route('user.me.dashboard') }}" class="hover:text-slate-700 transition-colors">{{ __('global.payments.breadcrumb_personal') }}</a>
          <span class="material-symbols-outlined text-[13px]">chevron_right</span>
          <span class="text-[#006948] font-medium">{{ __('global.payments.breadcrumb_reconcile') }}</span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight">{{ __('global.payments.title') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('global.payments.subtitle') }}</p>
      </div>

      <!-- Quick Actions Header -->
      <div class="flex items-center gap-3">
        @if($displayedOrders->isEmpty())
        <button
          type="button"
          disabled
          class="inline-flex items-center gap-2 px-3.5 py-2.5 bg-slate-50 border border-slate-200 text-slate-400 rounded-xl text-xs font-semibold cursor-not-allowed"
        >
          <span class="material-symbols-outlined text-[17px]">download</span>
          <span>{{ __('global.payments.export_excel') }}</span>
        </button>
        @else
        <a
          href="{{ route('user.me.payments.export', request()->only(['filter', 'sort'])) }}"
          class="inline-flex items-center gap-2 px-3.5 py-2.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 rounded-xl text-xs font-semibold transition-colors shadow-2xs cursor-pointer"
        >
          <span class="material-symbols-outlined text-[17px]">download</span>
          <span>{{ __('global.payments.export_excel') }}</span>
        </a>
        @endif
      </div>
    </div>

    <!-- Financial summary -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="bg-white border {{ $totalUnpaidAmount > 0 ? 'border-rose-200' : 'border-slate-200/80' }} rounded-xl p-5 shadow-xs">
        <p class="text-xs font-medium text-slate-500">{{ __('global.payments.total_unpaid') }}</p>
        <p class="mt-2 text-2xl font-bold {{ $totalUnpaidAmount > 0 ? 'text-rose-600' : 'text-slate-900' }} tracking-tight">
          {{ \App\Support\Helpers\FormatHelper::formatCurrency($totalUnpaidAmount) }}
        </p>
        <p class="mt-1 text-xs text-slate-400">{{ __('global.payments.unpaid_orders_count', ['count' => $unpaidCount]) }}</p>
      </div>
      <div class="bg-white border border-slate-200/80 rounded-xl p-5 shadow-xs">
        <p class="text-xs font-medium text-slate-500">{{ __('global.payments.paid_this_month') }}</p>
        <p class="mt-2 text-2xl font-bold text-[#006948] tracking-tight">
          {{ \App\Support\Helpers\FormatHelper::formatCurrency($paidThisMonthAmount) }}
        </p>
        <p class="mt-1 text-xs text-slate-400">{{ __('global.payments.transactions_count', ['count' => $paidThisMonthCount]) }}</p>
      </div>
      <div class="bg-white border border-slate-200/80 rounded-xl p-5 shadow-xs">
        <p class="text-xs font-medium text-slate-500">{{ __('global.payments.total_sponsor_received') }}</p>
        <p class="mt-2 text-2xl font-bold text-slate-900 tracking-tight">
          {{ \App\Support\Helpers\FormatHelper::formatCurrency($totalSponsorReceived) }}
        </p>
      </div>
    </div>

    <!-- Filter Bar & Search Container -->
    <div class="bg-white border border-slate-200/80 rounded-2xl p-4 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4">
      <!-- Filter Tabs -->
      <div class="flex items-center p-1 bg-slate-100 rounded-xl w-fit">
        <a
          href="{{ route('user.me.payments', array_merge(request()->query(), ['filter' => 'all'])) }}"
          class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5 {{ $activeFilter === 'all' ? 'bg-white text-slate-900 shadow-2xs' : 'text-slate-600 hover:text-slate-900' }}"
        >
          <span>{{ __('global.payments.tab_all') }}</span>
          <span class="bg-slate-200/70 text-slate-600 px-1.5 py-0.2 rounded-full text-[10px]">{{ $totalCount }}</span>
        </a>

        <a
          href="{{ route('user.me.payments', array_merge(request()->query(), ['filter' => 'unpaid'])) }}"
          class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5 {{ $activeFilter === 'unpaid' ? 'bg-white text-rose-600 shadow-2xs border border-rose-200/60' : 'text-slate-600 hover:text-slate-900' }}"
        >
          <span>{{ __('global.payments.tab_unpaid') }}</span>
          <span class="bg-rose-100 text-rose-700 px-1.5 py-0.2 rounded-full text-[10px] font-bold">{{ $unpaidCount }}</span>
        </a>

        <a
          href="{{ route('user.me.payments', array_merge(request()->query(), ['filter' => 'paid'])) }}"
          class="px-3.5 py-1.5 rounded-lg text-xs font-semibold transition-colors flex items-center gap-1.5 {{ $activeFilter === 'paid' ? 'bg-white text-[#006948] shadow-2xs' : 'text-slate-600 hover:text-slate-900' }}"
        >
          <span>{{ __('global.payments.tab_paid') }}</span>
          <span class="bg-slate-200/70 text-slate-600 px-1.5 py-0.2 rounded-full text-[10px]">{{ $paidCount }}</span>
        </a>
      </div>

      <!-- Quick Utilities / Sorting -->
      <form method="GET" action="{{ route('user.me.payments') }}" class="flex items-center gap-3">
        @if($activeFilter !== 'all')
          <input type="hidden" name="filter" value="{{ $activeFilter }}">
        @endif
        <div class="relative">
          <select
            name="sort"
            onchange="this.form.submit()"
            class="h-9 pl-3 pr-8 bg-slate-50 border border-slate-200 rounded-xl text-xs font-medium text-slate-700 focus:bg-white focus:border-[#006948] focus:ring-1 focus:ring-[#006948] appearance-none cursor-pointer"
          >
            <option value="due_asc" @selected($sort === 'due_asc')>{{ __('global.payments.sort_due_asc') }}</option>
            <option value="amount_desc" @selected($sort === 'amount_desc')>{{ __('global.payments.sort_amount_desc') }}</option>
            <option value="amount_asc" @selected($sort === 'amount_asc')>{{ __('global.payments.sort_amount_asc') }}</option>
          </select>
          <span class="material-symbols-outlined absolute right-2.5 top-2 text-slate-400 text-[18px] pointer-events-none">expand_more</span>
        </div>
      </form>
    </div>

    <!-- Payment Cards List (Itemized Debt Workspace) -->
    <div class="space-y-4">
      @if($displayedOrders->isEmpty())
        <!-- Empty State -->
        <div class="bg-white border border-slate-200/80 rounded-2xl p-12 text-center shadow-xs">
          <div class="w-14 h-14 rounded-2xl bg-slate-100 text-slate-400 flex items-center justify-center mx-auto mb-3">
            <span class="material-symbols-outlined text-[28px]">payments</span>
          </div>
          <h3 class="text-sm font-bold text-slate-900 mb-1">{{ __('global.payments.empty_title') }}</h3>
          <p class="text-xs text-slate-500 max-w-sm mx-auto mb-4">
            @if($activeFilter === 'unpaid')
              {{ __('global.payments.empty_unpaid_desc') }}
            @else
              {{ __('global.payments.empty_default_desc') }}
            @endif
          </p>
          <a href="{{ route('user.me.orders') }}" class="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 rounded-xl text-xs font-semibold transition-colors">
            <span class="material-symbols-outlined text-[16px]">receipt_long</span>
            {{ __('global.payments.view_orders') }}
          </a>
        </div>
      @else
        @foreach($displayedOrders as $order)
          @php
            $orderStatus = is_object($order->status) ? $order->status->value : (string) $order->status;
            $isUnpaid = in_array($orderStatus, ['submitted', 'confirmed', 'pending']);
            $isPaid = in_array($orderStatus, ['paid', 'completed']);

            // Resolve bank and host info from campaign or room
            $account = $order->campaign?->paymentAccount
                ?? $order->room?->paymentAccounts?->where('is_default', true)->first()
                ?? $order->room?->paymentAccounts?->first();

            $bankName = $account?->bank_name ?? $defaultBank['bank_name'];
            $bankCode = $account?->bank_code ?? $defaultBank['bank_code'];
            $accNumber = $account ? $account->getRawOriginal('account_number') : $defaultBank['account_number'];
            $accName = $account?->account_name ?? ($order->campaign?->sponsor_name ?? $defaultBank['account_name']);
          $hostName = $order->campaign?->sponsor_name ?? __('global.payments.host_room', ['name' => $order->room?->name ?? '']);

            // Transfer note syntax
            $transferSyntax = $order->code;

            // Items summary
            $itemsSummary = $order->items->map(function ($item) {
                $txt = $item->quantity . 'x ' . $item->item_name;
                if ($item->size_name) $txt .= ' (' . $item->size_name . ')';
                return $txt;
            })->join(', ');

            $orderQrData = [
                'order_id' => $order->id,
                'order_code' => $order->code ?? 'N/A',
                'amount' => (int) $order->final_amount,
                'bank_name' => $bankName,
                'bank_code' => $bankCode,
                'account_number' => $accNumber,
                'account_name' => $accName,
                'transfer_content' => $transferSyntax,
                'room_name' => $order->room?->name ?? __('global.common.room'),
                'restaurant' => $order->campaign?->restaurant ?? ($order->campaign?->name ?? 'Store'),
            ];
          @endphp

          @if($isUnpaid)
            <!-- UNPAID DEBT CARD (Cảnh báo nợ đỏ) -->
            <div class="bg-white border-2 border-rose-200/80 hover:border-rose-300 rounded-2xl p-5 shadow-xs transition-all relative">
              <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-5">
                <!-- Column 1: Order Identity & Meta -->
                <div class="flex items-start gap-4">
                  <div class="w-12 h-12 rounded-xl bg-rose-50 border border-rose-200 flex items-center justify-center text-rose-600 shrink-0">
                    <span class="material-symbols-outlined text-[24px]">receipt_long</span>
                  </div>
                  <div>
                    <div class="flex flex-wrap items-center gap-2 mb-1.5">
                      <span class="font-bold text-base text-slate-900 font-mono">{{ $order->code ?? 'N/A' }}</span>
                      <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-pulse"></span>
                        {{ __('global.payments.tab_unpaid') }}
                      </span>
                      <span class="text-xs text-slate-400">• {{ __('global.common.room') }}: <strong class="text-slate-800">{{ $order->room?->name ?? __('global.common.room') }}</strong></span>
                    </div>

                    <div class="flex flex-wrap items-center gap-y-1 gap-x-3 text-xs text-slate-500">
                      <span class="flex items-center gap-1">
                        <span class="material-symbols-outlined text-[15px] text-slate-400">storefront</span>
                        {{ __('global.dashboard.col_restaurant') }}: <strong class="text-slate-800 font-medium">{{ $order->campaign?->restaurant ?? ($order->campaign?->name ?? '') }}</strong>
                      </span>
                      <span>•</span>
                      <span class="flex items-center gap-1">
                        <span class="material-symbols-outlined text-[15px] text-slate-400">schedule</span>
                        {{ $order->created_at ? $order->created_at->format('d/m/Y H:i') : '' }}
                      </span>
                    </div>

                    <div class="text-xs text-slate-400 mt-2 flex flex-wrap items-center gap-2">
                      <span>{{ __('global.payments.transfer_syntax') }} <code class="bg-slate-100 px-1.5 py-0.5 rounded text-slate-800 font-mono text-[11px]">{{ $transferSyntax }}</code></span>
                      <span>•</span>
                      <span>{{ __('global.payments.reconcile_code') }} <span class="font-mono text-slate-700">{{ $order->code }}</span></span>
                    </div>
                  </div>
                </div>

                <!-- Column 2: Financials & Instant Actions -->
                <div class="flex flex-col sm:flex-row lg:flex-col sm:items-end justify-between lg:justify-center border-t lg:border-t-0 pt-4 lg:pt-0 border-slate-100 gap-3 shrink-0">
                  <div class="text-left sm:text-right">
                    <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">{{ __('global.payments.col_total_amount') }}</div>
                    <div class="text-2xl font-bold text-rose-600 font-mono">{{ \App\Support\Helpers\FormatHelper::formatCurrency($order->final_amount) }}</div>
                    @if($order->sponsor_amount > 0)
                      <div class="text-xs text-emerald-700 font-semibold mt-0.5">
                        {{ __('global.payments.sponsored_amount', ['amount' => \App\Support\Helpers\FormatHelper::formatCurrency($order->sponsor_amount)]) }}
                      </div>
                    @endif
                  </div>

                  <div class="flex items-center gap-2">
                    <button
                      type="button"
                      @click="openQr({{ json_encode(array_merge($orderQrData, ['payload' => $qrPayloads[$order->id] ?? ''])) }})"
                      class="inline-flex items-center gap-1.5 px-4 py-2 bg-[#006948] hover:bg-[#005137] text-white rounded-xl text-xs font-semibold shadow-xs transition-colors cursor-pointer"
                    >
                      <span class="material-symbols-outlined text-[16px]">qr_code_scanner</span>
                      {{ __('global.payments.scan_vietqr') }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          @else
            <!-- PAID ORDER CARD (Thẻ đã thanh toán thành công) -->
            <div class="bg-white border border-slate-200/80 hover:border-slate-300 rounded-2xl p-5 shadow-xs transition-all">
              <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-5">
                <!-- Column 1: Order Identity & Meta -->
                <div class="flex items-start gap-4">
                  <div class="w-12 h-12 rounded-xl bg-emerald-50 border border-emerald-200/80 flex items-center justify-center text-[#006948] shrink-0">
                    <span class="material-symbols-outlined text-[24px]">verified</span>
                  </div>
                  <div>
                    <div class="flex flex-wrap items-center gap-2 mb-1.5">
                      <span class="font-bold text-base text-slate-900 font-mono">{{ $order->code ?? 'N/A' }}</span>
                      <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        <span class="material-symbols-outlined text-[13px]">check</span>
                        {{ __('global.payments.paid_badge') }}
                      </span>
                      <span class="text-xs text-slate-400">• {{ __('global.common.room') }}: <strong class="text-slate-800">{{ $order->room?->name ?? __('global.common.room') }}</strong></span>
                    </div>

                    <div class="flex flex-wrap items-center gap-y-1 gap-x-3 text-xs text-slate-500">
                      <span class="flex items-center gap-1">
                        <span class="material-symbols-outlined text-[15px] text-slate-400">storefront</span>
                        {{ __('global.dashboard.col_restaurant') }}: <strong class="text-slate-800 font-medium">{{ $order->campaign?->restaurant ?? ($order->campaign?->name ?? '') }}</strong>
                      </span>
                      <span>•</span>
                      <span class="flex items-center gap-1 text-emerald-700 font-medium">
                        <span class="material-symbols-outlined text-[15px]">task_alt</span>
                        {{ __('global.payments.auto_reconciled_napas') }}
                      </span>
                    </div>

                    <div class="text-xs text-slate-400 mt-2 flex flex-wrap items-center gap-2">
                      <span>{{ __('global.payments.transfer_syntax') }} <code class="bg-slate-100 px-1.5 py-0.5 rounded text-slate-800 font-mono text-[11px]">{{ $transferSyntax }}</code></span>
                      <span>•</span>
                      <span>{{ __('global.payments.reconcile_code') }} <span class="font-mono text-slate-700">{{ $order->code }}</span></span>
                    </div>
                  </div>
                </div>

                <!-- Column 2: Financials -->
                <div class="flex flex-col sm:flex-row lg:flex-col sm:items-end justify-between lg:justify-center border-t lg:border-t-0 pt-4 lg:pt-0 border-slate-100 gap-3 shrink-0">
                  <div class="text-left sm:text-right">
                    <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">{{ __('global.payments.amount_paid') }}</div>
                    <div class="text-2xl font-bold text-slate-900 font-mono">{{ \App\Support\Helpers\FormatHelper::formatCurrency($order->final_amount) }}</div>
                    <div class="text-xs text-emerald-700 flex items-center sm:justify-end gap-1 font-semibold mt-0.5">
                      <span class="material-symbols-outlined text-[14px]">check</span>
                      {{ __('global.payments.debt_cleared_auto') }}
                    </div>
                  </div>

                  <div>
                    <button
                      type="button"
                      @click="triggerToast('{{ __('global.payments.transfer_receipt') }} ' + '{{ $order->code ?? 'N/A' }}')"
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-50 border border-slate-200 hover:bg-slate-100 text-slate-700 rounded-xl text-xs font-semibold transition-colors cursor-pointer"
                    >
                      <span class="material-symbols-outlined text-[15px]">receipt</span>
                      {{ __('global.payments.transfer_receipt') }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          @endif
        @endforeach
      @endif
    </div>

    @if($displayedOrders->hasPages())
      <div class="mt-6">
        {{ $displayedOrders->links() }}
      </div>
    @endif



    <!-- VIETQR MODAL POPUP -->
    <div
      x-show="showQrModal"
      x-cloak
      id="vietqr-modal"
      style="display: none;"
      class="fixed inset-0 z-50 overflow-y-auto items-center justify-center p-4"
      :class="{ 'flex': showQrModal, 'hidden': !showQrModal }"
      aria-modal="true"
      role="dialog"
    >
      <!-- Backdrop -->
      <div
        class="fixed inset-0 bg-slate-900/40 backdrop-blur-xs transition-opacity"
        @click="closeQr()"
      ></div>

      <!-- Modal Card -->
      <div
        class="relative bg-white rounded-2xl border border-slate-200 shadow-2xl max-w-md w-full overflow-hidden z-10 my-8"
        x-show="showQrModal"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
      >
        <!-- Modal Header -->
        <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/60">
          <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl bg-emerald-100 text-[#006948] flex items-center justify-center shrink-0">
              <span class="material-symbols-outlined text-[19px]">qr_code_scanner</span>
            </div>
            <div>
              <h3 class="text-sm font-bold text-slate-900">{{ __('global.payments.modal_title') }}</h3>
              <p class="text-[11px] text-slate-500" x-text="'{{ __('global.payments.modal_subtitle', ['code' => '']) }}' + qrData.order_code"></p>
            </div>
          </div>
          <button
            type="button"
            @click="closeQr()"
            class="text-slate-400 hover:text-slate-700 p-1 rounded-lg hover:bg-slate-200/60 transition-colors cursor-pointer"
            title="{{ __('global.common.close') }}"
          >
            <span class="material-symbols-outlined text-[20px]">close</span>
          </button>
        </div>

        <!-- Modal Body -->
        <div class="p-6 space-y-4">
          <template x-if="!qrData.has_bank">
            <div class="p-8 text-center bg-slate-50 border border-slate-200 rounded-2xl">
              <div class="w-14 h-14 rounded-2xl bg-amber-50 border border-amber-200 text-amber-600 flex items-center justify-center mx-auto mb-3">
                <span class="material-symbols-outlined text-[28px]">account_balance</span>
              </div>
              <h4 class="text-sm font-bold text-slate-800">{{ __('global.payments.no_bank_title') }}</h4>
              <p class="text-xs text-slate-500 mt-1 max-w-xs mx-auto leading-relaxed">{{ __('global.payments.no_bank_desc') }}</p>
            </div>
          </template>

          <template x-if="qrData.has_bank">
            <div class="space-y-4">
              <!-- Dynamic VietQR Card Box -->
              <div class="bg-white border-2 border-emerald-500/20 rounded-2xl p-4 flex flex-col items-center justify-center shadow-xs">
                <div class="w-full flex items-center justify-between pb-2 mb-2 border-b border-slate-100">
                  <span class="text-[11px] font-black tracking-widest text-[#004B87]">VIETQR</span>
                  <span class="text-[11px] font-bold tracking-wider text-emerald-700 flex items-center gap-1">
                    <span class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                    NAPAS 247
                  </span>
                </div>

                <!-- Real VietQR image with dynamic params & fallback -->
                <div class="relative p-2 bg-white rounded-xl border border-slate-200 shadow-inner flex items-center justify-center">
                  <img
                    :src="qrData.qrDataUrl"
                    alt="VietQR Code"
                    class="w-48 h-48 object-contain rounded-lg"
                    loading="lazy"
                  />
                </div>

                <span class="mt-2 text-[11px] text-slate-400 text-center flex items-center gap-1">
                  <span class="material-symbols-outlined text-[14px]">photo_camera</span>
                  {{ __('global.payments.qr_scan_prompt') }}
                </span>
              </div>

              <!-- Bank Details Table with 1-click Copy -->
              <div class="space-y-2 bg-slate-50 p-3.5 rounded-xl border border-slate-200 text-xs">
                <div class="flex items-center justify-between">
                  <span class="text-slate-500">{{ __('global.payments.bank_label') }}</span>
                  <span class="font-bold text-slate-900" x-text="qrData.bank_name"></span>
                </div>
                <div class="flex items-center justify-between">
                  <span class="text-slate-500">{{ __('global.payments.account_holder') }}</span>
                  <span class="font-bold text-slate-900" x-text="qrData.account_name"></span>
                </div>
                <div class="flex items-center justify-between border-t border-slate-200/60 pt-2">
                  <span class="text-slate-500">{{ __('global.payments.account_number') }}</span>
                  <div class="flex items-center gap-2">
                    <span class="font-mono font-bold text-slate-900 text-sm" x-text="qrData.account_number"></span>
                    <button
                      type="button"
                      @click="copyToClipboard(qrData.account_number, '{{ __('global.payments.account_number') }}')"
                      class="p-1 hover:bg-slate-200 text-[#006948] rounded transition-colors cursor-pointer"
                      title="{{ __('global.common.copy') }}"
                    >
                      <span class="material-symbols-outlined text-[16px]">content_copy</span>
                    </button>
                  </div>
                </div>

                <div class="flex items-center justify-between border-t border-slate-200/60 pt-2">
                  <span class="text-slate-500">{{ __('global.payments.exact_amount') }}</span>
                  <div class="flex items-center gap-2">
                    <span class="font-mono font-bold text-rose-600 text-base" x-text="formatMoney(qrData.amount)"></span>
                    <button
                      type="button"
                      @click="copyToClipboard(qrData.amount, '{{ __('global.payments.exact_amount') }}')"
                      class="p-1 hover:bg-slate-200 text-[#006948] rounded transition-colors cursor-pointer"
                      title="{{ __('global.common.copy') }}"
                    >
                      <span class="material-symbols-outlined text-[16px]">content_copy</span>
                    </button>
                  </div>
                </div>

                <div class="flex items-center justify-between border-t border-slate-200/60 pt-2 bg-rose-50/70 p-2 rounded-lg">
                  <span class="text-rose-700 font-semibold">{{ __('global.payments.transfer_content') }}</span>
                  <div class="flex items-center gap-2">
                    <span class="font-mono font-bold text-rose-700 bg-white px-2 py-0.5 border border-rose-200 rounded" x-text="qrData.transfer_content"></span>
                    <button
                      type="button"
                      @click="copyToClipboard(qrData.transfer_content, '{{ __('global.payments.transfer_content') }}')"
                      class="px-2 py-1 bg-rose-600 text-white rounded text-[11px] font-semibold hover:bg-rose-700 transition-colors flex items-center gap-1 cursor-pointer"
                    >
                      <span class="material-symbols-outlined text-[13px]">content_copy</span>
                      {{ __('global.payments.copy_btn') }}
                    </button>
                  </div>
                </div>
              </div>

              <!-- Alert Note -->
              <div class="p-3 bg-amber-50 border border-amber-200 rounded-xl flex items-start gap-2 text-xs text-amber-900 leading-relaxed">
                <span class="material-symbols-outlined text-amber-600 text-[18px] shrink-0 mt-0.5">info</span>
                <p>
                  {{ __('global.payments.qr_note') }}
                </p>
              </div>
            </div>
          </template>
        </div>

        <!-- Modal Footer -->
        <div class="px-6 py-4 bg-slate-50/80 border-t border-slate-100 flex items-center justify-end gap-3">
          <button
            type="button"
            @click="closeQr()"
            class="px-4 py-2 bg-white border border-slate-200 hover:bg-slate-100 text-slate-700 rounded-xl text-xs font-semibold transition-colors cursor-pointer"
          >
            {{ __('global.common.close') }}
          </button>
          <button
            type="button"
            @click="closeQr(); triggerToast('{{ __('global.payments.complete_transfer_btn') }}!');"
            class="px-4 py-2 bg-[#006948] hover:bg-[#005137] text-white rounded-xl text-xs font-semibold transition-colors shadow-xs flex items-center gap-1.5 cursor-pointer"
          >
            <span class="material-symbols-outlined text-[16px]">check</span>
            {{ __('global.payments.complete_transfer_btn') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</x-global.layout>
