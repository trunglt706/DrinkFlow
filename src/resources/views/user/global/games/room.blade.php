@php
  $isPlaying = $room->status === \App\Enums\GameRoomStatus::Playing;
  $isWaiting = $room->status === \App\Enums\GameRoomStatus::Waiting;
  $isFinished = $room->status === \App\Enums\GameRoomStatus::Finished;
  $activePlayers = $players->where('is_active', true)->values();
  $publicUrl = $room->publicUrl();
  $routeArgs = [$game, $room];
@endphp
<x-global.layout
  :title="'DrinkFlow - ' . $room->name"
  :user="$user"
  activeTab="games"
  :breadcrumbs="$breadcrumbs"
>
  <div class="space-y-5 pb-24 md:pb-0" data-games-page
       data-i18n="{{ json_encode(['copied' => __('games.room.link_copied'), 'confirm' => __('global.common.confirm'), 'cancel' => __('games.record.cancel')], JSON_UNESCAPED_UNICODE) }}">
    @include('user.global.games.partials.flash')

    {{-- Header: name, status, public link and lifecycle actions --}}
    <section class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs sm:p-6">
      <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 break-words">{{ $room->name }}</h1>
            <x-game.status-badge :status="$room->status" data-room-status />
          </div>
          <p class="mt-1 text-xs text-slate-500">
            {{ __('games.room.rule_version', ['name' => $room->ruleSet->name, 'version' => $room->ruleVersion->version]) }}
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          @if($isWaiting)
            <form method="POST" action="{{ route('user.me.games.rooms.start', $routeArgs) }}" data-confirm="{{ __('games.room.start_confirm') }}" class="flex-1 sm:flex-none">
              @csrf
              <button type="submit" data-start-room class="inline-flex h-11 w-full items-center justify-center gap-1.5 rounded-xl bg-[#006948] px-5 text-sm font-semibold text-white hover:bg-[#005137]">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">play_arrow</span>{{ __('games.room.start') }}
              </button>
            </form>
          @endif
          @if($isPlaying)
            <form method="POST" action="{{ route('user.me.games.rooms.rounds.store', $routeArgs) }}" data-confirm="{{ __('games.room.new_round_confirm') }}" class="flex-1 sm:flex-none">
              @csrf
              <input type="hidden" name="current_round" value="{{ $summary['current_round'] }}">
              <button type="submit" class="inline-flex h-11 w-full items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add_circle</span>{{ __('games.room.new_round') }}
              </button>
            </form>
          @endif
          @unless($isFinished)
            <form method="POST" action="{{ route('user.me.games.rooms.finish', $routeArgs) }}" data-confirm="{{ __('games.room.finish_confirm') }}" class="flex-1 sm:flex-none">
              @csrf
              <button type="submit" class="inline-flex h-11 w-full items-center justify-center gap-1.5 rounded-xl border border-rose-200 bg-white px-4 text-sm font-semibold text-rose-700 hover:bg-rose-50">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">flag</span>{{ __('games.room.finish') }}
              </button>
            </form>
          @endunless
        </div>
      </div>

      {{-- Public link --}}
      <div class="mt-5 rounded-xl border border-slate-200 bg-slate-50/60 p-4">
        <div class="flex items-center gap-2 text-sm font-semibold text-slate-800">
          <span class="material-symbols-outlined text-[18px] text-[#006948]" aria-hidden="true">public</span>{{ __('games.room.public_link') }}
        </div>
        <p class="mt-0.5 text-xs text-slate-500">{{ __('games.room.public_link_hint') }}</p>
        @if($isFinished)
          <p class="mt-3 flex items-center gap-1 text-xs font-medium text-slate-600" data-public-link-closed>
            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">lock</span>{{ __('games.room.link_closed') }}
          </p>
        @elseif($publicUrl)
          <div class="mt-3 flex flex-col gap-2 sm:flex-row">
            <input type="text" readonly value="{{ $publicUrl }}" aria-label="{{ __('games.room.public_link') }}" data-public-link
                   class="h-11 min-w-0 flex-1 rounded-xl border border-slate-200 bg-white px-3 font-mono text-xs text-slate-700">
            <div class="flex gap-2">
              <button type="button" data-copy-link="{{ $publicUrl }}" class="inline-flex h-11 flex-1 items-center justify-center gap-1.5 rounded-xl bg-[#006948] px-4 text-xs font-semibold text-white hover:bg-[#005137]">
                <span class="material-symbols-outlined text-[17px]" aria-hidden="true">content_copy</span>{{ __('games.room.copy_link') }}
              </button>
              <button type="button" data-qr-toggle="{{ $publicUrl }}" aria-expanded="false" class="inline-flex h-11 items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                <span class="material-symbols-outlined text-[17px]" aria-hidden="true">qr_code_2</span>{{ __('games.room.qr') }}
              </button>
            </div>
          </div>
          <div class="mt-3 hidden flex-col items-center gap-1" data-qr-panel>
            <canvas data-qr-canvas class="rounded-lg border border-slate-200 bg-white p-2"></canvas>
            <span class="text-xs text-slate-500">{{ __('games.room.qr_title') }}</span>
          </div>
        @else
          <p class="mt-3 flex items-center gap-1 text-xs font-medium text-slate-600">
            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">link_off</span>{{ __('games.history.link_revoked') }}
          </p>
        @endif
        @unless($isFinished)
        <div class="mt-3 flex flex-wrap gap-2">
          <form method="POST" action="{{ route('user.me.games.rooms.public-link.regenerate', $routeArgs) }}" @if($publicUrl) data-confirm="{{ __('games.room.regenerate_confirm') }}" @endif>
            @csrf
            <button type="submit" class="inline-flex h-9 items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-600 hover:bg-slate-50">
              <span class="material-symbols-outlined text-[16px]" aria-hidden="true">autorenew</span>{{ __('games.room.regenerate_link') }}
            </button>
          </form>
          @if($publicUrl)
            <form method="POST" action="{{ route('user.me.games.rooms.public-link.revoke', $routeArgs) }}" data-confirm="{{ __('games.room.revoke_confirm') }}">
              @csrf
              @method('DELETE')
              <button type="submit" class="inline-flex h-9 items-center gap-1 rounded-lg border border-slate-200 bg-white px-3 text-xs font-semibold text-rose-700 hover:bg-rose-50">
                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">link_off</span>{{ __('games.room.revoke_link') }}
              </button>
            </form>
          @endif
        </div>
        @endunless
      </div>
    </section>

    {{-- State notices --}}
    @if($isWaiting)
      <div class="flex items-start gap-2.5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">hourglass_top</span>
        <span>{{ __('games.room.waiting_notice') }}</span>
      </div>
    @elseif($isFinished)
      <div class="flex items-start gap-2.5 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-700">
        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">flag</span>
        <span>{{ __('games.room.finished_notice') }}</span>
      </div>
    @endif
    @if($hasNewerRuleVersion && ! $isFinished)
      <div class="flex flex-col gap-3 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-900 sm:flex-row sm:items-center sm:justify-between">
        <span class="flex items-start gap-2.5">
          <span class="material-symbols-outlined text-[20px]" aria-hidden="true">new_releases</span>
          <span>{{ __('games.room.new_version_available', ['name' => $room->ruleSet->name, 'version' => $latestRuleVersion]) }}</span>
        </span>
        <form method="POST" action="{{ route('user.me.games.rooms.rule-version', $routeArgs) }}" data-confirm="{{ __('games.room.apply_version_confirm') }}">
          @csrf
          <button type="submit" class="inline-flex h-10 items-center gap-1.5 rounded-xl bg-sky-700 px-4 text-xs font-semibold text-white hover:bg-sky-800">
            <span class="material-symbols-outlined text-[17px]" aria-hidden="true">sync</span>{{ __('games.room.apply_version') }}
          </button>
        </form>
      </div>
    @endif

    {{-- Summary + quick action --}}
    <section class="grid grid-cols-3 gap-3 sm:gap-4">
      <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs">
        <span class="block text-xs font-medium text-slate-500">{{ __('games.room.rounds') }}</span>
        <span class="mt-1 block text-2xl font-bold tabular-nums text-slate-900" data-summary-rounds>{{ $summary['rounds'] }}</span>
      </div>
      <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs">
        <span class="block text-xs font-medium text-slate-500">{{ __('games.room.results') }}</span>
        <span class="mt-1 block text-2xl font-bold tabular-nums text-slate-900" data-summary-results>{{ $summary['results'] }}</span>
      </div>
      <div class="rounded-2xl border border-slate-200/80 bg-white p-4 shadow-xs">
        <span class="block text-xs font-medium text-slate-500">{{ __('games.room.current_round') }}</span>
        <span class="mt-1 block text-2xl font-bold tabular-nums text-slate-900">{{ $summary['current_round'] ?? '—' }}</span>
      </div>
    </section>

    @if($isPlaying)
      <div class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white/95 p-3 backdrop-blur md:static md:border-0 md:bg-transparent md:p-0">
        <button type="button" data-record-open class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#006948] text-base font-semibold text-white shadow-md hover:bg-[#005137] md:w-auto md:px-8">
          <span class="material-symbols-outlined text-[22px]" aria-hidden="true">add_task</span>{{ __('games.room.record') }}
        </button>
      </div>
    @endif

    <div class="grid grid-cols-1 gap-5 lg:grid-cols-5">
      <div class="min-w-0 space-y-5 lg:col-span-3">
        <x-game.scoreboard
          :leaderboard="$leaderboard"
          :head-to-head="$headToHead"
          :history="$history"
          :can-void="$isPlaying"
          :void-url="route('user.me.games.rooms.results.void', [$game, $room, '__RESULT__'])"
        />
      </div>

      {{-- Players --}}
      <aside class="min-w-0 lg:col-span-2">
        <section class="rounded-2xl border border-slate-200/80 bg-white shadow-xs" data-players-panel>
          <h2 class="flex items-center gap-2 border-b border-slate-100 px-5 py-4 text-base font-bold text-slate-900">
            <span class="material-symbols-outlined text-[20px] text-[#006948]" aria-hidden="true">group</span>{{ __('games.players.title') }}
          </h2>
          @if($players->isEmpty())
            <p class="px-5 py-6 text-center text-sm text-slate-500">{{ __('games.room.no_players') }}</p>
          @endif
          <ul class="divide-y divide-slate-100">
            @foreach($players as $player)
              <li class="px-5 py-3">
                @if($isFinished)
                  <span class="text-sm font-semibold text-slate-800">{{ $player->display_name }}</span>
                  @unless($player->is_active)<span class="ml-1 text-xs text-slate-500">({{ __('games.leaderboard.inactive') }})</span>@endunless
                @else
                  <form method="POST" action="{{ route('user.me.games.rooms.players.update', [$game, $room, $player]) }}" class="flex items-center gap-2">
                    @csrf
                    @method('PATCH')
                    <input type="text" name="display_name" value="{{ $player->display_name }}" maxlength="50" required aria-label="{{ __('games.create.player_name') }}"
                           class="h-10 min-w-0 flex-1 rounded-lg border border-slate-200 bg-slate-50/70 px-3 text-sm focus:border-[#006948] focus:bg-white focus:ring-1 focus:ring-[#006948]">
                    <label class="flex shrink-0 items-center gap-1 text-xs text-slate-600">
                      <input type="checkbox" name="is_active" value="1" @checked($player->is_active) class="h-4 w-4 rounded text-[#006948] focus:ring-[#006948]">
                      {{ __('games.players.active') }}
                    </label>
                    <button type="submit" class="h-10 shrink-0 rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">{{ __('games.players.rename') }}</button>
                  </form>
                @endif
              </li>
            @endforeach
          </ul>
          @unless($isFinished)
            <form method="POST" action="{{ route('user.me.games.rooms.players.store', $routeArgs) }}" class="flex gap-2 border-t border-slate-100 px-5 py-4">
              @csrf
              <input type="text" name="display_name" maxlength="50" required placeholder="{{ __('games.players.add_placeholder') }}" aria-label="{{ __('games.players.add_placeholder') }}"
                     class="h-10 min-w-0 flex-1 rounded-lg border border-slate-200 bg-slate-50/70 px-3 text-sm focus:border-[#006948] focus:bg-white focus:ring-1 focus:ring-[#006948]">
              <button type="submit" class="inline-flex h-10 shrink-0 items-center gap-1 rounded-lg bg-emerald-50 border border-emerald-100 px-3 text-xs font-semibold text-[#006948] hover:bg-emerald-100">
                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">person_add</span>{{ __('games.players.add') }}
              </button>
            </form>
          @endunless
        </section>
      </aside>
    </div>
  </div>

  {{-- Record result sheet --}}
  @if($isPlaying)
    <dialog data-record-dialog data-url="{{ route('user.me.games.rooms.results.store', $routeArgs) }}" data-round="{{ $summary['current_round'] }}"
            data-i18n="{{ json_encode([
                'needCategory' => __('games.record.need_category'),
                'needWinner' => __('games.record.need_winner'),
                'needLosers' => __('games.record.need_losers'),
                'networkError' => __('games.record.network_error'),
                'error' => __('games.record.error'),
                'summary' => __('games.record.review_summary', ['winner' => ':winner', 'losers' => ':losers', 'category' => ':category', 'points' => ':points']),
            ], JSON_UNESCAPED_UNICODE) }}"
            class="!mx-0 !mb-0 !mt-auto sm:!m-auto w-full max-w-none rounded-t-2xl p-0 backdrop:bg-slate-900/50 sm:max-w-lg sm:rounded-2xl">
      <form method="dialog" class="flex max-h-[85vh] flex-col" data-record-form data-no-loading>
        <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
          <div>
            <h2 class="text-base font-bold text-slate-900">{{ __('games.record.title') }}</h2>
            <p class="text-xs text-slate-500">{{ $room->name }} · {{ __('games.history_list.round', ['number' => $summary['current_round']]) }}</p>
          </div>
          <button type="button" data-record-close class="flex h-9 w-9 items-center justify-center rounded-lg text-slate-500 hover:bg-slate-100" aria-label="{{ __('global.common.close') }}">
            <span class="material-symbols-outlined text-[20px]" aria-hidden="true">close</span>
          </button>
        </div>

        <div class="flex-1 overflow-y-auto px-5 py-4">
          <div data-record-step="1">
            <h3 class="mb-2 text-sm font-semibold text-slate-800">{{ __('games.record.step_category') }}</h3>
            @if($ruleItems->isEmpty())
              <p class="text-sm text-slate-500">{{ __('games.record.no_categories') }}</p>
            @endif
            <div class="grid gap-2">
              @foreach($ruleItems as $item)
                <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-slate-200 px-4 py-3 has-[:checked]:border-[#006948] has-[:checked]:bg-emerald-50">
                  <span class="flex items-center gap-3">
                    <input type="radio" name="rule_item_id" value="{{ $item->id }}" data-name="{{ $item->name }}" data-points="{{ $item->points }}" class="h-5 w-5 text-[#006948] focus:ring-[#006948]">
                    <span class="text-sm font-semibold text-slate-900">{{ $item->name }}</span>
                  </span>
                  <span class="text-sm font-bold tabular-nums text-[#006948]">{{ ($item->points > 0 ? '+' : '').$item->points }}</span>
                </label>
              @endforeach
            </div>
          </div>

          <div data-record-step="2" hidden>
            <h3 class="mb-2 text-sm font-semibold text-slate-800">{{ __('games.record.step_winner') }}</h3>
            <div class="grid grid-cols-2 gap-2">
              @foreach($activePlayers as $player)
                <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 px-3 py-3 has-[:checked]:border-[#006948] has-[:checked]:bg-emerald-50">
                  <input type="radio" name="winner_id" value="{{ $player->id }}" data-name="{{ $player->display_name }}" class="h-5 w-5 text-[#006948] focus:ring-[#006948]">
                  <span class="min-w-0 truncate text-sm font-semibold text-slate-900">{{ $player->display_name }}</span>
                </label>
              @endforeach
            </div>
          </div>

          <div data-record-step="3" hidden>
            <h3 class="mb-2 text-sm font-semibold text-slate-800">{{ __('games.record.step_losers') }}</h3>
            <div class="grid grid-cols-2 gap-2">
              @foreach($activePlayers as $player)
                <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 px-3 py-3 has-[:checked]:border-rose-400 has-[:checked]:bg-rose-50 has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-40" data-loser-option>
                  <input type="checkbox" name="loser_ids[]" value="{{ $player->id }}" data-name="{{ $player->display_name }}" class="h-5 w-5 rounded text-rose-600 focus:ring-rose-500">
                  <span class="min-w-0 truncate text-sm font-semibold text-slate-900">{{ $player->display_name }}</span>
                </label>
              @endforeach
            </div>
          </div>

          <div data-record-step="4" hidden>
            <h3 class="mb-2 text-sm font-semibold text-slate-800">{{ __('games.record.step_review') }}</h3>
            <p class="rounded-xl border border-emerald-100 bg-emerald-50 p-4 text-sm font-medium text-emerald-900" data-record-summary></p>
          </div>

          <p class="mt-3 hidden rounded-lg bg-rose-50 px-3 py-2 text-sm text-rose-700" role="alert" data-record-error></p>
        </div>

        <div class="flex gap-2 border-t border-slate-100 px-5 py-4">
          <button type="button" data-record-back class="h-12 flex-1 rounded-xl border border-slate-200 text-sm font-semibold text-slate-700 hover:bg-slate-50">{{ __('games.record.back') }}</button>
          <button type="button" data-record-next class="h-12 flex-[2] rounded-xl bg-[#006948] text-sm font-semibold text-white hover:bg-[#005137]">{{ __('games.record.next') }}</button>
          <button type="button" data-record-submit hidden class="h-12 flex-[2] rounded-xl bg-[#006948] text-sm font-semibold text-white hover:bg-[#005137] disabled:opacity-60">{{ __('games.record.confirm') }}</button>
        </div>
      </form>
    </dialog>

    {{-- Void a result --}}
    <dialog data-void-dialog class="!m-auto w-[calc(100%-2rem)] max-w-md rounded-2xl p-0 backdrop:bg-slate-900/50">
      <form method="POST" data-void-form class="p-5">
        @csrf
        <h2 class="text-base font-bold text-slate-900">{{ __('games.void.title') }}</h2>
        <p class="mt-1 text-xs text-slate-500">{{ __('games.void.hint') }}</p>
        <label for="void-reason" class="mt-4 block text-sm font-semibold text-slate-800">{{ __('games.void.reason') }}</label>
        <input id="void-reason" name="reason" type="text" maxlength="255" required placeholder="{{ __('games.void.reason_placeholder') }}"
               class="mt-1.5 h-11 w-full rounded-xl border border-slate-200 px-3 text-base focus:border-[#006948] focus:ring-1 focus:ring-[#006948]">
        <div class="mt-5 flex gap-2">
          <button type="button" data-void-cancel class="h-11 flex-1 rounded-xl border border-slate-200 text-sm font-semibold text-slate-700 hover:bg-slate-50">{{ __('games.record.cancel') }}</button>
          <button type="submit" class="h-11 flex-1 rounded-xl bg-rose-600 text-sm font-semibold text-white hover:bg-rose-700">{{ __('games.void.submit') }}</button>
        </div>
      </form>
    </dialog>
  @endif

  @include('user.global.games.partials.confirm-dialog')
</x-global.layout>
