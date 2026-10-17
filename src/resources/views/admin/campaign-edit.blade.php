@php
    $campaignStatusValue = $campaign->status instanceof \BackedEnum ? $campaign->status->value : (string) $campaign->status;
    $initialCampaignData = [
        'id' => $campaign->id,
        'name' => $campaign->name,
        'restaurant' => $campaign->restaurant,
        'deadline' => $campaign->deadline?->format('Y-m-d\TH:i') ?? '',
        'payment_account_id' => $campaign->payment_account_id ? (string) $campaign->payment_account_id : '',
        'description' => $campaign->description ?? '',
        'sponsor_type' => $campaign->sponsor_type ?? \App\Models\Campaign::SPONSOR_TYPE_NONE,
        'sponsor_description' => $campaign->sponsor_description ?? '',
        'self_paid_price_basis' => $campaign->self_paid_price_basis ?? \App\Models\Campaign::SELF_PAID_PRICE_BASIS_ORIGINAL,
        'max_budget' => $campaign->max_budget ?? $maxBudget,
        'flat_price' => $campaign->flat_price ?? '',
        'status' => $campaignStatusValue,
        'sponsor_allocations' => $campaign->sponsor_allocations ?? [],
        'items' => $campaign->items->map(fn ($item) => [
            'id' => $item->id,
            'name' => $item->name,
            'price' => (int) $item->base_price,
            'category' => $item->category ?? __('admin.default_category_other'),
            'description' => $item->description ?? '',
            'image_url' => $item->image_url ?? '',
            'status' => $item->status instanceof \BackedEnum ? $item->status->value : (string) ($item->status ?? 'active'),
            'toppings' => $item->toppings->map(fn ($top) => [
                'id' => $top->id,
                'name' => $top->name,
                'price' => (int) $top->price,
            ])->values()->all(),
            'options' => $item->sizes->map(fn ($size) => [
                'id' => $size->id,
                'name' => $size->name,
                'price_delta' => (int) $size->price_delta,
            ])->values()->all(),
        ])->values()->all(),
    ];
@endphp

