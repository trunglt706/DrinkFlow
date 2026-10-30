@php
  $oldItems = old('items');
  $rows = is_array($oldItems) ? array_values($oldItems) : $items;
  $isEdit = $ruleSet !== null;
  $action = $isEdit ? route('user.me.games.rule-sets.update', [$game, $ruleSet]) : route('user.me.games.rule-sets.store', $game);
@endphp
<x-global.layout
  :title="'DrinkFlow - ' . ($isEdit ? __('games.rules.edit_title') : __('games.rules.create_title'))"
  :user="$user"
  activeTab="games"
  :breadcrumbs="$breadcrumbs"
>
  <div class="mx-auto max-w-2xl space-y-6">
    @include('user.global.games.partials.flash')

    <section>
      <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ $isEdit ? __('games.rules.edit_title') : __('games.rules.create_title') }}</h1>
      @if($isEdit)
        <p class="mt-1 text-sm text-slate-500">{{ __('games.rules.version', ['version' => $ruleSet->currentVersion?->version]) }} · {{ __('games.rules.edit_hint') }}</p>
      @else
        <p class="mt-1 text-sm text-slate-500">{{ __('games.rules.subtitle') }}</p>
      @endif
    </section>

    <form method="POST" action="{{ $action }}" class="space-y-5" data-rule-set-form>
      @csrf
      @if($isEdit) @method('PUT') @endif
      @if($returnToCreate)<input type="hidden" name="return" value="1">@endif

      <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs space-y-4">
        <div>
          <label for="rule-set-name" class="block text-sm font-semibold text-slate-800">{{ __('games.rules.name') }}</label>
          <input id="rule-set-name" name="name" type="text" maxlength="100" required value="{{ old('name', $ruleSet?->name) }}" placeholder="{{ __('games.rules.name_placeholder') }}"
                 class="mt-1.5 h-12 w-full rounded-xl border border-slate-200 bg-slate-50/70 px-4 text-base focus:border-[#006948] focus:bg-white focus:ring-1 focus:ring-[#006948]">
        </div>
        <label class="flex items-center gap-2 text-sm text-slate-700">
          <input type="checkbox" name="allow_negative_points" value="1" @checked(old('allow_negative_points', $ruleSet?->allow_negative_points)) class="h-5 w-5 rounded text-[#006948] focus:ring-[#006948]">
          {{ __('games.rules.allow_negative') }}
        </label>
      </div>

      <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs">
        <h2 class="text-sm font-semibold text-slate-800">{{ __('games.rules.items') }}</h2>
        <ol class="mt-3 space-y-2" data-rule-items>
          @foreach($rows as $index => $row)
            <li class="rounded-xl border border-slate-200 p-3" data-rule-item>
              <div class="flex gap-2">
                <input type="text" name="items[{{ $index }}][name]" data-field="name" value="{{ $row['name'] ?? '' }}" maxlength="100" required aria-label="{{ __('games.rules.item_name') }}" placeholder="{{ __('games.rules.item_name') }}"
                       class="h-11 min-w-0 flex-1 rounded-lg border border-slate-200 bg-slate-50/70 px-3 text-base focus:border-[#006948] focus:bg-white focus:ring-1 focus:ring-[#006948]">
                <input type="number" name="items[{{ $index }}][points]" data-field="points" value="{{ $row['points'] ?? 1 }}" step="1" min="-1000" max="1000" required aria-label="{{ __('games.rules.item_points') }}"
                       class="h-11 w-24 shrink-0 rounded-lg border border-slate-200 bg-slate-50/70 px-3 text-right text-base tabular-nums focus:border-[#006948] focus:bg-white focus:ring-1 focus:ring-[#006948]">
              </div>
              <div class="mt-2 flex items-center justify-between gap-2">
                <label class="flex items-center gap-2 text-xs text-slate-600">
                  <input type="checkbox" name="items[{{ $index }}][is_active]" data-field="is_active" value="1" @checked(filter_var($row['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN)) class="h-4 w-4 rounded text-[#006948] focus:ring-[#006948]">
                  {{ __('games.rules.item_active') }}
                </label>
                <span class="flex gap-1">
                  <button type="button" data-move="up" class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50" title="{{ __('games.rules.move_up') }}" aria-label="{{ __('games.rules.move_up') }}"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_upward</span></button>
                  <button type="button" data-move="down" class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-slate-50" title="{{ __('games.rules.move_down') }}" aria-label="{{ __('games.rules.move_down') }}"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_downward</span></button>
                  <button type="button" data-remove-item class="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-500 hover:bg-rose-50 hover:text-rose-600" title="{{ __('games.rules.remove_item') }}" aria-label="{{ __('games.rules.remove_item') }}"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">delete</span></button>
                </span>
              </div>
            </li>
          @endforeach
        </ol>
        <button type="button" data-add-item class="mt-3 inline-flex h-11 w-full items-center justify-center gap-1.5 rounded-xl border border-dashed border-slate-300 text-sm font-semibold text-slate-600 hover:border-[#006948] hover:text-[#006948]">
          <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>{{ __('games.rules.add_item') }}
        </button>
      </div>

      <button type="submit" class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#006948] text-base font-semibold text-white shadow-xs hover:bg-[#005137]">
        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">save</span>
        {{ $isEdit ? __('games.rules.save_version') : __('games.rules.save') }}
      </button>
    </form>
  </div>
</x-global.layout>
