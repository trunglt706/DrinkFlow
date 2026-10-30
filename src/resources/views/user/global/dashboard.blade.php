<x-global.layout
  :title="'DrinkFlow - ' . __('global.dashboard.page_title')"
  :user="$user"
  :active-tab="'overview'"
  :unread-notifications-count="$unreadNotificationsCount"
  :notifications="$notifications"
>
  <!-- Main Canvas -->
  <div class="space-y-6">
    <!-- Status & Error Flash Alerts -->
    @if(session('status'))
      <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200/80 text-emerald-900 text-sm font-medium flex items-center justify-between shadow-xs transition-all animate-fadeIn">
        <div class="flex items-center gap-2.5">
          <span class="w-7 h-7 rounded-lg bg-emerald-100 text-[#006948] flex items-center justify-center shrink-0">
            <span class="material-symbols-outlined text-[18px]">check_circle</span>
          </span>
          <span>{{ session('status') }}</span>
        </div>
        <button type="button" onclick="this.parentElement.remove()" class="text-emerald-700 hover:text-emerald-950 p-1 rounded-lg hover:bg-emerald-100/60 transition-colors cursor-pointer" title="{{ __('global.common.close') }}">
          <span class="material-symbols-outlined text-[18px]">close</span>
        </button>
      </div>
    @endif

    <!-- Top Greeting Banner -->
    <section>
      <div class="bg-white border border-slate-200/80 rounded-2xl p-6 sm:p-7 shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-5">
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-3">
            <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight break-words min-w-0">{{ __('global.dashboard.greeting', ['name' => $user->name]) }}</h1>
            <span class="inline-flex max-w-full items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-50 text-emerald-800 text-xs font-medium border border-emerald-200/70">
              <span class="material-symbols-outlined text-[14px] text-[#006948] shrink-0" style="font-variation-settings: 'FILL' 1;">security</span>
              <span class="min-w-0 break-words">{{ $workspaceText }}</span>
            </span>
          </div>
          <p class="text-sm text-slate-500 mt-1.5 max-w-2xl leading-relaxed">{{ __('global.dashboard.subtitle') }}</p>
        </div>
        <div class="hidden sm:flex items-center gap-3 shrink-0">
          <div class="bg-slate-50 border border-slate-200/70 rounded-xl px-3.5 py-2 text-right">
            <span class="text-[11px] text-slate-400 block font-medium">{{ __('global.dashboard.session_title') }}</span>
            <span class="text-xs font-mono font-semibold text-slate-700">#SES-{{ strtoupper(substr(session()->getId() ?: md5($user->id), 0, 8)) }}</span>
          </div>
          <span class="w-10 h-10 rounded-xl bg-slate-50 border border-slate-200/70 flex items-center justify-center text-[#006948] shadow-2xs">
            <span class="material-symbols-outlined text-[20px]">fingerprint</span>
          </span>
        </div>
      </div>

      @if($roomsCount > 0)
        <!-- 4 Quick Stats Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mt-5">
          <!-- Stat 1: Rooms -->
          <div class="bg-white border border-slate-200/80 hover:border-slate-300 rounded-2xl p-5 shadow-xs transition-all duration-150 flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-50 text-[#006948] border border-emerald-100 flex items-center justify-center shrink-0">
              <span class="material-symbols-outlined text-[22px]">meeting_room</span>
            </div>
            <div>
              <span class="text-xs font-medium text-slate-500 block">{{ __('global.dashboard.stat_rooms') }}</span>
              <div class="flex items-baseline gap-1 mt-0.5">
                <span class="text-2xl font-bold text-slate-900 tracking-tight">{{ $roomsCount }}</span>
                <span class="text-xs text-slate-400">{{ __('global.dashboard.stat_rooms_unit') }}</span>
              </div>
            </div>
          </div>

          <!-- Stat 2: Orders -->
          <div class="bg-white border border-slate-200/80 hover:border-slate-300 rounded-2xl p-5 shadow-xs transition-all duration-150 flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-50 text-[#006948] border border-emerald-100 flex items-center justify-center shrink-0">
              <span class="material-symbols-outlined text-[22px]">receipt_long</span>
            </div>
            <div>
              <span class="text-xs font-medium text-slate-500 block">{{ __('global.dashboard.stat_orders') }}</span>
              <div class="flex items-baseline gap-1 mt-0.5">
                <span class="text-2xl font-bold text-slate-900 tracking-tight">{{ $totalOrdersCount }}</span>
                <span class="text-xs text-slate-400">{{ __('global.dashboard.stat_orders_unit') }}</span>
              </div>
            </div>
          </div>

          <!-- Stat 3: Spent -->
          <div class="bg-white border border-slate-200/80 hover:border-slate-300 rounded-2xl p-5 shadow-xs transition-all duration-150 flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-50 text-[#006948] border border-emerald-100 flex items-center justify-center shrink-0">
              <span class="material-symbols-outlined text-[22px]">account_balance_wallet</span>
            </div>
            <div>
              <span class="text-xs font-medium text-slate-500 block">{{ __('global.dashboard.stat_spent') }}</span>
              <div class="flex items-baseline gap-1 mt-0.5">
                <span class="text-2xl font-bold text-slate-900 tracking-tight">{{ \App\Support\Helpers\FormatHelper::formatCurrency($totalSpent) }}</span>
              </div>
            </div>
          </div>

          <!-- Stat 4: Sponsor -->
          <div class="bg-white border border-slate-200/80 hover:border-slate-300 rounded-2xl p-5 shadow-xs transition-all duration-150 flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-emerald-50 text-[#006948] border border-emerald-100 flex items-center justify-center shrink-0">
              <span class="material-symbols-outlined text-[22px]">savings</span>
            </div>
            <div>
              <span class="text-xs font-medium text-slate-500 block">{{ __('global.dashboard.stat_sponsor') }}</span>
              <div class="flex items-baseline gap-1 mt-0.5">
                <span class="text-2xl font-bold text-slate-900 tracking-tight">{{ \App\Support\Helpers\FormatHelper::formatCurrency($sponsorReceived) }}</span>
              </div>
              <span class="text-[11px] text-emerald-700 font-medium block mt-0.5">{{ __('global.dashboard.stat_sponsor_hint', ['percent' => $savingsPercent]) }}</span>
            </div>
          </div>
        </div>
      @endif
    </section>

    <!-- Banner: Trò chơi -->
    <section>
      <a href="{{ route('user.me.games') }}" data-games-banner
         style="background-image: linear-gradient(90deg, #006948, #059669);"
         class="group relative flex items-center gap-4 sm:gap-5 overflow-hidden rounded-2xl border border-emerald-200/70 bg-[#006948] p-5 sm:p-6 text-white shadow-xs transition-all hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400 focus-visible:ring-offset-2">
        <span class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-white/10" aria-hidden="true"></span>
        <span class="pointer-events-none absolute right-24 -bottom-14 h-32 w-32 rounded-full bg-white/5" aria-hidden="true"></span>
        <span class="relative flex h-12 w-12 sm:h-14 sm:w-14 shrink-0 items-center justify-center rounded-2xl bg-white/15 border border-white/20">
          <span class="material-symbols-outlined text-[28px] sm:text-[32px]" style="font-variation-settings: 'FILL' 1;" aria-hidden="true">sports_esports</span>
        </span>
        <span class="relative min-w-0 flex-1">
          <span class="block text-lg sm:text-xl font-bold tracking-tight">{{ __('games.banner.title') }}</span>
          <span class="mt-0.5 block text-xs sm:text-sm text-emerald-50/90 leading-relaxed">{{ __('games.banner.desc') }}</span>
        </span>
        <span class="relative hidden sm:inline-flex h-10 items-center gap-1.5 rounded-xl bg-white px-4 text-xs font-semibold text-[#006948] shadow-2xs">
          {{ __('games.banner.cta') }}
          <span class="material-symbols-outlined text-[18px] transition-transform group-hover:translate-x-0.5" aria-hidden="true">arrow_forward</span>
        </span>
        <span class="relative material-symbols-outlined text-[22px] sm:hidden" aria-hidden="true">chevron_right</span>
      </a>
    </section>

    @if($roomsCount > 0)
      <!-- Section: Room Gần Đây -->
      <section>
        <div class="flex items-center justify-between pb-4 border-b border-slate-200 mb-5">
          <div class="flex items-center gap-2.5">
            <span class="w-8 h-8 rounded-xl bg-emerald-50 text-[#006948] border border-emerald-100 flex items-center justify-center">
              <span class="material-symbols-outlined text-[18px]">group_work</span>
            </span>
            <div>
              <h2 class="text-base sm:text-lg font-bold text-slate-900">{{ __('global.dashboard.recent_rooms') }}</h2>
              <span class="text-xs text-slate-500 hidden sm:inline">{{ __('global.dashboard.recent_rooms_desc') }}</span>
            </div>
          </div>
          <a class="min-w-8 h-8 px-2.5 rounded-full bg-emerald-50 border border-emerald-100 text-[#006948] hover:bg-emerald-100 text-xs sm:text-sm font-bold flex items-center justify-center transition-colors" href="{{ route('user.me.rooms') }}">
            {{ $roomsCount }}
          </a>
        </div>

        <!-- Cards Grid (1 to 4 cols) -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
          @forelse($recentRooms as $roomItem)
            <x-global.room-card :item="$roomItem" :show-live-status="true" />
          @empty
            <div class="col-span-full p-8 border border-dashed border-slate-200 rounded-2xl bg-slate-50/50 text-center">
              <span class="material-symbols-outlined text-[32px] text-slate-300 mb-2">meeting_room</span>
              <h3 class="text-sm font-semibold text-slate-800">{{ __('global.dashboard.no_rooms_joined') }}</h3>
              <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">{{ __('global.dashboard.no_rooms_desc') }}</p>
            </div>
          @endforelse
        </div>
      </section>

      {{-- Tạm ẩn: Đơn hàng gần đây
      @if($recentOrders->isNotEmpty())
      <!-- Section: Đơn Hàng Gần Đây -->
      <section class="bg-white border border-slate-200/80 rounded-2xl shadow-xs overflow-hidden">
        <div class="p-6 pb-4 flex items-center justify-between border-b border-slate-100">
          <div class="flex items-center gap-2.5">
            <span class="w-8 h-8 rounded-xl bg-emerald-50 text-[#006948] border border-emerald-100 flex items-center justify-center">
              <span class="material-symbols-outlined text-[18px]">history</span>
            </span>
            <div>
              <h2 class="text-base sm:text-lg font-bold text-slate-900">{{ __('global.dashboard.recent_orders') }}</h2>
              <span class="text-xs text-slate-500 hidden sm:inline">{{ __('global.dashboard.recent_orders_desc') }}</span>
            </div>
          </div>
          <div class="flex items-center gap-3">
            <a class="text-[#006948] hover:text-emerald-700 text-xs sm:text-sm font-medium flex items-center gap-1 group" href="{{ route('user.me.orders') }}">
              <span>{{ __('global.dashboard.full_history') }}</span>
              <span class="material-symbols-outlined text-[16px] group-hover:translate-x-0.5 transition-transform">open_in_new</span>
            </a>
          </div>
        </div>

        <!-- Table structure -->
        <div class="overflow-x-auto w-full">
          <table class="w-full text-left border-collapse min-w-[850px]">
            <thead>
              <tr class="bg-slate-50/75 border-b border-slate-100 text-slate-500 text-xs font-semibold uppercase tracking-wider">
                <th class="py-3 px-5">{{ __('global.dashboard.col_code') }}</th>
                <th class="py-3 px-4">{{ __('global.dashboard.col_time') }}</th>
                <th class="py-3 px-4">{{ __('global.dashboard.col_room') }}</th>
                <th class="py-3 px-4">{{ __('global.dashboard.col_items') }}</th>
                <th class="py-3 px-4 text-right">{{ __('global.dashboard.col_amount') }}</th>
                <th class="py-3 px-4 text-center">{{ __('global.dashboard.col_status') }}</th>
                <th class="py-3 px-5 text-center">{{ __('global.dashboard.col_action') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 text-xs text-slate-700">
              @forelse($recentOrders as $order)
                <tr class="hover:bg-slate-50/70 transition-colors">
                  <td class="py-3.5 px-5 font-mono text-xs text-[#006948] font-semibold whitespace-nowrap">
                    <span class="px-2 py-0.5 rounded-md bg-slate-100 border border-slate-200">{{ $order['code'] }}</span>
                  </td>
                  <td class="py-3.5 px-4 whitespace-nowrap text-slate-400">{{ $order['time_formatted'] }}</td>
                  <td class="py-3.5 px-4 font-medium whitespace-nowrap text-slate-900">{{ $order['room_name'] }}</td>
                  <td class="py-3.5 px-4 font-medium max-w-[280px] truncate text-slate-800" title="{{ $order['items_summary'] }}">{{ $order['items_summary'] }}</td>
                  <td class="py-3.5 px-4 text-right font-semibold tabular-nums whitespace-nowrap text-slate-900">{{ $order['final_amount_formatted'] }}</td>
                  <td class="py-3.5 px-4 text-center whitespace-nowrap">
                    @if($order['is_paid'])
                      <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-xs font-medium border border-emerald-200/70">
                        <span class="material-symbols-outlined text-[13px]">check_circle</span>
                        {{ __('global.dashboard.paid') }}
                      </span>
                    @elseif($order['is_pending'])
                      <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-amber-50 text-amber-700 text-xs font-medium border border-amber-200/70">
                        <span class="material-symbols-outlined text-[13px]">pending</span>
                        {{ __('global.dashboard.unpaid') }}
                      </span>
                    @else
                      <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-slate-100 text-slate-600 text-xs font-medium">
                        {{ $order['status_label'] }}
                      </span>
                    @endif
                  </td>
                  <td class="py-3.5 px-5 text-center whitespace-nowrap">
                    <a class="text-[#006948] hover:underline font-medium text-xs" href="{{ $order['detail_url'] }}">{{ __('global.common.details') }}</a>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" class="py-8 text-center text-slate-400 text-xs">
                    <span class="material-symbols-outlined text-slate-300 text-[24px] mb-1 block">receipt_long</span>
                    {{ __('global.dashboard.no_recent_orders') }}
                  </td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <!-- Enterprise Standard Pagination Controls -->
        <div class="px-6 py-3.5 bg-slate-50/60 border-t border-slate-100 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500">
          <div class="flex items-center gap-3">
            <span>{{ __('global.dashboard.showing_orders', ['first' => count($recentOrders) > 0 ? 1 : 0, 'last' => count($recentOrders), 'total' => $totalOrdersCount]) }}</span>
            <span class="hidden md:inline text-slate-300">•</span>
            <div class="hidden md:flex items-center gap-1.5">
              <span>{{ __('global.dashboard.rows_per_page') }}</span>
              <select class="text-xs font-medium border border-slate-200 rounded-lg bg-white px-2 py-0.5 text-slate-700 focus:outline-none focus:ring-1 focus:ring-emerald-500" aria-label="{{ __('global.dashboard.rows_per_page') }}">
                <option selected="">5</option>
                <option>10</option>
                <option>20</option>
              </select>
            </div>
          </div>
          <!-- Pagination Buttons -->
          <div class="flex items-center gap-1">
            <button class="h-8 w-8 rounded-lg border border-slate-200 text-slate-400 flex items-center justify-center hover:bg-slate-50 transition-colors disabled:opacity-40 disabled:pointer-events-none" disabled="" title="{{ __('global.dashboard.prev_page') }}" aria-label="{{ __('global.dashboard.prev_page') }}">
              <span class="material-symbols-outlined text-[17px]">chevron_left</span>
            </button>
            <button class="h-8 min-w-[32px] px-2 rounded-lg bg-[#006948] text-white font-medium text-xs flex items-center justify-center shadow-xs">
              1
            </button>
            <a class="h-8 w-8 rounded-lg border border-slate-200 text-slate-600 flex items-center justify-center hover:bg-slate-50 transition-colors" href="{{ route('user.me.orders') }}" title="{{ __('global.dashboard.next_page') }}">
              <span class="material-symbols-outlined text-[17px]">chevron_right</span>
            </a>
          </div>
        </div>
      </section>
      @endif
      --}}
    @else
      <!-- Onboarding Hero Canvas when user hasn't joined any room -->
      <section class="bg-white border border-slate-200/80 rounded-2xl p-6 sm:p-8 shadow-xs relative overflow-hidden">
        <!-- Background Ambient Accent -->
        <div class="absolute -right-20 -bottom-20 w-80 h-80 bg-emerald-50 rounded-full blur-3xl opacity-60 pointer-events-none"></div>

        <div class="w-full relative z-10">
          <!-- Fast Join Room Form -->
          <div>
            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-500 mb-3 flex items-center gap-1.5">
              <span class="material-symbols-outlined text-[#006948] text-[18px]">link</span>
              {{ __('global.dashboard.join_by_url') }}
            </h3>
            <form method="POST" action="{{ route('user.me.rooms.join') }}" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
              @csrf
              <div class="relative flex-1">
                <input name="room_url"
                       value="{{ old('room_url') }}"
                       class="w-full px-4 h-11 bg-slate-50/70 border @error('room_url') border-red-500 @else border-slate-200 @enderror rounded-xl text-sm text-slate-900 placeholder:text-slate-400 focus:bg-white focus:border-[#006948] focus:ring-1 focus:ring-[#006948] font-mono transition-all outline-none"
                       placeholder="{{ __('global.dashboard.join_placeholder') }}"
                       type="url"
                       required>
              </div>
              <button type="submit" class="h-11 px-6 bg-[#006948] hover:bg-[#005137] text-white rounded-xl text-xs font-semibold transition-colors flex items-center justify-center gap-2 shadow-xs whitespace-nowrap cursor-pointer">
                <span class="material-symbols-outlined text-[18px]">login</span>
                <span>{{ __('global.dashboard.join_button') }}</span>
              </button>
            </form>
            @error('room_url')
              <p class="text-xs text-red-600 mt-2 font-medium flex items-center gap-1">
                <span class="material-symbols-outlined text-[14px]">error</span>
                {{ $message }}
              </p>
            @enderror
          </div>

          <!-- 3 Highlights Cards -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-8 pt-6 border-t border-slate-100">
            <div class="p-4 rounded-xl bg-slate-50/60 border border-slate-200/80 flex items-start gap-3">
              <div class="w-9 h-9 rounded-lg bg-emerald-50 text-[#006948] border border-emerald-100 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[20px]">groups</span>
              </div>
              <div>
                <h4 class="text-xs font-bold text-slate-900">{{ __('global.dashboard.highlight_team') }}</h4>
                <p class="text-xs text-slate-500 mt-0.5 leading-relaxed">{{ __('global.dashboard.highlight_team_desc') }}</p>
              </div>
            </div>

            <div class="p-4 rounded-xl bg-slate-50/60 border border-slate-200/80 flex items-start gap-3">
              <div class="w-9 h-9 rounded-lg bg-emerald-50 text-[#006948] border border-emerald-100 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[20px]">savings</span>
              </div>
              <div>
                <h4 class="text-xs font-bold text-slate-900">{{ __('global.dashboard.highlight_sponsor') }}</h4>
                <p class="text-xs text-slate-500 mt-0.5 leading-relaxed">{{ __('global.dashboard.highlight_sponsor_desc') }}</p>
              </div>
            </div>

            <div class="p-4 rounded-xl bg-slate-50/60 border border-slate-200/80 flex items-start gap-3">
              <div class="w-9 h-9 rounded-lg bg-emerald-50 text-[#006948] border border-emerald-100 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[20px]">qr_code_scanner</span>
              </div>
              <div>
                <h4 class="text-xs font-bold text-slate-900">{{ __('global.dashboard.highlight_vietqr') }}</h4>
                <p class="text-xs text-slate-500 mt-0.5 leading-relaxed">{{ __('global.dashboard.highlight_vietqr_desc') }}</p>
              </div>
            </div>
          </div>
        </div>
      </section>
    @endif

    <!-- Section: Lối tắt nhanh -->
    <section>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Action 1: Thông báo -->
          <a class="p-4 rounded-xl border border-slate-200/80 bg-slate-50/40 hover:bg-white hover:border-emerald-400 hover:shadow-md transition-all duration-200 flex items-center gap-3.5 group cursor-pointer text-left w-full" href="{{ route('user.me.notifications') }}">
            <div class="w-11 h-11 rounded-xl bg-emerald-50 text-[#006948] group-hover:bg-[#006948] group-hover:text-white transition-colors flex items-center justify-center shrink-0 border border-emerald-100">
              <span class="material-symbols-outlined text-[20px]">notifications</span>
            </div>
            <div class="flex-1 min-w-0">
              <span class="text-sm font-semibold text-slate-900 block truncate">{{ __('global.dashboard.shortcut_notifications') }}</span>
              <span class="text-xs text-slate-500 block mt-0.5">{{ __('global.dashboard.shortcut_notifications_desc') }}</span>
            </div>
            <span class="material-symbols-outlined text-slate-300 group-hover:text-[#006948] group-hover:translate-x-0.5 transition-all text-[17px]">chevron_right</span>
          </a>

        <!-- Action 2: Trợ giúp -->
          <a class="p-4 rounded-xl border border-slate-200/80 bg-slate-50/40 hover:bg-white hover:border-emerald-400 hover:shadow-md transition-all duration-200 flex items-center gap-3.5 group cursor-pointer text-left w-full" href="{{ route('contact') }}">
            <div class="w-11 h-11 rounded-xl bg-emerald-50 text-[#006948] group-hover:bg-[#006948] group-hover:text-white transition-colors flex items-center justify-center shrink-0 border border-emerald-100">
              <span class="material-symbols-outlined text-[20px]">help</span>
            </div>
            <div class="flex-1 min-w-0">
              <span class="text-sm font-semibold text-slate-900 block truncate">{{ __('global.dashboard.shortcut_help') }}</span>
              <span class="text-xs text-slate-500 block mt-0.5">{{ __('global.dashboard.shortcut_help_desc') }}</span>
            </div>
            <span class="material-symbols-outlined text-slate-300 group-hover:text-[#006948] group-hover:translate-x-0.5 transition-all text-[17px]">chevron_right</span>
          </a>

        <!-- Action 3: Thông tin cá nhân -->
          <a class="p-4 rounded-xl border border-slate-200/80 bg-slate-50/40 hover:bg-white hover:border-emerald-400 hover:shadow-md transition-all duration-200 flex items-center gap-3.5 group cursor-pointer text-left w-full" href="{{ route('user.me.profile') }}">
            <div class="w-11 h-11 rounded-xl bg-emerald-50 text-[#006948] group-hover:bg-[#006948] group-hover:text-white transition-colors flex items-center justify-center shrink-0 border border-emerald-100">
              <span class="material-symbols-outlined text-[20px]">person</span>
            </div>
            <div class="flex-1 min-w-0">
              <span class="text-sm font-semibold text-slate-900 block truncate">{{ __('global.dashboard.shortcut_profile') }}</span>
              <span class="text-xs text-slate-500 block mt-0.5">{{ __('global.dashboard.shortcut_profile_desc') }}</span>
            </div>
            <span class="material-symbols-outlined text-slate-300 group-hover:text-[#006948] group-hover:translate-x-0.5 transition-all text-[17px]">chevron_right</span>
          </a>

        <!-- Action 4 -->
        <a class="p-4 rounded-xl border border-slate-200/80 bg-slate-50/40 hover:bg-white hover:border-emerald-400 hover:shadow-md transition-all duration-200 flex items-center gap-3.5 group cursor-pointer" href="{{ route('user.me.devices') }}">
          <div class="w-11 h-11 rounded-xl bg-emerald-50 text-[#006948] group-hover:bg-[#006948] group-hover:text-white transition-colors flex items-center justify-center shrink-0 border border-emerald-100">
            <span class="material-symbols-outlined text-[20px]">shield</span>
          </div>
          <div class="flex-1 min-w-0">
            <span class="text-sm font-semibold text-slate-900 block truncate">{{ __('global.dashboard.shortcut_security') }}</span>
            <span class="text-xs text-slate-500 block mt-0.5">{{ __('global.dashboard.shortcut_security_desc') }}</span>
          </div>
          <span class="material-symbols-outlined text-slate-300 group-hover:text-[#006948] group-hover:translate-x-0.5 transition-all text-[17px]">chevron_right</span>
        </a>
      </div>

    </section>
  </div>

  <!-- Modal Thông tin hỗ trợ (Help & Support Center Modal) -->
  <div class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs transition-opacity duration-200" id="support-modal">
    <div class="bg-white border border-slate-200 rounded-2xl shadow-2xl max-w-lg w-full overflow-hidden transform transition-all">
      <!-- Modal Header -->
      <div class="p-5 border-b border-slate-100 bg-slate-50/60 flex items-center justify-between">
        <div class="flex items-center gap-2.5">
          <div class="w-9 h-9 rounded-xl bg-emerald-50 text-[#006948] border border-emerald-100 flex items-center justify-center">
            <span class="material-symbols-outlined text-[20px]">support_agent</span>
          </div>
          <div>
            <h3 class="text-base font-bold text-slate-900">{{ __('global.dashboard.support_center_title') }}</h3>
            <p class="text-xs text-slate-500">{{ __('global.dashboard.support_center_subtitle') }}</p>
          </div>
        </div>
        <button class="w-8 h-8 rounded-lg flex items-center justify-center text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer" id="close-modal-btn" title="{{ __('global.common.close') }}" aria-label="{{ __('global.common.close') }}">
          <span class="material-symbols-outlined text-[19px]">close</span>
        </button>
      </div>

      <!-- Modal Content -->
      <div class="p-6 space-y-5 max-h-[75vh] overflow-y-auto">
        <!-- Quick Contact Channels -->
        <div>
          <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-3 flex items-center gap-1.5">
            <span class="material-symbols-outlined text-[#006948] text-[16px]">contact_support</span>
            {{ __('global.dashboard.contact_channels') }}
          </h4>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div class="p-3.5 rounded-xl border border-slate-200/80 bg-slate-50/50 hover:bg-white hover:border-emerald-400 transition-all flex items-center gap-3">
              <span class="w-9 h-9 rounded-lg bg-emerald-50 text-[#006948] border border-emerald-100 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[18px]">call</span>
              </span>
              <div class="min-w-0">
                <span class="text-[11px] text-slate-400 block">{{ __('global.dashboard.hotline_title') }}</span>
                <a class="text-xs font-semibold text-[#006948] hover:underline" href="tel:0377300950">{{ __('global.dashboard.hotline_value') }}</a>
              </div>
            </div>
            <div class="p-3.5 rounded-xl border border-slate-200/80 bg-slate-50/50 hover:bg-white hover:border-emerald-400 transition-all flex items-center gap-3">
              <span class="w-9 h-9 rounded-lg bg-emerald-50 text-[#006948] border border-emerald-100 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[18px]">forum</span>
              </span>
              <div class="min-w-0">
                <span class="text-[11px] text-slate-400 block">{{ __('global.dashboard.slack_title') }}</span>
                <span class="text-xs font-semibold text-slate-800">{{ __('global.dashboard.slack_value') }}</span>
              </div>
            </div>
            <div class="sm:col-span-2 p-3.5 rounded-xl border border-slate-200/80 bg-slate-50/50 hover:bg-white hover:border-emerald-400 transition-all flex items-center gap-3">
              <span class="w-9 h-9 rounded-lg bg-emerald-50 text-[#006948] border border-emerald-100 flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[18px]">mail</span>
              </span>
              <div class="min-w-0">
                <span class="text-[11px] text-slate-400 block">{{ __('global.dashboard.email_title') }}</span>
                <span class="text-xs font-semibold text-[#006948]">{{ __('global.dashboard.email_value') }}</span>
              </div>
            </div>
          </div>
        </div>

        <!-- FAQ Section -->
        <div>
          <h4 class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2.5 flex items-center gap-1.5">
            <span class="material-symbols-outlined text-[#006948] text-[16px]">quiz</span>
            {{ __('global.dashboard.faq_title') }}
          </h4>
          <div class="space-y-2">
            <details class="group border border-slate-200/80 rounded-xl bg-slate-50/40 p-3.5 cursor-pointer hover:bg-slate-50/70 transition-colors">
              <summary class="text-xs font-semibold text-slate-800 flex justify-between items-center select-none">
                <span>{{ __('global.dashboard.faq_q1') }}</span>
                <span class="material-symbols-outlined text-[17px] text-slate-400 group-open:rotate-180 transition-transform">expand_more</span>
              </summary>
              <p class="text-xs text-slate-600 mt-2.5 pt-2.5 border-t border-slate-100 leading-relaxed">
                {{ __('global.dashboard.faq_a1') }}
              </p>
            </details>
            <details class="group border border-slate-200/80 rounded-xl bg-slate-50/40 p-3.5 cursor-pointer hover:bg-slate-50/70 transition-colors">
              <summary class="text-xs font-semibold text-slate-800 flex justify-between items-center select-none">
                <span>{{ __('global.dashboard.faq_q2') }}</span>
                <span class="material-symbols-outlined text-[17px] text-slate-400 group-open:rotate-180 transition-transform">expand_more</span>
              </summary>
              <p class="text-xs text-slate-600 mt-2.5 pt-2.5 border-t border-slate-100 leading-relaxed">
                {{ __('global.dashboard.faq_a2') }}
              </p>
            </details>
            <details class="group border border-slate-200/80 rounded-xl bg-slate-50/40 p-3.5 cursor-pointer hover:bg-slate-50/70 transition-colors">
              <summary class="text-xs font-semibold text-slate-800 flex justify-between items-center select-none">
                <span>{{ __('global.dashboard.faq_q3') }}</span>
                <span class="material-symbols-outlined text-[17px] text-slate-400 group-open:rotate-180 transition-transform">expand_more</span>
              </summary>
              <p class="text-xs text-slate-600 mt-2.5 pt-2.5 border-t border-slate-100 leading-relaxed">
                {{ __('global.dashboard.faq_a3') }}
              </p>
            </details>
          </div>
        </div>
      </div>

      <!-- Modal Footer -->
      <div class="p-4 bg-slate-50/60 border-t border-slate-100 flex justify-end">
        <button class="px-5 h-9 rounded-xl bg-[#059669] hover:bg-[#047857] text-white text-xs font-semibold transition-colors cursor-pointer shadow-2xs" id="modal-confirm-btn">
          {{ __('global.dashboard.understood') }}
        </button>
      </div>
    </div>
  </div>
</x-global.layout>
