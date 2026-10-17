<x-admin.layout :title="__('admin.campaigns_management_title')" active="campaigns" :room="$room">
    <!-- Header & Action Ribbon -->
    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-outline-variant/40">
        <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl font-bold text-on-surface tracking-tight">{{ __('admin.campaigns_management_title') }}</h1>
            <span id="campaigns-total-badge" class="px-3 py-1 bg-surface-container rounded-full text-xs font-mono font-semibold text-secondary">
                {{ __('admin.total_campaigns_badge', ['count' => $campaigns->total()]) }}
            </span>
        </div>
        @if (!($hasLiveCampaign ?? false))
            <div class="flex items-center gap-2.5">
                <a href="{{ route('admin.campaigns.create', $room) }}" class="px-4 py-2 bg-primary hover:bg-primary/90 text-on-primary rounded text-xs font-bold flex items-center gap-2 shadow-sm transition-all no-underline">
                    <span class="material-symbols-outlined text-[18px]">add_circle</span>
                    <span>{{ __('admin.fast_create_campaign') }}</span>
                </a>
            </div>
        @endif
    </div>

    <!-- Notice Notification Banner -->
    <div id="notice" class="hidden mb-4 rounded-xl px-4 py-3 text-xs font-medium"></div>
    @if (session('error'))
        <div class="my-4 flex items-center gap-2 rounded-xl border border-error/30 bg-error-container/40 px-4 py-3 text-xs font-medium text-on-error-container" role="alert">
            <span class="material-symbols-outlined text-[18px] text-error" aria-hidden="true">error</span>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Campaigns Search & Filter Toolbar -->
    @php
        $hasCampaignFilters = trim((string) ($filters['search'] ?? '')) !== ''
            || ($filters['status'] ?? 'all') !== 'all'
            || ($filters['sponsor_type'] ?? 'all') !== 'all';
    @endphp
    <form id="campaigns-filter-form" data-skeleton-on-submit method="GET" action="{{ route('admin.campaigns.page', $room) }}" class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 flex flex-col gap-3 shadow-xs lg:flex-row lg:flex-wrap lg:items-center lg:gap-2">
        {{-- Mobile: row 1 = search + status, row 2 = sponsor type + buttons, both full width.
             From lg up both rows dissolve (lg:contents) into one line: search on the left, filters and buttons pushed right. --}}
        <div class="flex w-full items-center gap-2 lg:contents">
            <x-admin.search-input id="campaign-search" name="search" :value="$filters['search'] ?? ''" placeholder="{{ __('admin.search_campaigns_placeholder') }}" containerClass="relative min-w-0 flex-1 lg:min-w-[280px] lg:max-w-md" />
            <select id="campaign-status-select" name="status" class="h-9 min-w-0 flex-1 px-3 bg-surface border border-outline-variant rounded text-xs text-on-surface lg:ml-auto lg:flex-none">
                <option value="all" {{ ($filters['status'] ?? 'all') === 'all' ? 'selected' : '' }}>{{ __('admin.filter_all') }}</option>
                @foreach($statusFilters as $statusFilter)
                    <option value="{{ $statusFilter['value'] }}" {{ ($filters['status'] ?? '') === $statusFilter['value'] ? 'selected' : '' }}>{{ $statusFilter['label'] }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex w-full items-center gap-2 lg:contents">
            <select id="campaign-sponsor-type-select" name="sponsor_type" aria-label="{{ __('admin.sponsor_type_label') }}" class="h-9 min-w-0 flex-1 px-3 bg-surface border border-outline-variant rounded text-xs text-on-surface lg:flex-none">
                <option value="all" {{ ($filters['sponsor_type'] ?? 'all') === 'all' ? 'selected' : '' }}>{{ __('admin.filter_all_sponsor_types') }}</option>
                @foreach($sponsorTypeFilters as $sponsorTypeFilter)
                    <option value="{{ $sponsorTypeFilter['value'] }}" {{ ($filters['sponsor_type'] ?? '') === $sponsorTypeFilter['value'] ? 'selected' : '' }}>{{ $sponsorTypeFilter['label'] }}</option>
                @endforeach
            </select>
            <button type="submit" data-icon-only data-tooltip="{{ __('admin.filter_apply') }}" aria-label="{{ __('admin.filter_apply') }}" class="text-xs font-semibold h-9 w-9 shrink-0 inline-flex items-center justify-center rounded-lg bg-primary text-on-primary hover:bg-primary-container transition-colors">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">filter_alt</span>
            </button>
            @if($hasCampaignFilters)
                <a id="campaign-clear-filters" href="{{ route('admin.campaigns.page', $room) }}" class="h-9 shrink-0 inline-flex items-center gap-1.5 px-3 rounded-lg border border-outline-variant bg-surface text-on-surface text-xs font-semibold hover:bg-surface-container transition-colors no-underline">
                    <span class="material-symbols-outlined text-[16px]">filter_alt_off</span>
                    {{ __('admin.filter_clear') }}
                </a>
            @endif
            <x-admin.reload-button />
        </div>
    </form>

    <!-- Campaigns Table Ledger -->
    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-xs">
        <div class="overflow-x-auto">
            {{-- Fixed-width side columns; the campaign/restaurant column takes the remaining space. --}}
            <table data-skeleton="table" class="table-colgroup w-full min-w-[56rem] table-fixed text-left text-xs border-collapse">
                <colgroup>
                    <col>
                    <col class="w-28">
                    <col class="w-24">
                    <col class="w-36">
                    <col class="w-32">
                    <col class="w-24">
                </colgroup>
                <thead>
                    <tr class="bg-surface-container-low text-outline font-mono uppercase text-[11px] border-b border-outline-variant">
                        <th class="py-3 px-4">{{ __('admin.th_campaign_restaurant') }}</th>
                        <th class="py-3 px-4 text-right">{{ __('admin.th_total_items') }}</th>
                        <th class="py-3 px-4 text-right">{{ __('admin.th_orders_count') }}</th>
                        <th class="py-3 px-4 text-right" title="{{ __('admin.th_store_total_hint') }}">{{ __('admin.th_store_total') }}</th>
                        <th class="py-3 px-4 text-center">{{ __('admin.th_status') }}</th>
                        <th class="py-3 px-4 text-center">{{ __('admin.th_actions') }}</th>
                    </tr>
                </thead>
                <tbody id="campaigns-tbody" class="divide-y divide-outline-variant/50">
                    @forelse($campaigns as $camp)
                        @php
                            $statusValue = $camp->status instanceof \BackedEnum ? $camp->status->value : (string) $camp->status;
                        @endphp
                        <tr class="hover:bg-surface-container-low/50 transition-colors">
                            <td class="py-3.5 px-4">
                                @php
                                    $isSponsored = ($camp->sponsor_type ?? \App\Models\Campaign::SPONSOR_TYPE_NONE) !== \App\Models\Campaign::SPONSOR_TYPE_NONE;
                                    $sponsorTooltip = $isSponsored
                                        ? collect([__('admin.sponsor_type_' . $camp->sponsor_type), $camp->sponsor_name])->filter()->implode(' · ')
                                        : '';
                                @endphp
                                <a href="{{ route('admin.campaigns.info', [$room, $camp]) }}" class="font-bold text-on-surface text-sm hover:text-primary hover:underline transition-colors no-underline">
                                    {{ $camp->name }}
                                </a>
                                @if($isSponsored)
                                    <span data-sponsor-icon tabindex="0" data-tooltip="{{ $sponsorTooltip }}" aria-label="{{ $sponsorTooltip }}" class="ml-1 inline-flex items-center justify-center align-middle w-5 h-5 rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 cursor-help">
                                        <span class="material-symbols-outlined text-[14px]" aria-hidden="true">volunteer_activism</span>
                                    </span>
                                @endif
                                <div class="text-secondary flex items-center gap-1.5 mt-0.5">
                                    <span class="material-symbols-outlined text-[14px]">storefront</span>
                                    <span>{{ $camp->restaurant }}</span>
                                    <span class="text-outline-variant" aria-hidden="true">•</span>
                                    <span class="inline-flex items-center gap-0.5 font-mono text-[11px] text-outline" data-campaign-started-at title="{{ __('admin.campaign_started_at') }}">
                                        <span class="material-symbols-outlined text-[13px]" aria-hidden="true">schedule</span>
                                        {{ $camp->started_at ? $camp->started_at->format('H:i d/m/Y') : __('admin.order_window_not_started') }}
                                    </span>
                                    @if($camp->source_url)
                                        <a href="{{ $camp->source_url }}" target="_blank" class="text-primary hover:underline text-[11px]">🔗 {{ __('admin.store_link') }}</a>
                                    @endif
                                </div>
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono font-bold text-on-surface" data-campaign-total-items>
                                {{ (int) ($camp->total_items ?? 0) }} {{ __('admin.items_unit') }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono font-bold text-on-surface">
                                {{ __('admin.orders_unit', ['count' => $camp->orders_count ?? 0]) }}
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono font-bold text-primary">
                                {{ \App\Support\Helpers\FormatHelper::formatCurrency($camp->grossTotal((int) ($camp->gross_subtotal ?? 0))) }}
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <x-admin.campaign-status-badge :campaign="$camp" />
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <details class="relative inline-block text-left">
                                    <summary class="list-none cursor-pointer inline-flex items-center justify-center w-8 h-8 rounded-lg text-secondary hover:text-primary hover:bg-surface-container transition-colors" aria-label="{{ __('admin.th_actions') }}">
                                        <span class="material-symbols-outlined text-[20px]">more_vert</span>
                                    </summary>
                                    <div class="absolute right-0 mt-1 w-48 z-20 rounded-xl border border-outline-variant bg-surface-container-lowest shadow-xl p-1.5 space-y-0.5">
                                        <a href="{{ route('admin.campaigns.info', [$room, $camp]) }}" class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs text-on-surface hover:bg-surface-container no-underline">
                                            <span class="material-symbols-outlined text-[14px]">visibility</span>
                                            <span>{{ __('admin.view_campaign_details') }}</span>
                                        </a>
                                    @if(in_array($statusValue, ['draft'], true))
                                        <a href="{{ route('admin.campaigns.edit', [$room, $camp]) }}" class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs text-on-surface hover:bg-surface-container no-underline">
                                            <span class="material-symbols-outlined text-[14px]">edit</span>
                                            <span>{{ __('admin.edit') }}</span>
                                        </a>
                                    @elseif(in_array($statusValue, ['active', 'closing', 'scheduled'], true))
                                        <a href="{{ route('admin.campaigns.edit', [$room, $camp]) }}" class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs text-on-surface hover:bg-surface-container no-underline">
                                            <span class="material-symbols-outlined text-[14px]">edit</span>
                                            <span>{{ __('admin.edit') }}</span>
                                        </a>
                                        @if(in_array($statusValue, ['active', 'closing'], true))
                                            <button type="button" data-close-campaign-open data-campaign-id="{{ $camp->id }}" onclick="this.closest('details').open = false" class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs text-on-surface hover:bg-surface-container text-left">
                                                <span class="material-symbols-outlined text-[16px]">lock</span>{{ __('admin.close_campaign_early') }}
                                            </button>
                                        @endif
                                        <button type="button" onclick="window.openCancelCampaignModal({{ $camp->id }}); this.closest('details').open = false" class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs text-error hover:bg-error-container/30 text-left">
                                            <span class="material-symbols-outlined text-[16px]">cancel</span>{{ __('admin.cancel_campaign_btn') }}
                                        </button>
                                    @elseif($statusValue === 'closed')
                                        <button type="button" onclick="window.openArchiveCampaignModal({{ $camp->id }}); this.closest('details').open = false" class="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs text-on-surface hover:bg-surface-container text-left">
                                            <span class="material-symbols-outlined text-[16px]">archive</span>{{ __('admin.archive_campaign') }}
                                        </button>
                                    @endif
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-outline">
                                <div class="flex flex-col items-center gap-2">
                                    <span class="material-symbols-outlined text-4xl text-outline-variant">campaign</span>
                                    @if($hasCampaignFilters)
                                        <p class="font-medium text-sm">{{ __('admin.no_campaigns_matching_filters') }}</p>
                                    @else
                                        <p class="font-medium text-sm">{{ __('admin.no_campaigns_found') }}</p>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($campaigns->hasPages())
            <div class="p-4 border-t border-outline-variant">
                {{ $campaigns->links() }}
            </div>
        @endif
    </div>

    <!-- Duplicate Campaign Modal -->
    {{-- Modals reset their margin: <main> uses space-y-*, which (Tailwind v4) adds a bottom margin to every child
         but the last, and that margin would leave a gap under a fixed full-screen backdrop. --}}
    <div id="duplicate-campaign-modal" class="fixed inset-0 z-50 !m-0 hidden items-center justify-center bg-black/60 p-4">
        <div class="w-full max-w-sm rounded-xl bg-surface-container-lowest p-5 shadow-xl">
            <h2 class="text-base font-bold text-on-surface">{{ __('admin.duplicate_campaign') }}</h2>
            <p class="mt-2 text-xs text-outline">{{ __('admin.confirm_duplicate_campaign') }}</p>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" data-duplicate-cancel class="px-3 py-2 rounded text-xs font-semibold bg-surface-container">{{ __('admin.cancel') }}</button>
                <button type="button" data-duplicate-confirm class="px-3 py-2 rounded text-xs font-semibold bg-primary text-on-primary">{{ __('admin.confirm_duplicate') }}</button>
            </div>
        </div>
    </div>

    <!-- Shared close-campaign modal (same as the campaign detail page and dashboard) -->
    <x-admin.close-campaign-modal :room="$room" />

    <!-- Cancel Campaign Confirmation Modal -->
    <div id="cancel-campaign-modal" class="fixed inset-0 z-50 !m-0 hidden items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true" aria-labelledby="cancel-campaign-modal-title" data-error-message="{{ __('admin.cancel_campaign_failed') }}">
        <div class="w-full max-w-sm rounded-xl bg-surface-container-lowest border border-outline-variant p-5 shadow-xl">
            <div class="flex items-start gap-3">
                <div class="w-10 h-10 shrink-0 rounded-full bg-error-container/60 text-error flex items-center justify-center" aria-hidden="true">
                    <span class="material-symbols-outlined text-[22px]">event_busy</span>
                </div>
                <div class="min-w-0">
                    <h2 id="cancel-campaign-modal-title" class="text-base font-bold text-on-surface">{{ __('admin.confirm_cancel_campaign_title') }}</h2>
                    <p class="mt-1 text-xs text-outline leading-relaxed">{{ __('admin.confirm_cancel_campaign_desc') }}</p>
                </div>
            </div>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" data-cancel-modal-cancel class="px-3 py-2 rounded-lg text-xs font-semibold bg-surface-container hover:bg-surface-container-high text-on-surface transition-colors flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">close</span>
                    <span>{{ __('admin.cancel') }}</span>
                </button>
                <button type="button" data-cancel-modal-confirm class="px-3.5 py-2 rounded-lg text-xs font-semibold bg-error hover:bg-error/90 text-white transition-colors flex items-center gap-1.5 disabled:opacity-60">
                    <span class="material-symbols-outlined text-[16px]" data-action-icon aria-hidden="true">cancel</span>
                    <span class="material-symbols-outlined text-[16px] animate-spin" data-spinner style="display: none;" aria-hidden="true">progress_activity</span>
                    <span data-label>{{ __('admin.confirm_cancel_campaign_btn') }}</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Archive Campaign Confirmation Modal -->
    <div id="archive-campaign-modal" class="fixed inset-0 z-50 !m-0 hidden items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true" aria-labelledby="archive-campaign-modal-title" data-error-message="{{ __('admin.archive_campaign_failed') }}">
        <div class="w-full max-w-sm rounded-xl bg-surface-container-lowest border border-outline-variant p-5 shadow-xl">
            <div class="flex items-center gap-2 text-primary mb-2">
                <span class="material-symbols-outlined text-[22px]">archive</span>
                <h2 id="archive-campaign-modal-title" class="text-base font-bold text-on-surface">{{ __('admin.confirm_archive_campaign_title') }}</h2>
            </div>
            <p class="mt-2 text-xs text-outline leading-relaxed">{{ __('admin.confirm_archive_campaign_desc') }}</p>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" data-archive-cancel class="px-3 py-2 rounded-lg text-xs font-semibold bg-surface-container hover:bg-surface-container-high text-on-surface transition-colors">{{ __('admin.cancel') }}</button>
                <button type="button" data-archive-confirm class="px-3.5 py-2 rounded-lg text-xs font-semibold bg-primary hover:bg-primary-container text-on-primary transition-colors flex items-center gap-1.5 disabled:opacity-60">
                    <span class="material-symbols-outlined text-[16px]" data-action-icon aria-hidden="true">archive</span>
                    <span class="material-symbols-outlined text-[16px] animate-spin" data-spinner style="display: none;" aria-hidden="true">progress_activity</span>
                    <span>{{ __('admin.archive_campaign') }}</span>
                </button>
            </div>
        </div>
    </div>
</x-admin.layout>