<x-admin.layout :title="__('admin.edit_campaign')" active="campaigns" :room="$room">
<div id="campaign-edit-page"
     class="w-full space-y-6"
     data-is-edit="true"
     data-submit-method="PATCH"
     data-budget-error="{{ __('admin.campaign_budget_exceeds_limit', ['limit' => ':limit']) }}"
     data-sponsor-percentage-error="{{ __('admin.sponsor_percentage_total_invalid') }}"
     data-item-deleted-success="{{ __('admin.item_deleted_success') }}"
     data-category-deleted-success="{{ __('admin.category_deleted_success', ['category' => ':category', 'count' => ':count']) }}"
     data-messages="{{ json_encode(\App\Support\Helpers\CampaignFormHelper::messages(), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) }}"
     data-submit-url="{{ route('admin.campaigns.update', [$room, $campaign]) }}"
     data-delete-url="{{ route('admin.campaigns.destroy', [$room, $campaign]) }}"
     data-index-url="{{ route('admin.campaigns.page', $room) }}"
     data-image-upload-url="{{ route('admin.campaigns.menu-images.store', $room) }}"
     data-item-categories="{{ json_encode(__('admin.item_category_suggestions'), JSON_UNESCAPED_UNICODE) }}"
     data-default-category-other="{{ __('admin.default_category_other') }}"
     data-sponsor-type-full="{{ \App\Models\Campaign::SPONSOR_TYPE_FULL }}"
     data-sponsor-type-none="{{ \App\Models\Campaign::SPONSOR_TYPE_NONE }}"
     x-data="campaignCreateComponent(
        { max_budget: {{ (int) $maxBudget }} },
        @js($roomUsers->map(fn ($roomUser) => [
            'id' => $roomUser->id,
            'name' => $roomUser->globalUser?->name ?? $roomUser->display_name,
            'user_code' => $roomUser->user_code,
        ])->values()),
        @js($initialCampaignData)
     )">

    <!-- Top Action Bar -->
    {{-- Mobile (below lg): the campaign name sits under "Edit campaign"; "View details" and "Save changes" share a full-width row. --}}
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-2 border-b border-outline-variant">
        <div class="flex items-center gap-3">
            <a href="{{ route('admin.campaigns.page', $room) }}" class="w-9 h-9 rounded-lg border border-outline-variant flex items-center justify-center text-outline hover:text-on-surface hover:bg-surface-container-low transition-colors">
                <span class="material-symbols-outlined text-[20px]">arrow_back</span>
            </a>
            <div>
                <h1 class="text-xl font-bold text-on-surface tracking-tight flex flex-col items-start gap-0.5 lg:flex-row lg:items-center lg:gap-2">
                    <span>{{ __('admin.edit_campaign') }}<span class="max-lg:hidden">:</span></span>
                    <span class="text-primary">{{ $campaign->name }}</span>
                </h1>
                <p class="text-xs text-outline">{{ __('admin.edit_campaign_subtitle', ['room' => $room->name]) }}</p>
            </div>
        </div>

        <div class="flex items-center gap-2.5 max-lg:w-full">
            <a href="{{ route('admin.campaigns.info', [$room, $campaign]) }}" class="px-4 py-2 rounded-lg border border-outline-variant text-xs font-semibold text-on-surface hover:bg-surface-container-low transition-colors flex items-center justify-center gap-1.5 no-underline whitespace-nowrap max-lg:min-w-0 max-lg:flex-1 max-lg:basis-[calc(50%-0.3125rem)]">
                <span class="material-symbols-outlined text-[16px]">visibility</span>
                <span>{{ __('admin.view_campaign_details') }}</span>
            </a>

            <!-- Save Actions Dropdown -->
            <div class="relative inline-flex rounded-lg shadow-sm max-lg:min-w-0 max-lg:flex-1 max-lg:basis-[calc(50%-0.3125rem)]" x-data="{ saveDropdownOpen: false }" @click.outside="saveDropdownOpen = false">
                <!-- Main button: Lưu thay đổi (giữ nguyên trạng thái) -->
                <button type="button"
                        @click="saveChanges()"
                        :disabled="submitting || cancelSubmitting"
                        class="px-4 py-2 rounded-l-lg bg-primary hover:bg-primary-container text-on-primary text-xs font-semibold transition-colors flex items-center justify-center gap-1.5 whitespace-nowrap disabled:opacity-50 max-lg:min-w-0 max-lg:flex-1">
                    <span x-show="submitting" class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                    <span x-show="!submitting" class="material-symbols-outlined text-[16px]">save</span>
                    <span x-text="submitting ? '{{ __('admin.processing') }}' : '{{ __('admin.save_changes') }}'"></span>
                </button>

                <!-- Dropdown Trigger Button -->
                <button type="button"
                        @click="saveDropdownOpen = !saveDropdownOpen"
                        :disabled="submitting || cancelSubmitting"
                        class="px-2 py-2 rounded-r-lg bg-primary hover:bg-primary-container text-on-primary text-xs border-l border-white/20 transition-colors flex items-center justify-center disabled:opacity-50"
                        title="{{ __('admin.save_changes') }}">
                    <span class="material-symbols-outlined text-[18px] transition-transform duration-200" :class="saveDropdownOpen ? 'rotate-180' : ''">expand_more</span>
                </button>

                <!-- Dropdown Menu -->
                <div x-show="saveDropdownOpen"
                     x-cloak
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                     x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                     x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                     class="absolute right-0 top-full mt-1.5 z-40 w-64 rounded-xl border border-outline-variant bg-surface-container-lowest p-1.5 shadow-xl">
                    
                    <!-- Action 1: Lưu thay đổi -->
                    <button type="button"
                            @click="saveDropdownOpen = false; saveChanges()"
                            :disabled="submitting || cancelSubmitting"
                            class="flex w-full items-start gap-2.5 rounded-lg px-3 py-2 text-left text-xs text-on-surface hover:bg-surface-container-low transition-colors">
                        <span class="material-symbols-outlined text-[18px] text-primary shrink-0 mt-0.5">save</span>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-on-surface">{{ __('admin.save_changes') }}</div>
                            <div class="text-[10px] text-outline leading-tight mt-0.5">{{ __('admin.save_changes_desc') }}</div>
                        </div>
                    </button>

                    <!-- Action 2: Lưu thay đổi và phát hành (active) -->
                    @if($campaignStatusValue !== 'active')
                    <button type="button"
                            x-show="form.status !== 'active'"
                            x-cloak
                            @click="saveDropdownOpen = false; saveChangesAndPublish()"
                            :disabled="submitting || cancelSubmitting"
                            class="flex w-full items-start gap-2.5 rounded-lg px-3 py-2 text-left text-xs text-on-surface hover:bg-surface-container-low transition-colors border-t border-outline-variant/60 mt-1 pt-1.5">
                        <span class="material-symbols-outlined text-[18px] text-emerald-600 dark:text-emerald-400 shrink-0 mt-0.5">rocket_launch</span>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-emerald-700 dark:text-emerald-300">{{ __('admin.save_and_publish') }}</div>
                            <div class="text-[10px] text-outline leading-tight mt-0.5">{{ __('admin.save_and_publish_desc') }}</div>
                        </div>
                    </button>
                    @endif

                    <!-- Action 3: Hủy chiến dịch (Xóa hoàn toàn) -->
                    <button type="button"
                            @click="saveDropdownOpen = false; showCancelCampaignModal = true"
                            :disabled="submitting || cancelSubmitting"
                            class="flex w-full items-start gap-2.5 rounded-lg px-3 py-2 text-left text-xs text-error hover:bg-error-container/30 transition-colors border-t border-outline-variant/60 mt-1 pt-1.5">
                        <span class="material-symbols-outlined text-[18px] text-error shrink-0 mt-0.5">delete_forever</span>
                        <div class="flex-1 min-w-0">
                            <div class="font-semibold text-error">{{ __('admin.cancel_campaign_action_btn') }}</div>
                            <div class="text-[10px] text-error/70 leading-tight mt-0.5">{{ __('admin.cancel_campaign_action_desc') }}</div>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Bento Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left 2 Cols: Form Info + Menu Builder -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Card 1: Basic Campaign Meta -->
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-5 shadow-sm space-y-4">
                <h2 class="text-sm font-bold text-on-surface uppercase tracking-wider flex items-center gap-2 border-b border-outline-variant/60 pb-2.5">
                    <span class="material-symbols-outlined text-[18px] text-primary">edit_note</span>
                    {{ __('admin.campaign_info') }}
                </h2>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-on-surface mb-1">{{ __('admin.campaign_name') }} <span class="text-error">*</span></label>
                        <input type="text" x-model="form.name" placeholder="{{ __('admin.campaign_name_example') }}" class="w-full px-3 py-2 bg-surface border border-outline-variant rounded-lg text-xs text-on-surface focus:outline-none focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-on-surface mb-1">{{ __('admin.restaurant_brand') }} <span class="text-error">*</span></label>
                        <input type="text" x-model="form.restaurant" placeholder="{{ __('admin.restaurant_example') }}" class="w-full px-3 py-2 bg-surface border border-outline-variant rounded-lg text-xs text-on-surface focus:outline-none focus:border-primary">
                    </div>
                </div>

                <div data-deadline-payment-grid class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-on-surface mb-1">{{ __('admin.order_deadline') }}</label>
                        <input type="datetime-local" x-model="form.deadline" class="w-full px-3 py-2 bg-surface border border-outline-variant rounded-lg text-xs text-on-surface focus:outline-none focus:border-primary">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-on-surface mb-1">{{ __('admin.room_vietqr_account') }}</label>
                        <select x-model="form.payment_account_id" class="w-full px-3 py-2 bg-surface border border-outline-variant rounded-lg text-xs text-on-surface focus:outline-none focus:border-primary">
                            <option value="">-- {{ __('admin.default_badge') }} Room --</option>
                            @foreach($paymentAccounts as $account)
                            <option value="{{ $account->id }}">{{ $account->account_name }} ({{ $account->bank_name }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1">{{ __('admin.campaign_desc_label') }}</label>
                    <textarea x-model="form.description" rows="2" placeholder="{{ __('admin.campaign_desc_placeholder') }}" class="w-full px-3 py-2 bg-surface border border-outline-variant rounded-lg text-xs text-on-surface focus:outline-none focus:border-primary"></textarea>
                </div>
            </div>

            <!-- Card 2: Multi-Source Menu Builder -->
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-5 shadow-sm space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-outline-variant/60 pb-2.5">
                    <h2 class="text-sm font-bold text-on-surface uppercase tracking-wider flex items-center gap-2">
                        <span class="material-symbols-outlined text-[18px] text-primary">restaurant_menu</span>
                        {{ __('admin.menu_and_items') }}
                    </h2>
                    <!-- Tab Switcher -->
                    <div class="flex items-center gap-1 bg-surface-container-low p-1 rounded-lg border border-outline-variant/60 text-xs overflow-x-auto no-scrollbar max-w-full">
                        <button type="button" @click="menuTab = 'reuse'" :class="menuTab === 'reuse' ? 'bg-primary font-bold text-white shadow-sm' : 'text-outline hover:text-on-surface'" class="px-2.5 py-1 rounded transition-all whitespace-nowrap flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">history</span>
                            <span>{{ __('admin.source_previous') }}</span>
                        </button>
                        <button type="button" @click="menuTab = 'data_gateway'" :class="menuTab === 'data_gateway' ? 'bg-gradient-to-r from-primary to-secondary font-bold text-white shadow-sm' : 'text-outline hover:text-on-surface'" class="px-2.5 py-1 rounded transition-all whitespace-nowrap flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">auto_awesome</span>
                            <span>{{ __('admin.source_data_gateway') }}</span>
                        </button>
                        <button type="button" @click="menuTab = 'crawler'" :class="menuTab === 'crawler' ? 'bg-primary font-bold text-white shadow-sm' : 'text-outline hover:text-on-surface'" class="px-2.5 py-1 rounded transition-all whitespace-nowrap flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">travel_explore</span>
                            <span>{{ __('admin.source_crawler') }}</span>
                        </button>
                    </div>
                </div>

                <!-- Tab 1: Previous Campaign -->
                <div x-show="menuTab === 'reuse'" x-cloak class="space-y-3">
                    <div class="text-xs text-outline">{{ __('admin.copy_previous_menu_desc') }}</div>
                    <x-admin.previous-campaign-picker :room="$room" :campaigns="$previousCampaigns" :has-more="$previousCampaignsHasMore" :exclude-campaign-id="$campaign->id" />
                </div>

                <!-- Tab 2: Data Gateway AI Converter -->
                <div x-show="menuTab === 'data_gateway'" x-cloak>
                    <x-admin.data-gateway-converter />
                </div>

                <!-- Tab 3: URL Crawler -->
                <div x-show="menuTab === 'crawler'" x-cloak class="space-y-3">
                    <div class="text-xs text-outline">{{ __('admin.crawler_desc') }}</div>
                    <div class="flex gap-2">
                        <input type="url" x-model="crawlerUrl" placeholder="{{ __('admin.crawler_url_placeholder') }}" class="flex-1 px-3 py-2 bg-surface border border-outline-variant rounded-lg text-xs text-on-surface focus:outline-none focus:border-primary">
                        <button type="button" @click="previewCrawler()" :disabled="crawlerLoading || !crawlerUrl" class="px-4 py-2 bg-primary text-on-primary rounded-lg text-xs font-semibold hover:bg-primary-container disabled:opacity-50 transition-colors flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px]" :class="crawlerLoading ? 'animate-spin' : ''">sync</span>
                            <span>{{ __('admin.crawl_menu_btn') }}</span>
                        </button>
                    </div>
                    <div x-show="crawlerMessage" class="text-xs p-2.5 rounded bg-surface-container-low border border-outline-variant text-on-surface" x-text="crawlerMessage"></div>
                </div>
            </div>
        </div>

        <!-- Right 1 Col: Sponsorship & Cost Breakdown -->
        <div class="space-y-6">
            <!-- Card 3: Sponsorship Policy -->
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-5 shadow-sm space-y-4">
                <h2 class="text-sm font-bold text-on-surface uppercase tracking-wider flex items-center gap-2 border-b border-outline-variant/60 pb-2.5">
                    <span class="material-symbols-outlined text-[18px] text-primary">volunteer_activism</span>
                    {{ __('admin.sponsor_policy_title') }}
                </h2>

                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1">{{ __('admin.sponsor_type_label') }}</label>
                    <select x-model="form.sponsor_type" class="w-full px-3 py-2 bg-surface border border-outline-variant rounded-lg text-xs text-on-surface">
                        <option value="none">{{ __('admin.sponsor_type_none') }}</option>
                        <option value="full">{{ __('admin.sponsor_type_full') }}</option>
                    </select>
                </div>

                <div x-show="form.sponsor_type === document.getElementById('campaign-edit-page').getAttribute('data-sponsor-type-full')" x-cloak class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-on-surface">{{ __('admin.sponsor_users_label') }}</span>
                        <button type="button" @click="addSponsor()" class="text-xs text-primary font-semibold hover:underline flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">add</span><span>{{ __('admin.add_sponsor') }}</span></button>
                    </div>
                    <template x-for="(sponsor, index) in sponsors" :key="index">
                        <div class="flex items-center gap-2 p-2 rounded-lg border border-outline-variant bg-surface">
                            <!-- Select sponsor search input -->
                            <div class="relative flex-1 min-w-0" @click.outside="sponsor.open = false">
                                <span class="material-symbols-outlined pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-[16px] text-outline">search</span>
                                <input type="text" x-model="sponsor.search" @focus="sponsor.open = true" @input="sponsor.open = true; sponsor.user_id = ''" placeholder="{{ __('admin.search_sponsor_placeholder') }}" autocomplete="off" class="w-full pl-8 pr-3 py-1.5 bg-surface-container-lowest border border-outline-variant rounded text-xs text-on-surface focus:outline-none focus:border-primary">
                                <div x-show="sponsor.open" x-cloak class="absolute z-30 mt-1 max-h-52 w-full overflow-y-auto rounded-lg border border-outline-variant bg-surface-container-lowest p-1 shadow-xl">
                                    <template x-for="user in filteredSponsorUsers(sponsor.search)" :key="user.id">
                                        <button type="button" @click="selectSponsor(sponsor, user)" class="flex w-full items-center justify-between gap-2 rounded-md px-2.5 py-2 text-left text-xs hover:bg-primary/5">
                                            <span class="truncate text-on-surface" x-text="user.name || user.user_code"></span>
                                        </button>
                                    </template>
                                    <p x-show="filteredSponsorUsers(sponsor.search).length === 0" class="px-2.5 py-3 text-center text-[11px] text-outline">{{ __('admin.no_sponsor_user_found') }}</p>
                                </div>
                            </div>
                            <!-- Percentage input with % suffix -->
                            <div class="relative w-24 shrink-0">
                                <input type="number" min="0" max="100" step="1" inputmode="numeric" x-model="sponsor.percentage" @input="clampSponsorPercentage(sponsor)" @blur="sponsor.percentage = sponsor.percentage === '' ? 0 : sponsor.percentage" placeholder="0" class="w-full pl-2.5 pr-6 py-1.5 bg-surface-container-lowest border border-outline-variant rounded text-xs font-mono text-on-surface focus:outline-none focus:border-primary text-right">
                                <span class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-xs font-mono font-bold text-outline">%</span>
                            </div>
                            <!-- Delete button -->
                            <button type="button" @click="removeSponsor(index)" class="p-1.5 text-error hover:bg-error-container/40 rounded transition-colors shrink-0 flex items-center justify-center" title="{{ __('admin.delete') }}">
                                <span class="material-symbols-outlined text-[18px]">delete</span>
                            </button>
                        </div>
                    </template>
                    <p x-show="sponsors.length === 0" class="text-[11px] text-outline italic">{{ __('admin.no_sponsors_added') }}</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1">{{ __('admin.max_product_budget_ceiling') }}</label>
                    <div class="relative">
                        <input type="text" inputmode="numeric" data-format-currency="true" x-model="form.max_budget" x-effect="$el.value = formatCurrencyDisplay(form.max_budget)" placeholder="0" class="w-full px-3 py-2 bg-surface border border-outline-variant rounded-lg text-xs font-mono font-bold text-primary focus:outline-none focus:border-primary pr-8">
                        <span class="absolute right-3 top-2 text-xs text-outline font-mono">đ</span>
                    </div>
                </div>
                <div x-show="form.sponsor_type !== 'none'">
                    <label class="block text-xs font-semibold text-on-surface mb-1">{{ __('admin.sponsor_description_label') }}</label>
                    <textarea x-model="form.sponsor_description" rows="2" class="w-full px-3 py-2 bg-surface border border-outline-variant rounded-lg text-xs text-on-surface"></textarea>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1">{{ __('admin.self_paid_price_basis_label') }}</label>
                    <select x-model="form.self_paid_price_basis" class="w-full px-3 py-2 bg-surface border border-outline-variant rounded-lg text-xs text-on-surface">
                        <option value="original">{{ __('admin.self_paid_price_basis_original') }}</option>
                        <option value="campaign_prorated">{{ __('admin.self_paid_price_basis_campaign_prorated') }}</option>
                    </select>
                    <p class="mt-1 text-[11px] text-outline">{{ __('admin.self_paid_price_basis_hint') }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Selected menu section -->
    <section class="bg-surface-container-lowest border border-outline-variant rounded-xl p-5 shadow-sm space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 border-b border-outline-variant/60 pb-3">
            <div>
                <h2 class="text-sm font-bold text-on-surface uppercase tracking-wider flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px] text-primary">fact_check</span>
                    {{ __('admin.selected_menu_preview') }}
                </h2>
                <p class="mt-1 text-[11px] text-outline"><span x-text="menuItems.length"></span> {{ __('admin.items_unit') }} · {{ __('admin.selected_menu_edit_hint') }}</p>
            </div>
            <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                <div class="flex rounded-lg border border-outline-variant bg-surface-container-low p-1 text-xs">
                    <button type="button" @click="setMenuView('all')" :class="menuView === 'all' ? 'bg-primary text-white shadow-sm font-semibold' : 'text-outline hover:text-on-surface'" class="rounded-md px-3 py-1.5 font-semibold transition-colors flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[15px]">grid_view</span>
                        <span>{{ __('admin.menu_view_all') }}</span>
                    </button>
                    <button type="button" @click="setMenuView('category')" :class="menuView === 'category' ? 'bg-primary text-white shadow-sm' : 'text-outline hover:text-on-surface'" class="rounded-md px-3 py-1.5 font-semibold transition-colors flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[15px]">category</span>
                        <span>{{ __('admin.menu_view_category') }}</span>
                    </button>
                </div>
                <button type="button" @click="openAddItemModal()" class="px-3 py-2 bg-primary hover:bg-primary-container text-white rounded-lg text-xs font-semibold transition-colors flex items-center justify-center gap-1.5">
                    <span class="material-symbols-outlined text-[16px]">add</span>
                    {{ __('admin.add_manual_item_button') }}
                </button>
            </div>
        </div>

        <div class="relative max-w-xl">
            <span class="material-symbols-outlined pointer-events-none absolute left-3 top-2.5 text-[18px] text-outline">search</span>
            <input type="text" data-search-debounce="300" :value="menuSearchInput" @input="updateMenuSearch($event.target.value)" placeholder="{{ __('admin.search_menu_item_placeholder') }}" class="w-full rounded-lg border border-outline-variant bg-surface py-2.5 pl-10 pr-9 text-xs text-on-surface focus:border-primary focus:outline-none">
            <button x-show="menuSearchInput" type="button" @click="updateMenuSearch('')" class="absolute right-2.5 top-2 text-outline hover:text-on-surface" title="{{ __('admin.clear_search') }}"><span class="material-symbols-outlined text-[18px]">close</span></button>
        </div>

        <!-- Menu Expiry Notice -->
        <div x-show="menuItems.length > 0" class="flex items-start gap-2.5 rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-900 dark:text-amber-200">
            <span class="material-symbols-outlined text-[18px] text-amber-600 dark:text-amber-400 shrink-0 mt-0.5">info</span>
            <div class="leading-relaxed">
                <strong class="font-semibold">{{ __('admin.data_gateway_menu_expiry_title') }}:</strong>
                <span>{{ __('admin.data_gateway_menu_expiry_desc') }}</span>
            </div>
        </div>

        <div x-show="menuView === 'category'" x-cloak class="flex flex-wrap gap-x-3 gap-y-3 pt-2 pr-2">
            <template x-for="category in menuCategories" :key="category">
                <div class="relative">
                    <button type="button" @click="selectedCategory = category" :class="selectedCategory === category ? 'border-primary bg-primary/10 text-primary font-bold' : 'border-outline-variant bg-surface text-outline hover:text-on-surface'" class="flex items-center gap-2 rounded-lg border py-2 pl-3 pr-4 text-xs font-semibold transition-colors">
                        <span x-text="category"></span>
                        <span class="rounded-full bg-surface-container-high px-1.5 py-0.5 font-mono text-[10px]" x-text="categoryItemCount(category)"></span>
                    </button>
                    <button type="button" @click.stop="removeMenuCategory(category)"
                        title="{{ __('admin.delete_category') }}" aria-label="{{ __('admin.delete_category') }}"
                        class="absolute -top-2 -right-2 z-10 flex h-5 w-5 items-center justify-center rounded-full bg-red-600 text-white shadow-sm ring-2 ring-surface-container-lowest transition-colors hover:bg-red-700 cursor-pointer">
                        <span class="material-symbols-outlined text-[14px] leading-none">close</span>
                    </button>
                </div>
            </template>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
            <template x-for="entry in visibleMenuItems" :key="entry.index">
                <article class="flex items-center gap-3 rounded-xl border border-outline-variant bg-surface p-3 hover:border-primary/40 transition-colors" :class="entry.item.status === 'inactive' ? 'opacity-65 bg-surface-container-low/50' : ''">
                    <img x-show="entry.item.image_url && !entry.item.image_load_failed" x-lazy-src="entry.item.image_url" :alt="entry.item.name || '{{ __('admin.item_image_alt') }}'" x-on:load="entry.item.image_load_failed = false" x-on:error="entry.item.image_load_failed = true" loading="lazy" class="h-16 w-16 shrink-0 rounded-lg object-cover border border-outline-variant">
                    <div x-show="!entry.item.image_url || entry.item.image_load_failed" class="flex h-16 w-16 shrink-0 items-center justify-center rounded-lg border border-outline-variant bg-surface-container-low text-outline">
                        <span class="material-symbols-outlined">restaurant</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <h3 class="truncate text-sm font-semibold text-on-surface" x-text="entry.item.name"></h3>
                            <template x-if="entry.item.status === 'inactive'">
                                <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-bold bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 border border-gray-300 dark:border-gray-700">
                                    {{ __('admin.status_inactive') }}
                                </span>
                            </template>
                        </div>
                        <div class="mt-1 flex flex-wrap items-center gap-1">
                            <span class="inline-flex items-center rounded bg-surface-container px-1.5 py-0.5 text-[10px] font-medium text-outline" x-text="entry.item.category || '{{ __('admin.uncategorized') }}'"></span>
                            <span x-show="entry.item.toppings?.length" class="inline-flex items-center rounded bg-primary/10 px-1.5 py-0.5 text-[10px] font-medium text-primary">
                                {{ __('admin.toppings_label') }} <span class="ml-0.5" x-text="entry.item.toppings.length"></span>
                            </span>
                            <span x-show="entry.item.options?.length" class="inline-flex items-center rounded bg-secondary/10 px-1.5 py-0.5 text-[10px] font-medium text-secondary">
                                {{ __('admin.options_label') }} <span class="ml-0.5" x-text="entry.item.options.length"></span>
                            </span>
                        </div>
                        <p class="mt-1 font-mono text-xs font-bold text-primary" x-text="formatVND(entry.item.price)"></p>
                    </div>
                    <div class="flex shrink-0 flex-col gap-1">
                        <button type="button" @click="openEditItemModal(entry.index)" class="group/edit relative rounded-lg p-1.5 text-primary hover:bg-primary/10 transition-colors" title="{{ __('admin.edit_item') }}" aria-label="{{ __('admin.edit_item') }}">
                            <span class="material-symbols-outlined text-[18px]">edit</span>
                            <span role="tooltip" class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-1.5 -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-[10px] font-medium text-white opacity-0 shadow-lg transition-opacity group-hover/edit:opacity-100 group-focus-visible/edit:opacity-100">{{ __('admin.edit_item') }}</span>
                        </button>
                        <button type="button" @click="removeMenuItem(entry.index)" class="group/del relative rounded-lg p-1.5 text-error hover:bg-error-container/40 transition-colors" title="{{ __('admin.delete_item') }}" aria-label="{{ __('admin.delete_item') }}">
                            <span class="material-symbols-outlined text-[18px]">delete</span>
                            <span role="tooltip" class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-1.5 -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-[10px] font-medium text-white opacity-0 shadow-lg transition-opacity group-hover/del:opacity-100 group-focus-visible/del:opacity-100">{{ __('admin.delete_item') }}</span>
                        </button>
                    </div>
                </article>
            </template>
        </div>
        <div x-show="menuItems.length === 0" class="flex flex-col items-center justify-center py-12 px-4 rounded-xl border border-dashed border-outline-variant bg-surface-container-low/30 text-center">
            <span class="material-symbols-outlined text-4xl text-primary/60 mb-2">no_food</span>
            <p class="text-xs text-outline font-medium max-w-md">{{ __('admin.no_items_yet') }}</p>
        </div>
        <div x-show="menuItems.length > 0 && visibleMenuItems.length === 0" class="flex flex-col items-center justify-center py-10 px-4 rounded-xl border border-dashed border-outline-variant bg-surface-container-low/30 text-center">
            <span class="material-symbols-outlined text-3xl text-outline-variant mb-2">search_off</span>
            <p class="text-xs text-outline font-medium">{{ __('admin.no_menu_item_found') }}</p>
        </div>
    </section>

    {{-- Modals reset their margin: <main> uses space-y-*, which (Tailwind v4) adds a bottom margin to every child
         but the last, and that margin would leave a gap under a fixed full-screen backdrop. --}}
    <!-- Modal for Manual Add / Edit Item -->
    <div x-show="showAddItemModal" x-cloak class="fixed inset-0 z-50 !m-0 flex items-center justify-center bg-black/60 p-4">
        <div class="w-full max-w-3xl max-h-[90vh] overflow-y-auto rounded-xl bg-surface-container-lowest border border-outline-variant p-5 shadow-2xl">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold text-base text-on-surface">
                    <span x-show="editingItemIndex === null">{{ __('admin.add_manual_item_title') }}</span>
                    <span x-show="editingItemIndex !== null">{{ __('admin.edit_menu_item_title') }}</span>
                </h3>
                <button type="button" @click="showAddItemModal = false; editingItemIndex = null" class="text-outline hover:text-on-surface"><span class="material-symbols-outlined">close</span></button>
            </div>

            <div class="mb-4 flex justify-start">
                <div class="inline-flex rounded-lg border border-outline-variant bg-surface-container-low p-1 text-xs gap-1">
                    <button type="button" @click="itemModalTab = 'basic'" :class="itemModalTab === 'basic' ? 'bg-primary text-on-primary shadow-xs font-semibold' : 'text-outline hover:text-on-surface'" class="rounded-md px-3 py-1.5 font-medium transition-colors flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[15px]">tune</span>
                        <span>{{ __('admin.item_tab_basic') }}</span>
                    </button>
                    <button type="button" @click="itemModalTab = 'additional'" :class="itemModalTab === 'additional' ? 'bg-primary text-on-primary shadow-xs font-semibold' : 'text-outline hover:text-on-surface'" class="rounded-md px-3 py-1.5 font-medium transition-colors flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[15px]">extension</span>
                        <span>{{ __('admin.item_tab_additional') }}</span>
                    </button>
                </div>
            </div>

            <div x-show="itemModalTab === 'basic'" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-on-surface mb-1">{{ __('admin.item_category_label') }}</label>
                    <input type="text"
                           x-model="newItem.category"
                           list="campaign-item-categories"
                           placeholder="{{ __('admin.item_category_placeholder') }}"
                           class="w-full px-3 py-2 bg-surface border border-outline-variant rounded-lg text-xs text-on-surface focus:outline-none focus:border-primary">
                    <datalist id="campaign-item-categories">
                        @foreach (__('admin.item_category_suggestions') as $cat)
                            <option value="{{ $cat }}">{{ $cat }}</option>
                        @endforeach
                        <template x-for="category in availableItemCategories" :key="category">
                            <option :value="category" x-text="category"></option>
                        </template>
                    </datalist>
                    <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
                        <template x-for="cat in availableItemCategories" :key="cat">
                            <button type="button"
                                    @click="newItem.category = cat"
                                    :class="newItem.category === cat ? 'bg-primary text-on-primary border-primary font-semibold shadow-2xs' : 'bg-surface-container-low text-outline hover:text-on-surface hover:bg-surface-container border-outline-variant'"
                                    class="px-2 py-0.5 rounded-md text-[10px] border transition-colors"
                                    x-text="cat">
                            </button>
                        </template>
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1">{{ __('admin.item_name_placeholder') }}</label>
                    <input type="text" x-model="newItem.name" class="w-full px-3 py-2 bg-surface border border-outline-variant rounded-lg text-xs text-on-surface">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1">{{ __('admin.price_vnd') }}</label>
                    <input type="text" inputmode="numeric" data-format-currency="true" x-model="newItem.price" placeholder="0" class="w-full px-3 py-2 bg-surface border border-outline-variant rounded-lg text-xs font-mono font-bold text-primary text-on-surface">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1">{{ __('admin.item_image_url_label') }}</label>
                    <input type="url" x-model="newItem.image_url" placeholder="https://example.com/image.jpg" class="w-full px-3 py-2 bg-surface border border-outline-variant rounded-lg text-xs text-on-surface">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-on-surface mb-1">{{ __('admin.item_image_upload_label') }}</label>
                    <input type="file" accept="image/jpeg,image/png,image/webp" @change="uploadManualImage($event)" :disabled="imageUploading" class="w-full text-xs text-outline file:mr-3 file:rounded-lg file:border-0 file:bg-primary file:px-3 file:py-2 file:text-xs file:font-semibold file:text-on-primary">
                    <p x-show="imageUploading" class="mt-1 text-[11px] text-primary">{{ __('admin.image_uploading') }}</p>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-on-surface mb-1">{{ __('admin.item_description_label') }}</label>
                    <textarea x-model="newItem.description" rows="2" class="w-full px-3 py-2 bg-surface border border-outline-variant rounded-lg text-xs text-on-surface"></textarea>
                </div>

                <!-- Item Status Checkbox / Switch -->
                <div class="sm:col-span-2 pt-3 border-t border-outline-variant/60 flex items-center justify-between">
                    <div>
                        <label for="edit-modal-item-status" class="text-xs font-semibold text-on-surface cursor-pointer flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[16px] text-primary" x-show="newItem.status !== 'inactive'">check_circle</span>
                            <span class="material-symbols-outlined text-[16px] text-outline" x-show="newItem.status === 'inactive'">do_not_disturb_on</span>
                            <span>{{ __('admin.item_status_active_label') }}</span>
                        </label>
                        <p class="text-[11px] text-outline mt-0.5">{{ __('admin.item_status_active_desc') }}</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input id="edit-modal-item-status"
                               type="checkbox"
                               :checked="newItem.status !== 'inactive'"
                               @change="newItem.status = $event.target.checked ? 'active' : 'inactive'"
                               class="sr-only peer">
                        <div class="w-9 h-5 bg-surface-container-highest peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-primary"></div>
                    </label>
                </div>
            </div>

            <div x-show="itemModalTab === 'additional'" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="rounded-lg border border-outline-variant p-3 space-y-2">
                    <div class="flex items-center justify-between"><span class="text-xs font-semibold">{{ __('admin.toppings_and_extras') }}</span><button type="button" @click="addTopping(newItem)" class="text-xs text-primary font-semibold">{{ __('admin.add_topping') }}</button></div>
                    <template x-for="(topping, index) in newItem.toppings" :key="index"><div class="grid grid-cols-[1fr_7rem_auto] gap-2"><input type="text" x-model="topping.name" placeholder="{{ __('admin.option_name_placeholder') }}" class="min-w-0 px-2 py-1.5 border border-outline-variant rounded text-xs"><input type="text" inputmode="numeric" data-format-currency="true" x-model="topping.price" placeholder="{{ __('admin.price_vnd') }}" class="min-w-0 px-2 py-1.5 border border-outline-variant rounded text-xs font-mono"><button type="button" @click="newItem.toppings.splice(index, 1)" class="text-error"><span class="material-symbols-outlined text-[16px]">close</span></button></div></template>
                </div>
                <div class="rounded-lg border border-outline-variant p-3 space-y-2">
                    <div class="flex items-center justify-between"><span class="text-xs font-semibold">{{ __('admin.sizes_and_options') }}</span><button type="button" @click="addOption(newItem)" class="text-xs text-primary font-semibold">{{ __('admin.add_option') }}</button></div>
                    <template x-for="(option, index) in newItem.options" :key="index"><div class="grid grid-cols-[1fr_7rem_auto] gap-2"><input type="text" x-model="option.name" placeholder="{{ __('admin.option_name_placeholder') }}" class="min-w-0 px-2 py-1.5 border border-outline-variant rounded text-xs"><input type="text" inputmode="numeric" data-format-currency="true" x-model="option.price_delta" placeholder="{{ __('admin.price_delta_placeholder') }}" class="min-w-0 px-2 py-1.5 border border-outline-variant rounded text-xs font-mono"><button type="button" @click="newItem.options.splice(index, 1)" class="text-error"><span class="material-symbols-outlined text-[16px]">close</span></button></div></template>
                </div>
            </div>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" @click="showAddItemModal = false; editingItemIndex = null" :disabled="itemSubmitting" class="px-4 py-2 rounded-lg bg-surface-container text-on-surface text-xs font-semibold disabled:opacity-50">{{ __('admin.cancel') }}</button>
                <button type="button" @click="confirmAddItem()" :disabled="itemSubmitting || imageUploading" class="min-w-32 px-4 py-2 rounded-lg bg-primary text-on-primary text-xs font-semibold disabled:opacity-60 flex items-center justify-center gap-1.5 transition-colors">
                    <span x-show="itemSubmitting" class="material-symbols-outlined animate-spin text-[16px]">progress_activity</span>
                    <span x-show="!itemSubmitting && editingItemIndex === null" class="material-symbols-outlined text-[16px]">add</span>
                    <span x-show="!itemSubmitting && editingItemIndex !== null" class="material-symbols-outlined text-[16px]">check</span>
                    <span x-show="!itemSubmitting && editingItemIndex === null">{{ __('admin.add_new_item_btn') }}</span>
                    <span x-show="!itemSubmitting && editingItemIndex !== null">{{ __('admin.save_item_changes') }}</span>
                    <span x-show="itemSubmitting">{{ __('admin.processing') }}</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Confirm Cancel & Delete Temporary Campaign -->
    <div x-show="showCancelCampaignModal"
         x-cloak
         class="fixed inset-0 z-50 !m-0 flex items-center justify-center bg-black/60 p-4"
         @keydown.escape.window="showCancelCampaignModal = false">
        <div class="w-full max-w-md rounded-xl bg-surface-container-lowest border border-outline-variant p-5 shadow-2xl space-y-4"
             @click.outside="showCancelCampaignModal = false">
            <div class="flex items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-error-container text-on-error-container">
                    <span class="material-symbols-outlined text-[22px]">warning</span>
                </div>
                <div class="flex-1 min-w-0">
                    <h3 class="font-bold text-base text-on-surface">{{ __('admin.confirm_cancel_campaign_title') }}</h3>
                    <p class="mt-1 text-xs text-outline leading-relaxed">
                        {{ __('admin.confirm_delete_temporary_campaign_message') }}
                    </p>
                </div>
            </div>

            <div class="rounded-lg bg-surface-container-low p-3 text-xs space-y-1.5 border border-outline-variant/60">
                <div class="flex justify-between">
                    <span class="text-outline">{{ __('admin.campaign_name') }}:</span>
                    <span class="font-semibold text-on-surface truncate ml-2" x-text="form.name"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-outline">{{ __('admin.restaurant_brand') }}:</span>
                    <span class="font-semibold text-on-surface truncate ml-2" x-text="form.restaurant"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-outline">{{ __('admin.current_status') }}:</span>
                    <span class="font-mono font-semibold uppercase text-primary" x-text="form.status"></span>
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button"
                        @click="showCancelCampaignModal = false"
                        :disabled="cancelSubmitting"
                        class="px-4 py-2 rounded-lg border border-outline-variant text-on-surface text-xs font-semibold hover:bg-surface-container-low transition-colors disabled:opacity-50">
                    {{ __('admin.cancel') }}
                </button>
                <button type="button"
                        @click="executeCancelCampaign()"
                        :disabled="cancelSubmitting"
                        class="px-4 py-2 rounded-lg bg-error text-on-error text-xs font-semibold hover:opacity-90 shadow-xs transition-all flex items-center gap-1.5 disabled:opacity-50">
                    <span x-show="cancelSubmitting" class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                    <span x-show="!cancelSubmitting" class="material-symbols-outlined text-[16px]">delete</span>
                    <span x-text="cancelSubmitting ? '{{ __('admin.processing') }}' : '{{ __('admin.confirm_cancel_campaign_btn') }}'"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Confirm Publish Campaign -->
    <div x-show="showConfirmModal"
         x-cloak
         class="fixed inset-0 z-50 !m-0 flex items-center justify-center bg-black/60 p-4"
         @keydown.escape.window="if (!submitting) showConfirmModal = false">
        <div class="w-full max-w-lg rounded-xl bg-surface-container-lowest border border-outline-variant p-5 shadow-2xl"
             @click.outside="if (!submitting) showConfirmModal = false">
            <div class="flex items-center justify-between border-b border-outline-variant pb-3">
                <h3 class="font-bold text-base text-on-surface">{{ __('admin.confirm_campaign_publish_title') }}</h3>
                <button type="button" @click="showConfirmModal = false" :disabled="submitting" class="text-outline hover:text-on-surface disabled:opacity-50"><span class="material-symbols-outlined">close</span></button>
            </div>
            <div class="grid grid-cols-2 gap-3 py-4 text-xs">
                <div><span class="text-outline">{{ __('admin.campaign_name') }}</span><p class="font-semibold text-on-surface truncate" x-text="form.name"></p></div>
                <div><span class="text-outline">{{ __('admin.restaurant_brand') }}</span><p class="font-semibold text-on-surface truncate" x-text="form.restaurant"></p></div>
                <div><span class="text-outline">{{ __('admin.max_product_budget_ceiling') }}</span><p class="font-mono font-semibold text-primary" x-text="formatVND(form.max_budget)"></p></div>
                <div><span class="text-outline">{{ __('admin.menu_item_count_label') }}</span><p class="font-semibold text-on-surface" x-text="menuItems.length"></p></div>
                <div class="col-span-2"><span class="text-outline">{{ __('admin.order_deadline') }}</span><p class="font-semibold text-on-surface" x-text="formatDeadline(form.deadline)"></p></div>
            </div>
            <p class="rounded-lg bg-amber-50 dark:bg-amber-950/30 px-3 py-2 text-xs text-amber-800 dark:text-amber-300">{{ __('admin.confirm_campaign_publish_message') }}</p>
            <div class="flex justify-end gap-2 pt-4">
                <button type="button" @click="showConfirmModal = false" :disabled="submitting" class="px-4 py-2 rounded-lg border border-outline-variant text-on-surface text-xs font-semibold hover:bg-surface-container-low transition-colors disabled:opacity-50">{{ __('admin.cancel') }}</button>
                <button type="button" @click="confirmPublish()" :disabled="submitting" class="px-4 py-2 rounded-lg bg-primary text-on-primary text-xs font-semibold disabled:opacity-50 flex items-center gap-1.5 transition-colors">
                    <span x-show="submitting && submittingAction === 'publish'" class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                    <span x-show="!(submitting && submittingAction === 'publish')" class="material-symbols-outlined text-[16px]">rocket_launch</span>
                    <span x-text="submitting && submittingAction === 'publish' ? '{{ __('admin.processing') }}' : '{{ __('admin.confirm_publish_campaign') }}'"></span>
                </button>
            </div>
        </div>
    </div>

    <!-- Modal Confirm Update Campaign (notify members toggle) -->
    <div x-show="showNotifyModal"
         x-cloak
         class="fixed inset-0 z-50 !m-0 flex items-center justify-center bg-black/60 p-4"
         @keydown.escape.window="if (!submitting) showNotifyModal = false">
        <div class="w-full max-w-md rounded-xl bg-surface-container-lowest border border-outline-variant p-5 shadow-2xl"
             @click.outside="if (!submitting) showNotifyModal = false">
            <div class="flex items-center justify-between border-b border-outline-variant pb-3">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-primary/10 text-primary" aria-hidden="true">
                        <span class="material-symbols-outlined text-[19px]">edit_note</span>
                    </span>
                    <h3 class="font-bold text-base text-on-surface">{{ __('admin.confirm_update_campaign_title') }}</h3>
                </div>
                <button type="button" @click="showNotifyModal = false" :disabled="submitting" class="text-outline hover:text-on-surface disabled:opacity-50"><span class="material-symbols-outlined">close</span></button>
            </div>
            <p class="py-4 text-xs text-on-surface-variant leading-relaxed">{{ __('admin.confirm_update_campaign_desc') }}</p>
            <label class="flex items-center gap-2.5 cursor-pointer text-xs text-on-surface-variant select-none pb-2">
                <input type="checkbox" x-model="notifyMembers" class="rounded border-outline-variant text-primary focus:ring-primary w-4 h-4">
                <span class="font-medium">{{ __('admin.notify_members_checkbox_label') }}</span>
            </label>
            <div class="flex justify-end gap-2 pt-3 border-t border-outline-variant">
                <button type="button" @click="showNotifyModal = false" :disabled="submitting" class="px-4 py-2 rounded-lg border border-outline-variant text-on-surface text-xs font-semibold hover:bg-surface-container-low transition-colors disabled:opacity-50">{{ __('admin.cancel') }}</button>
                <button type="button" @click="confirmSaveChanges()" :disabled="submitting" class="px-4 py-2 rounded-lg bg-primary text-on-primary text-xs font-semibold disabled:opacity-50 flex items-center gap-1.5 transition-colors">
                    <span x-show="submitting" class="material-symbols-outlined text-[16px] animate-spin">progress_activity</span>
                    <span x-show="!submitting" class="material-symbols-outlined text-[16px]">save</span>
                    <span x-text="submitting ? '{{ __('admin.processing') }}' : '{{ __('admin.confirm_update_btn') }}'"></span>
                </button>
            </div>
        </div>
    </div>
</div>
</x-admin.layout>
