<x-room.layout
  :title="'DrinkFlow - ' . __('room.campaign.page_title') . ' - ' . ($room->name ?? __('global.common.room'))"
  :room="$room"
  :room-user="$roomUser"
  :user="$user"
  :active-tab="'campaigns'"
  :unread-notifications-count="$unreadNotificationsCount ?? 0"
  :user-rooms="$userRooms ?? collect()"
>
  <div class="space-y-6" x-data="{
    searchQuery: '',
    searchInput: '',
    searchTimer: null,
    selectedCategory: {{ Js::from($categories->first() ?? 'all') }},
    menuItems: {{ Js::from($activeCampaign?->items ?? []) }},
    maxBudget: {{ (int) ($activeCampaign?->max_budget ?? 0) }},
    withinBudgetOnly: false,
    {{-- Menu display mode ('grid' default | 'list': compact rows, 1 per row on mobile, 2 from sm), remembered in this browser only. --}}
    menuViewKey: 'drinkflow.campaign.menu_view',
    menuView: (() => { try { return localStorage.getItem('drinkflow.campaign.menu_view') === 'list' ? 'list' : 'grid'; } catch (e) { return 'grid'; } })(),
    setMenuView(mode) {
      this.menuView = mode === 'list' ? 'list' : 'grid';
      try { localStorage.setItem(this.menuViewKey, this.menuView); } catch (e) { /* storage unavailable: keep it for this visit only */ }
    },
    hasDeclined: {{ Js::from((bool) $hasDeclined) }},
    showCustomModal: false,
    showCartModal: false,
    showConfirmModal: false,
    {{-- Set by "Đặt nhanh": the confirm modal then submits only this item and leaves the cart untouched. --}}
    quickOrderItem: null,
    showDeclineModal: false,
    showRejoinModal: false,
    showFavoriteModal: false,
    favoriteItems: [],
    favoriteLoading: false,
    favoriteError: false,
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
    favoriteItemsUrl: {{ Js::from($activeCampaign ? route('user.campaigns.favorite-items', [$room, $activeCampaign]) : '') }},
    favoriteRankLabel: {{ Js::from(__('room.dashboard.rank_label', ['rank' => ':rank'])) }},
    {{-- Giữ đồng bộ bảng màu với components/room/rank-medal.blade.php --}}
    medalClasses: ['text-amber-500', 'text-slate-400', 'text-orange-700', 'text-emerald-600', 'text-emerald-400'],
    async openFavorites() {
      this.showFavoriteModal = true;
      this.favoriteError = false;
      this.favoriteItems = [];
      if (!this.favoriteItemsUrl) return;
      this.favoriteLoading = true;
      try {
        const response = await fetch(this.favoriteItemsUrl, { headers: { 'Accept': 'application/json' } });
        if (!response.ok) throw new Error('favorite items request failed');
        const payload = await response.json();
        this.favoriteItems = payload.data ?? [];
      } catch (error) {
        this.favoriteError = true;
      } finally {
        this.favoriteLoading = false;
      }
    },
    showBudgetErrors: false,
    participationSubmitting: false,
    cartItems: {{ Js::from($cart ?? []) }},
    cartSubmitting: false,
    cartUpdating: false,
    showProxyModal: false,
    proxyEditingIndex: null,
    proxyUserCode: '',
    proxyUserLookupResult: null,
    proxyUserLookupLoading: false,
    proxyUserLookupError: null,
    async submitDecline() {
      this.participationSubmitting = true;
      if (window.showGlobalLoading) {
        window.showGlobalLoading('{{ __('global.common.loading') }}');
      }
      try {
        const response = await fetch('{{ $activeCampaign ? route('user.campaigns.decline', [$room, $activeCampaign]) : '' }}', {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json'
          }
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || '{{ __('room.campaign.error_generic') }}');
        this.cartItems = [];
        this.hasDeclined = true;
        this.showDeclineModal = false;
        window.location.reload();
      } catch (error) {
        if (window.hideGlobalLoading) window.hideGlobalLoading();
        this.participationSubmitting = false;
        window.alert(error.message);
      }
    },
    async submitRejoin() {
      this.participationSubmitting = true;
      if (window.showGlobalLoading) {
        window.showGlobalLoading('{{ __('global.common.loading') }}');
      }
      try {
        const response = await fetch('{{ $activeCampaign ? route('user.campaigns.rejoin', [$room, $activeCampaign]) : '' }}', {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json'
          }
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || '{{ __('room.campaign.error_generic') }}');
        this.hasDeclined = false;
        this.showRejoinModal = false;
        window.location.reload();
      } catch (error) {
        if (window.hideGlobalLoading) window.hideGlobalLoading();
        this.participationSubmitting = false;
        window.alert(error.message);
      }
    },
    selectedItem: null,
    selectedSize: null,
    selectedToppings: [],
    note: '',
    isSelfPaid: false,
    quantity: 1,
    /** Clamp the chosen quantity to a whole number between 1 and the budget-derived maximum. */
    normalizeQuantity(value) {
      const parsed = Number.parseInt(String(value ?? '').replace(/\D/g, ''), 10);
      const max = Math.max(1, this.maxQuantity);
      return Number.isFinite(parsed) ? Math.min(max, Math.max(1, parsed)) : 1;
    },
    defaultNote: {{ Js::from($user?->default_order_note ?? '') }},
    sampleNotes: [
      '{{ __('room.campaign.sample_note_less_sweet') }}',
      '{{ __('room.campaign.sample_note_no_ice') }}',
      '{{ __('room.campaign.sample_note_separate') }}'
    ],
    get calculatedPrice() {
      if (!this.selectedItem) return 0;
      let total = parseInt(this.selectedItem.base_price || 0);
      if (this.selectedSize && this.selectedSize.price_delta) {
        total += parseInt(this.selectedSize.price_delta);
      }
      if (this.selectedToppings && this.selectedToppings.length > 0) {
        this.selectedToppings.forEach(top => {
          total += parseInt(top.price || 0);
        });
      }
      return total;
    },
    /** Largest quantity whose line total stays within the per-product budget cap (and the 1–99 range). */
    get maxQuantity() {
      if (this.maxBudget <= 0 || this.calculatedPrice <= 0) return 99;
      return Math.min(99, Math.floor(this.maxBudget / this.calculatedPrice));
    },
    get isCustomItemExceeded() {
      return this.maxBudget > 0 && this.calculatedPrice * this.normalizeQuantity(this.quantity) > this.maxBudget;
    },
    isItemExceeded(item) {
      return this.maxBudget > 0 && Number(item.unit_price) * Number(item.quantity) > this.maxBudget;
    },
    hasExceededItems() {
      return this.cartItems.some(item => this.isItemExceeded(item));
    },
    exceededCount() {
      return this.cartItems.filter(item => this.isItemExceeded(item)).length;
    },
    openCustomize(item) {
      this.selectedItem = item;
      this.selectedSize = (item.sizes && item.sizes.length > 0) ? item.sizes[0] : null;
      this.selectedToppings = [];
      this.note = this.defaultNote;
      this.isSelfPaid = false;
      this.quantity = 1;
      this.showCustomModal = true;
    },
    closeCustomModal() {
      this.showCustomModal = false;
      this.selectedToppings = [];
      this.selectedSize = null;
      this.isSelfPaid = false;
      this.quantity = 1;
    },
    /** Order one item straight away (default size, no topping, qty 1) without going through the cart. */
    openQuickOrder(item) {
      const size = (item.sizes && item.sizes.length > 0) ? item.sizes[0] : null;
      const price = Number(item.base_price || 0) + Number(size?.price_delta || 0);
      if (this.maxBudget > 0 && price > this.maxBudget) {
        window.alert({{ Js::from(__('room.campaign.custom_exceeds_budget_msg', ['limit' => \App\Support\Helpers\FormatHelper::formatCurrency((int) ($activeCampaign?->max_budget ?? 0))])) }});
        return;
      }
      this.quickOrderItem = {
        item_id: item.id,
        item_name: item.name,
        size_id: size?.id || null,
        topping_ids: [],
        quantity: 1,
        note: this.defaultNote,
        is_self_paid: false,
        proxy_user_code: null
      };
      this.showConfirmModal = true;
    },
    closeConfirmModal() {
      this.showConfirmModal = false;
      this.quickOrderItem = null;
    },
    /** Items the confirm modal is about to submit: the quick-order item, or the whole cart. */
    get confirmItems() {
      return this.quickOrderItem ? [this.quickOrderItem] : this.cartItems;
    },
    async addToCart() {
      if (this.isCustomItemExceeded) {
        window.alert('{{ __('room.campaign.custom_exceeds_budget_msg', ['limit' => \App\Support\Helpers\FormatHelper::formatCurrency((int) ($activeCampaign?->max_budget ?? 0))]) }}');
        return;
      }
      this.cartSubmitting = true;
      try {
        const response = await fetch('{{ $activeCampaign ? route('user.campaigns.cart.store', [$room, $activeCampaign]) : '' }}', {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            item_id: this.selectedItem.id,
            size_id: this.selectedSize?.id || null,
            topping_ids: this.selectedToppings.map(top => top.id),
            quantity: this.normalizeQuantity(this.quantity),
            note: this.note,
            is_self_paid: this.isSelfPaid,
            proxy_user_code: null
          })
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || '{{ __('room.campaign.error_generic') }}');
        this.cartItems = payload.data || [];
        this.showCustomModal = false;
      } catch (error) {
        window.alert(error.message);
      } finally {
        this.cartSubmitting = false;
      }
    },
    proceedToConfirm() {
      // Ordering for others requires at least one item for yourself (the server enforces this too).
      if (this.cartItems.some(item => item.proxy_user_code) && !this.cartItems.some(item => !item.proxy_user_code)) {
        window.alert(@js(__('room.campaign.proxy_requires_own_order')));
        return;
      }
      if (this.hasExceededItems()) {
        this.showBudgetErrors = true;
        return;
      }
      this.showBudgetErrors = false;
      this.showConfirmModal = true;
      this.showCartModal = false;
    },
    async confirmCart() {
      if (!this.quickOrderItem && this.hasExceededItems()) {
        window.alert('{{ __('room.campaign.cart_exceeded_banner_desc', ['limit' => \App\Support\Helpers\FormatHelper::formatCurrency((int) ($activeCampaign?->max_budget ?? 0))]) }}');
        return;
      }
      this.cartSubmitting = true;
      try {
        const response = await fetch('{{ $activeCampaign ? route('user.orders.store', [$room, $activeCampaign]) : '' }}', {
          method: 'POST',
          headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({
            items: this.confirmItems.map(item => ({ ...item, proxy_user_code: item.proxy_user_code || null })),
            payment_method: 'transfer'
          })
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || '{{ __('room.campaign.error_generic') }}');
        window.location.href = '{{ route('user.orders.index', $room->slug) }}';
      } catch (error) {
        window.alert(error.message);
      } finally {
        this.cartSubmitting = false;
      }
    },
    cartTotal() {
      return this.cartItems.reduce((total, item) => total + (Number(item.unit_price) * Number(item.quantity)), 0);
    },
    get ownItemsCount() {
      // Chỉ tính món tự đặt cho chính mình, không tính món trả riêng hay đặt dùm (đã có số đếm riêng).
      return this.confirmItems.filter(item => !item.proxy_user_code && !item.is_self_paid).reduce((total, item) => total + Number(item.quantity), 0);
    },
    get proxyItemsCount() {
      return this.confirmItems.filter(item => item.proxy_user_code).reduce((total, item) => total + Number(item.quantity), 0);
    },
    get selfPaidItemsCount() {
      return this.confirmItems.filter(item => item.is_self_paid).reduce((total, item) => total + Number(item.quantity), 0);
    },
    openProxyModal(index) {
      const item = this.cartItems[index];
      if (!item) return;
      this.proxyEditingIndex = index;
      this.proxyUserCode = item.proxy_user_code || '';
      this.proxyUserLookupResult = item.proxy_user_code ? { display_name: item.proxy_user_name || '', user_code: item.proxy_user_code } : null;
      this.proxyUserLookupError = null;
      this.showProxyModal = true;
    },
    async lookupProxyUser() {
      const code = this.proxyUserCode.trim();
      this.proxyUserLookupError = null;
      this.proxyUserLookupResult = null;
      if (!code) return;
      this.proxyUserLookupLoading = true;
      try {
        const url = '{{ route('user.room-members.lookup', $room) }}' + '?q=' + encodeURIComponent(code);
        const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
        const payload = await response.json();
        if (!response.ok) {
          // 404 = nobody matches; 422 = a business rule (own account, debt limit) with its own message.
          throw new Error(response.status === 404
            ? '{{ __('room.campaign.proxy_user_not_found', ['code' => '']) }}' + code
            : (payload.message || '{{ __('room.campaign.error_generic') }}'));
        }
        this.proxyUserLookupResult = payload;
      } catch (error) {
        this.proxyUserLookupError = error.message;
      } finally {
        this.proxyUserLookupLoading = false;
      }
    },
    async saveProxyAssignment() {
      if (this.proxyEditingIndex === null) return;
      const item = this.cartItems[this.proxyEditingIndex];
      if (!item) return;
      this.cartUpdating = true;
      try {
        const response = await fetch('{{ $activeCampaign ? route('user.campaigns.cart.proxy', [$room, $activeCampaign, '__INDEX__']) : '' }}'.replace('__INDEX__', this.proxyEditingIndex), {
          method: 'PATCH',
          headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json', 'Content-Type': 'application/json' },
          body: JSON.stringify({
            proxy_user_code: this.proxyUserLookupResult?.user_code || null,
            proxy_user_name: this.proxyUserLookupResult?.display_name || null
          })
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || '{{ __('room.campaign.error_generic') }}');
        this.cartItems = payload.data || [];
        this.showProxyModal = false;
      } catch (error) {
        window.alert(error.message);
      } finally {
        this.cartUpdating = false;
      }
    },
    itemImageUrl(item) {
      if (!item) return '';

      const menuItem = this.menuItems.find(candidate => String(candidate.id) === String(item.item_id || item.id));
      const imageUrl = String(item.image_url || menuItem?.image_url || '').trim();
      if (!imageUrl) return '';
      if (imageUrl.startsWith('storage/')) return `/${imageUrl}`;

      try {
        const parsedUrl = new URL(imageUrl, window.location.origin);
        if (parsedUrl.pathname.startsWith('/storage/')) {
          return `${parsedUrl.pathname}${parsedUrl.search}${parsedUrl.hash}`;
        }
      } catch (_) {
        return imageUrl;
      }

      return imageUrl;
    },
    get filteredItemCount() {
      return this.menuItems.filter(item => this.filterMatch(item)).length;
    },
    async removeCartItem(index) {
      this.cartUpdating = true;
      try {
        const response = await fetch('{{ $activeCampaign ? route('user.campaigns.cart.remove', [$room, $activeCampaign, '__INDEX__']) : '' }}'.replace('__INDEX__', index), {
          method: 'DELETE',
          headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || '{{ __('room.campaign.error_generic') }}');
        this.cartItems = payload.data || [];
      } catch (error) {
        window.alert(error.message);
      } finally {
        this.cartUpdating = false;
      }
    },
    async clearCart() {
      this.cartUpdating = true;
      try {
        const response = await fetch('{{ $activeCampaign ? route('user.campaigns.cart.clear', [$room, $activeCampaign]) : '' }}', {
          method: 'DELETE',
          headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' }
        });
        const payload = await response.json();
        if (!response.ok) throw new Error(payload.message || '{{ __('room.campaign.error_generic') }}');
        this.cartItems = payload.data || [];
      } catch (error) {
        window.alert(error.message);
      } finally {
        this.cartUpdating = false;
      }
    },
    debounceSearch(value) {
      clearTimeout(this.searchTimer);
      this.searchTimer = setTimeout(() => { this.searchQuery = value; }, 300);
    },
    filterMatch(item) {
      if (this.selectedCategory !== 'all' && item.category !== this.selectedCategory) {
        return false;
      }
      if (this.withinBudgetOnly && this.maxBudget > 0 && this.minOrderablePrice(item) > this.maxBudget) {
        return false;
      }
      if (this.searchQuery.trim() !== '') {
        const q = this.normalizeText(this.searchQuery.trim());
        const name = this.normalizeText(item.name);
        const desc = this.normalizeText(item.description);
        return name.includes(q) || desc.includes(q);
      }
      return true;
    },
    minOrderablePrice(item) {
      // Giá thấp nhất có thể đặt được: giá gốc cộng phụ thu size nhỏ nhất (nếu có).
      const deltas = (item.sizes || []).map(size => Number(size.price_delta || 0));
      return Number(item.base_price || 0) + (deltas.length ? Math.min(...deltas) : 0);
    },
    normalizeText(text) {
      // Không phân biệt hoa thường và dấu tiếng Việt (đ/Đ được quy về d).
      return String(text || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'D')
        .toLowerCase();
    }
  }">

    @if($activeCampaign)
      <!-- 1. Active Campaign Top Banner -->
      <section class="bg-white border border-slate-200/80 rounded-xl shadow-2xs p-3.5 sm:p-4.5 relative overflow-hidden">
        <div class="absolute -right-16 -top-16 w-64 h-64 rounded-full bg-emerald-50 pointer-events-none blur-2xl"></div>

        <div class="relative z-10 space-y-3.5">
          @php
            $campTimeRemaining = $campaignStats['time_remaining'] ?? '';
            $campHasExpired = ($campaignStats['has_expired'] ?? false) || $campTimeRemaining === '00:00' || $campTimeRemaining === '00:00:00' || empty($campTimeRemaining);
          @endphp
          {{-- Mobile only (same as the dashboard card): code badge + countdown on one row at equal height, no logo. --}}
          <div class="sm:hidden space-y-1">
            <div class="flex items-center justify-between gap-2">
              <span class="inline-flex h-7 items-center px-2.5 rounded-lg bg-emerald-50 text-emerald-700 text-[10px] font-bold uppercase tracking-wider border border-emerald-200 whitespace-nowrap">
                {{ $activeCampaign->code ?: '—' }}
              </span>
              <div class="flex h-7 items-center gap-1.5 px-2.5 rounded-lg border {{ $campHasExpired ? 'bg-slate-100 border-slate-200 text-slate-600' : 'bg-rose-50/50 border-rose-200/80 text-rose-700' }} font-mono text-xs font-bold shrink-0 whitespace-nowrap">
                <span class="material-symbols-outlined text-[15px] {{ $campHasExpired ? '' : 'animate-pulse' }}">schedule</span>
                <span>{{ $campHasExpired ? __('room.header.countdown_closed') : $campTimeRemaining }}</span>
              </div>
            </div>
            <p class="text-right text-[10px] text-slate-400 font-medium">{{ __('room.campaign.auto_lock_notice') }}</p>
          </div>

          <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
            <!-- Restaurant Meta -->
            <div class="flex items-start sm:items-center gap-3">
              <div class="hidden sm:flex w-9 h-9 rounded-lg bg-emerald-50 text-[#006948] border border-emerald-100 items-center justify-center shrink-0 shadow-2xs">
                <span class="material-symbols-outlined text-[20px]">local_cafe</span>
              </div>
              <div>
                <div class="hidden sm:flex flex-wrap items-center gap-1.5">
                  <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[10px] font-bold uppercase tracking-wider border border-emerald-200">
                    {{ $activeCampaign->code ?: '—' }}
                  </span>
                </div>
                <h1 class="text-sm sm:text-base font-bold text-slate-900 tracking-tight mt-0.5">{{ $activeCampaign->name }}</h1>
                <p class="text-[11px] text-slate-500 flex flex-wrap items-center gap-x-1 gap-y-0.5 mt-0.5">
                  <span class="material-symbols-outlined text-[13px] text-[#006948]">storefront</span>
                  <span class="font-semibold text-slate-800">{{ $activeCampaign->restaurant }}</span>
                  <span class="text-slate-300">•</span>
                  <span>{{ __('room.dashboard.created_by', ['name' => $activeCampaign->creator?->name ?? __('global.common.admin')]) }}</span>
                  <span class="text-slate-300">•</span>
                  <span class="inline-flex items-center gap-0.5 font-semibold text-[#006948]" data-campaign-ordered-members>
                    <span class="material-symbols-outlined text-[13px]">group</span>
                    {{ trans_choice('room.campaign.ordered_members_count', $campaignStats['ordered_members'], ['count' => $campaignStats['ordered_members']]) }}
                  </span>
                </p>
              </div>
            </div>

            <!-- Countdown Timer Pill (mobile renders it in the row above) -->
            <div class="hidden sm:flex sm:flex-row lg:flex-col items-start lg:items-end gap-1 {{ $campHasExpired ? 'bg-slate-100 border-slate-200 text-slate-600' : 'bg-rose-50/50 border-rose-200/80 text-rose-700' }} border p-2.5 rounded-lg">
              <div class="flex items-center gap-1.5 font-mono text-xs font-bold {{ $campHasExpired ? 'text-slate-600' : 'text-rose-700' }}">
                <span class="material-symbols-outlined text-[15px] {{ $campHasExpired ? '' : 'animate-pulse' }}">schedule</span>
                <span>{{ $campHasExpired ? __('room.header.countdown_closed') : $campTimeRemaining }}</span>
              </div>
              <span class="text-[10px] text-slate-400 font-medium">{{ __('room.campaign.auto_lock_notice') }}</span>
            </div>
          </div>

          <!-- Policy Badges Strip -->
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 pt-2.5 border-t border-slate-100">
            <div class="p-2.5 rounded-lg bg-slate-50/70 border border-slate-100 flex items-center gap-2.5">
              <div class="w-7 h-7 rounded-md bg-emerald-50 text-[#006948] flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[16px]">savings</span>
              </div>
              <div>
                <span class="text-[10px] text-slate-400 block font-medium">{{ __('room.campaign.policy_sponsor_title') }}</span>
                <span class="text-xs font-bold text-slate-900">{{ __('room.campaign.sponsor_type_'.($activeCampaign->sponsor_type ?: 'none')) }}</span>
                @if ($campaignSponsors->isNotEmpty())
                  <div class="mt-1 flex flex-wrap gap-1">
                    @foreach ($campaignSponsors as $sponsor)
                      <span class="inline-flex items-center rounded-md bg-emerald-100 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-800"
                        title="{{ $sponsor['user_code'] }}">
                        {{ $sponsor['name'] }} · {{ __('room.campaign.sponsor_percentage', ['percentage' => (int) $sponsor['percentage']]) }}
                      </span>
                    @endforeach
                  </div>
                @endif
                @if ($activeCampaign->sponsor_description)
                  <span class="mt-0.5 block text-[10px] leading-relaxed text-slate-400">{{ $activeCampaign->sponsor_description }}</span>
                @endif
              </div>
            </div>
            <div class="p-2.5 rounded-lg bg-slate-50/70 border border-slate-100 flex items-center gap-2.5">
              <div class="w-7 h-7 rounded-md bg-emerald-50 text-[#006948] flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[16px]">price_check</span>
              </div>
              <div>
                <span class="text-[10px] text-slate-400 block font-medium">{{ __('room.campaign.max_product_budget_title') }}</span>
                <span class="text-xs font-bold text-slate-900">
                  {{ $activeCampaign->max_budget
                    ? __('room.campaign.max_product_budget_value', ['amount' => \App\Support\Helpers\FormatHelper::formatCurrency((int) $activeCampaign->max_budget)])
                    : __('room.campaign.unlimited_budget') }}
                </span>
              </div>
            </div>
            <div class="p-2.5 rounded-lg bg-slate-50/70 border border-slate-100 flex items-start gap-2.5">
              <div class="w-7 h-7 rounded-md bg-emerald-50 text-[#006948] flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[16px]">account_balance</span>
              </div>
              <div class="min-w-0">
                <span class="text-[10px] text-slate-400 block font-medium">{{ __('room.campaign.policy_payment_title') }}</span>
                <span class="text-xs font-bold text-slate-900">{{ __('room.campaign.policy_payment_val') }}@if($paymentAccountName ?? null) - {{ $paymentAccountName }}@endif</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Active User Order Alert Banner if placed -->
      @if($activeUserOrder)
        <div class="p-4 rounded-2xl bg-emerald-50 border border-emerald-200/80 text-emerald-900 text-xs sm:text-sm font-medium flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-xs">
          <div class="flex items-center gap-2.5">
            <span class="w-7 h-7 rounded-lg bg-emerald-100 text-[#006948] flex items-center justify-center shrink-0">
              <span class="material-symbols-outlined text-[18px]">check_circle</span>
            </span>
            <span>
              {{ __('room.campaign.cart_summary_title') }}: <strong class="text-[#006948]">{{ $activeUserOrder->items->pluck('item_name')->join(', ') }}</strong> ({{ \App\Support\Helpers\FormatHelper::formatCurrency($activeUserOrder->final_amount) }})
            </span>
          </div>
          <a href="{{ route('user.orders.index', $room->slug) }}" class="inline-flex items-center gap-1 font-bold text-[#006948] shrink-0">
            <span>{{ __('room.orders.order_details') }}</span>
            <span class="material-symbols-outlined text-[16px]">arrow_forward</span>
          </a>
        </div>
      @elseif($hasDeclined)
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 p-4 rounded-2xl border border-amber-200 bg-amber-50 text-amber-900 shadow-xs">
          <div class="flex items-center gap-2.5">
            <span class="w-7 h-7 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
              <span class="material-symbols-outlined text-[18px]">block</span>
            </span>
            <div>
              <p class="text-xs sm:text-sm font-bold">{{ __('room.campaign.declined') }}</p>
            </div>
          </div>
          @if($activeCampaign?->isOrderable())
            <div data-participation-form>
              <button type="button" @click="showRejoinModal = true" class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-[#006948] text-white text-xs font-bold hover:bg-[#005137] shadow-xs cursor-pointer transition-colors shrink-0">
                <span class="material-symbols-outlined text-[16px]">undo</span>
                <span>{{ __('room.campaign.rejoin') }}</span>
              </button>
            </div>
          @endif
        </div>
      @elseif($activeCampaign?->isOrderable())
        {{-- Floating action button: same 44px round shape as the support / go-to-top buttons, sitting above the cart button. --}}
        <div data-participation-form class="fixed right-6 top-[calc(50%-3.5rem)] z-30">
          <button type="button" @click="showDeclineModal = true" class="group flex h-11 min-w-11 items-center justify-center rounded-full border border-rose-200 bg-white px-2.5 text-xs font-bold text-rose-700 shadow-lg transition-all duration-200 hover:bg-rose-600 hover:text-white active:scale-95 focus:outline-none focus:ring-2 focus:ring-rose-400 focus:ring-offset-2 cursor-pointer" title="{{ __('room.campaign.decline') }}" aria-label="{{ __('room.campaign.decline') }}">
            <span class="material-symbols-outlined text-[22px] transition-transform duration-200 group-hover:rotate-90">close</span>
            <x-room.hover-label>{{ __('room.campaign.decline') }}</x-room.hover-label>
          </button>
        </div>
      @endif

      @if($activeCampaign?->isOpenForOrders() && $activeCampaign->isOrderingLocked())
        <div data-ordering-locked-banner class="flex items-start gap-2.5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
          <span class="material-symbols-outlined text-[20px]">lock</span>
          <div>
            <p class="font-bold">{{ __('room.campaign.ordering_locked_title') }}</p>
            <p class="mt-0.5 text-xs leading-relaxed text-amber-800">{{ __('room.campaign.ordering_locked') }}</p>
          </div>
        </div>
      @elseif(!$activeCampaign?->isOrderable() && !$activeUserOrder && !$hasDeclined)
        <div class="flex items-start gap-2.5 rounded-2xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
          <span class="material-symbols-outlined text-[20px]">event_busy</span>
          <div>
            <p class="font-bold">{{ __('room.campaign.ordering_closed_title') }}</p>
            <p class="mt-0.5 text-xs leading-relaxed text-amber-800">{{ __('room.campaign.ordering_closed') }}</p>
          </div>
        </div>
      @endif

      <!-- 2. Controls: Category Pills & Search Bar -->
      <section class="space-y-4">
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
          <!-- Category Pills & Dropdown (Show 3 categories + Dropdown for the rest) -->
          <div class="flex flex-wrap items-center gap-2 py-1">
            <button type="button" @click="selectedCategory = 'all'" class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all whitespace-nowrap cursor-pointer shrink-0" :class="selectedCategory === 'all' ? 'bg-[#006948] text-white shadow-2xs' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50'">
              {{ __('room.campaign.filter_all') }}
            </button>
            @foreach($categories->take(3) as $cat)
              <button type="button" @click="selectedCategory = '{{ $cat }}'" class="px-3.5 py-1.5 rounded-xl text-xs font-semibold transition-all whitespace-nowrap cursor-pointer shrink-0" :class="selectedCategory === '{{ $cat }}' ? 'bg-[#006948] text-white shadow-2xs' : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50'">
                {{ $cat }}
              </button>
            @endforeach

            @if($categories->count() > 3)
              @php
                $moreCategories = $categories->slice(3)->values();
              @endphp
              <div class="relative shrink-0" x-data="{ openMoreCats: false }" @click.outside="openMoreCats = false">
                <button type="button" 
                        @click="openMoreCats = !openMoreCats"
                        class="px-3 py-1.5 rounded-xl text-xs font-semibold transition-all whitespace-nowrap flex items-center gap-1 cursor-pointer"
                        :class="({{ Js::from($moreCategories->all()) }}).includes(selectedCategory) 
                          ? 'bg-[#006948] text-white shadow-2xs font-bold' 
                          : 'bg-white text-slate-700 border border-slate-200 hover:bg-slate-50'">
                  <span x-text="({{ Js::from($moreCategories->all()) }}).includes(selectedCategory) ? selectedCategory : '{{ __('room.campaign.more_categories_count', ['count' => $moreCategories->count()]) }}'"></span>
                  <span class="material-symbols-outlined text-[16px] transition-transform duration-200" :class="openMoreCats ? 'rotate-180' : ''">expand_more</span>
                </button>

                <div x-show="openMoreCats" 
                     x-cloak 
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="transform opacity-0 scale-95"
                     x-transition:enter-end="transform opacity-100 scale-100"
                     x-transition:leave="transition ease-in duration-75"
                     x-transition:leave-start="transform opacity-100 scale-100"
                     x-transition:leave-end="transform opacity-0 scale-95"
                     class="absolute left-0 top-full mt-1.5 z-50 min-w-[180px] max-h-60 overflow-y-auto rounded-xl bg-white border border-slate-200 shadow-2xl py-1">
                  @foreach($moreCategories as $cat)
                    <button type="button" 
                            @click="selectedCategory = '{{ $cat }}'; openMoreCats = false"
                            class="w-full text-left px-3.5 py-2 text-xs font-semibold transition-colors flex items-center justify-between gap-2 cursor-pointer"
                            :class="selectedCategory === '{{ $cat }}' ? 'bg-emerald-50 text-[#006948] font-bold' : 'text-slate-700 hover:bg-slate-50'">
                      <span class="truncate">{{ $cat }}</span>
                      <span x-show="selectedCategory === '{{ $cat }}'" class="material-symbols-outlined text-[16px] text-[#006948] shrink-0">check</span>
                    </button>
                  @endforeach
                </div>
              </div>
            @endif
          </div>

          <!-- Search Bar & Action Buttons -->
          <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 w-full sm:w-auto">
            <!-- Search bar (1 line on mobile) -->
            <div class="relative w-full sm:w-64 lg:w-72 order-1 sm:order-2">
              <span class="absolute left-0 top-0 bottom-0 flex w-10 items-center justify-center text-slate-400 pointer-events-none">
                <span class="material-symbols-outlined text-[18px]">search</span>
              </span>
              <input x-model="searchInput" @input="debounceSearch($event.target.value)" type="text" class="w-full pl-9 pr-8 h-10 bg-white border border-slate-200 rounded-xl text-xs text-slate-900 placeholder:text-slate-400 focus:border-[#006948] focus:ring-1 focus:ring-[#006948] outline-none transition-all" placeholder="{{ __('room.campaign.search_placeholder') }}">
              <button x-show="searchInput.length > 0" x-cloak type="button" @click="searchInput = ''; searchQuery = ''" class="absolute right-0 top-0 bottom-0 flex w-9 items-center justify-center text-slate-400 hover:text-slate-700 cursor-pointer">
                <span class="material-symbols-outlined text-[16px]">close</span>
              </button>
            </div>

            <!-- Button xem đơn cả phòng & Top món yêu thích (cùng 1 line trên mobile) -->
            <div class="grid grid-cols-2 gap-2 w-full sm:w-auto sm:flex sm:items-center order-2 sm:order-1">
              <button type="button" @click="openCampaignDetail({{ $activeCampaign->id }})" class="inline-flex h-10 items-center justify-center sm:justify-start gap-1.5 rounded-xl border border-sky-200 bg-sky-50 px-3 text-xs font-bold text-sky-800 transition-colors hover:bg-sky-100 whitespace-nowrap">
                <span class="material-symbols-outlined text-[17px] shrink-0">receipt_long</span>
                <span class="truncate">{{ __('room.campaign.view_room_orders_button') }}</span>
              </button>
              <button type="button" @click="openFavorites()" class="inline-flex h-10 items-center justify-center sm:justify-start gap-1.5 rounded-xl border border-amber-200 bg-amber-50 px-3 text-xs font-bold text-amber-800 transition-colors hover:bg-amber-100 whitespace-nowrap">
                <span class="material-symbols-outlined text-[17px] shrink-0">favorite</span>
                <span class="truncate">{{ __('room.campaign.favorite_items_button') }}</span>
              </button>
            </div>
          </div>
        </div>
        <div class="flex items-center justify-between gap-3">
          @if((int) ($activeCampaign->max_budget ?? 0) > 0)
            <!-- Budget Filter: chỉ hiện món có giá nằm trong trần ngân sách của chiến dịch -->
            <label class="inline-flex min-w-0 cursor-pointer items-center gap-2 text-xs font-medium text-slate-700 select-none">
              <input type="checkbox" x-model="withinBudgetOnly" class="h-4 w-4 shrink-0 cursor-pointer rounded border-slate-300 text-[#006948] focus:ring-[#006948]">
              <span>{{ __('room.campaign.budget_filter_label', ['amount' => \App\Support\Helpers\FormatHelper::formatCurrency((int) $activeCampaign->max_budget)]) }}</span>
            </label>
          @endif
          <!-- Menu display mode: grid (default) / list (compact rows: 1 per row on mobile, 2 from sm) -->
          <div class="ml-auto inline-flex shrink-0 rounded-xl border border-slate-200 bg-white p-0.5" role="group" aria-label="{{ __('room.campaign.menu_view_label') }}" data-menu-view-toggle>
            @foreach(['grid' => 'grid_view', 'list' => 'view_list'] as $mode => $icon)
              <button type="button" @click="setMenuView('{{ $mode }}')" data-menu-view="{{ $mode }}"
                      :aria-pressed="menuView === '{{ $mode }}' ? 'true' : 'false'"
                      title="{{ __('room.campaign.menu_view_'.$mode) }}" aria-label="{{ __('room.campaign.menu_view_'.$mode) }}"
                      class="flex h-8 w-8 items-center justify-center rounded-lg transition-colors cursor-pointer"
                      :class="menuView === '{{ $mode }}' ? 'bg-[#006948] text-white shadow-2xs' : 'text-slate-500 hover:bg-slate-50'">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">{{ $icon }}</span>
              </button>
            @endforeach
          </div>
        </div>

        <!-- Menu Items: grid (default) or list (1 compact item per row on mobile, 2 from sm) -->
        <div class="grid" data-menu-items :class="menuView === 'list' ? 'grid-cols-1 sm:grid-cols-2 gap-2' : 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3'">
          @forelse($activeCampaign->items as $item)
            <div x-show="filterMatch({{ json_encode($item) }})"
                 class="bg-white border border-slate-200/80 hover:border-emerald-300 rounded-xl flex flex-col justify-between transition-all duration-200 shadow-2xs hover:shadow-md hover:-translate-y-0.5"
                 :class="menuView === 'list' ? 'p-2' : 'p-3 sm:p-3.5'">
              <div :class="menuView === 'list' ? 'flex items-start gap-2' : ''">
                <div class="rounded-lg bg-slate-50 border border-slate-100 flex items-center justify-center text-[#006948] overflow-hidden relative"
                     :class="menuView === 'list' ? 'h-12 w-12 shrink-0' : 'w-full h-28 mb-2.5'">
                  <span class="material-symbols-outlined" :class="menuView === 'list' ? 'text-[22px]' : 'text-[32px]'">local_cafe</span>
                  <template x-if="itemImageUrl({{ Js::from($item) }})">
                    <img x-lazy-src="itemImageUrl({{ Js::from($item) }})"
                         :data-lightbox-src="itemImageUrl({{ Js::from($item) }})"
                         alt="{{ $item->name }}"
                         data-menu-item-image
                         data-lightbox="campaign-menu"
                         data-lightbox-title="{{ $item->name }}"
                         data-lightbox-price="{{ \App\Support\Helpers\FormatHelper::formatCurrency($item->base_price) }}"
                         data-lightbox-description="{{ $item->description ?: __('room.campaign.default_drink_desc') }}"
                         tabindex="0"
                         role="button"
                         aria-label="{{ __('room.campaign.view_item_image', ['name' => $item->name]) }}"
                         loading="lazy"
                         x-on:load="$el.hidden = false"
                         x-on:error="$el.hidden = true"
                         class="absolute inset-0 h-full w-full object-cover cursor-zoom-in">
                  </template>
                  @if($item->category)
                    <span x-show="menuView !== 'list'" class="absolute top-1.5 left-1.5 px-1.5 py-0.5 rounded-md bg-white/90 backdrop-blur-xs text-[10px] font-semibold text-slate-600 border border-slate-200/60 shadow-2xs">
                      {{ $item->category }}
                    </span>
                  @endif
                </div>
                <div class="min-w-0 flex-1">
                {{-- List mode: the price sits on the name line, aligned right; the action row only keeps the icon buttons. --}}
                <div class="flex items-baseline justify-between gap-2">
                  <h3 class="min-w-0 text-xs font-bold text-slate-900 leading-snug truncate" title="{{ $item->name }}">{{ $item->name }}</h3>
                  <span x-show="menuView === 'list'" x-cloak class="shrink-0 text-xs font-bold font-mono text-slate-900" data-list-price>{{ \App\Support\Helpers\FormatHelper::formatCurrency($item->base_price) }}</span>
                </div>
                @if($dishRating = $itemRatings[$item->id] ?? null)
                  {{-- Average stars the room gave this dish in earlier campaigns. --}}
                  <p class="mt-0.5 flex items-center gap-0.5 text-[11px] font-semibold text-amber-600" data-item-average="{{ $item->id }}"
                     title="{{ __('room.rating.average_title', ['average' => number_format($dishRating['average'], 1), 'count' => $dishRating['count']]) }}">
                    <span class="material-symbols-outlined text-[14px]" style="font-variation-settings:'FILL' 1" aria-hidden="true">star</span>
                    <span>{{ number_format($dishRating['average'], 1) }}</span>
                    <span class="font-normal text-slate-400">({{ trans_choice('room.rating.count', $dishRating['count'], ['count' => $dishRating['count']]) }})</span>
                  </p>
                @endif
                <p class="text-[11px] text-slate-500 mt-0.5 line-clamp-2 leading-relaxed">{{ $item->description ?: __('room.campaign.default_drink_desc') }}</p>
                </div>
              </div>

              <div class="border-t border-slate-100 flex items-center justify-between gap-2"
                   :class="menuView === 'list' ? 'mt-2 pt-2' : 'mt-3 pt-2.5'"
                   @if(!($canOrderCampaign && !$activeUserOrder)) x-show="menuView !== 'list'" @endif>
                <span x-show="menuView !== 'list'" class="text-xs sm:text-sm font-bold font-mono text-slate-900">{{ \App\Support\Helpers\FormatHelper::formatCurrency($item->base_price) }}</span>
                @if($canOrderCampaign && !$activeUserOrder)
                  <div class="flex items-center gap-1.5" :class="menuView === 'list' ? 'w-full justify-end' : ''">
                  <button type="button"
                          @click="openQuickOrder({{ json_encode($item) }})"
                          data-quick-order-button
                          title="{{ __('room.campaign.quick_order') }}" aria-label="{{ __('room.campaign.quick_order') }}"
                          class="h-7 bg-white hover:bg-emerald-50 border border-[#006948] text-[#006948] rounded-lg text-xs font-semibold flex items-center justify-center gap-1 transition-colors shadow-2xs cursor-pointer whitespace-nowrap"
                          :class="menuView === 'list' ? 'w-8' : 'px-2.5'">
                    <span class="material-symbols-outlined text-[14px]" aria-hidden="true">bolt</span>
                    <span x-show="menuView !== 'list'">{{ __('room.campaign.quick_order') }}</span>
                  </button>
                  <button type="button"
                          @click="openCustomize({{ json_encode($item) }})"
                          data-add-to-cart-button
                          title="{{ __('room.dashboard.select_drink') }}" aria-label="{{ __('room.dashboard.select_drink') }}"
                          class="h-7 bg-[#006948] hover:bg-[#005137] text-white rounded-lg text-xs font-semibold flex items-center justify-center gap-1 transition-colors shadow-2xs cursor-pointer"
                          :class="menuView === 'list' ? 'w-8' : 'px-2.5'">
                    <span class="material-symbols-outlined text-[14px]" aria-hidden="true">add</span>
                    <span x-show="menuView !== 'list'">{{ __('room.dashboard.select_drink') }}</span>
                  </button>
                  </div>
                @endif
              </div>
            </div>
          @empty
            <div x-show="menuItems.length === 0" class="col-span-full py-8 text-center text-slate-400 bg-white rounded-xl border border-dashed border-slate-200">
              <span class="material-symbols-outlined text-[28px] text-slate-300 mb-1.5">restaurant_menu</span>
              <p class="text-xs font-semibold text-slate-700">{{ __('room.campaign.menu_empty') }}</p>
            </div>
          @endforelse
        </div>
        <div x-show="menuItems.length > 0 && filteredItemCount === 0" x-cloak class="py-12 text-center text-slate-400 bg-white rounded-2xl border border-dashed border-slate-200">
          <span class="material-symbols-outlined text-[36px] text-slate-300 mb-2">search_off</span>
          <p class="text-xs font-semibold text-slate-700">{{ __('room.campaign.search_empty_title') }}</p>
          <p class="mt-1 text-xs text-slate-400">{{ __('room.campaign.search_empty_desc') }}</p>
        </div>
      </section>

      <template x-teleport="body">
        <div x-show="showFavoriteModal" x-cloak class="fixed inset-0 z-[115] flex min-h-[100dvh] items-center justify-center bg-slate-900/60 p-4 backdrop-blur-md" @click.self="showFavoriteModal = false">
          <div class="w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl" @click.stop>
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-[20px] text-amber-600">favorite</span>
                <h3 class="text-base font-bold text-slate-900">{{ __('room.campaign.favorite_items_title') }}</h3>
              </div>
              <button type="button" @click="showFavoriteModal = false" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100">
                <span class="material-symbols-outlined">close</span>
              </button>
            </div>
            <div class="mt-4 space-y-2">
              <div x-show="favoriteLoading" class="flex items-center justify-center gap-2 py-6 text-xs text-slate-500">
                <span class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
                <span>{{ __('room.campaign.favorite_items_loading') }}</span>
              </div>
              <div x-show="!favoriteLoading && favoriteError" x-cloak class="flex flex-col items-center gap-2 py-6 text-center">
                <p class="text-xs text-red-600">{{ __('room.campaign.favorite_items_error') }}</p>
                <button type="button" @click="openFavorites()" class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">{{ __('room.campaign.favorite_items_retry') }}</button>
              </div>
              <template x-for="item in favoriteItems" :key="item.rank">
                <div class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 px-3 py-2.5">
                  <div class="flex min-w-0 items-center gap-2.5">
                    <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center" :class="medalClasses[item.rank - 1] ?? 'text-slate-300'" role="img" :title="favoriteRankLabel.replace(':rank', item.rank)" :aria-label="favoriteRankLabel.replace(':rank', item.rank)">
                      <span class="material-symbols-outlined text-[24px]" style="font-variation-settings: 'FILL' 1;" aria-hidden="true">military_tech</span>
                    </span>
                    <span class="truncate text-sm font-semibold text-slate-800" x-text="item.name"></span>
                  </div>
                  <span class="shrink-0 text-sm font-bold text-[#006948]" x-text="'(' + item.quantity + ')'"></span>
                </div>
              </template>
              <div x-show="!favoriteLoading && !favoriteError && favoriteItems.length === 0" x-cloak class="flex flex-col items-center gap-2 py-6 text-center">
                <span class="flex h-12 w-12 items-center justify-center rounded-full bg-amber-50 text-amber-400">
                  <span class="material-symbols-outlined text-[26px]">heart_broken</span>
                </span>
                <p class="text-xs text-slate-500">{{ __('room.campaign.favorite_items_empty') }}</p>
              </div>
            </div>
          </div>
        </div>
      </template>

      <!-- Room Orders Modal: xem đơn của tất cả thành viên trong phòng cho chiến dịch này -->
      <x-room.campaign-orders-modal />

      <!-- Full-screen viewer for menu and cart item images (data-lightbox) -->
      <x-image-lightbox />

      @if($canOrderCampaign && !$activeUserOrder)
      <!-- 3. Modal Tùy chỉnh món (Item Customization Modal) -->
      <template x-teleport="body">
        <div x-show="showCustomModal" 
             x-cloak 
             class="fixed inset-0 z-[100] flex h-screen min-h-screen w-screen items-center justify-center overflow-y-auto bg-slate-900/60 p-0 backdrop-blur-md">
          <div @click.outside="closeCustomModal()"
               class="my-auto h-screen min-h-screen w-full overflow-hidden border border-slate-200 bg-white shadow-2xl sm:h-auto sm:min-h-0 sm:max-h-[calc(100dvh-2rem)] sm:max-w-lg sm:rounded-2xl animate-fadeIn">
            <!-- Modal Header -->
            <div class="p-5 bg-slate-50/80 border-b border-slate-100 flex items-center justify-between">
              <div class="flex items-center gap-2.5">
                <div class="relative w-9 h-9 overflow-hidden rounded-xl bg-emerald-50 text-[#006948] flex items-center justify-center shrink-0">
                  <span class="material-symbols-outlined text-[20px]">local_cafe</span>
                  <template x-if="itemImageUrl(selectedItem)">
                    <img x-lazy-src="itemImageUrl(selectedItem)"
                         :alt="selectedItem?.name || '{{ __('admin.item_image_alt') }}'"
                         loading="lazy"
                         x-on:load="$el.hidden = false"
                         x-on:error="$el.hidden = true"
                         class="absolute inset-0 h-full w-full object-cover">
                  </template>
                </div>
                <div>
                  <h3 class="text-sm sm:text-base font-bold text-slate-900" x-text="selectedItem?.name"></h3>
                  <span class="text-xs font-mono font-semibold text-slate-500" x-text="new Intl.NumberFormat('vi-VN').format(calculatedPrice) + 'đ'"></span>
                </div>
              </div>
              <button type="button" @click="closeCustomModal()" class="text-slate-400 hover:text-slate-700 p-1 rounded-lg hover:bg-slate-100 cursor-pointer">
                <span class="material-symbols-outlined text-[20px]">close</span>
              </button>
            </div>

            <!-- Modal Body Form -->
            <form @submit.prevent="addToCart()" class="p-5 sm:p-6 space-y-4 max-h-[calc(100dvh-5rem)] sm:max-h-[70vh] overflow-y-auto">
              @csrf
              <input type="hidden" name="campaign_item_id" :value="selectedItem?.id">
              <input type="hidden" name="campaign_item_size_id" :value="selectedSize?.id">

              <!-- Item description -->
              <p class="text-xs leading-relaxed text-slate-600 whitespace-pre-line" x-text="selectedItem?.description || {{ Js::from(__('room.campaign.default_drink_desc')) }}"></p>

              <!-- Size Options -->
              <template x-if="selectedItem?.sizes && selectedItem.sizes.length > 0">
                <div class="space-y-2">
                  <label class="text-xs font-bold uppercase tracking-wider text-slate-700 block">{{ __('room.campaign.size_label') }}</label>
                  <div class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                    <template x-for="size in selectedItem.sizes" :key="size.id">
                      <button type="button" 
                              @click="selectedSize = size"
                              class="p-2.5 rounded-xl border text-left transition-all cursor-pointer"
                              :class="selectedSize?.id === size.id ? 'border-[#006948] bg-emerald-50/50 text-[#006948] font-bold' : 'border-slate-200 hover:bg-slate-50 text-slate-700'">
                        <span class="text-xs block" x-text="'Size ' + size.name"></span>
                        <span class="text-[11px] font-mono opacity-80" x-text="size.price_delta > 0 ? '+' + new Intl.NumberFormat('vi-VN').format(size.price_delta) + 'đ' : '{{ __('room.campaign.standard_size') }}'"></span>
                      </button>
                    </template>
                  </div>
                </div>
              </template>

              <!-- Topping Checkboxes -->
              <template x-if="selectedItem?.toppings && selectedItem.toppings.length > 0">
                <div class="space-y-2">
                  <label class="text-xs font-bold uppercase tracking-wider text-slate-700 block">{{ __('room.campaign.topping_label') }}</label>
                  <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <template x-for="top in selectedItem.toppings" :key="top.id">
                      <label class="p-2.5 rounded-xl border border-slate-200 flex items-center justify-between gap-2 hover:bg-slate-50 cursor-pointer text-xs">
                        <div class="flex items-center gap-2">
                          <input type="checkbox"
                                 name="toppings[]"
                                 :value="top.id"
                                 :checked="selectedToppings.some(t => t.id === top.id)"
                                 @change="if ($event.target.checked) { selectedToppings.push(top); } else { selectedToppings = selectedToppings.filter(t => t.id !== top.id); }"
                                 class="rounded border-slate-300 text-[#006948] focus:ring-[#006948]">
                          <span x-text="top.name" class="font-medium text-slate-800"></span>
                        </div>
                        <span class="font-mono text-slate-500 text-[11px]" x-text="'+' + new Intl.NumberFormat('vi-VN').format(top.price) + 'đ'"></span>
                      </label>
                    </template>
                  </div>
                </div>
              </template>

              <!-- Quantity -->
              <div class="space-y-1.5">
                <label for="custom-item-quantity" class="text-xs font-bold uppercase tracking-wider text-slate-700 block">{{ __('room.campaign.quantity_label') }}</label>
                <div class="inline-flex items-center rounded-xl border border-slate-200 bg-slate-50 overflow-hidden">
                  <button type="button"
                          @click="quantity = Math.max(1, normalizeQuantity(quantity) - 1)"
                          :disabled="normalizeQuantity(quantity) <= 1"
                          class="w-10 h-10 flex items-center justify-center text-slate-600 hover:bg-slate-100 hover:text-[#006948] transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                          title="{{ __('room.campaign.quantity_decrease') }}" aria-label="{{ __('room.campaign.quantity_decrease') }}">
                    <span class="material-symbols-outlined text-[18px]">remove</span>
                  </button>
                  <input id="custom-item-quantity" name="quantity" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="2"
                         x-model="quantity"
                         x-effect="if (Number(quantity) > Math.max(1, maxQuantity)) quantity = Math.max(1, maxQuantity)"
                         @input="const digits = $event.target.value.replace(/\D/g, ''); const cap = Math.max(1, maxQuantity); $event.target.value = digits !== '' && Number(digits) > cap ? String(cap) : digits; quantity = $event.target.value"
                         @blur="quantity = normalizeQuantity(quantity); $event.target.value = quantity"
                         class="w-14 h-10 border-x border-slate-200 bg-white text-center text-sm font-bold font-mono text-slate-900 focus:outline-none focus:ring-1 focus:ring-inset focus:ring-[#006948]"
                         aria-label="{{ __('room.campaign.quantity_label') }}">
                  <button type="button"
                          @click="quantity = normalizeQuantity(normalizeQuantity(quantity) + 1)"
                          :disabled="normalizeQuantity(quantity) >= maxQuantity"
                          class="w-10 h-10 flex items-center justify-center text-slate-600 hover:bg-slate-100 hover:text-[#006948] transition-colors cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                          title="{{ __('room.campaign.quantity_increase') }}" aria-label="{{ __('room.campaign.quantity_increase') }}">
                    <span class="material-symbols-outlined text-[18px]">add</span>
                  </button>
                </div>
                <template x-if="maxBudget > 0 && maxQuantity >= 1 && maxQuantity < 99">
                  <p class="text-[11px] text-slate-500"
                     x-text="{{ Js::from(__('room.campaign.quantity_budget_hint', ['max' => ':max', 'limit' => \App\Support\Helpers\FormatHelper::formatCurrency((int) ($activeCampaign?->max_budget ?? 0))])) }}.replace(':max', maxQuantity)"></p>
                </template>
              </div>

              <!-- Special Note -->
              <div class="space-y-1.5">
                <label class="text-xs font-bold uppercase tracking-wider text-slate-700 block">{{ __('room.campaign.note_label') }}</label>
                <div class="flex flex-wrap gap-1.5">
                  <template x-for="sample in sampleNotes" :key="sample">
                    <button type="button" @click="note = note ? note + '; ' + sample : sample" class="rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-medium text-slate-600 transition-colors hover:border-emerald-300 hover:bg-emerald-50 hover:text-[#006948]" x-text="sample"></button>
                  </template>
                </div>
                <textarea name="note"
                          x-model="note"
                          rows="2"
                          class="w-full p-3 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder:text-slate-400 focus:bg-white focus:border-[#006948] focus:ring-1 focus:ring-[#006948] outline-none"
                          placeholder="{{ __('room.campaign.note_placeholder') }}"></textarea>
              </div>

              <!-- Self-paid (không sponsor) toggle -->
              <label class="flex items-start gap-2.5 rounded-xl border border-slate-200 bg-slate-50 p-3 cursor-pointer hover:border-amber-300 hover:bg-amber-50 transition-colors">
                <input type="checkbox" name="is_self_paid" x-model="isSelfPaid"
                       class="mt-0.5 rounded border-slate-300 text-amber-600 focus:ring-amber-500">
                <span class="text-xs">
                  <span class="block font-bold text-slate-800">{{ __('room.campaign.self_paid_label') }}</span>
                  <span class="block text-slate-500 mt-0.5">{{ __('room.campaign.self_paid_hint') }}</span>
                </span>
              </label>

              <!-- Budget Limit Alert -->
              <template x-if="isCustomItemExceeded">
                <div class="p-2.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs flex items-center gap-2 font-medium">
                  <span class="material-symbols-outlined text-[18px] text-rose-600 shrink-0">error</span>
                  <span>{{ __('room.campaign.custom_exceeds_budget_msg', ['limit' => \App\Support\Helpers\FormatHelper::formatCurrency((int) ($activeCampaign?->max_budget ?? 0))]) }}</span>
                </div>
              </template>

              <!-- Modal Footer CTA -->
              <div class="pt-3 border-t border-slate-100 flex items-center justify-between gap-3">
                <div class="text-left">
                  <span class="text-[11px] text-slate-400 block">{{ __('room.campaign.unit_price') }}</span>
                  <span class="text-sm sm:text-base font-bold font-mono" :class="isCustomItemExceeded ? 'text-rose-600' : 'text-[#006948]'" x-text="new Intl.NumberFormat('vi-VN').format(calculatedPrice) + 'đ'"></span>
                </div>
                <button type="submit" :disabled="cartSubmitting || isCustomItemExceeded"
                        class="px-5 h-10 bg-[#006948] hover:bg-[#005137] disabled:bg-slate-300 disabled:cursor-not-allowed text-white rounded-xl text-xs font-semibold flex items-center gap-1.5 transition-colors shadow-xs cursor-pointer">
                  <span x-show="!cartSubmitting" class="material-symbols-outlined text-[17px]">shopping_bag</span>
                  <span x-show="cartSubmitting" class="material-symbols-outlined animate-spin text-[17px]">progress_activity</span>
                  <span x-text="cartSubmitting ? '{{ __('global.common.loading') }}' : '{{ __('room.campaign.add_to_order') }}'"></span>
                </button>
              </div>
            </form>
          </div>
        </div>
      </template>
      @endif

      @if($canOrderCampaign && !$activeUserOrder)
      <!-- Fixed Cart and Confirmation Modal -->
      <button type="button" @click="showCartModal = true" data-campaign-cart-button class="group fixed right-6 top-1/2 z-30 flex h-11 min-w-11 items-center justify-center rounded-full bg-[#006948] px-[11px] text-xs font-bold text-white shadow-lg transition-all duration-200 hover:bg-[#005137] active:scale-95 focus:outline-none focus:ring-2 focus:ring-[#006948] focus:ring-offset-2 cursor-pointer" title="{{ __('room.campaign.cart_button') }}" aria-label="{{ __('room.campaign.cart_button') }}">
        <span class="material-symbols-outlined text-[22px]">shopping_cart</span>
        <x-room.hover-label>{{ __('room.campaign.cart_button') }}</x-room.hover-label>
        <span x-show="cartItems.length > 0" x-cloak class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-rose-600 px-1 text-[10px] font-bold leading-none text-white ring-2 ring-white" x-text="cartItems.length"></span>
      </button>

      <template x-teleport="body">
        <div x-show="showCartModal" x-cloak class="fixed inset-0 z-[100] flex min-h-[100dvh] items-center justify-center bg-slate-900/60 p-4 backdrop-blur-md" @click.self="showCartModal = false">
          <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl" @click.stop>
            <div class="flex items-center justify-between border-b border-slate-100 p-5">
              <h3 class="text-base font-bold text-slate-900">{{ __('room.campaign.cart_title') }}</h3>
              <div class="flex items-center gap-1">
                <button type="button" @click="clearCart()" :disabled="cartItems.length === 0 || cartUpdating" class="group relative rounded-lg p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600 disabled:opacity-40" title="{{ __('room.campaign.cart_clear') }}" aria-label="{{ __('room.campaign.cart_clear') }}">
                  <span x-show="!cartUpdating" class="material-symbols-outlined text-[18px]">delete_sweep</span>
                  <span x-show="cartUpdating" class="material-symbols-outlined animate-spin text-[18px]">progress_activity</span>
                  <span role="tooltip" class="pointer-events-none absolute right-0 top-full z-20 mt-2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-[10px] font-medium text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100">{{ __('room.campaign.cart_clear') }}</span>
                </button>
                <button type="button" @click="showCartModal = false" class="rounded-lg p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-700"><span class="material-symbols-outlined">close</span></button>
              </div>
            </div>
            <div class="max-h-[50vh] space-y-3 overflow-y-auto p-5">
              <div class="flex items-start gap-2 rounded-xl border border-violet-200 bg-violet-50/70 px-3 py-2.5 text-[11px] leading-relaxed text-violet-800">
                <span class="material-symbols-outlined mt-0.5 shrink-0 text-[16px] text-violet-600">info</span>
                <p>{{ __('room.campaign.cart_proxy_hint') }}</p>
              </div>
              <!-- Exceeded items alert banner -->
              <template x-if="hasExceededItems()">
                <div class="p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-xs flex items-start gap-2 shadow-2xs">
                  <span class="material-symbols-outlined text-[18px] text-rose-600 shrink-0 mt-0.5">warning</span>
                  <div>
                    <p class="font-bold">{{ __('room.campaign.cart_exceeded_banner_title') }}</p>
                    <p class="mt-0.5 text-[11px] text-rose-700 leading-relaxed">{{ __('room.campaign.cart_exceeded_banner_desc', ['limit' => \App\Support\Helpers\FormatHelper::formatCurrency((int) ($activeCampaign?->max_budget ?? 0))]) }}</p>
                  </div>
                </div>
              </template>

              <template x-if="cartItems.length === 0">
                <div class="flex flex-col items-center justify-center py-10 text-center">
                  <span class="material-symbols-outlined mb-2 text-[42px] text-slate-300">shopping_cart</span>
                  <p class="text-sm font-semibold text-slate-700">{{ __('room.campaign.cart_empty_title') }}</p>
                  <p class="mt-1 max-w-xs text-xs leading-relaxed text-slate-400">{{ __('room.campaign.cart_empty_desc') }}</p>
                </div>
              </template>
              <template x-for="(item, index) in cartItems" :key="item.item_id + '-' + item.size_id + '-' + item.note + '-' + index">
                <div class="flex flex-col gap-1.5 rounded-xl p-3 transition-colors"
                     :class="isItemExceeded(item) ? 'bg-rose-50/70 border border-rose-300 shadow-2xs' : 'bg-slate-50 border border-transparent'">
                  <div class="flex items-start justify-between gap-3">
                    <div class="relative flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-white text-[#006948]">
                      <span class="material-symbols-outlined text-[20px]">local_cafe</span>
                      <template x-if="itemImageUrl(item)">
                        <img x-lazy-src="itemImageUrl(item)"
                             :data-lightbox-src="itemImageUrl(item)"
                             :alt="item.item_name || '{{ __('admin.item_image_alt') }}'"
                             data-lightbox="campaign-cart"
                             :data-lightbox-title="item.item_name || ''"
                             :data-lightbox-description="(menuItems.find((menuItem) => menuItem.id === item.item_id)?.description) || {{ Js::from(__('room.campaign.default_drink_desc')) }}"
                             tabindex="0"
                             role="button"
                             :aria-label="{{ Js::from(__('room.campaign.view_item_image', ['name' => ':name'])) }}.replace(':name', item.item_name || '')"
                             loading="lazy"
                             x-on:load="$el.hidden = false"
                             x-on:error="$el.hidden = true"
                             class="absolute inset-0 h-full w-full object-cover cursor-zoom-in">
                      </template>
                    </div>
                    <div class="min-w-0 flex-1">
                      <p class="truncate text-sm font-bold text-slate-900" x-text="item.item_name"></p>
                      <p class="mt-0.5 text-[11px] text-slate-500" x-text="[item.size_name, ...(item.topping_names || [])].filter(Boolean).join(' · ')"></p>
                      <p class="mt-1 text-[11px] text-slate-500" x-show="item.note" x-text="item.note"></p>
                      <template x-if="item.proxy_user_code">
                        <span class="mt-1 inline-flex max-w-full items-center gap-1 rounded-full bg-violet-50 px-2 py-0.5 text-[10px] font-semibold text-violet-700">
                          <span class="material-symbols-outlined text-[12px]">person_add</span>
                          <span class="truncate" x-text="'{{ __('room.campaign.proxy_for') }} ' + (item.proxy_user_name || item.proxy_user_code)"></span>
                        </span>
                      </template>
                      <template x-if="item.is_self_paid">
                        <span class="mt-1 inline-flex max-w-full items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-semibold text-amber-700">
                          <span class="material-symbols-outlined text-[12px]">payments</span>
                          <span class="truncate">{{ __('room.campaign.self_paid_badge') }}</span>
                        </span>
                      </template>
                    </div>
                    <div class="flex shrink-0 items-center gap-1">
                      <span class="text-xs font-bold font-mono" :class="isItemExceeded(item) ? 'text-rose-600 font-bold' : 'text-[#006948]'" x-text="new Intl.NumberFormat('vi-VN').format(item.unit_price * item.quantity) + 'đ'"></span>
                      <template x-if="!item.is_self_paid">
                        <button type="button" @click="openProxyModal(index)" :disabled="cartUpdating" class="group relative rounded-md p-1 text-slate-400 hover:bg-violet-50 hover:text-violet-600 disabled:opacity-40" title="{{ __('room.campaign.proxy_edit') }}" aria-label="{{ __('room.campaign.proxy_edit') }}">
                          <span class="material-symbols-outlined text-[16px]">edit</span>
                          <span role="tooltip" class="pointer-events-none absolute right-0 top-full z-20 mt-2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-[10px] font-medium text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100">{{ __('room.campaign.proxy_edit') }}</span>
                        </button>
                      </template>
                      <button type="button" @click="removeCartItem(index)" :disabled="cartUpdating" class="group relative rounded-md p-1 text-slate-400 hover:bg-rose-50 hover:text-rose-600 disabled:opacity-40" title="{{ __('room.campaign.cart_remove_item') }}" aria-label="{{ __('room.campaign.cart_remove_item') }}">
                        <span x-show="!cartUpdating" class="material-symbols-outlined text-[16px]">delete</span>
                        <span x-show="cartUpdating" class="material-symbols-outlined animate-spin text-[16px]">progress_activity</span>
                        <span role="tooltip" class="pointer-events-none absolute right-0 top-full z-20 mt-2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-[10px] font-medium text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100">{{ __('room.campaign.cart_remove_item') }}</span>
                      </button>
                    </div>
                  </div>
                  <template x-if="isItemExceeded(item)">
                    <div class="mt-1 pt-1.5 border-t border-rose-200/80 flex items-center gap-1 text-[11px] font-semibold text-rose-600">
                      <span class="material-symbols-outlined text-[14px]">error</span>
                      <span>{{ __('room.campaign.item_exceeded_budget_error', ['limit' => \App\Support\Helpers\FormatHelper::formatCurrency((int) ($activeCampaign?->max_budget ?? 0))]) }}</span>
                    </div>
                  </template>
                </div>
              </template>
            </div>
            <div class="flex items-center justify-between border-t border-slate-100 p-5">
              <div>
                <span class="block text-[11px] text-slate-400">{{ __('room.campaign.cart_total') }}</span>
                <strong class="font-mono text-base text-[#006948]" x-text="new Intl.NumberFormat('vi-VN').format(cartTotal()) + 'đ'"></strong>
              </div>
              <button type="button" 
                      :disabled="cartItems.length === 0 || hasExceededItems()" 
                      @click="proceedToConfirm()" 
                      class="rounded-xl bg-[#006948] px-4 py-2.5 text-xs font-bold text-white hover:bg-[#005137] disabled:cursor-not-allowed disabled:opacity-50 flex items-center gap-1.5 transition-colors shadow-2xs cursor-pointer">
                <span class="material-symbols-outlined text-[16px]">shopping_cart_checkout</span>
                <span>{{ __('room.campaign.cart_confirm') }}</span>
                <span x-show="hasExceededItems()" class="material-symbols-outlined text-[14px] text-amber-300">warning</span>
              </button>
            </div>
          </div>
        </div>
      </template>

      <template x-teleport="body">
        <div x-show="showProxyModal" x-cloak class="fixed inset-0 z-[115] flex min-h-[100dvh] items-center justify-center bg-slate-900/50 p-4 backdrop-blur-[2px]" @click.self="showProxyModal = false">
          <div class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl animate-fadeIn" @click.stop>
            <!-- Close -->
            <button type="button" @click="showProxyModal = false" class="absolute right-4 top-4 flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-slate-400 transition hover:bg-slate-200 hover:text-slate-600" aria-label="{{ __('global.common.close') }}">
              <span class="material-symbols-outlined text-[18px]">close</span>
            </button>

            <div class="px-5 pb-5 pt-6 sm:px-6">
              <!-- Header -->
              <div class="text-center">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-violet-50 text-violet-600">
                  <span class="material-symbols-outlined text-[24px]">person_add</span>
                </div>
                <h3 class="text-base font-bold tracking-tight text-slate-900">{{ __('room.campaign.proxy_title') }}</h3>
                <p class="mt-1 text-xs leading-relaxed text-slate-500 sm:text-sm">{{ __('room.campaign.proxy_desc') }}</p>
              </div>

              <!-- Form -->
              <div class="mt-5 rounded-xl bg-slate-50 p-3 sm:p-4">
                <label class="mb-2 block text-xs font-semibold text-slate-700">{{ __('room.campaign.proxy_code_label') }}</label>
                <div class="flex gap-2">
                  <div class="relative min-w-0 flex-1">
                    <input x-model="proxyUserCode" @input="proxyUserLookupResult = null; proxyUserLookupError = null" @keydown.enter.prevent="lookupProxyUser()" type="text" maxlength="255" class="h-11 w-full rounded-xl border-2 border-slate-200 bg-white pl-3.5 pr-10 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-[#006948] focus:ring-4 focus:ring-[#006948]/10" placeholder="{{ __('room.campaign.proxy_code_placeholder') }}">
                    <button type="button" x-show="proxyUserCode" @click="proxyUserCode = ''; proxyUserLookupResult = null; proxyUserLookupError = null" class="absolute inset-y-0 right-2 my-auto flex h-7 w-7 items-center justify-center rounded-full text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600" aria-label="{{ __('room.campaign.proxy_code_clear') }}">
                      <span class="material-symbols-outlined text-[16px] leading-none">close</span>
                    </button>
                  </div>
                  <button type="button" @click="lookupProxyUser()" :disabled="proxyUserLookupLoading || !proxyUserCode.trim()" class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#006948] text-white shadow-sm transition hover:bg-[#005137] disabled:cursor-not-allowed disabled:opacity-50" title="{{ __('room.campaign.proxy_lookup') }}" aria-label="{{ __('room.campaign.proxy_lookup') }}">
                    <span x-show="!proxyUserLookupLoading" class="material-symbols-outlined text-[18px] leading-none">search</span>
                    <span x-show="proxyUserLookupLoading" class="material-symbols-outlined animate-spin text-[18px] leading-none">progress_activity</span>
                  </button>
                </div>
              </div>

              <!-- Feedback: gợi ý mặc định, hoặc lỗi/kết quả tìm thấy (loại trừ nhau) -->
              <div x-show="!proxyUserLookupError && !proxyUserLookupResult" class="mt-2 flex items-start gap-2 rounded-xl bg-sky-50 px-3 py-2.5 text-xs text-sky-700">
                <span class="material-symbols-outlined shrink-0 text-[16px]">info</span>
                <span>{{ __('room.campaign.proxy_code_hint') }}</span>
              </div>
              <div x-show="proxyUserLookupError" class="mt-2 flex items-start gap-2 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2.5 text-xs font-medium text-rose-700">
                <span class="material-symbols-outlined shrink-0 text-[16px]">error</span>
                <p x-text="proxyUserLookupError"></p>
              </div>
              <div x-show="proxyUserLookupResult" class="mt-2 flex items-start gap-2.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2.5 text-xs text-emerald-800">
                <span class="material-symbols-outlined shrink-0 text-[16px] text-emerald-600">verified_user</span>
                <div class="min-w-0">
                  <p class="font-bold" x-text="proxyUserLookupResult?.display_name || proxyUserLookupResult?.user_code"></p>
                  <p class="mt-0.5 truncate text-[11px] text-emerald-700" x-show="proxyUserLookupResult?.email" x-text="proxyUserLookupResult?.email"></p>
                  <p class="mt-0.5 truncate text-[11px] text-emerald-700" x-show="proxyUserLookupResult?.phone && !proxyUserLookupResult?.email" x-text="proxyUserLookupResult?.phone"></p>
                </div>
              </div>

              <!-- Footer -->
              <div class="mt-5 flex items-center justify-end gap-2 border-t border-slate-100 pt-4">
                <button type="button" @click="showProxyModal = false" class="rounded-xl px-4 py-2.5 text-xs font-semibold text-slate-500 transition hover:bg-slate-100 hover:text-slate-700">{{ __('global.common.cancel') }}</button>
                <button type="button" @click="saveProxyAssignment()" :disabled="cartUpdating || (proxyUserCode.trim() && !proxyUserLookupResult)" class="inline-flex items-center justify-center gap-1.5 rounded-xl bg-[#006948] px-4 py-2.5 text-xs font-bold text-white shadow-sm transition hover:bg-[#005137] disabled:cursor-not-allowed disabled:opacity-50">
                  <span x-show="!cartUpdating" class="material-symbols-outlined text-[16px]">save</span>
                  <span x-show="cartUpdating" class="material-symbols-outlined animate-spin text-[16px]">progress_activity</span>
                  {{ __('room.campaign.proxy_save') }}
                </button>
              </div>
            </div>
          </div>
        </div>
      </template>

      <template x-teleport="body">
        <div x-show="showConfirmModal" x-cloak class="fixed inset-0 z-[110] flex min-h-[100dvh] items-center justify-center bg-slate-900/70 p-4 backdrop-blur-md" @click.self="closeConfirmModal()">
          <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl" @click.stop>
            <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-[#006948]"><span class="material-symbols-outlined">fact_check</span></div>
            <h3 class="text-base font-bold text-slate-900">{{ __('room.campaign.confirm_title') }}</h3>
            <p class="mt-1 text-sm leading-relaxed text-slate-500" x-show="!quickOrderItem">{{ __('room.campaign.confirm_desc') }}</p>
            <p class="mt-1 text-sm leading-relaxed text-slate-500" x-show="quickOrderItem" x-cloak
               x-text="{{ Js::from(__('room.campaign.quick_order_confirm_desc', ['name' => ':name'])) }}.replace(':name', quickOrderItem?.item_name || '')"></p>

            <div class="mt-4 grid grid-cols-3 divide-x divide-slate-200 rounded-xl border border-slate-200 bg-slate-50">
              <div class="px-2 py-2.5 text-center">
                <p class="text-base font-bold text-slate-900" x-text="ownItemsCount"></p>
                <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ __('room.campaign.confirm_own_items') }}</p>
              </div>
              <div class="px-2 py-2.5 text-center">
                <p class="text-base font-bold" :class="proxyItemsCount > 0 ? 'text-violet-600' : 'text-slate-300'" x-text="proxyItemsCount"></p>
                <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ __('room.campaign.confirm_proxy_items') }}</p>
              </div>
              <div class="px-2 py-2.5 text-center">
                <p class="text-base font-bold" :class="selfPaidItemsCount > 0 ? 'text-amber-600' : 'text-slate-300'" x-text="selfPaidItemsCount"></p>
                <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ __('room.campaign.confirm_self_paid_items') }}</p>
              </div>
            </div>

            <div class="mt-5 flex justify-end gap-2">
              <button type="button" @click="closeConfirmModal()" class="rounded-xl px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-100">{{ __('global.common.cancel') }}</button>
              <button type="button" :disabled="cartSubmitting" @click="confirmCart()" class="inline-flex items-center gap-1.5 rounded-xl bg-[#006948] px-4 py-2.5 text-xs font-bold text-white hover:bg-[#005137] disabled:cursor-not-allowed disabled:opacity-50">
                <span x-show="!cartSubmitting" class="material-symbols-outlined text-[16px]">send</span>
                <span x-show="cartSubmitting" class="material-symbols-outlined animate-spin text-[16px]">progress_activity</span>
                <span x-text="cartSubmitting ? '{{ __('global.common.loading') }}' : '{{ __('room.campaign.confirm_order') }}'"></span>
              </button>
            </div>
          </div>
        </div>
      </template>
      @endif

      @if($activeCampaign?->isOrderable() && !$activeUserOrder)
      <!-- Modal Confirm Decline -->
      <template x-teleport="body">
        <div x-show="showDeclineModal" x-cloak class="fixed inset-0 z-[110] flex min-h-[100dvh] items-center justify-center bg-slate-900/70 p-4 backdrop-blur-md" @click.self="showDeclineModal = false">
          <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl animate-fadeIn" @click.stop>
            <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-rose-50 text-rose-600">
              <span class="material-symbols-outlined text-[24px]">block</span>
            </div>
            <h3 class="text-base font-bold text-slate-900">{{ __('room.campaign.decline_confirm_title') }}</h3>
            <p class="mt-1.5 text-xs sm:text-sm leading-relaxed text-slate-500">{{ __('room.campaign.decline_confirm_desc') }}</p>
            <div class="mt-6 flex justify-end gap-2">
              <button type="button" @click="showDeclineModal = false" :disabled="participationSubmitting" class="rounded-xl px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-100 cursor-pointer disabled:opacity-50">
                {{ __('global.common.cancel') }}
              </button>
              <button type="button" :disabled="participationSubmitting" @click="submitDecline()" class="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-4 py-2.5 text-xs font-bold text-white hover:bg-rose-700 disabled:cursor-not-allowed disabled:opacity-50 cursor-pointer shadow-xs">
                <span x-show="!participationSubmitting" class="material-symbols-outlined text-[16px]">check</span>
                <span x-show="participationSubmitting" class="material-symbols-outlined animate-spin text-[16px]">progress_activity</span>
                <span x-text="participationSubmitting ? '{{ __('global.common.loading') }}' : '{{ __('room.campaign.decline_confirm_btn') }}'"></span>
              </button>
            </div>
          </div>
        </div>
      </template>

      <!-- Modal Confirm Rejoin -->
      <template x-teleport="body">
        <div x-show="showRejoinModal" x-cloak class="fixed inset-0 z-[110] flex min-h-[100dvh] items-center justify-center bg-slate-900/70 p-4 backdrop-blur-md" @click.self="showRejoinModal = false">
          <div class="w-full max-w-sm rounded-2xl bg-white p-6 shadow-2xl animate-fadeIn" @click.stop>
            <div class="mb-4 flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-[#006948]">
              <span class="material-symbols-outlined text-[24px]">undo</span>
            </div>
            <h3 class="text-base font-bold text-slate-900">{{ __('room.campaign.rejoin_confirm_title') }}</h3>
            <p class="mt-1.5 text-xs sm:text-sm leading-relaxed text-slate-500">{{ __('room.campaign.rejoin_confirm_desc') }}</p>
            <div class="mt-6 flex justify-end gap-2">
              <button type="button" @click="showRejoinModal = false" :disabled="participationSubmitting" class="rounded-xl px-4 py-2.5 text-xs font-semibold text-slate-600 hover:bg-slate-100 cursor-pointer disabled:opacity-50">
                {{ __('global.common.cancel') }}
              </button>
              <button type="button" :disabled="participationSubmitting" @click="submitRejoin()" class="inline-flex items-center gap-1.5 rounded-xl bg-[#006948] px-4 py-2.5 text-xs font-bold text-white hover:bg-[#005137] disabled:cursor-not-allowed disabled:opacity-50 cursor-pointer shadow-xs">
                <span x-show="!participationSubmitting" class="material-symbols-outlined text-[16px]">check</span>
                <span x-show="participationSubmitting" class="material-symbols-outlined animate-spin text-[16px]">progress_activity</span>
                <span x-text="participationSubmitting ? '{{ __('global.common.loading') }}' : '{{ __('room.campaign.rejoin_confirm_btn') }}'"></span>
              </button>
            </div>
          </div>
        </div>
      </template>
      @endif
    @else
      <!-- Empty State when no campaign is open -->
      <section class="bg-white border border-slate-200/80 rounded-2xl p-8 sm:p-10 text-center shadow-xs">
        <div class="w-12 h-12 rounded-xl bg-slate-50 text-slate-400 border border-slate-200/80 mx-auto flex items-center justify-center mb-2.5">
          <span class="material-symbols-outlined text-[24px]">bedtime</span>
        </div>
        <h2 class="text-sm font-bold text-slate-900">{{ __('room.campaign.no_campaign_title') }}</h2>
        <p class="text-xs text-slate-500 max-w-sm mx-auto mt-1 leading-relaxed">
          {{ __('room.campaign.no_campaign_desc') }}
        </p>
      </section>
    @endif
  </div>
</x-room.layout>
