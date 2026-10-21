<x-room.layout
  :title="'DrinkFlow - ' . __('room.dashboard.page_title') . ' - ' . ($room->name ?? __('global.common.room'))"
  :room="$room"
  :room-user="$roomUser"
  :user="$user"
  :active-tab="'overview'"
  :unread-notifications-count="$unreadNotificationsCount ?? 0"
  :user-rooms="$userRooms ?? collect()"
>
  <!-- Main Canvas Space -->
  <div class="space-y-4">

    @if(count($sponsorHonors['sponsors']) > 0)
      <x-sponsor-honors :honors="$sponsorHonors" />
    @endif

    @if(! empty($recapYear))
      {{-- Recap season: the member's year recap. --}}
      <a href="{{ route('user.rooms.recap', [$room, $recapYear]) }}" data-recap-banner
         class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 rounded-xl p-4 text-white shadow-sm bg-gradient-to-r from-[#006948] via-indigo-800 to-violet-600 hover:brightness-110 transition">
        <div>
          <p class="text-sm font-bold">{{ __('recap.banner_title', ['year' => $recapYear]) }}</p>
          <p class="mt-0.5 text-xs text-white/80">{{ __('recap.banner_desc') }}</p>
        </div>
        <span class="inline-flex shrink-0 items-center gap-1 self-start sm:self-auto rounded-full bg-white px-3 py-1.5 text-xs font-bold text-[#006948]">
          {{ __('recap.banner_cta') }}<span class="material-symbols-outlined text-[16px]" aria-hidden="true">arrow_forward</span>
        </span>
      </a>
    @endif

    @if(! empty($ratingsPending))
      {{-- Recently received items to rate (1 tap per star). --}}
      <section class="bg-white border border-amber-200/80 rounded-xl p-4 shadow-2xs" data-ratings-pending>
        <h2 class="flex items-center gap-2 text-sm font-bold text-slate-900">
          <span class="material-symbols-outlined text-[18px] text-amber-500" style="font-variation-settings:'FILL' 1" aria-hidden="true">star</span>{{ __('room.rating.pending_title') }}
        </h2>
        <p class="mt-0.5 text-xs text-slate-500">{{ __('room.rating.pending_description') }}</p>
        <ul class="mt-3 grid grid-cols-1 md:grid-cols-2 gap-3">
          @foreach($ratingsPending as $pendingItem)
            <li class="flex items-start gap-3 rounded-lg border border-slate-100 bg-slate-50/60 p-2.5">
              <div class="relative h-10 w-10 shrink-0 overflow-hidden rounded-lg bg-slate-100 flex items-center justify-center text-[#006948]">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">local_cafe</span>
                @if($pendingItem['image_url'])
                  <img src="{{ $pendingItem['image_url'] }}" alt="{{ $pendingItem['name'] }}" loading="lazy" class="absolute inset-0 h-full w-full object-cover" onerror="this.remove()">
                @endif
              </div>
              <div class="min-w-0 flex-1">
                <p class="truncate text-xs font-bold text-slate-900">{{ $pendingItem['name'] }}</p>
                <p class="truncate text-[11px] text-slate-500">{{ $pendingItem['restaurant'] }}</p>
                <x-room.item-rating :rating="['can_rate' => true, 'stars' => null, 'comment' => null, 'url' => $pendingItem['url']]" :compact="true" />
              </div>
            </li>
          @endforeach
        </ul>
      </section>
    @endif

    <!-- Weekly charts followed by the active campaign -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
      
      <!-- Sponsor Leaderboard & Weekly Trend Charts -->
      <div class="lg:col-span-12 grid grid-cols-1 lg:grid-cols-12 gap-4"
        data-room-dashboard-charts
        data-top-sponsors="{{ json_encode($topSponsors ?? [], JSON_UNESCAPED_UNICODE) }}"
        data-weekly-trend="{{ json_encode($weeklyItemTrend ?? [], JSON_UNESCAPED_UNICODE) }}"
        data-chart-labels="{{ json_encode([
          'items' => __('room.dashboard.chart_items_label'),
          'value' => __('room.dashboard.chart_value_label'),
          'sponsoredCampaigns' => __('room.dashboard.sponsored_campaigns_count'),
          'sponsorTotal' => __('room.dashboard.top_sponsors_total'),
          'noSponsorData' => __('room.dashboard.no_sponsor_data'),
          'noTrendData' => __('room.dashboard.no_weekly_trend_data'),
        ], JSON_UNESCAPED_UNICODE) }}"
      >
        <!-- Top Sponsors Donut Chart -->
        <section class="lg:col-span-5 bg-white border border-slate-200/80 rounded-xl p-4 shadow-2xs flex flex-col">
          <div class="flex items-center gap-2 pb-2.5 border-b border-slate-100 mb-2.5">
            <span class="material-symbols-outlined text-[#006948] text-[18px]" aria-hidden="true">military_tech</span>
            <div class="min-w-0">
              <h3 class="text-[11px] font-bold uppercase tracking-wider text-slate-700">{{ __('room.dashboard.top_sponsors_title') }}</h3>
              <p class="text-[10px] text-slate-400 truncate">{{ __('room.dashboard.top_sponsors_subtitle') }}</p>
            </div>
          </div>
          <div class="relative flex-1 min-h-[200px]">
            <div id="top-sponsors-chart" class="h-full min-h-[200px]"></div>
            <x-loading-overlay data-chart-loading :visible="true" />
          </div>
        </section>

        <!-- Weekly Items & Value Combo Chart -->
        <section class="lg:col-span-7 bg-white border border-slate-200/80 rounded-xl p-4 shadow-2xs flex flex-col">
          {{-- Mobile: title, subtitle and legend stack vertically; from sm up the legend sits on the right. --}}
          <div class="flex flex-col items-start gap-2 sm:flex-row sm:items-center sm:justify-between pb-2.5 border-b border-slate-100 mb-2.5">
            <div class="flex items-center gap-2 min-w-0">
              <span class="material-symbols-outlined text-[#006948] text-[18px]" aria-hidden="true">area_chart</span>
              <div class="min-w-0">
                <h3 class="text-[11px] font-bold uppercase tracking-wider text-slate-700">{{ __('room.dashboard.weekly_trend_title') }}</h3>
                <p class="text-[10px] text-slate-400 sm:truncate">{{ __('room.dashboard.weekly_trend_subtitle') }}</p>
              </div>
            </div>
            <div class="flex items-center gap-3 pl-[26px] sm:pl-0 text-[10px] text-slate-500 shrink-0">
              <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-[#006948]"></span>{{ __('room.dashboard.chart_items_label') }}</span>
              <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm border-t-2 border-blue-600 bg-blue-600/25"></span>{{ __('room.dashboard.chart_value_label') }}</span>
            </div>
          </div>
          <div class="relative flex-1 min-h-[200px]">
            <div id="weekly-trend-chart" class="h-full min-h-[200px]"></div>
            <x-loading-overlay data-chart-loading :visible="true" />
          </div>
        </section>
      </div>

      <!-- Live Campaign and Top Items -->
      <div class="lg:col-span-12">
        @if($activeCampaign)
          <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-stretch">
          <!-- Top 5 Live Items -->
          <section class="lg:col-span-5 bg-white border border-slate-200/80 rounded-xl p-4 shadow-2xs flex flex-col justify-between h-full">
            <div>
              <div class="flex items-center gap-2 pb-2.5 border-b border-slate-100 mb-2.5">
                <span class="material-symbols-outlined text-[#006948] text-[18px]" aria-hidden="true">leaderboard</span>
                <h3 class="text-[11px] font-bold uppercase tracking-wider text-slate-700">{{ __('room.dashboard.top_live_items') }}</h3>
              </div>
              @forelse($activeCampaign['popular_items'] as $index => $item)
                <div class="flex items-center justify-between gap-3 py-2 {{ $loop->last ? '' : 'border-b border-slate-100' }}">
                  <div class="flex items-center gap-2.5 min-w-0">
                    <x-room.rank-medal :rank="$index + 1" />
                    <span class="text-xs font-semibold text-slate-800 truncate">{{ $item['name'] }}</span>
                  </div>
                  <span class="text-xs font-semibold text-[#006948] whitespace-nowrap">({{ $item['quantity'] }})</span>
                </div>
              @empty
                <div class="py-10 flex flex-col items-center justify-center text-center gap-2 text-slate-400 my-auto">
                  <span class="material-symbols-outlined text-[26px] text-slate-300" aria-hidden="true">no_meals</span>
                  <p class="text-xs">{{ __('room.dashboard.no_live_item_data') }}</p>
                </div>
              @endforelse
            </div>
          </section>

          <!-- Active Campaign Hero Card -->
          <div class="lg:col-span-7 bg-white border border-slate-200/80 rounded-xl shadow-2xs p-4 sm:p-5 relative overflow-hidden flex flex-col justify-between h-full">
            <!-- Background Ambient Glow -->
            <div class="absolute -right-16 -top-16 w-56 h-56 rounded-full bg-emerald-50 pointer-events-none blur-2xl"></div>

            <div class="relative z-10 space-y-4">
              <!-- Campaign Header Info -->
              @php
                $dashTimeRemaining = $activeCampaign['time_remaining'] ?? '';
                $dashHasExpired = ($activeCampaign['has_expired'] ?? false) || $dashTimeRemaining === '00:00' || $dashTimeRemaining === '00:00:00' || empty($dashTimeRemaining);
              @endphp
              <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                {{-- Mobile: status badge + countdown share one row at equal height; from sm up only the countdown sits on the right. --}}
                <div class="flex items-center justify-between gap-2 sm:order-2 sm:shrink-0">
                  <span class="sm:hidden inline-flex h-7 items-center gap-1 whitespace-nowrap px-2.5 rounded-lg bg-emerald-50 text-emerald-700 text-[10px] font-bold uppercase tracking-wider border border-emerald-200/70">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-pulse"></span>
                    {{ __('room.dashboard.active_campaign_badge') }}
                  </span>
                  <div class="flex h-7 items-center gap-1.5 px-2.5 rounded-lg {{ $dashHasExpired ? 'bg-slate-100 text-slate-600 border border-slate-200' : 'bg-rose-50 text-rose-700 border border-rose-200/80' }} font-mono text-xs font-bold shrink-0 whitespace-nowrap">
                    <span class="material-symbols-outlined text-[14px] {{ $dashHasExpired ? '' : 'animate-pulse' }}">schedule</span>
                    <span>{{ $dashHasExpired ? __('room.header.countdown_closed') : $dashTimeRemaining }}</span>
                  </div>
                </div>

                <div class="flex w-full min-w-0 flex-1 items-start gap-3 sm:order-1">
                  <div class="hidden sm:flex w-10 h-10 rounded-xl bg-emerald-50 text-[#006948] border border-emerald-100 items-center justify-center shrink-0 shadow-2xs">
                    <span class="material-symbols-outlined text-[20px]">local_cafe</span>
                  </div>
                  <div class="min-w-0">
                    <div class="hidden sm:flex items-center gap-1.5">
                      <span class="inline-flex items-center gap-1 whitespace-nowrap px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[10px] font-bold uppercase tracking-wider border border-emerald-200/70">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-600 animate-pulse"></span>
                        {{ __('room.dashboard.active_campaign_badge') }}
                      </span>
                    </div>
                    <h2 class="text-sm sm:text-base font-bold text-slate-900 tracking-tight mt-0.5 break-words">{{ $activeCampaign['name'] }}</h2>
                    <p class="text-[11px] text-slate-500 flex flex-wrap items-center gap-x-1 gap-y-0.5 mt-0.5">
                      <span class="material-symbols-outlined text-[13px] text-[#006948]">storefront</span>
                      <span class="font-semibold text-slate-800">{{ $activeCampaign['restaurant'] }}</span>
                      <span class="text-slate-300">•</span>
                      <span>{{ __('room.dashboard.created_by', ['name' => $activeCampaign['creator_name']]) }}</span>
                      <span class="text-slate-300">•</span>
                      <span class="inline-flex items-center gap-0.5 font-semibold text-[#006948]" data-campaign-ordered-members>
                        <span class="material-symbols-outlined text-[13px]">group</span>
                        {{ trans_choice('room.campaign.ordered_members_count', $activeCampaign['ordered_members'], ['count' => $activeCampaign['ordered_members']]) }}
                      </span>
                    </p>
                  </div>
                </div>
              </div>

              <!-- Sponsorship policy (same data as the campaign menu page: sponsor_type + per-product cap) -->
              @php($dashSponsored = $activeCampaign['sponsor_type'] !== \App\Models\Campaign::SPONSOR_TYPE_NONE)
              <div class="bg-slate-50/80 border border-slate-100 rounded-lg p-3 space-y-1.5">
                <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-xs">
                  <div class="flex flex-wrap items-center gap-1.5 text-slate-600">
                    <span class="material-symbols-outlined text-[15px] text-[#006948]">savings</span>
                    <span class="font-medium">{{ __('room.campaign.policy_sponsor_title') }}:</span>
                    <span class="font-bold text-slate-900">{{ __('room.campaign.sponsor_type_'.$activeCampaign['sponsor_type']) }}</span>
                  </div>
                  @if($dashSponsored)
                    <div class="text-[11px] font-semibold text-[#006948]">
                      {{ __('room.dashboard.sponsor_used_total', ['amount' => \App\Support\Helpers\FormatHelper::formatCurrency($activeCampaign['sponsor_used'])]) }}
                    </div>
                  @endif
                </div>
                @if($activeCampaign['max_budget'] > 0)
                  <div class="text-[10px] text-slate-400">
                    {{ __('room.dashboard.sponsor_item_cap', ['amount' => \App\Support\Helpers\FormatHelper::formatCurrency($activeCampaign['max_budget'])]) }}
                  </div>
                @endif
              </div>

              <!-- Campaign CTA Buttons -->
              <div class="grid grid-cols-1 {{ ($activeCampaign['has_ordered'] ?? false) ? 'sm:grid-cols-2' : '' }} gap-2">
                <a href="{{ route('user.campaigns.index', $room->slug) }}" class="h-9 bg-[#006948] hover:bg-[#005137] text-white rounded-lg text-xs font-semibold flex items-center justify-center gap-1.5 transition-colors shadow-2xs cursor-pointer">
                  <span class="material-symbols-outlined text-[16px]">restaurant_menu</span>
                  <span>{{ __('room.dashboard.enter_campaign') }}</span>
                </a>
                {{-- Only members who already ordered in this campaign see their placed orders. --}}
                @if($activeCampaign['has_ordered'] ?? false)
                  <a href="{{ route('user.orders.index', $room->slug) }}" class="h-9 bg-white hover:bg-slate-50 border border-[#006948] text-[#006948] rounded-lg text-xs font-semibold flex items-center justify-center gap-1.5 transition-colors cursor-pointer">
                    <span class="material-symbols-outlined text-[16px]">receipt_long</span>
                    <span>{{ __('room.dashboard.placed_orders') }}</span>
                  </a>
                @endif
              </div>
            </div>
          </div>
          </div>

        @else
          <!-- No Active Campaign State -->
          <div class="bg-white border border-slate-200/80 rounded-xl p-6 shadow-2xs text-center lg:min-h-64">
            <div class="w-12 h-12 rounded-xl bg-slate-50 text-slate-400 border border-slate-200 mx-auto flex items-center justify-center mb-2.5">
              <span class="material-symbols-outlined text-[24px]">bedtime</span>
            </div>
            <h3 class="text-sm font-bold text-slate-900">{{ __('room.dashboard.no_active_campaign_title') }}</h3>
            <p class="text-xs text-slate-500 max-w-md mx-auto mt-1 leading-relaxed">
              {{ __('room.dashboard.no_active_campaign_desc') }}
            </p>
          </div>
        @endif
      </div>

    </div>
  </div>
</x-room.layout>
