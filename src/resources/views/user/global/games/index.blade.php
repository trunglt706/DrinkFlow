<x-global.layout
  :title="'DrinkFlow - ' . __('games.page_title')"
  :user="$user"
  activeTab="games"
  :breadcrumbs="$breadcrumbs"
>
  <div class="space-y-6">
    <section class="pb-6 border-b border-slate-200">
      <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight">{{ __('games.page_title') }}</h1>
      <p class="text-sm text-slate-500 mt-1">{{ __('games.page_subtitle') }}</p>
    </section>

    @if($games->isEmpty())
      <div class="p-10 border border-dashed border-slate-200 rounded-2xl bg-slate-50/50 text-center">
        <span class="material-symbols-outlined text-[36px] text-slate-300" aria-hidden="true">sports_esports</span>
        <p class="text-sm font-semibold text-slate-700 mt-2">{{ __('games.empty') }}</p>
      </div>
    @else
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
        @foreach($games as $game)
          <a href="{{ route('user.me.games.show', $game) }}" data-game-card
             class="group flex flex-col rounded-2xl border border-slate-200/80 bg-white p-6 shadow-xs transition-all hover:border-emerald-400 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">
            <span class="flex h-12 w-12 items-center justify-center rounded-xl border border-emerald-100 bg-emerald-50 text-[#006948] transition-colors group-hover:bg-[#006948] group-hover:text-white">
              <span class="material-symbols-outlined text-[26px]" aria-hidden="true">{{ $game->icon ?: 'sports_esports' }}</span>
            </span>
            <span class="mt-4 text-base font-bold text-slate-900">{{ $game->name }}</span>
            @if($game->description)
              <span class="mt-1 text-sm text-slate-500 leading-relaxed">{{ $game->description }}</span>
            @endif
            <span class="mt-5 inline-flex items-center gap-1 text-sm font-semibold text-[#006948]">
              {{ __('games.open') }}
              <span class="material-symbols-outlined text-[18px] transition-transform group-hover:translate-x-0.5" aria-hidden="true">arrow_forward</span>
            </span>
          </a>
        @endforeach
      </div>
    @endif
  </div>
</x-global.layout>
