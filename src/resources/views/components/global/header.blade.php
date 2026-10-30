@props([
  'user',
  'activeTab' => 'overview',
  'breadcrumbs' => [],
  'unreadNotificationsCount' => 0,
  'notifications' => collect(),
])

@php
  $tabClasses = function (bool $isActive) {
    return $isActive
      ? 'border-b-2 border-[#006948] text-[#006948] text-xs sm:text-sm font-semibold pb-2.5 sm:pb-3 whitespace-nowrap flex items-center gap-1.5 shrink-0 transition-colors'
      : 'text-slate-600 hover:text-slate-900 font-medium text-xs sm:text-sm pb-2.5 sm:pb-3 transition-colors whitespace-nowrap border-b-2 border-transparent hover:border-slate-300 flex items-center gap-1.5 shrink-0';
  };

  $currentLocale = app()->getLocale();
  $activeLocaleMeta = $locales[$currentLocale] ?? $locales['vi'];

  // Menus hidden for now. The markup is kept: remove an entry here to show that menu again.
  $hiddenMenus = ['rooms', 'orders', 'payments', 'statistics'];
  $showMenu = static fn (string $menu): bool => ! in_array($menu, $hiddenMenus, true);

  $hasRooms = $user !== null && $user->roomUsers()->exists();
  $hasOrders = $hasRooms
    && \App\Models\Order::query()
      ->whereIn('room_user_id', $user->roomUsers()->select('id'))
      ->exists();
  $unreadNotificationsCount = $unreadNotificationsCount ?? ($user ? $user->notifications()->whereNull('read_at')->count() : 0);
  $notifications = ($notifications && $notifications->isNotEmpty())
    ? $notifications->filter(fn($n) => is_null($n->read_at))
    : ($user ? $user->notifications()->whereNull('read_at')->latest()->take(5)->get() : collect());
@endphp

