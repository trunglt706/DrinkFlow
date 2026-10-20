@props([
  'room' => null,
  'roomUser' => null,
  'user' => null,
  'activeTab' => 'overview',
  'unreadNotificationsCount' => null,
  'notifications' => null,
  'userRooms' => collect(),
])

<header class="sticky top-0 w-full z-40 bg-white border-b border-slate-200/80 shadow-2xs"
  x-data="{ showRoomDropdown: false, showLangDropdown: false, showNotifDropdown: false }">
  <!-- Tier 1: Main Global Header Bar -->
  <div class="h-14 border-b border-slate-100 bg-white">
    <div class="max-w-7xl mx-auto px-3 sm:px-6 h-full flex items-center justify-between gap-2 sm:gap-4">
      <!-- Left Brand & Room Selector -->
      <div class="flex items-center gap-1 sm:gap-3 min-w-0">
        <a class="flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-slate-900 transition-colors py-1 px-1.5 sm:px-2 rounded-lg hover:bg-slate-50 group shrink-0"
          href="{{ route('user.me.dashboard') }}" title="{{ __('room.header.back_to_portal') }}"
          aria-label="{{ __('room.header.back_to_portal') }}">
          <span
            class="material-symbols-outlined text-[18px] group-hover:-translate-x-0.5 transition-transform text-[#006948]">arrow_back</span>
          <span class="hidden sm:inline">{{ __('room.header.back_to_portal') }}</span>
        </a>
        <span class="h-4 w-px bg-slate-200 hidden sm:block"></span>

        <!-- Room Selector Dropdown -->
        <div class="relative min-w-0">
          <button @click="showRoomDropdown = !showRoomDropdown" @click.outside="showRoomDropdown = false" type="button"
            class="flex items-center gap-1.5 sm:gap-2 max-w-full px-2 sm:px-2.5 h-8 sm:h-auto sm:py-1 rounded-xl bg-slate-50 hover:bg-slate-100 border border-slate-200/70 text-slate-800 transition-all cursor-pointer">
            <span
              class="w-6 h-6 rounded-lg bg-emerald-50 text-[#006948] border border-emerald-300 ring-1 ring-emerald-500/20 hidden sm:flex items-center justify-center font-bold text-xs shrink-0 shadow-2xs">
              <span class="material-symbols-outlined text-[15px]">corporate_fare</span>
            </span>
            <span
              class="font-bold text-xs sm:text-sm text-slate-900 truncate min-w-0 max-w-[120px] sm:max-w-[200px]">{{ $room->name ?? __('global.common.room') }}</span>
            <span class="material-symbols-outlined text-[16px] text-slate-400 shrink-0"
              :class="{ 'rotate-180': showRoomDropdown }">expand_more</span>
          </button>

          <!-- Room Switch Dropdown Menu -->
          <div x-show="showRoomDropdown" x-cloak x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
            class="absolute left-0 mt-1.5 w-64 bg-white border border-slate-200 rounded-2xl shadow-xl py-2 z-50 overflow-hidden">
            <div class="px-3 py-1.5 text-[11px] font-semibold text-slate-400 uppercase tracking-wider">
              {{ __('room.header.switch_room') }}
            </div>
            @if(isset($userRooms) && count($userRooms) > 0)
              <div class="max-h-56 overflow-y-auto divide-y divide-slate-50">
                @foreach($userRooms as $r)
                  <a href="{{ route('user.dashboard', $r->slug ?? $r['slug']) }}"
                    class="flex items-center justify-between px-3 py-2 text-xs text-slate-700 hover:bg-emerald-50/60 hover:text-[#006948] transition-colors {{ ($room && ($r->id ?? $r['id']) == $room->id) ? 'bg-emerald-50 text-[#006948] font-semibold' : '' }}">
                    <span class="truncate">{{ $r->name ?? $r['name'] }}</span>
                    @if($room && ($r->id ?? $r['id']) == $room->id)
                      <span class="material-symbols-outlined text-[16px] text-[#006948]">check</span>
                    @endif
                  </a>
                @endforeach
              </div>
            @endif
          </div>
        </div>
      </div>

      <!-- Right Controls: Language, Notifications, User Code & Profile -->
      <div class="flex items-center gap-1 sm:gap-2 shrink-0">
        <!-- Language Switcher -->
        <div class="relative shrink-0">
          <button @click="showLangDropdown = !showLangDropdown" @click.outside="showLangDropdown = false" type="button"
            class="flex items-center gap-1 sm:gap-1.5 h-8 sm:h-9 px-2 sm:px-2.5 rounded-lg sm:rounded-xl text-[11px] sm:text-xs font-medium text-slate-600 hover:bg-slate-50 transition-colors duration-150 border border-slate-200 shadow-2xs cursor-pointer shrink-0"
            title="{{ __('global.header.language_select') }}">
            <span class="text-xs sm:text-sm leading-none">{{ $activeLocaleMeta['flag'] }}</span>
            <span
              class="font-semibold text-slate-800 text-[11px] sm:text-xs leading-none">{{ $activeLocaleMeta['code'] }}</span>
            <span class="material-symbols-outlined text-[14px] sm:text-[16px] text-slate-400">arrow_drop_down</span>
          </button>
          <div x-show="showLangDropdown" x-cloak x-transition
            class="absolute right-0 mt-1.5 w-36 bg-white border border-slate-200 rounded-xl shadow-lg py-1 z-50">
            @foreach($locales as $code => $info)
              <a href="{{ route('locale.switch', $code) }}"
                class="flex items-center justify-between px-3 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50 hover:text-[#006948] transition-colors {{ $currentLocale === $code ? 'bg-emerald-50 text-[#006948] font-semibold' : '' }}">
                <span class="flex items-center gap-1.5">
                  <span>{{ $info['flag'] }}</span>
                  <span>{{ $info['name'] }}</span>
                </span>
                @if($currentLocale === $code)
                  <span class="material-symbols-outlined text-[14px]">check</span>
                @endif
              </a>
            @endforeach
          </div>
        </div>

        <x-global.desktop-notify-toggle />

        <!-- Notifications Action & Dropdown -->
        <div class="relative shrink-0">
          <button @click="showNotifDropdown = !showNotifDropdown; showRoomDropdown = false; showLangDropdown = false"
            @click.outside="showNotifDropdown = false" type="button"
            class="relative w-8 h-8 sm:w-9 sm:h-9 flex items-center justify-center rounded-lg sm:rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 hover:text-slate-900 transition-colors shadow-2xs cursor-pointer shrink-0"
            id="room-notification-btn" title="{{ __('room.header.notifications') }}"
            aria-label="{{ __('room.header.notifications') }}">
            <span class="material-symbols-outlined text-[17px] sm:text-[19px]">notifications</span>
            @if($unreadNotificationsCount > 0)
              <span data-user-notification-badge
                class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-rose-500 text-white text-[10px] font-bold pointer-events-none ring-2 ring-white"
                id="room-notif-badge">
                {{ $unreadNotificationsCount > 9 ? '9+' : $unreadNotificationsCount }}
              </span>
            @endif
          </button>

          <!-- Notification Dropdown Menu (Responsive: fixed centered on mobile, absolute on desktop) -->
          <div x-show="showNotifDropdown" x-cloak x-transition:enter="transition ease-out duration-100"
            x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100"
            class="fixed sm:absolute left-3 right-3 sm:left-auto sm:right-0 top-14 sm:top-12 z-50 sm:w-96 bg-white rounded-2xl shadow-xl border border-slate-200/80 overflow-hidden"
            id="room-notification-dropdown">
            <div class="p-3.5 px-4 bg-slate-50/70 border-b border-slate-100 flex items-center justify-between">
              <div class="flex items-center gap-2">
                <h3 class="text-sm font-semibold text-slate-900">{{ __('global.header.notifications') }}</h3>
                @if($unreadNotificationsCount > 0)
                  <span data-user-notification-new-badge data-template="{{ __('global.header.new_badge', ['count' => ':count']) }}"
                    class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold border border-emerald-200/60">{{ __('global.header.new_badge', ['count' => $unreadNotificationsCount]) }}</span>
                @endif
              </div>
              <button class="text-xs font-medium text-[#006948] hover:text-[#047857] transition-colors cursor-pointer"
                id="room-mark-all-read-btn" type="button"
                data-read-all-url="{{ route('user.me.notifications.read-all') }}"
                data-read-text="{{ __('global.header.all_read') }}">
                {{ __('global.header.mark_all_read') }}
              </button>
            </div>
            <div class="divide-y divide-slate-100 max-h-[380px] overflow-y-auto">
              @forelse($notifications as $notif)
                @php
                  $notificationPresentation = $notificationPresentations[$notif->getKey()] ?? ['title' => '', 'body' => '', 'icon' => 'notifications', 'link' => null];
                @endphp
                <div data-header-notification-id="{{ $notif->id }}"
                  data-read-url="{{ route('user.notifications.read', $notif) }}"
                  @if(!empty($notificationPresentation['link'])) data-notification-link="{{ $notificationPresentation['link'] }}" @endif
                  data-unread="{{ is_null($notif->read_at) ? '1' : '0' }}"
                  class="p-3.5 flex items-start gap-3 cursor-pointer hover:bg-slate-50/80 transition-colors {{ is_null($notif->read_at) ? 'bg-emerald-50/20' : '' }}">
                  <span
                    class="w-8 h-8 rounded-full bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0 mt-0.5 border border-emerald-100">
                    <span class="material-symbols-outlined text-[17px]">
                      {{ $notificationPresentation['icon'] }}
                    </span>
                  </span>
                  <div class="flex-1 min-w-0">
                    <p class="text-xs font-semibold text-slate-800">{{ $notificationPresentation['title'] }}</p>
                    <p class="text-xs text-slate-500 mt-0.5 line-clamp-2 [overflow-wrap:anywhere]">{{ $notificationPresentation['body'] }}</p>
                    <div class="flex items-center justify-between gap-2 mt-1">
                      <span class="text-[11px] text-slate-400 flex items-center gap-1">
                        <span class="material-symbols-outlined text-[12px]">schedule</span>
                        {{ $notif->created_at->diffForHumans() }}
                      </span>
                      @if(!empty($notificationPresentation['link']))
                        <a href="{{ $notificationPresentation['link'] }}" class="text-[11px] font-semibold text-[#006948] hover:underline shrink-0">
                          {{ __('global.notifications.view_detail') }}
                        </a>
                      @endif
                    </div>
                  </div>
                  @if(is_null($notif->read_at))
                    <span data-header-notification-dot class="w-2 h-2 rounded-full bg-emerald-600 shrink-0 mt-2"></span>
                  @endif
                </div>
              @empty
                <div class="p-6 text-center text-slate-500 text-xs">
                  <span class="material-symbols-outlined text-slate-300 text-[26px] mb-1.5 block">notifications_off</span>
                  {{ __('global.header.no_notifications') }}
                </div>
              @endforelse
            </div>
            <div class="p-2.5 bg-slate-50/60 border-t border-slate-100 text-center">
              <a class="text-xs font-medium text-[#006948] hover:text-[#047857] inline-flex items-center gap-1"
                href="{{ $room ? route('user.rooms.notifications', $room->slug) : route('user.me.notifications') }}">
                {{ __('global.header.view_all_notifications') }}
                <span class="material-symbols-outlined text-[14px]">chevron_right</span>
              </a>
            </div>
          </div>
        </div>

        <!-- User Profile Pill -->
        <a href="{{ $room ? route('user.rooms.profile', $room->slug) : '#' }}"
          class="relative flex items-center gap-1 sm:gap-2 pl-1 sm:pl-2 shrink-0 rounded-xl hover:bg-slate-100/60 transition-colors"
          title="{{ __('global.header.profile_menu') }}" aria-label="{{ __('global.header.profile_menu') }}">
          <x-avatar :user="$user" size="sm" class="border-2 border-white ring-2 ring-[#006948]/30 shadow-xs" :alt="$user->name ?? __('global.common.user')" />
          <span class="hidden lg:flex flex-col text-left">
            <span class="text-xs font-semibold text-slate-800 truncate max-w-[130px]">
              {{ $user->name ?? __('global.common.user') }}
            </span>
            @if(!empty($user?->email))
              <span class="text-[10px] font-medium text-slate-500 truncate max-w-[130px]"
                title="{{ $user->email }}">
                {{ $user->email }}
              </span>
            @endif
          </span>
        </a>
      </div>
    </div>
  </div>

  <!-- Tier 2: Navigation Tabs Bar -->
  <div class="h-11 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 h-full flex items-center">
      <nav data-room-tabs class="flex items-center h-full gap-1 overflow-x-auto no-scrollbar">
        <!-- Tab 1: Tổng quan -->
        <a href="{{ $room ? route('user.dashboard', $room->slug) : '#' }}"
          @if ($activeTab === 'overview') aria-current="page" @endif
          class="h-full px-3.5 inline-flex items-center gap-1.5 text-xs font-semibold transition-all border-b-2 whitespace-nowrap {{ $activeTab === 'overview' ? 'border-[#006948] text-[#006948] bg-emerald-50/40' : 'border-transparent text-slate-600 hover:text-slate-900 hover:bg-slate-50' }}">
          <span class="material-symbols-outlined text-[16px]">dashboard</span>
          <span>{{ __('room.nav.overview') }}</span>
        </a>

        <!-- Tab 2: Chiến dịch & Menu -->
        @if($hasActiveCampaign)
          <a href="{{ $room ? route('user.campaigns.index', $room->slug) : '#' }}"
            @if ($activeTab === 'campaigns') aria-current="page" @endif
            class="h-full px-3.5 inline-flex items-center gap-1.5 text-xs font-semibold transition-all border-b-2 whitespace-nowrap {{ $activeTab === 'campaigns' ? 'border-[#006948] text-[#006948] bg-emerald-50/40' : 'border-transparent text-slate-600 hover:text-slate-900 hover:bg-slate-50' }} {{ $hasActiveCampaign ? 'bg-amber-50 text-amber-700' : '' }}">
            <span
              class="material-symbols-outlined text-[16px] {{ $hasActiveCampaign ? 'text-amber-600 animate-pulse' : '' }}">restaurant_menu</span>
            <span>{{ __('room.nav.campaign_menu') }}</span>
            <span class="inline-flex h-2 w-2 rounded-full bg-amber-500 animate-pulse"
              title="{{ __('room.header.active_campaign') }}" aria-label="{{ __('room.header.active_campaign') }}"></span>
          </a>
        @endif

        <!-- Tab 3: Đơn hàng của tôi (chỉ hiện khi thành viên có đơn trong chiến dịch đang live) -->
        @if($showMyOrdersTab ?? false)
          <a href="{{ $room ? route('user.orders.index', $room->slug) : '#' }}"
            @if ($activeTab === 'orders') aria-current="page" @endif
            class="h-full px-3.5 inline-flex items-center gap-1.5 text-xs font-semibold transition-all border-b-2 whitespace-nowrap {{ $activeTab === 'orders' ? 'border-[#006948] text-[#006948] bg-emerald-50/40' : 'border-transparent text-slate-600 hover:text-slate-900 hover:bg-slate-50' }}">
            <span class="material-symbols-outlined text-[16px]">receipt_long</span>
            <span>{{ __('room.nav.my_orders') }}</span>
          </a>
        @endif

        <!-- Tab 4: Thanh toán & Nợ -->
        <a href="{{ $room ? route('user.debts.index', $room->slug) : '#' }}"
          @if ($activeTab === 'debts') aria-current="page" @endif
          class="h-full px-3.5 inline-flex items-center gap-1.5 text-xs font-semibold transition-all border-b-2 whitespace-nowrap {{ $activeTab === 'debts' ? 'border-[#006948] text-[#006948] bg-emerald-50/40' : 'border-transparent text-slate-600 hover:text-slate-900 hover:bg-slate-50' }}">
          <span class="material-symbols-outlined text-[16px]">account_balance_wallet</span>
          <span>{{ __('room.nav.payment_debts') }}</span>
          @if($unpaidDebtCount > 0)
            <span
              class="inline-flex min-w-5 h-5 items-center justify-center rounded-full bg-red-600 px-1.5 text-[10px] font-bold text-white">{{ $unpaidDebtCount }}</span>
          @endif
        </a>

        <!-- Tab 5: Thống kê Room -->
        <a href="{{ $room ? route('user.analytics.room', $room->slug) : '#' }}"
          @if ($activeTab === 'analytics') aria-current="page" @endif
          class="h-full px-3.5 inline-flex items-center gap-1.5 text-xs font-semibold transition-all border-b-2 whitespace-nowrap {{ $activeTab === 'analytics' ? 'border-[#006948] text-[#006948] bg-emerald-50/40' : 'border-transparent text-slate-600 hover:text-slate-900 hover:bg-slate-50' }}">
          <span class="material-symbols-outlined text-[16px]">bar_chart</span>
          <span>{{ __('room.nav.room_analytics') }}</span>
        </a>

        <!-- Tab 6: Thông báo -->
        <a href="{{ $room ? route('user.rooms.notifications', $room->slug) : '#' }}"
          @if ($activeTab === 'notifications') aria-current="page" @endif
          class="h-full px-3.5 inline-flex items-center gap-1.5 text-xs font-semibold transition-all border-b-2 whitespace-nowrap {{ $activeTab === 'notifications' ? 'border-[#006948] text-[#006948] bg-emerald-50/40' : 'border-transparent text-slate-600 hover:text-slate-900 hover:bg-slate-50' }}">
          <span class="material-symbols-outlined text-[16px]">notifications</span>
          <span>{{ __('room.nav.notifications') }}</span>
        </a>

        <!-- Tab: Thành tích (TOP 1-3 sponsor medals of the year) -->
        <a href="{{ $room ? route('user.rooms.achievements', $room->slug) : '#' }}"
          @if ($activeTab === 'achievements') aria-current="page" @endif data-room-tab-achievements
          class="h-full px-3.5 inline-flex items-center gap-1.5 text-xs font-semibold transition-all border-b-2 whitespace-nowrap {{ $activeTab === 'achievements' ? 'border-[#006948] text-[#006948] bg-emerald-50/40' : 'border-transparent text-slate-600 hover:text-slate-900 hover:bg-slate-50' }}">
          <span class="material-symbols-outlined text-[16px]">emoji_events</span>
          <span>{{ __('room.nav.achievements') }}</span>
        </a>

        <!-- Tab 7: Hồ sơ -->
        <a href="{{ $room ? route('user.rooms.profile', $room->slug) : '#' }}"
          @if ($activeTab === 'profile') aria-current="page" @endif
          class="h-full px-3.5 inline-flex items-center gap-1.5 text-xs font-semibold transition-all border-b-2 whitespace-nowrap {{ $activeTab === 'profile' ? 'border-[#006948] text-[#006948] bg-emerald-50/40' : 'border-transparent text-slate-600 hover:text-slate-900 hover:bg-slate-50' }}">
          <span class="material-symbols-outlined text-[16px]">person</span>
          <span>{{ __('room.nav.profile') }}</span>
        </a>

        <!-- Tab 8: Hướng dẫn sử dụng -->
        <a href="{{ $room ? route('user.rooms.guides', $room->slug) : '#' }}"
          @if ($activeTab === 'guides') aria-current="page" @endif
          class="h-full px-3.5 inline-flex items-center gap-1.5 text-xs font-semibold transition-all border-b-2 whitespace-nowrap {{ $activeTab === 'guides' ? 'border-[#006948] text-[#006948] bg-emerald-50/40' : 'border-transparent text-slate-600 hover:text-slate-900 hover:bg-slate-50' }}">
          <span class="material-symbols-outlined text-[16px]">menu_book</span>
          <span>{{ __('room.nav.user_guide') }}</span>
        </a>
      </nav>
    </div>
  </div>
</header>