@php
  // Old input (after a validation error) wins over the clone pre-fill, which wins over the empty defaults.
  $prefill = $prefill ?? null;
  $oldPlayers = old('players');
  $players = is_array($oldPlayers) && $oldPlayers !== []
      ? array_values($oldPlayers)
      : ($prefill['players'] ?? array_fill(0, $defaultPlayers, ''));
  $selectedRuleSet = (int) old('rule_set_id', $prefill['rule_set_id'] ?? $ruleSets->first()?->id);
  $roomName = old('name', $prefill['name'] ?? '');
@endphp
<x-global.layout
  :title="'DrinkFlow - ' . __('games.create.title')"
  :user="$user"
  activeTab="games"
  :breadcrumbs="$breadcrumbs"
>
  <div class="mx-auto max-w-2xl space-y-6">
    @include('user.global.games.partials.flash')

    <section>
      <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ __('games.create.title') }}</h1>
      <p class="text-sm text-slate-500 mt-1">{{ __('games.create.subtitle') }} · {{ $game->name }}</p>
    </section>

    @if($cloneSource ?? null)
      <div class="flex items-start gap-2 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900" data-clone-notice>
        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">content_copy</span>
        <span>
          {{ __('games.create.clone_notice', ['name' => $cloneSource->name]) }}
          @if($prefill['rule_set_id'] === null)
            <span class="mt-1 block font-semibold text-amber-800" data-clone-rule-set-missing>{{ __('games.create.clone_rule_set_missing') }}</span>
          @endif
        </span>
      </div>
    @endif

    <form method="POST" action="{{ route('user.me.games.rooms.store', $game) }}" class="space-y-5" data-room-create
          data-min="{{ $minPlayers }}" data-max="{{ $maxPlayers }}"
          data-i18n="{{ json_encode(['placeholder' => __('games.create.player_placeholder', ['number' => ':number'])], JSON_UNESCAPED_UNICODE) }}">
      @csrf

      <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs space-y-4">
        <div>
          <label for="room-name" class="block text-sm font-semibold text-slate-800">{{ __('games.create.room_name') }}</label>
          <input id="room-name" name="name" type="text" maxlength="100" required value="{{ $roomName }}"
                 placeholder="{{ __('games.create.room_name_placeholder') }}"
                 class="mt-1.5 h-12 w-full rounded-xl border border-slate-200 bg-slate-50/70 px-4 text-base text-slate-900 focus:border-[#006948] focus:bg-white focus:ring-1 focus:ring-[#006948]">
        </div>
        <div>
          <label for="player-count" class="block text-sm font-semibold text-slate-800">{{ __('games.create.player_count') }}</label>
          <select id="player-count" data-player-count class="mt-1.5 h-12 w-full rounded-xl border border-slate-200 bg-slate-50/70 px-4 text-base text-slate-900 focus:border-[#006948] focus:ring-1 focus:ring-[#006948]">
            @for($count = $minPlayers; $count <= $maxPlayers; $count++)
              <option value="{{ $count }}" @selected($count === count($players))>{{ $count }}</option>
            @endfor
          </select>
          <p class="mt-1 text-xs text-slate-500">{{ __('games.create.player_count_hint', ['min' => $minPlayers, 'max' => $maxPlayers]) }}</p>
        </div>
      </div>

      <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs">
        <h2 class="text-sm font-semibold text-slate-800">{{ __('games.create.players') }}</h2>
        <ol class="mt-3 space-y-2" data-player-rows>
          @foreach($players as $index => $name)
            <li class="flex items-center gap-2" data-player-row>
              <span class="w-7 shrink-0 text-center text-xs font-semibold text-slate-400" data-player-number>{{ $index + 1 }}</span>
              <input name="players[]" type="text" maxlength="50" value="{{ $name }}" aria-label="{{ __('games.create.player_name') }}"
                     placeholder="{{ __('games.create.player_placeholder', ['number' => $index + 1]) }}"
                     class="h-11 min-w-0 flex-1 rounded-xl border border-slate-200 bg-slate-50/70 px-3 text-base text-slate-900 focus:border-[#006948] focus:bg-white focus:ring-1 focus:ring-[#006948]">
              <button type="button" data-remove-player class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-slate-200 text-slate-500 hover:bg-rose-50 hover:text-rose-600" title="{{ __('games.create.remove_player') }}" aria-label="{{ __('games.create.remove_player') }}">
                <span class="material-symbols-outlined text-[20px]" aria-hidden="true">close</span>
              </button>
            </li>
          @endforeach
        </ol>
        <button type="button" data-add-player class="mt-3 inline-flex h-11 w-full items-center justify-center gap-1.5 rounded-xl border border-dashed border-slate-300 text-sm font-semibold text-slate-600 hover:border-[#006948] hover:text-[#006948]">
          <span class="material-symbols-outlined text-[18px]" aria-hidden="true">person_add</span>
          {{ __('games.create.add_player') }}
        </button>
      </div>

      <fieldset class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs">
        <legend class="sr-only">{{ __('games.create.rule_set') }}</legend>
        <div class="flex items-center justify-between gap-3">
          <h2 class="text-sm font-semibold text-slate-800">{{ __('games.create.rule_set') }}</h2>
          <a href="{{ route('user.me.games.rule-sets.create', ['game' => $game->slug, 'return' => 1]) }}" class="text-xs font-semibold text-[#006948] hover:underline">{{ __('games.create.create_rule_set') }}</a>
        </div>
        @if($ruleSets->isEmpty())
          <div class="mt-3 rounded-xl border border-dashed border-slate-200 bg-slate-50/60 p-5 text-center">
            <p class="text-sm text-slate-600">{{ __('games.create.no_rule_set') }}</p>
            <a href="{{ route('user.me.games.rule-sets.create', ['game' => $game->slug, 'return' => 1]) }}" class="mt-3 inline-flex h-11 items-center gap-1.5 rounded-xl bg-[#006948] px-5 text-sm font-semibold text-white hover:bg-[#005137]">
              <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>
              {{ __('games.create.create_rule_set') }}
            </a>
          </div>
        @else
          <div class="mt-3 space-y-2">
            @foreach($ruleSets as $ruleSet)
              <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3.5 has-[:checked]:border-[#006948] has-[:checked]:bg-emerald-50/60">
                <input type="radio" name="rule_set_id" value="{{ $ruleSet->id }}" @checked($selectedRuleSet === (int) $ruleSet->id) class="mt-0.5 h-5 w-5 text-[#006948] focus:ring-[#006948]" required>
                <span class="min-w-0 flex-1">
                  <span class="block text-sm font-semibold text-slate-900">{{ $ruleSet->name }}
                    <span class="ml-1 text-xs font-medium text-slate-500">{{ __('games.create.rule_set_version', ['version' => $ruleSet->currentVersion?->version]) }}</span>
                  </span>
                  <span class="mt-1 block text-xs text-slate-500">
                    {{ $ruleSet->currentVersion?->items->where('is_active', true)->map(fn ($item) => $item->name.' ('.($item->points > 0 ? '+' : '').$item->points.')')->implode(' · ') }}
                  </span>
                </span>
              </label>
            @endforeach
          </div>
        @endif
      </fieldset>

      <div class="rounded-2xl border border-emerald-100 bg-emerald-50/50 p-4 text-sm text-emerald-900">
        <p class="font-semibold">{{ __('games.create.review') }}</p>
        <p class="mt-1 text-xs leading-relaxed text-emerald-800">{{ __('games.create.review_hint') }}</p>
      </div>

      <button type="submit" @disabled($ruleSets->isEmpty()) class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#006948] text-base font-semibold text-white shadow-xs hover:bg-[#005137] disabled:cursor-not-allowed disabled:opacity-50">
        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">add_circle</span>
        {{ __('games.create.submit') }}
      </button>
    </form>
  </div>
</x-global.layout>
