{{-- Public game room that has not started yet: basic room info, the waiting status and a reload action only. --}}
<x-game.play-layout :title="$room->name" :back-url="$isOwner ? route('user.me.games.rooms.show', [$room->game, $room]) : null" :socket-token-url="route('public.games.rooms.socket-token', $token)">
  <main class="mx-auto max-w-md px-4 py-10" data-play-waiting>
    <section class="rounded-2xl border border-slate-200/80 bg-white p-6 text-center shadow-xs">
      <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-emerald-50 text-[#006948]">
        <span class="material-symbols-outlined text-[30px]" aria-hidden="true">{{ $room->game?->icon ?: 'sports_esports' }}</span>
      </span>
      <h1 class="mt-4 text-xl font-bold leading-tight text-slate-900 break-words">{{ $room->name }}</h1>
      @if($room->game)
        <p class="mt-1 text-sm text-slate-500">{{ $room->game->name }}</p>
      @endif
      <div class="mt-3 flex flex-wrap items-center justify-center gap-x-2 gap-y-1 text-xs text-slate-500">
        <x-game.status-badge :status="$room->status" />
        <span aria-hidden="true">·</span>
        <span>{{ __('games.play.players_count', ['count' => $room->players_count]) }}</span>
      </div>

      <p class="mt-5 flex items-start gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-left text-sm text-amber-900">
        {{ __('games.play.waiting') }}
      </p>

      <a href="{{ route('public.games.rooms.show', $token) }}" data-play-reload
         class="mt-5 inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#006948] text-base font-semibold text-white shadow-md hover:bg-[#005137]">
        <span class="material-symbols-outlined text-[22px]" aria-hidden="true">refresh</span>{{ __('games.play.reload') }}
      </a>
    </section>
  </main>
</x-game.play-layout>