<!-- Top Navigation Bar (2 Tầng chuẩn hệ thống, responsive mobile tối ưu) -->
<header class="bg-white/95 backdrop-blur-md border-b border-slate-200/80 shadow-2xs w-full sticky top-0 z-30">
  <div class="w-full max-w-[1200px] mx-auto px-3 sm:px-6 flex flex-col">
    <!-- Tầng 1: Upper Section (Logo, Actions, Profile SSO) -->
    <div class="flex items-center justify-between h-14 sm:h-16 gap-2 sm:gap-4">
      <!-- Left: Logo DrinkFlow -->
      <div class="flex items-center shrink-0">
        <a class="flex items-center gap-2 sm:gap-2.5 group shrink-0" href="{{ route('user.me.dashboard') }}">
          <span
            class="w-8 h-8 rounded-xl flex items-center justify-center text-white shadow-xs shrink-0 transition-transform duration-150 group-hover:scale-105"
            style="background: linear-gradient(135deg, #006948 0%, #047857 100%); background-color: #006948; border: 1px solid #005137; box-shadow: 0 1px 2px rgba(0,0,0,0.08);">
            <svg class="w-4.5 h-4.5 text-white fill-current" viewBox="0 0 24 24" aria-hidden="true"
              style="width: 18px; height: 18px; fill: #ffffff; color: #ffffff;">
              <path
                d="M20 3H4v10c0 2.21 1.79 4 4 4h6c2.21 0 4-1.79 4-4v-3h2c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 5h-2V5h2v3zM4 19h16v2H4z" />
            </svg>
          </span>
          <span class="hidden min-[360px]:inline text-lg sm:text-xl font-bold text-[#006948] tracking-tight shrink-0">DrinkFlow</span>
        </a>
      </div>

      <!-- Right: Actions & User Dropdown -->
      <div class="flex items-center gap-1 sm:gap-2 shrink-0">
        <!-- Language Selector Dropdown -->
        <div class="relative shrink-0" id="global-lang-selector">
          <button type="button" id="global-lang-btn" aria-haspopup="true" aria-expanded="false"
            class="flex items-center gap-1 sm:gap-1.5 h-8 sm:h-9 px-2 sm:px-2.5 rounded-lg sm:rounded-xl text-[11px] sm:text-xs font-medium text-slate-600 hover:bg-slate-50 transition-colors duration-150 border border-slate-200 shadow-2xs cursor-pointer shrink-0"
            title="{{ __('global.header.language_select') }}">
            <span class="text-xs sm:text-sm leading-none">{{ $activeLocaleMeta['flag'] }}</span>
            <span
              class="font-semibold text-slate-800 text-[11px] sm:text-xs leading-none">{{ $activeLocaleMeta['code'] }}</span>
            <span class="material-symbols-outlined text-[14px] sm:text-[16px] text-slate-400">arrow_drop_down</span>
          </button>

          <!-- Language Dropdown Menu -->
          <div id="global-lang-menu"
            class="hidden absolute right-0 top-11 sm:top-12 w-36 bg-white rounded-xl shadow-xl border border-slate-200/80 py-1.5 z-50">
            @foreach($locales as $code => $meta)
              <a href="{{ route('locale.switch', $code) }}"
                class="flex items-center justify-between px-3 py-2 text-xs text-slate-700 hover:bg-emerald-50 hover:text-[#006948] transition-colors {{ $currentLocale === $code ? 'font-semibold text-[#006948] bg-emerald-50/50' : '' }}">
                <div class="flex items-center gap-2">
                  <span>{{ $meta['flag'] }}</span>
                  <span>{{ $meta['name'] }}</span>
                </div>
                @if($currentLocale === $code)
                  <span class="material-symbols-outlined text-[16px] text-[#006948]">check</span>
                @endif
              </a>
            @endforeach
          </div>
        </div>

        <x-global.desktop-notify-toggle />

        <!-- Notification Action -->
        <div class="relative shrink-0">
          <button
            class="w-8 h-8 sm:w-9 sm:h-9 flex items-center justify-center rounded-lg sm:rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-600 hover:text-slate-900 transition-colors shadow-2xs cursor-pointer shrink-0"
            id="global-notification-btn" title="{{ __('global.header.notifications') }}"
            aria-label="{{ __('global.header.notifications') }}">
            <span class="material-symbols-outlined text-[17px] sm:text-[19px]">notifications</span>
          </button>
          @if($unreadNotificationsCount > 0)
            <span data-user-notification-badge
              class="absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full bg-rose-500 text-white text-[10px] font-bold pointer-events-none ring-2 ring-white"
              id="global-notif-badge">
              {{ $unreadNotificationsCount }}
            </span>
          @endif

          <!-- Notification Dropdown Menu (Responsive: fixed centered on mobile, absolute on desktop) -->
          <div
            class="hidden fixed sm:absolute left-3 right-3 sm:left-auto sm:right-0 top-14 sm:top-12 z-50 sm:w-96 bg-white rounded-2xl shadow-xl border border-slate-200/80 overflow-hidden"
            id="global-notification-dropdown">
            <div class="p-3.5 px-4 bg-slate-50/70 border-b border-slate-100 flex items-center justify-between">
              <div class="flex items-center gap-2">
                <h3 class="text-sm font-semibold text-slate-900">{{ __('global.header.notifications') }}</h3>
                @if($unreadNotificationsCount > 0)
                  <span data-user-notification-new-badge data-template="{{ __('global.header.new_badge', ['count' => ':count']) }}"
                    class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-semibold border border-emerald-200/60">{{ __('global.header.new_badge', ['count' => $unreadNotificationsCount]) }}</span>
                @endif
              </div>
              <button class="text-xs font-medium text-[#006948] hover:text-[#047857] transition-colors cursor-pointer"
                id="global-mark-all-read-btn" type="button"
                data-read-all-url="{{ route('user.me.notifications.read-all') }}"
                data-read-text="{{ __('global.header.all_read') }}">
                {{ __('global.header.mark_all_read') }}
              </button>
            </div>
            <div class="divide-y divide-slate-100 max-h-[380px] overflow-y-auto">
              @forelse($notifications as $notif)
                @php
                  $notificationPresentation = app(\App\Services\Notification\NotificationPresentationService::class)->present($notif);
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
                href="{{ route('user.me.notifications') }}">
                {{ __('global.header.view_all_notifications') }}
                <span class="material-symbols-outlined text-[14px]">chevron_right</span>
              </a>
            </div>
          </div>
        </div>

        <!-- User Avatar & Dropdown Anchor -->
        <div class="relative flex items-center pl-1 sm:pl-2 border-l border-slate-200 shrink-0"
          id="global-user-menu-wrapper">
          <button
            class="flex items-center gap-1 sm:gap-2 focus:outline-none cursor-pointer rounded-xl p-0.5 sm:p-1 hover:bg-slate-100/60 transition-colors shrink-0"
            id="global-user-menu-btn" title="{{ __('global.header.profile_menu') }}"
            aria-label="{{ __('global.header.profile_menu') }}" aria-haspopup="true" aria-expanded="false">
            <div class="relative shrink-0">
              <x-avatar :user="$user" size="sm" class="border-2 border-white ring-2 ring-[#006948]/30 shadow-xs" :alt="'Avatar ' . $user->name" />
              <span
                class="absolute bottom-0 right-0 w-2 h-2 sm:w-2.5 sm:h-2.5 bg-emerald-500 rounded-full ring-2 ring-white"
                title="Google Workspace Verified"></span>
            </div>
            <div class="hidden lg:flex flex-col text-left">
              <div class="flex items-center gap-1">
                <span class="text-xs font-semibold text-slate-800 truncate max-w-[130px]">{{ $user->name }}</span>
                <span class="material-symbols-outlined text-emerald-600 text-[13px]"
                  style="font-variation-settings: 'FILL' 1;">verified</span>
              </div>
              <span class="text-[11px] text-slate-500 truncate max-w-[150px]">{{ $user->email }}</span>
            </div>
            <span
              class="material-symbols-outlined text-slate-400 text-[15px] sm:text-[18px] transition-transform duration-200"
              id="global-user-chevron">expand_more</span>
          </button>

          <!-- User Dropdown Menu -->
          <div
            class="hidden absolute right-0 top-11 sm:top-12 z-50 w-[calc(100vw-1.5rem)] max-w-[280px] sm:max-w-xs sm:w-72 bg-white rounded-2xl shadow-xl border border-slate-200/80 overflow-hidden"
            id="global-user-dropdown">
            <div class="p-3.5 sm:p-4 bg-slate-50/70 border-b border-slate-100">
              <div class="flex items-center gap-2.5 sm:gap-3">
                <x-avatar :user="$user" size="md" class="border-2 border-white ring-2 ring-[#006948]/30 shrink-0 shadow-xs" :alt="'Avatar ' . $user->name" />
                <div class="min-w-0 flex-1">
                  <div class="flex items-center gap-1.5">
                    <p class="text-xs sm:text-sm font-bold text-slate-900 truncate">{{ $user->name }}</p>
                    <span
                      class="inline-flex items-center gap-0.5 px-1.5 py-0.2 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-bold shrink-0">{{ __('global.header.sso_badge') }}</span>
                  </div>
                  <p class="text-[11px] text-slate-400 truncate mt-0.5">{{ $user->email }}</p>
                </div>
              </div>
            </div>
            <div class="p-2 divide-y divide-slate-100">
              <div class="space-y-0.5 pb-2">
                <a class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium {{ $activeTab === 'profile' ? 'text-[#006948] bg-emerald-50/60 font-semibold' : 'text-slate-700 hover:text-slate-900 hover:bg-slate-50' }} rounded-xl transition-colors"
                  href="{{ route('user.me.profile') }}">
                  <span
                    class="material-symbols-outlined text-[17px] {{ $activeTab === 'profile' ? 'text-[#006948]' : 'text-slate-400' }}">account_circle</span>
                  <span class="flex-1">{{ __('global.header.profile_taste') }}</span>
                  @if($activeTab === 'profile')
                    <span class="w-1.5 h-1.5 rounded-full bg-[#006948]"></span>
                  @endif
                </a>
                @if($hasRooms && $showMenu('payments'))
                  <a class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium {{ $activeTab === 'payments' ? 'text-[#006948] bg-emerald-50/60 font-semibold' : 'text-slate-700 hover:text-slate-900 hover:bg-slate-50' }} rounded-xl transition-colors"
                    href="{{ route('user.me.payments') }}">
                    <span
                      class="material-symbols-outlined text-[17px] {{ $activeTab === 'payments' ? 'text-[#006948]' : 'text-slate-400' }}">account_balance_wallet</span>
                    <span>{{ __('global.header.wallet_payments') }}</span>
                  </a>
                @endif
                @if($hasRooms && $showMenu('statistics'))
                  <a class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium {{ $activeTab === 'statistics' ? 'text-[#006948] bg-emerald-50/60 font-semibold' : 'text-slate-700 hover:text-slate-900 hover:bg-slate-50' }} rounded-xl transition-colors"
                    href="{{ route('user.me.statistics') }}">
                    <span
                      class="material-symbols-outlined text-[17px] {{ $activeTab === 'statistics' ? 'text-[#006948]' : 'text-slate-400' }}">bar_chart</span>
                    <span>{{ __('global.header.spend_stats') }}</span>
                  </a>
                @endif
                <a class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium {{ $activeTab === 'devices' ? 'text-[#006948] bg-emerald-50/60 font-semibold' : 'text-slate-700 hover:text-slate-900 hover:bg-slate-50' }} rounded-xl transition-colors"
                  href="{{ route('user.me.devices') }}">
                  <span
                    class="material-symbols-outlined text-[17px] {{ $activeTab === 'devices' ? 'text-[#006948]' : 'text-slate-400' }}">shield</span>
                  <span>{{ __('global.header.security_devices') }}</span>
                </a>
                <a class="flex items-center gap-2.5 px-3 py-2 text-xs font-medium {{ $activeTab === 'notifications' ? 'text-[#006948] bg-emerald-50/60 font-semibold' : 'text-slate-700 hover:text-slate-900 hover:bg-slate-50' }} rounded-xl transition-colors"
                  href="{{ route('user.me.notifications') }}">
                  <span
                    class="material-symbols-outlined text-[17px] {{ $activeTab === 'notifications' ? 'text-[#006948]' : 'text-slate-400' }}">tune</span>
                  <span>{{ __('global.header.notif_settings') }}</span>
                </a>
              </div>
              <div class="pt-2">
                <button type="button" onclick="openLogoutModal()"
                  class="w-full flex items-center gap-2.5 px-3 py-2 text-xs font-medium text-rose-600 hover:bg-rose-50 rounded-xl transition-colors text-left cursor-pointer">
                  <span class="material-symbols-outlined text-[17px] text-rose-500">logout</span>
                  <span>{{ __('global.header.logout') }}</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tầng 2: Navigation Links & Breadcrumb Trail -->
    <div
      class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 sm:gap-2 pt-1 sm:pt-2 border-t border-slate-100 sm:border-0">
      <nav class="flex items-center gap-3 sm:gap-6 overflow-x-auto no-scrollbar -mx-3 px-3 sm:mx-0 sm:px-0"
        aria-label="{{ __('global.header.main_nav') }}">
        <!-- Luôn hiển thị Tổng quan -->
        <a class="{{ $tabClasses($activeTab === 'overview') }}" href="{{ route('user.me.dashboard') }}">
          <span class="material-symbols-outlined text-[16px] sm:text-[17px]">dashboard</span>
          <span>{{ __('global.header.overview') }}</span>
        </a>

        <!-- Chỉ hiển thị khi đã tham gia ít nhất 1 Room -->
        @if($hasRooms && $showMenu('rooms'))
          <a class="{{ $tabClasses($activeTab === 'rooms') }}" href="{{ route('user.me.rooms') }}">
            <span>{{ __('global.header.my_rooms') }}</span>
          </a>
        @endif

        @if($hasOrders && $showMenu('orders'))
          <a class="{{ $tabClasses($activeTab === 'orders') }}" href="{{ route('user.me.orders') }}">
            <span>{{ __('global.header.order_history') }}</span>
          </a>
        @endif
        @if($hasOrders && $showMenu('payments'))
          <a class="{{ $tabClasses($activeTab === 'payments') }}" href="{{ route('user.me.payments') }}">
            <span>{{ __('global.header.payments') }}</span>
          </a>
        @endif
        @if($hasOrders && $showMenu('statistics'))
          <a class="{{ $tabClasses($activeTab === 'statistics') }}" href="{{ route('user.me.statistics') }}">
            <span>{{ __('global.header.statistics') }}</span>
          </a>
        @endif

        <!-- Luôn hiển thị Hướng dẫn sử dụng -->
        <a class="{{ $tabClasses($activeTab === 'guides') }}" href="{{ route('user.me.guides') }}">
          <span class="material-symbols-outlined text-[16px] sm:text-[17px]">menu_book</span>
          <span>{{ __('global.header.user_guide') }}</span>
        </a>

        <!-- Trò chơi (Games) -->
        @if($showMenu('games'))
          <a class="{{ $tabClasses($activeTab === 'games') }}" href="{{ route('user.me.games') }}" data-nav-games>
            <span class="material-symbols-outlined text-[16px] sm:text-[17px]">sports_esports</span>
            <span>{{ __('games.page_title') }}</span>
          </a>
        @endif

        <!-- Luôn hiển thị Đánh giá -->
        <a class="{{ $tabClasses($activeTab === 'feedback') }}" href="{{ route('user.me.feedback') }}">
          <span class="material-symbols-outlined text-[16px] sm:text-[17px]">rate_review</span>
          <span>{{ __('global.header.feedback') }}</span>
        </a>
      </nav>

      <!-- Breadcrumbs Trail -->
      @if(!empty($breadcrumbs))
        <div class="hidden lg:flex items-center gap-1.5 text-xs text-slate-400 pb-2.5">
          @foreach($breadcrumbs as $index => $crumb)
            @if(!$loop->first)
              <span class="material-symbols-outlined text-[13px]">chevron_right</span>
            @endif
            @if(!empty($crumb['url']) && !$loop->last)
              <a href="{{ $crumb['url'] }}" class="hover:text-slate-700 transition-colors">{{ $crumb['label'] }}</a>
            @else
              <span class="{{ $loop->last ? 'text-[#006948] font-semibold' : '' }}">{{ $crumb['label'] }}</span>
            @endif
          @endforeach
        </div>
      @endif
    </div>
  </div>
</header>
