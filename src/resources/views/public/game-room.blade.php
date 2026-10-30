@php
  $isPlaying = $room->status === \App\Enums\GameRoomStatus::Playing;
  $activePlayers = $players->where('is_active', true)->values();
  $opponents = $selected ? $activePlayers->where('id', '!=', $selected->id)->values() : collect();
  $playerUrl = fn ($id) => route('public.games.rooms.show', ['token' => $token, 'player' => $id]);
  $showBottomBar = $isPlaying && ($canRecord || $isOwner || ! $selected);
@endphp
<x-game.play-layout :title="$room->name" :back-url="$isOwner ? route('user.me.games.rooms.show', [$room->game, $room]) : null">
  <main class="mx-auto max-w-3xl space-y-4 px-4 pt-2 {{ $showBottomBar ? 'pb-28' : 'pb-10' }}" data-play-page>
    {{-- Room header --}}
    <section class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs">
      <div class="flex items-start gap-3">
        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-[#006948]">
          <span class="material-symbols-outlined text-[24px]" aria-hidden="true">{{ $room->game?->icon ?: 'sports_esports' }}</span>
        </span>
        <div class="min-w-0 flex-1">
          <h1 class="text-lg font-bold leading-tight text-slate-900 break-words">{{ $room->name }}</h1>
          <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-slate-500">
            <x-game.status-badge :status="$room->status" />
            @if($summary['current_round'])
              <span>{{ __('games.play.round', ['number' => $summary['current_round']]) }}</span>
              <span aria-hidden="true">·</span>
            @endif
            <span>{{ __('games.play.results_count', ['count' => $summary['results']]) }}</span>
          </div>
        </div>
        <button type="button" data-play-share-open class="flex h-10 shrink-0 items-center gap-1 rounded-xl border border-slate-200 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50" aria-haspopup="dialog">
          <span class="material-symbols-outlined text-[18px]" aria-hidden="true">share</span>{{ __('games.play.share') }}
        </button>
      </div>
    </section>

    @if(session('status'))
      <div class="flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-900" role="status">
        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">check_circle</span>{{ session('status') }}
      </div>
    @endif
    @if(session('error'))
      <div class="flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-800" role="alert">
        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">error</span>{{ session('error') }}
      </div>
    @endif

    {{-- Who am I --}}
    <section id="who" class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs" data-choose-player>
      @if($selected)
        <div class="flex items-center justify-between gap-3">
          <p class="flex min-w-0 items-center gap-2 text-sm font-semibold text-slate-900">
            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-800 text-sm font-bold" aria-hidden="true">{{ mb_strtoupper(mb_substr($selected->display_name, 0, 1)) }}</span>
            <span class="truncate">{{ __('games.play.you', ['name' => $selected->display_name]) }}</span>
          </p>
          <details class="relative shrink-0" data-change-player>
            <summary class="flex h-9 cursor-pointer list-none items-center gap-1 rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-600">
              <span class="material-symbols-outlined text-[16px]" aria-hidden="true">swap_horiz</span>{{ __('games.play.change') }}
            </summary>
            <div class="absolute right-0 z-20 mt-2 w-60 rounded-xl border border-slate-200 bg-white p-2 shadow-lg">
              @foreach($players as $player)
                <a href="{{ $playerUrl($player->id) }}" class="flex h-10 items-center rounded-lg px-3 text-sm {{ $player->id === $selected->id ? 'bg-amber-50 font-semibold text-amber-900' : 'text-slate-700 hover:bg-slate-50' }}">{{ $player->display_name }}</a>
              @endforeach
              <a href="{{ $playerUrl($clearPlayer) }}" class="mt-1 flex h-10 items-center rounded-lg border-t border-slate-100 px-3 text-xs font-semibold text-slate-500">{{ __('games.public.clear') }}</a>
            </div>
          </details>
        </div>
        @if($selectedRow)
          <dl class="mt-3 grid grid-cols-4 gap-2 text-center" data-my-stats>
            @foreach([['rank', '#'.$selectedRow['rank']], ['points', $selectedRow['points']], ['wins', $selectedRow['wins']], ['losses', $selectedRow['losses']]] as [$key, $value])
              <div class="rounded-xl bg-slate-50 py-2">
                <dt class="text-[11px] text-slate-500">{{ __('games.play.'.$key) }}</dt>
                <dd class="text-lg font-bold tabular-nums {{ $key === 'points' ? 'text-[#006948]' : 'text-slate-900' }}">{{ $value }}</dd>
              </div>
            @endforeach
          </dl>
        @endif
      @else
        <h2 class="text-base font-bold text-slate-900">{{ __('games.public.choose_name') }}</h2>
        <p class="mt-0.5 text-xs text-slate-500">{{ __('games.public.choose_hint') }}</p>
        @if($players->isEmpty())
          <p class="mt-3 text-sm text-slate-500">{{ __('games.room.no_players') }}</p>
        @else
          <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
            @foreach($players as $player)
              <a href="{{ $playerUrl($player->id) }}" data-player-choice
                 class="flex h-12 items-center justify-center rounded-xl border px-3 text-sm font-semibold {{ $player->is_active ? 'border-slate-200 bg-white text-slate-800 hover:border-[#006948] hover:bg-emerald-50' : 'border-dashed border-slate-200 bg-slate-50 text-slate-400' }}">
                <span class="truncate">{{ $player->display_name }}</span>
              </a>
            @endforeach
          </div>
        @endif
      @endif
    </section>

    {{-- State notices --}}
    @if($room->status === \App\Enums\GameRoomStatus::Waiting)
      <p class="flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">hourglass_top</span>{{ __('games.play.waiting') }}
      </p>
    @elseif($selected && ! $selected->is_active)
      <p class="flex items-start gap-2 rounded-xl border border-slate-200 bg-slate-100 px-4 py-3 text-sm text-slate-700">
        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">person_off</span>{{ __('games.play.inactive_player') }}
      </p>
    @endif

    <x-game.scoreboard
      :leaderboard="$leaderboard"
      :head-to-head="$headToHead"
      :history="$history"
      :highlight-player-id="$selected?->id"
    />
  </main>

  {{-- Sticky actions: record (own wins) and, for the signed-in owner, new round --}}
  @if($showBottomBar)
    <div class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 backdrop-blur" style="padding-bottom: env(safe-area-inset-bottom);">
      <div class="mx-auto flex max-w-3xl gap-2 p-3">
        @if($canRecord)
          <button type="button" data-play-record-open class="flex h-12 flex-[2] items-center justify-center gap-2 rounded-xl bg-[#006948] text-base font-semibold text-white shadow-md hover:bg-[#005137]">
            <span class="material-symbols-outlined text-[22px]" aria-hidden="true">add_task</span>{{ __('games.play.record') }}
          </button>
        @elseif(! $selected)
          <a href="#who" class="flex h-12 flex-[2] items-center justify-center gap-2 rounded-xl border border-[#006948] text-sm font-semibold text-[#006948]">
            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">person_search</span>{{ __('games.play.choose_to_record') }}
          </a>
        @endif
        @if($isOwner)
          <form method="POST" action="{{ route('user.me.games.rooms.rounds.store', [$room->game, $room]) }}" data-confirm="{{ __('games.room.new_round_confirm') }}" class="flex flex-1">
            @csrf
            <button type="submit" data-play-new-round class="flex h-12 w-full items-center justify-center gap-1.5 whitespace-nowrap rounded-xl border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-700 hover:bg-slate-50">
              <span class="material-symbols-outlined text-[20px]" aria-hidden="true">add_circle</span>{{ __('games.play.new_round') }}
            </button>
          </form>
        @endif
      </div>
    </div>
  @endif

  {{-- Record sheet: the winner is the visitor's player; only category and losers are picked --}}
  @if($canRecord)
    <dialog data-play-record-dialog data-url="{{ route('public.games.rooms.results.store', $token) }}"
            data-i18n="{{ json_encode([
                'summary' => __('games.play.summary', ['winner' => ':winner', 'losers' => ':losers', 'category' => ':category', 'points' => ':points']),
                'summaryEmpty' => __('games.play.summary_empty'),
                'networkError' => __('games.record.network_error'),
                'error' => __('games.record.error'),
            ], JSON_UNESCAPED_UNICODE) }}"
            data-winner="{{ $selected->display_name }}"
            class="!mx-0 !mb-0 !mt-auto w-full max-w-none rounded-t-2xl p-0 backdrop:bg-slate-900/50 sm:!m-auto sm:max-w-lg sm:rounded-2xl">
      <div class="flex max-h-[88vh] flex-col" data-play-record-form>
        <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
          <div class="min-w-0">
            <h2 class="flex items-center gap-1.5 text-base font-bold text-slate-900">
              <span class="material-symbols-outlined text-[20px] text-amber-500" style="font-variation-settings: 'FILL' 1;" aria-hidden="true">emoji_events</span>{{ __('games.play.sheet_title') }}
            </h2>
            <p class="truncate text-xs text-slate-500">{{ __('games.play.sheet_subtitle', ['name' => $selected->display_name, 'round' => $summary['current_round']]) }}</p>
          </div>
          <button type="button" data-play-record-close class="flex h-10 w-10 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100" aria-label="{{ __('global.common.close') }}">
            <span class="material-symbols-outlined text-[22px]" aria-hidden="true">close</span>
          </button>
        </div>

        <div class="flex-1 space-y-5 overflow-y-auto px-4 py-4">
          <fieldset>
            <legend class="mb-2 text-sm font-semibold text-slate-800">{{ __('games.play.pick_category') }}</legend>
            @if($ruleItems->isEmpty())
              <p class="text-sm text-slate-500">{{ __('games.record.no_categories') }}</p>
            @endif
            <div class="flex flex-wrap gap-2">
              @foreach($ruleItems as $item)
                <label class="cursor-pointer">
                  <input type="radio" name="rule_item_id" value="{{ $item->id }}" data-name="{{ $item->name }}" data-points="{{ $item->points }}" class="peer sr-only" @checked($loop->first)>
                  <span class="flex h-11 items-center gap-2 rounded-full border border-slate-200 px-4 text-sm font-semibold text-slate-700 peer-checked:border-[#006948] peer-checked:bg-[#006948] peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-400">
                    {{ $item->name }}<span class="tabular-nums opacity-80">{{ ($item->points > 0 ? '+' : '').$item->points }}</span>
                  </span>
                </label>
              @endforeach
            </div>
          </fieldset>

          <fieldset>
            <legend class="mb-2 text-sm font-semibold text-slate-800">{{ __('games.play.pick_losers') }}</legend>
            @if($opponents->isEmpty())
              <p class="text-sm text-slate-500">{{ __('games.play.no_opponents') }}</p>
            @endif
            <div class="grid grid-cols-2 gap-2">
              @foreach($opponents as $player)
                <label class="cursor-pointer">
                  <input type="radio" name="loser_id" value="{{ $player->id }}" data-name="{{ $player->display_name }}" class="peer sr-only" @checked($loop->first)>
                  <span class="flex h-12 items-center gap-2 rounded-xl border border-slate-200 px-3 text-sm font-semibold text-slate-800 peer-checked:border-rose-400 peer-checked:bg-rose-50 peer-checked:text-rose-800 peer-focus-visible:ring-2 peer-focus-visible:ring-rose-300">
                    <span class="material-symbols-outlined text-[18px] text-slate-300" aria-hidden="true">radio_button_unchecked</span>
                    <span class="truncate">{{ $player->display_name }}</span>
                  </span>
                </label>
              @endforeach
            </div>
          </fieldset>

          <p class="hidden rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700" role="alert" data-play-record-error></p>
        </div>

        <div class="border-t border-slate-100 px-4 py-3" style="padding-bottom: max(0.75rem, env(safe-area-inset-bottom));">
          <p class="mb-2 text-center text-xs text-slate-600" data-play-record-summary aria-live="polite"></p>
          <button type="button" data-play-record-submit disabled class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#006948] text-base font-semibold text-white hover:bg-[#005137] disabled:opacity-40">
            {{ __('games.play.confirm') }}
          </button>
        </div>
      </div>
    </dialog>
  @endif

  {{-- Share: QR code + link of this public page --}}
  @php $shareUrl = route('public.games.rooms.show', $token); @endphp
  <dialog data-play-share-dialog data-url="{{ $shareUrl }}" data-title="{{ $room->name }}"
          data-i18n="{{ json_encode(['copied' => __('games.play.copied')], JSON_UNESCAPED_UNICODE) }}"
          class="!m-auto w-[calc(100%-2rem)] max-w-sm rounded-2xl p-0 backdrop:bg-slate-900/50">
    <div class="p-5 text-center">
      <div class="flex items-start justify-between gap-3 text-left">
        <div class="min-w-0">
          <h2 class="text-base font-bold text-slate-900">{{ __('games.play.share_title') }}</h2>
          <p class="mt-0.5 text-xs text-slate-500">{{ __('games.play.share_hint') }}</p>
        </div>
        <button type="button" data-play-share-close class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100" aria-label="{{ __('global.common.close') }}">
          <span class="material-symbols-outlined text-[20px]" aria-hidden="true">close</span>
        </button>
      </div>
      <canvas data-play-share-qr class="mx-auto mt-4 rounded-xl border border-slate-200 bg-white p-2" width="232" height="232" aria-label="{{ __('games.room.qr_title') }}" role="img"></canvas>
      <p class="mt-2 truncate text-sm font-semibold text-slate-800">{{ $room->name }}</p>
      <input type="text" readonly value="{{ $shareUrl }}" data-play-share-link aria-label="{{ __('games.room.public_link') }}"
             class="mt-3 h-11 w-full rounded-xl border border-slate-200 bg-slate-50 px-3 font-mono text-xs text-slate-700">
      <div class="mt-3 grid grid-cols-2 gap-2">
        <button type="button" data-play-share-copy class="flex h-11 items-center justify-center gap-1.5 rounded-xl bg-[#006948] text-sm font-semibold text-white hover:bg-[#005137]">
          <span class="material-symbols-outlined text-[18px]" aria-hidden="true">content_copy</span>{{ __('games.play.copy_link') }}
        </button>
        <button type="button" data-play-share-native class="flex h-11 items-center justify-center gap-1.5 rounded-xl border border-slate-200 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-40" disabled>
          <span class="material-symbols-outlined text-[18px]" aria-hidden="true">ios_share</span>{{ __('games.play.native_share') }}
        </button>
      </div>
    </div>
  </dialog>

  @if($isOwner)
    @include('user.global.games.partials.confirm-dialog')
  @endif
</x-game.play-layout>
