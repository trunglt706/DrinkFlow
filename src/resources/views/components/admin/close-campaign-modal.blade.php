@props(['room'])

{{-- Shared "End orders & finalize campaign" modal (campaign info page + dashboard). Controlled by
     resources/js/admin/close-campaign-modal.js: every open re-fetches the latest summary before the admin confirms. --}}
<div id="close-campaign-summary-modal"
    data-close-campaign-modal
    data-summary-url-template="{{ route('admin.campaigns.close-summary', [$room, '__CAMPAIGN__']) }}"
    data-close-url-template="{{ route('admin.campaigns.close', [$room, '__CAMPAIGN__']) }}"
    data-i18n="{{ json_encode([
        'portions' => __('admin.portions'),
        'loading' => __('admin.close_summary_loading'),
        'failed' => __('admin.close_summary_failed'),
        'notClosable' => __('admin.close_summary_not_closable'),
        'closing' => __('admin.closing_in_progress'),
        'success' => __('admin.campaign_closed_success'),
        'closeFailed' => __('admin.close_campaign_failed'),
    ], JSON_UNESCAPED_UNICODE) }}"
    class="fixed inset-0 z-50 !m-0 hidden items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
    <div class="bg-surface-container-lowest border border-outline-variant rounded-2xl shadow-2xl w-full max-w-xl max-h-[92vh] overflow-hidden flex flex-col">
        <div class="p-5 shrink-0 border-b border-outline-variant/60 flex items-center justify-between bg-surface-container-low">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="w-9 h-9 rounded-full bg-amber-500/10 text-amber-600 flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-[22px]">lock_clock</span>
                </span>
                <div class="min-w-0">
                    <h3 class="text-base font-bold text-on-surface">{{ __('admin.close_campaign_confirm_modal_title') }}</h3>
                    <p data-close-summary-heading class="text-xs text-outline font-mono font-code truncate">—</p>
                </div>
            </div>
            <button type="button" data-close-campaign-dismiss class="text-outline hover:text-on-surface cursor-pointer">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <div class="p-5 space-y-4 text-xs overflow-y-auto">
            @php
                $statRow = 'flex items-center justify-between gap-3';
                $sectionTitle = 'text-[11px] font-bold uppercase tracking-wide text-outline';
                $sectionBox = 'bg-surface-container-low border border-outline-variant/60 rounded-xl p-4 space-y-2.5';
            @endphp
            <div class="relative space-y-3">
                {{-- Items --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                    @foreach ([
                        'own_items' => __('admin.close_summary_own_items'),
                        'proxy_items' => __('admin.close_summary_proxy_items'),
                        'self_paid_items' => __('admin.close_summary_self_paid_items'),
                        'total_items' => __('admin.total_items_ordered_label'),
                    ] as $field => $label)
                        <div class="flex flex-col p-2.5 rounded-lg bg-surface-container-low border border-outline-variant/60">
                            <span class="text-[11px] text-outline leading-tight">{{ $label }}</span>
                            <span data-close-summary="{{ $field }}" class="text-sm font-bold mt-1 {{ $field === 'total_items' ? 'text-primary' : 'text-on-surface' }}">—</span>
                        </div>
                    @endforeach
                </div>

                {{-- Money --}}
                <div class="{{ $sectionBox }}">
                    <p class="{{ $sectionTitle }}">{{ __('admin.close_summary_money_section') }}</p>
                    <div class="space-y-1.5 font-medium">
                        <div class="{{ $statRow }}">
                            <span class="text-outline">{{ __('admin.close_summary_gross_subtotal') }}</span>
                            <span data-close-summary="gross_subtotal" class="font-mono font-semibold text-on-surface">—</span>
                        </div>
                        <div class="{{ $statRow }}">
                            <span class="text-outline">{{ __('admin.close_summary_discount_total') }}</span>
                            <span data-close-summary="discount_total" data-sign="-" class="font-mono font-semibold text-emerald-700">—</span>
                        </div>
                        <div class="{{ $statRow }}">
                            <span class="text-outline">{{ __('admin.close_summary_extra_fee_total') }}</span>
                            <span data-close-summary="extra_fee_total" data-sign="+" class="font-mono font-semibold text-amber-700">—</span>
                        </div>
                        <div class="{{ $statRow }}">
                            <span class="text-outline">{{ __('admin.close_summary_sponsor_total') }}</span>
                            <span data-close-summary="sponsor_total" data-sign="-" class="font-mono font-semibold text-emerald-700">—</span>
                        </div>
                        <div class="{{ $statRow }}">
                            <span class="text-outline">{{ __('admin.close_summary_self_paid_total') }}</span>
                            <span data-close-summary="self_paid_total" class="font-mono font-semibold text-violet-700 dark:text-violet-300">—</span>
                        </div>
                        <div class="{{ $statRow }} pt-2 mt-1 border-t border-outline-variant/40">
                            <span class="flex flex-col">
                                <span class="text-sm font-bold text-on-surface">{{ __('admin.close_summary_final_total') }}</span>
                                <span class="text-[10px] text-outline font-normal">{{ __('admin.close_summary_final_hint') }}</span>
                            </span>
                            <span data-close-summary="final_total" class="font-mono text-base font-bold text-primary">—</span>
                        </div>
                    </div>
                </div>

                {{-- Orders & members --}}
                <div class="{{ $sectionBox }}">
                    <p class="{{ $sectionTitle }}">{{ __('admin.close_summary_people_section') }}</p>
                    <div class="grid grid-cols-2 gap-x-4 gap-y-1.5 font-medium">
                        <div class="{{ $statRow }}">
                            <span class="text-outline">{{ __('admin.close_summary_orders_count') }}</span>
                            <span data-close-summary="orders_count" class="font-bold text-on-surface">—</span>
                        </div>
                        <div class="{{ $statRow }}">
                            <span class="text-outline">{{ __('admin.close_summary_ordered_users') }}</span>
                            <span data-close-summary="ordered_users" class="font-bold text-primary">—</span>
                        </div>
                        <div class="{{ $statRow }}">
                            <span class="text-outline">{{ __('admin.close_summary_pending_users') }}</span>
                            <span data-close-summary="pending_users_count" class="font-bold text-amber-700">—</span>
                        </div>
                        <div class="{{ $statRow }}">
                            <span class="text-outline">{{ __('admin.close_summary_declined_users') }}</span>
                            <span data-close-summary="declined_users_count" class="font-bold text-on-surface">—</span>
                        </div>
                    </div>
                </div>
                <x-loading-overlay data-close-summary-loading :label="__('admin.close_summary_loading')" />
            </div>

            <p data-close-summary-error class="hidden p-3 rounded-xl border border-error/30 bg-error/5 text-error font-medium"></p>

            <div class="pt-3 border-t border-outline-variant/60 flex items-center justify-end gap-2">
                <button type="button" data-close-campaign-dismiss
                    class="px-4 py-2.5 rounded-lg border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed">
                    {{ __('admin.cancel') }}
                </button>
                <button type="button" id="execute-close-campaign-btn" data-close-campaign-confirm disabled
                    class="px-4 py-2.5 rounded-lg bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold flex items-center gap-2 transition-colors cursor-pointer shadow-xs disabled:opacity-60 disabled:cursor-not-allowed">
                    <span class="material-symbols-outlined text-[18px]">lock</span>
                    <span>{{ __('admin.confirm_close_campaign_btn_text') }}</span>
                </button>
            </div>
        </div>
    </div>
</div>
