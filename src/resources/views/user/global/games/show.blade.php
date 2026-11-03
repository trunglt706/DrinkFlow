<x-global.layout
  :title="'DrinkFlow - ' . $game->name"
  :user="$user"
  activeTab="games"
  :breadcrumbs="$breadcrumbs"
>
  <div class="space-y-6" data-games-page data-i18n="{{ json_encode(['copied' => __('games.room.link_copied')], JSON_UNESCAPED_UNICODE) }}">
    @include('user.global.games.partials.flash')

    <section class="flex flex-col gap-4 pb-6 border-b border-slate-200 sm:flex-row sm:items-end sm:justify-between">
      <div class="flex items-center gap-3 min-w-0">
        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl border border-emerald-100 bg-emerald-50 text-[#006948]">
          <span class="material-symbols-outlined text-[26px]" aria-hidden="true">{{ $game->icon ?: 'sports_esports' }}</span>
        </span>
        <div class="min-w-0">
          <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight break-words">{{ $game->name }}</h1>
          <p class="text-sm text-slate-500 mt-0.5">{{ __('games.history.subtitle') }}</p>
        </div>
      </div>
      {{-- Mobile: "Create room" on its own full-width row first, the two secondary links side by side below. --}}
      <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
        <a href="{{ route('user.me.utilities.rule-sets.index', $game) }}" class="inline-flex h-11 min-w-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-xl border border-slate-200 bg-white px-3 text-[13px] font-semibold text-slate-700 hover:bg-slate-50 sm:px-4 sm:text-sm">
          <span class="material-symbols-outlined text-[18px]" aria-hidden="true">rule</span>
          {{ __('games.history.rule_sets') }}
        </a>
        <a href="{{ route('user.me.utilities.channels.index') }}" class="inline-flex h-11 min-w-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-xl border border-slate-200 bg-white px-3 text-[13px] font-semibold text-slate-700 hover:bg-slate-50 sm:px-4 sm:text-sm">
          <span class="material-symbols-outlined text-[18px]" aria-hidden="true">campaign</span>
          {{ __('games.channels.title') }}
        </a>
        <a href="{{ route('user.me.utilities.rooms.create', $game) }}" data-create-room class="order-first col-span-2 inline-flex h-11 items-center justify-center gap-1.5 rounded-xl bg-[#006948] px-5 text-sm font-semibold text-white shadow-xs hover:bg-[#005137] sm:order-none">
          <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
          {{ __('games.history.create') }}
        </a>
      </div>
    </section>

    <nav class="flex gap-2 overflow-x-auto" aria-label="{{ __('global.common.filter') }}">
      @foreach([null => __('games.history.filter_all'), 'active' => __('games.history.filter_active'), 'finished' => __('games.history.filter_finished')] as $value => $label)
        @php $isCurrent = ($filter ?? '') === (string) $value; @endphp
        <a href="{{ route('user.me.utilities.show', array_filter(['game' => $game->slug, 'status' => $value ?: null])) }}"
           @if($isCurrent) aria-current="page" @endif
           class="inline-flex h-9 shrink-0 items-center rounded-full border px-4 text-xs font-semibold transition-colors {{ $isCurrent ? 'border-[#006948] bg-[#006948] text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
          {{ $label }}
        </a>
      @endforeach
    </nav>

    @if($rooms->isEmpty())
      <div class="p-10 border border-dashed border-slate-200 rounded-2xl bg-slate-50/50 text-center">
        <span class="material-symbols-outlined text-[36px] text-slate-300" aria-hidden="true">meeting_room</span>
        @if($filter)
          <p class="text-sm font-semibold text-slate-700 mt-2">{{ __('games.history.empty_filtered') }}</p>
        @else
          <p class="text-sm font-semibold text-slate-700 mt-2">{{ __('games.history.empty') }}</p>
          <p class="text-xs text-slate-500 mt-1">{{ __('games.history.empty_desc') }}</p>
          <a href="{{ route('user.me.utilities.rooms.create', $game) }}" class="mt-4 inline-flex h-11 items-center gap-1.5 rounded-xl bg-[#006948] px-5 text-sm font-semibold text-white hover:bg-[#005137]">
            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
            {{ __('games.history.create') }}
          </a>
        @endif
      </div>
    @else
      <ul class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs" data-room-list>
        @foreach($rooms as $room)
          <li class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:gap-5 sm:px-5">
            <a href="{{ route('user.me.utilities.rooms.show', [$game, $room]) }}" class="min-w-0 flex-1 group">
              <span class="flex flex-wrap items-center gap-2">
                <span class="text-base font-bold text-slate-900 group-hover:text-[#006948] break-words">{{ $room->name }}</span>
                <x-game.status-badge :status="$room->status" />
              </span>
              <span class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-500">
                <span class="inline-flex items-center gap-1">
                  <span class="material-symbols-outlined text-[15px]" aria-hidden="true">group</span>
                  {{ __('games.history.players_count', ['count' => $room->players_count]) }}
                </span>
                <span class="inline-flex items-center gap-1">
                  <span class="material-symbols-outlined text-[15px]" aria-hidden="true">schedule</span>
                  {{ __('games.history.last_activity', ['time' => ($room->last_activity_at ?? $room->created_at)->format('d/m/Y H:i')]) }}
                </span>
              </span>
            </a>
            <div class="grid grid-cols-2 gap-2 sm:flex">
              {{-- A finished room shows no link action: its status badge next to the name already says it is finished. --}}
              @php
                $isFinishedRoom = $room->status === \App\Enums\GameRoomStatus::Finished;
                $canResend = $isFinishedRoom && $room->notificationChannel?->is_active;
              @endphp
              @if($canResend)
                {{-- Send the final leaderboard to the room's notification channel again. --}}
                <form method="POST" action="{{ route('user.me.utilities.rooms.results.notify', [$game, $room]) }}" class="col-span-2 sm:col-span-1" data-confirm="{{ __('games.history.resend_confirm', ['channel' => $room->notificationChannel->name]) }}">
                  @csrf
                  <button type="submit" data-list-resend-results title="{{ $room->notificationChannel->name }}"
                          class="inline-flex h-10 w-full items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto">
                    <span class="material-symbols-outlined text-[17px]" aria-hidden="true">send</span>
                    {{ __('games.room.resend_results') }}
                  </button>
                </form>
              @endif
              @unless($isFinishedRoom)
                @if($room->publicUrl())
                  <button type="button" data-copy-link="{{ $room->publicUrl() }}" class="inline-flex h-10 flex-1 sm:flex-none items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                    <span class="material-symbols-outlined text-[17px]" aria-hidden="true">link</span>
                    {{ __('games.history.copy_link') }}
                  </button>
                @else
                  <span class="inline-flex h-10 items-center gap-1 px-1 text-xs text-slate-400">
                    <span class="material-symbols-outlined text-[16px]" aria-hidden="true">link_off</span>
                    {{ __('games.history.link_revoked') }}
                  </span>
                @endif
              @endunless
              {{-- Clone: opens the create form pre-filled with this room's rule set and players. --}}
              <a href="{{ route('user.me.utilities.rooms.create', ['game' => $game->slug, 'from' => $room->id]) }}" data-clone-room
                 class="inline-flex h-10 flex-1 sm:flex-none items-center justify-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                 title="{{ __('games.history.clone_hint') }}">
                <span class="material-symbols-outlined text-[17px]" aria-hidden="true">content_copy</span>
                {{ __('games.history.clone') }}
              </a>
              <a href="{{ route('user.me.utilities.rooms.show', [$game, $room]) }}" class="{{ $isFinishedRoom ? '' : 'col-span-2 sm:col-span-1' }} inline-flex h-10 flex-1 sm:flex-none items-center justify-center gap-1 rounded-xl bg-emerald-50 px-3 text-xs font-semibold text-[#006948] border border-emerald-100 hover:bg-emerald-100">
                {{ __('games.history.open') }}
                <span class="material-symbols-outlined text-[17px]" aria-hidden="true">arrow_forward</span>
              </a>
            </div>
          </li>
        @endforeach
      </ul>
      <div>{{ $rooms->links() }}</div>
    @endif
  </div>

  @include('user.global.games.partials.confirm-dialog')
</x-global.layout>
