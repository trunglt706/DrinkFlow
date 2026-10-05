{{-- Package form fields shared by the create modal and the edit page. $package is null when creating. --}}
@php
    $fieldLabel = 'flex flex-col gap-1.5 text-[13px] font-semibold text-on-surface';
    $fieldInput = 'sa-input !min-w-0 w-full';
    $package = $package ?? null;
@endphp
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
    <label class="{{ $fieldLabel }}">{{ __('platform.packages.field_code') }}
        <input name="code" type="text" required maxlength="50" pattern="[a-z0-9][a-z0-9_\-]*" value="{{ old('code', $package?->code) }}" class="{{ $fieldInput }} font-mono">
        <small class="sa-hint !mt-0">{{ __('platform.packages.code_hint') }}</small>
    </label>
    <label class="{{ $fieldLabel }}">{{ __('platform.packages.field_name') }}
        <input name="name" type="text" required maxlength="255" value="{{ old('name', $package?->name) }}" class="{{ $fieldInput }}">
    </label>
    <label class="{{ $fieldLabel }}">{{ __('platform.packages.field_monthly_price') }}
        <input name="monthly_price" type="number" required min="0" max="1000000000" step="1000" value="{{ old('monthly_price', $package?->monthly_price ?? 0) }}" class="{{ $fieldInput }}">
        <small class="sa-hint !mt-0">{{ __('superadmin.modals.package.price_hint') }}</small>
    </label>
    <label class="{{ $fieldLabel }}">{{ __('platform.packages.field_room_limit') }}
        <input name="room_limit" type="number" required min="1" max="10000" value="{{ old('room_limit', $package?->room_limit ?? 1) }}" class="{{ $fieldInput }}">
        <small class="sa-hint !mt-0">{{ __('superadmin.modals.package.room_limit_hint') }}</small>
    </label>
    <label class="{{ $fieldLabel }}">{{ __('superadmin.common.status') }}
        <select name="status" class="{{ $fieldInput }}">
            @foreach (\App\Enums\PackageStatus::cases() as $case)
                <option value="{{ $case->value }}" @selected(old('status', $package?->status->value ?? 'active') === $case->value)>{{ __('superadmin.common.'.$case->value) }}</option>
            @endforeach
        </select>
        <small class="sa-hint !mt-0">{{ __('superadmin.modals.package.status_hint') }}</small>
    </label>
    <label class="{{ $fieldLabel }}">{{ __('platform.packages.field_sort_order') }}
        <input name="sort_order" type="number" min="0" max="65535" value="{{ old('sort_order', $package?->sort_order ?? 0) }}" class="{{ $fieldInput }}">
        <small class="sa-hint !mt-0">{{ __('superadmin.modals.package.sort_hint') }}</small>
    </label>
    <label class="{{ $fieldLabel }} sm:col-span-2">{{ __('platform.packages.field_description') }}
        <textarea name="description" rows="3" maxlength="2000" class="{{ $fieldInput }}">{{ old('description', $package?->description) }}</textarea>
    </label>
</div>
