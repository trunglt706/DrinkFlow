@props([
    'debts',
    'paymentRequests' => null,
    'paymentRequestDetails' => [],
    'transferContents' => [],
    'qrPayloads' => [],
    'qrAccounts' => [],
])

@php
    // One merged ledger: top-level debts and consolidated payment requests share a single table.
    // A debt bundled into a request (parent_id set) is never a row of its own — it is revealed by
    // expanding the row of the debt that owns it, and listed inside the request detail modal.
    $requests = $paymentRequests ?? collect();
    $requestStatusClass = [
        'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
        'approved' => 'bg-primary-fixed text-on-primary-fixed-variant border-transparent',
        'rejected' => 'bg-error-container text-on-error-container border-transparent',
    ];
    $requestStatusIcon = [
        'pending' => 'hourglass_top',
        'approved' => 'verified',
        'rejected' => 'cancel',
    ];
@endphp

<div class="w-full flex flex-col gap-3" id="unpaid-bills"
    x-data="{
        openRequests: {},
        detailOpen: false,
        detail: null,
        openDetail(code) {
            this.detail = {{ \Illuminate\Support\Js::from($paymentRequestDetails) }}[code] || null;
            if (!this.detail) return;
            this.detailOpen = true;
        },
        closeDetail() {
            this.detailOpen = false;
        },
        toggleRequest(id) {
            this.openRequests[id] = !this.openRequests[id];
        }
    }">
    <div class="bg-surface-container-lowest rounded-xl shadow-2xs overflow-hidden border border-outline-variant/30">
        <div
            class="p-3 sm:p-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-2 bg-surface-container-lowest border-b border-outline-variant/30">
            <div>
                <h3 class="text-xs sm:text-sm text-on-surface font-bold">{{ __('room.debts.history_title') }}</h3>
                <p class="text-[11px] text-on-surface-variant mt-0.5">{{ __('room.debts.subtitle') }}</p>
            </div>
            <div class="flex items-center gap-2">
                <span
                    class="text-[11px] px-2 py-0.5 rounded bg-surface-container-high text-on-surface font-medium">{{ __('room.debts.merged_ledger_badge') }}</span>
            </div>
        </div>

        <div class="w-full overflow-x-auto">
            <table class="w-full min-w-[42rem] sm:min-w-full text-left text-xs">
                <thead>
                    <tr
                        class="bg-surface-container-low text-on-surface-variant text-[10px] sm:text-[11px] font-bold uppercase tracking-wider border-b border-outline-variant/20">
                        <th class="py-2.5 px-3 whitespace-nowrap">{{ __('room.debts.table_tx_id') }}</th>
                        <th class="py-2.5 px-2.5 whitespace-nowrap">{{ __('room.debts.table_time') }}</th>
                        <th class="py-2.5 px-2.5 min-w-[220px] sm:min-w-[260px]">{{ __('room.debts.table_content_campaign') }}</th>
                        <th class="py-2.5 px-2.5 text-right whitespace-nowrap">{{ __('room.debts.table_total') }}</th>
                        <th class="py-2.5 px-2.5 text-center whitespace-nowrap">{{ __('room.debts.table_status') }}</th>
                        <th class="py-2.5 px-3 text-center whitespace-nowrap">{{ __('room.debts.table_action') }}</th>
                    </tr>
                </thead>
                <tbody class="text-on-surface divide-y divide-outline-variant/20">
                    @forelse($debts as $debt)
                        @php
                            $debtStatus = $debt->getStatusValue();
                            $isPaid = $debtStatus === \App\Enums\DebtStatus::Paid->value;
                            $isPending = $debtStatus === \App\Enums\DebtStatus::Pending->value;
                        @endphp
                        <tr class="hover:bg-surface-container-low/50 transition-colors">
                            <td class="py-2.5 px-3 font-tabular-nums font-bold text-primary font-mono text-xs whitespace-nowrap">
                                {{ $debt->code ?? 'N/A' }}
                            </td>
                            <td class="py-2.5 px-2.5 text-on-surface-variant whitespace-nowrap text-[11px]">
                                {{ $debt->created_at?->format('d/m/Y H:i') }}
                            </td>
                            <td class="py-2.5 px-2.5 font-medium max-w-sm min-w-[220px] sm:min-w-[260px]">
                                <div class="flex flex-col gap-0.5">
                                    <span class="text-on-surface text-xs font-medium leading-snug">
                                        {{ $debt->note ?: ($debt->sponsor_description ?: __('room.debts.default_debt_content')) }}
                                    </span>
                                    @if($debt->campaign_id)
                                        <button type="button" @click="openCampaignDetail({{ (int) $debt->campaign_id }})"
                                            class="text-[11px] text-primary hover:text-[#005137] hover:underline flex items-center gap-1 font-semibold text-left transition-colors cursor-pointer group">
                                            <span
                                                class="material-symbols-outlined text-[13px] text-primary group-hover:scale-110 transition-transform">campaign</span>
                                            <span>{{ $debt->campaign?->name ?? __('global.common.campaign') }}</span>
                                        </button>
                                    @else
                                        <span class="text-[11px] text-on-surface-variant flex items-center gap-1 font-semibold">
                                            <span class="material-symbols-outlined text-[13px] text-primary">campaign</span>
                                            {{ $debt->campaign?->name ?? __('global.common.campaign') }}
                                        </span>
                                    @endif
                                </div>
                            </td>
                            <td
                                class="py-2.5 px-2.5 text-right whitespace-nowrap font-tabular-nums font-bold text-xs {{ $debt->remaining_amount > 0 ? 'text-error' : 'text-on-surface' }}">
                                {{ \App\Support\Helpers\FormatHelper::formatCurrency($debt->remaining_amount > 0 ? $debt->remaining_amount : $debt->original_amount) }}
                            </td>
                            <td class="py-2.5 px-2.5 text-center whitespace-nowrap">
                                @if($isPaid)
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] sm:text-[11px] bg-primary-fixed text-on-primary-fixed-variant font-medium">
                                        <span class="w-1.5 h-1.5 rounded-full bg-primary"></span>
                                        {{ __('room.debts.status_paid') }}
                                    </span>
                                @elseif($isPending)
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] sm:text-[11px] bg-amber-50 text-amber-700 border border-amber-200 font-medium">
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                        {{ __('room.orders.payment_pending_badge', ['default' => 'Chờ duyệt']) }}
                                    </span>
                                @else
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] sm:text-[11px] bg-error-container text-on-error-container font-medium">
                                        <span class="w-1.5 h-1.5 rounded-full bg-error"></span>
                                        {{ __('room.debts.status_unpaid') }}
                                    </span>
                                @endif
                            </td>
                            <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                @if(!$isPaid && $debtStatus !== \App\Enums\DebtStatus::Rejected->value && $debt->remaining_amount > 0)
                                    <button
                                        class="px-2.5 py-1 rounded bg-[#006948] text-white hover:bg-[#005137] transition-all inline-flex items-center gap-1 shadow-2xs cursor-pointer font-medium text-xs"
                                        data-pay-debt-code="{{ $debt->code }}"
                                        @if($debt->campaign?->code) data-pay-debt-campaign="{{ $debt->campaign->code }}" @endif
                                        @click="openQr({{ (int) $debt->remaining_amount }}, '{{ \App\Support\Helpers\FormatHelper::formatCurrency($debt->remaining_amount) }}', {{ \Illuminate\Support\Js::from($transferContents[$debt->id] ?? $debt->code) }}, '', {{ $debt->id }}, {{ $debtStatus === \App\Enums\DebtStatus::Pending->value ? 'true' : 'false' }}, {{ \Illuminate\Support\Js::from($qrPayloads[$debt->id] ?? '') }}, {{ \Illuminate\Support\Js::from($qrAccounts[$debt->id] ?? null) }}, {{ \Illuminate\Support\Js::from((string) $debt->code) }})">
                                        <span class="material-symbols-outlined text-[14px] text-white">qr_code</span>
                                        <span class="text-white">{{ __('room.debts.btn_view_qr') }}</span>
                                    </button>
                                @else
                                    <span class="inline-flex items-center gap-1 text-[11px] text-primary font-medium">
                                        <span class="material-symbols-outlined text-[13px]">verified</span>
                                        {{ __('room.debts.status_done') }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        @if($requests->isEmpty())
                            <tr>
                                <td colspan="6" class="py-8 text-center text-on-surface-variant text-xs">
                                    <div
                                        class="sticky left-0 flex w-[calc(100vw-4rem)] max-w-full flex-col items-center justify-center gap-1.5 whitespace-normal">
                                        <span class="material-symbols-outlined text-[28px] text-outline-variant"
                                            aria-hidden="true">receipt_long</span>
                                        <span>{{ __('room.debts.all_settled') }}</span>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @endforelse

                    {{-- Consolidated payment requests: same table, marked as requests rather than debts. --}}
                    @foreach($requests as $paymentRequest)
                        @php
                            $requestStatus = $paymentRequest->getStatusValue();
                            $requestRowId = 'request-' . $paymentRequest->id;
                        @endphp
                        <tr class="bg-surface-container-low/30">
                            <td class="py-2.5 px-3 whitespace-nowrap">
                                <button type="button"
                                    @click="openDetail({{ \Illuminate\Support\Js::from((string) $paymentRequest->code) }})"
                                    title="{{ __('room.debts.view_request_detail') }}"
                                    class="inline-flex items-center gap-1 font-mono font-bold text-xs text-primary hover:underline cursor-pointer">
                                    <span class="material-symbols-outlined text-[14px]" aria-hidden="true">stacks</span>
                                    <span>{{ $paymentRequest->code }}</span>
                                </button>
                            </td>
                            <td class="py-2.5 px-2.5 text-on-surface-variant whitespace-nowrap text-[11px]">
                                {{ $paymentRequest->payment_requested_at?->format('d/m/Y H:i') }}
                            </td>
                            <td class="py-2.5 px-2.5 font-medium max-w-sm min-w-[220px] sm:min-w-[260px]">
                                <div class="flex flex-col gap-0.5">
                                    <span class="text-on-surface text-xs font-semibold leading-snug">
                                        {{ __('room.debts.requests_title') }}
                                    </span>
                                    <button type="button" @click="toggleRequest('{{ $requestRowId }}')"
                                        :aria-expanded="(openRequests['{{ $requestRowId }}'] || false).toString()"
                                        class="text-[11px] text-primary hover:underline flex items-center gap-1 font-semibold text-left cursor-pointer">
                                        <span class="material-symbols-outlined text-[13px] transition-transform"
                                            :class="openRequests['{{ $requestRowId }}'] && 'rotate-90'"
                                            aria-hidden="true">chevron_right</span>
                                        <span>{{ __('room.debts.request_debts_count', ['count' => $paymentRequest->children->count()]) }}</span>
                                    </button>
                                </div>
                            </td>
                            <td class="py-2.5 px-2.5 text-right whitespace-nowrap font-tabular-nums font-bold text-xs text-on-surface">
                                {{ \App\Support\Helpers\FormatHelper::formatCurrency((int) $paymentRequest->original_amount) }}
                            </td>
                            <td class="py-2.5 px-2.5 text-center whitespace-nowrap">
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] sm:text-[11px] font-medium border {{ $requestStatusClass[$requestStatus] ?? '' }}">
                                    <span class="material-symbols-outlined text-[12px]"
                                        aria-hidden="true">{{ $requestStatusIcon[$requestStatus] ?? 'info' }}</span>
                                    {{ __('room.debts.request_status_'.$requestStatus) }}
                                </span>
                                @if($paymentRequest->review_reason)
                                    <div class="mt-1 text-[10px] text-error">
                                        {{ __('room.debts.request_reason', ['reason' => $paymentRequest->review_reason]) }}
                                    </div>
                                @endif
                            </td>
                            <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                <button type="button"
                                    @click="openDetail({{ \Illuminate\Support\Js::from((string) $paymentRequest->code) }})"
                                    class="px-2.5 py-1 rounded border border-outline-variant text-on-surface hover:bg-surface-container-high transition-all inline-flex items-center gap-1 cursor-pointer font-medium text-xs">
                                    <span class="material-symbols-outlined text-[14px]" aria-hidden="true">visibility</span>
                                    <span>{{ __('global.common.details') }}</span>
                                </button>
                            </td>
                        </tr>
                        <tr x-show="openRequests['{{ $requestRowId }}']" x-cloak class="bg-surface-container-low/40">
                            <td colspan="6" class="py-2 pl-8 pr-3">
                                <div class="flex flex-col gap-1.5">
                                    @foreach($paymentRequest->children as $child)
                                        <div class="flex flex-wrap items-center justify-between gap-2 text-[11px]">
                                            <span class="min-w-0 truncate">
                                                <span class="font-mono font-semibold text-on-surface">{{ $child->code }}</span>
                                                <span class="text-on-surface-variant">· {{ $child->campaign?->name ?? __('global.common.campaign') }}</span>
                                                <span class="text-on-surface-variant">· {{ __('room.debts.in_request_label', ['code' => $paymentRequest->code]) }}</span>
                                            </span>
                                            <span class="flex items-center gap-2 shrink-0">
                                                <span class="font-tabular-nums text-on-surface-variant">{{ \App\Support\Helpers\FormatHelper::formatCurrency((int) ($child->remaining_amount > 0 ? $child->remaining_amount : $child->paid_amount)) }}</span>
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        @if($debts->hasPages())
            <div class="p-3 border-t border-outline-variant/30 text-xs">
                {{ $debts->links() }}
            </div>
        @endif
    </div>

    {{-- Payment request detail modal (opened from a request code). --}}
    <template x-teleport="body">
        <div x-show="detailOpen" x-cloak @keydown.escape.window="closeDetail()"
            class="fixed inset-0 z-[100] flex items-center justify-center p-3 sm:p-4 bg-slate-900/60 backdrop-blur-md"
            role="dialog" aria-modal="true" aria-labelledby="debt-request-detail-title">
            <div @click.outside="closeDetail()"
                class="w-full max-w-lg max-h-[90vh] overflow-hidden rounded-2xl border border-outline-variant bg-surface-container-lowest shadow-2xl flex flex-col">
                <div class="p-3.5 sm:p-4 border-b border-outline-variant/30 flex items-start justify-between gap-3 bg-surface-container-low">
                    <div class="flex items-start gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-lg bg-primary-fixed flex items-center justify-center text-on-primary-fixed-variant shrink-0">
                            <span class="material-symbols-outlined text-[20px]">stacks</span>
                        </div>
                        <div class="min-w-0">
                            <h3 id="debt-request-detail-title" class="text-sm font-bold text-on-surface">
                                {{ __('room.debts.request_detail_title') }}
                            </h3>
                            <p class="font-mono text-xs font-bold text-primary mt-0.5" x-text="detail?.code || ''"></p>
                        </div>
                    </div>
                    <button type="button" @click="closeDetail()"
                        class="w-8 h-8 rounded-lg flex items-center justify-center text-on-surface-variant hover:bg-surface-container-high transition-colors cursor-pointer shrink-0">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <div class="p-3.5 sm:p-4 overflow-y-auto flex flex-col gap-3">
                    <div class="grid grid-cols-2 gap-2 text-[11px]">
                        <div class="rounded-lg border border-outline-variant/40 bg-surface-container-low p-2.5">
                            <span class="block text-on-surface-variant">{{ __('room.debts.table_status') }}</span>
                            <span class="mt-0.5 block font-bold text-on-surface" x-text="detail?.status_label || ''"></span>
                        </div>
                        <div class="rounded-lg border border-outline-variant/40 bg-surface-container-low p-2.5">
                            <span class="block text-on-surface-variant">{{ __('room.debts.request_total_label') }}</span>
                            <span class="mt-0.5 block font-bold font-tabular-nums text-on-surface"
                                x-text="detail?.amount_formatted || ''"></span>
                        </div>
                        <div class="rounded-lg border border-outline-variant/40 bg-surface-container-low p-2.5">
                            <span class="block text-on-surface-variant">{{ __('room.orders.payment_request_time') }}</span>
                            <span class="mt-0.5 block font-medium text-on-surface"
                                x-text="detail?.requested_at || '{{ __('room.orders.not_available') }}'"></span>
                        </div>
                        <div class="rounded-lg border border-outline-variant/40 bg-surface-container-low p-2.5">
                            <span class="block text-on-surface-variant">{{ __('room.debts.request_debts_total_label') }}</span>
                            <span class="mt-0.5 block font-bold text-on-surface"
                                x-text="detail?.debts_count ?? 0"></span>
                        </div>
                    </div>

                    <div x-show="detail?.transfer_content"
                        class="rounded-lg border border-outline-variant/40 bg-surface-container-low p-2.5 text-[11px]">
                        <span class="block text-on-surface-variant">{{ __('room.orders.payment_request_content') }}</span>
                        <span class="mt-0.5 block font-mono font-bold text-primary"
                            x-text="detail?.transfer_content || ''"></span>
                    </div>

                    <div x-show="detail?.reviewer || detail?.reviewed_at"
                        class="rounded-lg border border-outline-variant/40 bg-surface-container-low p-2.5 text-[11px]">
                        <span class="block font-bold text-on-surface mb-1">{{ __('room.orders.payment_approval_info') }}</span>
                        <div class="flex items-center justify-between gap-3">
                            <span class="text-on-surface-variant">{{ __('room.orders.payment_approved_by') }}</span>
                            <span class="font-semibold text-on-surface" x-text="detail?.reviewer || '{{ __('room.orders.not_available') }}'"></span>
                        </div>
                        <div class="flex items-center justify-between gap-3 mt-0.5">
                            <span class="text-on-surface-variant">{{ __('room.debts.request_reviewed_at') }}</span>
                            <span class="font-semibold text-on-surface" x-text="detail?.reviewed_at || '{{ __('room.orders.not_available') }}'"></span>
                        </div>
                    </div>

                    <div x-show="detail?.review_reason"
                        class="rounded-lg border border-error/30 bg-error-container p-2.5 text-[11px] text-on-error-container">
                        <span class="font-bold">{{ __('room.debts.request_reject_reason_label') }}</span>
                        <span x-text="detail?.review_reason || ''"></span>
                    </div>

                    <div class="rounded-lg border border-outline-variant/40 overflow-hidden">
                        <div class="px-2.5 py-2 bg-surface-container-low border-b border-outline-variant/30">
                            <span class="text-[11px] font-bold text-on-surface">{{ __('room.debts.bundled_debts_heading') }}</span>
                        </div>
                        <div class="divide-y divide-outline-variant/20">
                            <template x-for="child in (detail?.debts || [])" :key="child.code">
                                <div class="flex items-center justify-between gap-2 px-2.5 py-2 text-[11px]">
                                    <span class="min-w-0 truncate">
                                        <span class="font-mono font-semibold text-on-surface" x-text="child.code"></span>
                                        <span class="text-on-surface-variant">· <span x-text="child.campaign"></span></span>
                                    </span>
                                    <span class="font-tabular-nums text-on-surface-variant shrink-0"
                                        x-text="child.amount_formatted"></span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="p-3 border-t border-outline-variant/30 flex justify-end bg-surface-container-low">
                    <button type="button" @click="closeDetail()"
                        class="rounded-xl px-4 py-2 text-xs font-semibold text-on-surface border border-outline-variant hover:bg-surface-container-high transition-colors cursor-pointer">
                        {{ __('global.common.close') }}
                    </button>
                </div>
            </div>
        </div>
    </template>
</div>
