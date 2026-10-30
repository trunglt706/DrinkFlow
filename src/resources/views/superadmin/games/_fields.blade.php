{{-- Game form fields shared by the create modal and the edit page. $game is null when creating. --}}
@php
    $fieldLabel = 'flex flex-col gap-1.5 text-[13px] font-semibold text-on-surface';
    $fieldInput = 'sa-input !min-w-0 w-full';
    $game = $game ?? null;
@endphp
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <label class="{{ $fieldLabel }}">{{ __('games.admin.field_name') }}
        <input name="name" type="text" required maxlength="150" value="{{ old('name', $game?->name) }}" class="{{ $fieldInput }}">
    </label>
    <label class="{{ $fieldLabel }}">{{ __('games.admin.field_slug') }}
        <input name="slug" type="text" required maxlength="100" pattern="[a-z0-9][a-z0-9\-]*" value="{{ old('slug', $game?->slug) }}" class="{{ $fieldInput }} font-mono">
        <small class="sa-hint !mt-0">{{ __('games.admin.slug_hint') }}</small>
    </label>
    <label class="{{ $fieldLabel }}">{{ __('games.admin.field_icon') }}
        <span class="flex items-center gap-2">
            <span class="material-symbols-outlined text-[22px] text-primary" aria-hidden="true">{{ old('icon', $game?->icon) ?: 'sports_esports' }}</span>
            <input name="icon" type="text" maxlength="64" pattern="[a-z0-9_]+" value="{{ old('icon', $game?->icon) }}" placeholder="sports_esports" class="{{ $fieldInput }} font-mono">
        </span>
        <small class="sa-hint !mt-0">
            {{ __('games.admin.icon_hint') }}
            <a href="https://fonts.google.com/icons?icon.set=Material+Symbols" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-0.5 font-semibold text-primary hover:underline" data-icon-picker-link>
                {{ __('games.admin.icon_picker') }}<span class="material-symbols-outlined text-[14px]" aria-hidden="true">open_in_new</span>
            </a>
        </small>
    </label>
    <label class="{{ $fieldLabel }}">{{ __('games.admin.field_sort_order') }}
        <input name="sort_order" type="number" min="0" max="65535" value="{{ old('sort_order', $game?->sort_order ?? 0) }}" class="{{ $fieldInput }}">
    </label>
    <label class="{{ $fieldLabel }} sm:col-span-2">{{ __('games.admin.field_description') }}
        <textarea name="description" rows="3" maxlength="500" class="{{ $fieldInput }}">{{ old('description', $game?->description) }}</textarea>
    </label>
    <label class="flex items-center gap-2 text-[13px] font-semibold text-on-surface sm:col-span-2">
        <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $game?->is_active ?? true))>
        {{ __('games.admin.field_active') }}
        <small class="sa-hint !mt-0 font-normal">{{ __('games.admin.active_hint') }}</small>
    </label>
</div>
