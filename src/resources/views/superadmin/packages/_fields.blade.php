{{-- Package form fields shared by the create modal and the edit page. $package is null when creating. --}}
@php
    $fieldLabel = 'flex flex-col gap-1.5 text-[13px] font-semibold text-on-surface';
    $fieldInput = 'sa-input !min-w-0 w-full';
    $package = $package ?? null;
    // Grouped like FormatHelper::formatCurrency() (resources/js/superadmin/number-input.js); the request strips the separators.
    $groupSeparator = '.';
    $grouped = static fn (mixed $value): string => is_numeric($value) ? number_format((int) $value, 0, ',', $groupSeparator) : (string) $value;
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
        <span class="relative block">
            <input name="monthly_price" type="text" inputmode="numeric" required maxlength="13" autocomplete="off" data-number-input data-group-separator="{{ $groupSeparator }}"
                value="{{ $grouped(old('monthly_price', $package?->monthly_price ?? 0)) }}" class="{{ $fieldInput }} !pr-9 text-right tabular-nums">
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-[13px] font-semibold text-outline" aria-hidden="true">{{ __('global.common.money_suffix') }}</span>
        </span>
        <small class="sa-hint !mt-0">{{ __('superadmin.modals.package.price_hint') }}</small>
    </label>
    <label class="{{ $fieldLabel }}">{{ __('platform.packages.field_room_limit') }}
        <input name="room_limit" type="text" inputmode="numeric" required maxlength="6" autocomplete="off" data-number-input data-group-separator="{{ $groupSeparator }}"
            value="{{ $grouped(old('room_limit', $package?->room_limit ?? 1)) }}" class="{{ $fieldInput }} text-right tabular-nums">
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
