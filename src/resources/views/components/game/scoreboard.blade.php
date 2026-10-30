@props([
    'leaderboard',
    'headToHead',
    'history',
    'highlightPlayerId' => null,
    'voidUrl' => null,
    'canVoid' => false,
])
{{--
  Leaderboard, head-to-head and result history of a game room, shared by the owner dashboard and the public page.
  Desktop shows tables; mobile shows stacked cards. With $canVoid the owner gets a "void" action per valid result.
--}}
<section class="rounded-2xl border border-slate-200/80 bg-white shadow-xs" data-leaderboard>
  <h2 class="flex items-center gap-2 border-b border-slate-100 px-5 py-4 text-base font-bold text-slate-900">
    <span class="material-symbols-outlined text-[20px] text-[#006948]" aria-hidden="true">leaderboard</span>
    {{ __('games.leaderboard.title') }}
  </h2>
  @if($leaderboard === [])
    <p class="px-5 py-8 text-center text-sm text-slate-500">{{ __('games.room.no_players') }}</p>
  @else
    <table class="hidden w-full text-left text-sm md:table">
      <thead>
        <tr class="bg-slate-50/75 text-xs font-semibold uppercase tracking-wider text-slate-500">
          <th class="px-5 py-3 w-20">{{ __('games.leaderboard.rank') }}</th>
          <th class="px-4 py-3">{{ __('games.leaderboard.player') }}</th>
          <th class="px-4 py-3 text-right">{{ __('games.leaderboard.points') }}</th>
          <th class="px-4 py-3 text-right">{{ __('games.leaderboard.wins') }}</th>
          <th class="px-5 py-3 text-right">{{ __('games.leaderboard.losses') }}</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-slate-100">
        @foreach($leaderboard as $row)
          @php $isMe = $highlightPlayerId !== null && (int) $highlightPlayerId === $row['player_id']; @endphp
          <tr class="{{ $isMe ? 'bg-amber-50' : '' }}" @if($isMe) data-highlighted @endif>
            <td class="px-5 py-3 font-bold tabular-nums text-slate-700">
              <span class="inline-flex items-center gap-1">
                #{{ $row['rank'] }}
                @if($row['rank'] === 1 && $row['points'] > 0)
                  <span class="material-symbols-outlined text-[18px] text-amber-500" title="{{ __('games.leaderboard.leader') }}" aria-label="{{ __('games.leaderboard.leader') }}" style="font-variation-settings: 'FILL' 1;">trophy</span>
                @endif
              </span>
            </td>
            <td class="px-4 py-3 font-semibold text-slate-900">
              {{ $row['name'] }}
              @if($isMe)
                <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">{{ __('games.leaderboard.you') }}</span>
              @endif
              @unless($row['is_active'])
                <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-500">{{ __('games.leaderboard.inactive') }}</span>
              @endunless
            </td>
            <td class="px-4 py-3 text-right font-bold tabular-nums text-[#006948]">{{ $row['points'] }}</td>
            <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ $row['wins'] }}</td>
            <td class="px-5 py-3 text-right tabular-nums text-slate-700">{{ $row['losses'] }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
    <ul class="divide-y divide-slate-100 md:hidden">
      @foreach($leaderboard as $row)
        @php $isMe = $highlightPlayerId !== null && (int) $highlightPlayerId === $row['player_id']; @endphp
        <li class="flex items-center gap-3 px-4 py-3 {{ $isMe ? 'bg-amber-50' : '' }}">
          <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full {{ $row['rank'] === 1 ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600' }} text-sm font-bold tabular-nums">{{ $row['rank'] }}</span>
          <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-semibold text-slate-900">
              {{ $row['name'] }}
              @if($isMe)<span class="ml-1 text-[11px] font-semibold text-amber-800">({{ __('games.leaderboard.you') }})</span>@endif
            </span>
            <span class="block text-xs text-slate-500">
              {{ __('games.leaderboard.wins') }} {{ $row['wins'] }} · {{ __('games.leaderboard.losses') }} {{ $row['losses'] }}
              @unless($row['is_active']) · {{ __('games.leaderboard.inactive') }} @endunless
            </span>
          </span>
          <span class="text-right">
            <span class="block text-lg font-bold tabular-nums text-[#006948]">{{ $row['points'] }}</span>
            <span class="block text-[11px] text-slate-400">{{ __('games.leaderboard.points') }}</span>
          </span>
        </li>
      @endforeach
    </ul>
  @endif
</section>

<section class="rounded-2xl border border-slate-200/80 bg-white shadow-xs" data-head-to-head>
  <div class="border-b border-slate-100 px-5 py-4">
    <h2 class="flex items-center gap-2 text-base font-bold text-slate-900">
      <span class="material-symbols-outlined text-[20px] text-[#006948]" aria-hidden="true">swords</span>
      {{ __('games.head_to_head.title') }}
    </h2>
    <p class="mt-0.5 text-xs text-slate-500">{{ __('games.head_to_head.subtitle') }}</p>
  </div>
  <div class="divide-y divide-slate-100">
    @foreach($leaderboard as $row)
      @php $isMe = $highlightPlayerId !== null && (int) $highlightPlayerId === $row['player_id']; @endphp
      <details class="group px-5 py-3 {{ $isMe ? 'bg-amber-50' : '' }}" @if($isMe) open @endif>
        <summary class="flex cursor-pointer select-none items-center justify-between gap-3 text-sm">
          <span class="font-semibold text-slate-900">{{ $row['name'] }}</span>
          <span class="flex items-center gap-2 text-xs text-slate-500">
            {{ __('games.head_to_head.total_points', ['points' => $row['points']]) }}
            <span class="material-symbols-outlined text-[18px] transition-transform group-open:rotate-180" aria-hidden="true">expand_more</span>
          </span>
        </summary>
        @if(($headToHead[$row['player_id']] ?? []) === [])
          <p class="mt-2 text-xs text-slate-500">{{ __('games.head_to_head.empty') }}</p>
        @else
          <ul class="mt-2 grid gap-1.5 sm:grid-cols-2">
            @foreach($headToHead[$row['player_id']] as $versus)
              <li class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-xs">
                <span class="text-slate-700">{{ __('games.head_to_head.vs', ['name' => $versus['opponent']]) }}</span>
                <span class="font-semibold tabular-nums text-slate-900">{{ __('games.head_to_head.record', ['wins' => $versus['wins'], 'losses' => $versus['losses']]) }}</span>
              </li>
            @endforeach
          </ul>
        @endif
      </details>
    @endforeach
  </div>
</section>

<section class="rounded-2xl border border-slate-200/80 bg-white shadow-xs" data-result-history>
  <h2 class="flex items-center gap-2 border-b border-slate-100 px-5 py-4 text-base font-bold text-slate-900">
    <span class="material-symbols-outlined text-[20px] text-[#006948]" aria-hidden="true">history</span>
    {{ __('games.history_list.title') }}
  </h2>
  @if($history->isEmpty())
    <p class="px-5 py-8 text-center text-sm text-slate-500">{{ __('games.history_list.empty') }}</p>
  @else
    <ul class="divide-y divide-slate-100">
      @foreach($history as $result)
        @php $voided = $result->isVoided(); @endphp
        <li class="px-5 py-3.5 {{ $voided ? 'bg-slate-50/70' : '' }}" data-result-row="{{ $result->id }}">
          <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0 text-sm {{ $voided ? 'text-slate-400' : 'text-slate-800' }}">
              <p class="{{ $voided ? 'line-through' : '' }}">
                <span class="inline-flex items-center gap-1 font-semibold {{ $voided ? '' : 'text-[#006948]' }}">
                  <span class="material-symbols-outlined text-[16px]" aria-hidden="true">emoji_events</span>{{ $result->winner?->display_name }}
                </span>
                <span class="mx-1 text-slate-400">›</span>
                <span>{{ $result->losers->pluck('display_name')->implode(', ') }}</span>
              </p>
              <p class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                <span>{{ $result->created_at?->format('d/m H:i') }}</span>
                <span>{{ __('games.history_list.round', ['number' => $result->round?->number]) }}</span>
                <span>{{ $result->rule_item_name }} · {{ __('games.history_list.version', ['version' => $result->rule_version_number]) }}</span>
                <span class="font-semibold {{ $voided ? '' : 'text-slate-700' }}">{{ __('games.history_list.points_value', ['points' => ($result->points > 0 ? '+' : '').$result->points]) }}</span>
                @if($result->recordedByPlayer)
                  <span class="inline-flex items-center gap-0.5">
                    <span class="material-symbols-outlined text-[14px]" aria-hidden="true">edit_note</span>{{ __('games.history_list.recorded_by', ['name' => $result->recordedByPlayer->display_name]) }}
                  </span>
                @endif
              </p>
              @if($voided)
                <p class="mt-1 flex items-center gap-1 text-xs font-medium text-rose-700">
                  <span class="material-symbols-outlined text-[15px]" aria-hidden="true">block</span>
                  {{ __('games.history_list.voided') }} — {{ __('games.history_list.void_reason', ['reason' => $result->void_reason]) }}
                </p>
              @endif
            </div>
            @if($canVoid && ! $voided && $voidUrl)
              <button type="button" data-void-open data-void-url="{{ str_replace('__RESULT__', (string) $result->id, $voidUrl) }}"
                      class="inline-flex h-9 shrink-0 items-center gap-1 self-start rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-600 hover:border-rose-300 hover:bg-rose-50 hover:text-rose-700">
                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">undo</span>
                {{ __('games.void.action') }}
              </button>
            @endif
          </div>
        </li>
      @endforeach
    </ul>
  @endif
</section>
