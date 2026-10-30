<x-global.layout
  :title="'DrinkFlow - ' . __('games.rules.title')"
  :user="$user"
  activeTab="games"
  :breadcrumbs="$breadcrumbs"
>
  <div class="space-y-6">
    @include('user.global.games.partials.flash')

    <section class="flex flex-col gap-4 pb-6 border-b border-slate-200 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight">{{ __('games.rules.title') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('games.rules.subtitle') }}</p>
      </div>
      <a href="{{ route('user.me.games.rule-sets.create', $game) }}" class="inline-flex h-11 items-center justify-center gap-1.5 rounded-xl bg-[#006948] px-5 text-sm font-semibold text-white hover:bg-[#005137]">
        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>{{ __('games.rules.create') }}
      </a>
    </section>

    @if($ruleSets->isEmpty())
      <div class="p-10 border border-dashed border-slate-200 rounded-2xl bg-slate-50/50 text-center">
        <span class="material-symbols-outlined text-[36px] text-slate-300" aria-hidden="true">rule</span>
        <p class="text-sm font-semibold text-slate-700 mt-2">{{ __('games.rules.empty') }}</p>
      </div>
    @else
      <div class="grid gap-4 md:grid-cols-2">
        @foreach($ruleSets as $ruleSet)
          @php $version = $ruleSet->currentVersion; @endphp
          <article class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs">
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <h2 class="text-base font-bold text-slate-900 break-words">{{ $ruleSet->name }}</h2>
                <p class="mt-0.5 text-xs text-slate-500">
                  {{ __('games.rules.version', ['version' => $version?->version]) }} · {{ __('games.rules.items_count', ['count' => $version?->items->count() ?? 0]) }}
                  @if($ruleSet->allow_negative_points) · {{ __('games.rules.allow_negative') }} @endif
                </p>
              </div>
              <a href="{{ route('user.me.games.rule-sets.edit', [$game, $ruleSet]) }}" class="inline-flex h-9 shrink-0 items-center gap-1 rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">edit</span>{{ __('games.rules.edit') }}
              </a>
            </div>
            <ul class="mt-4 space-y-1.5">
              @foreach($version?->items ?? [] as $item)
                <li class="flex items-center justify-between rounded-lg px-3 py-2 text-sm {{ $item->is_active ? 'bg-slate-50 text-slate-800' : 'bg-white text-slate-400 line-through' }}">
                  <span>
                    {{ $item->name }}
                    @unless($item->is_active)<span class="ml-1 text-[11px] no-underline">({{ __('games.rules.item_archived') }})</span>@endunless
                  </span>
                  <span class="font-semibold tabular-nums">{{ ($item->points > 0 ? '+' : '').$item->points }}</span>
                </li>
              @endforeach
            </ul>
          </article>
        @endforeach
      </div>
    @endif
  </div>
</x-global.layout>
