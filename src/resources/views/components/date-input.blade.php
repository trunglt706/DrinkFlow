@props([
    'name' => null,
    'value' => null,
    'id' => null,
    'min' => null,
    'max' => null,
    'required' => false,
    'disabled' => false,
    'placeholder' => null,
    'inputClass' => '',
    'valueClass' => '',
    'label' => null,
])

{{-- Shared date input of the user, admin and superadmin pages (resources/js/shared/date-input.js): shows and accepts
     dd/mm/yyyy, opens a calendar on any click, with "Clear" and "Today". The value sent (and read by page scripts)
     stays yyyy-mm-dd in a hidden input carrying `name`, `id`, `valueClass` and the other attributes; a <label for>
     should target "{id}-display". `inputClass` styles the visible field. --}}
@php
    $iso = static function (mixed $date): string {
        if ($date instanceof \DateTimeInterface) {
            return $date->format('Y-m-d');
        }

        return is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 ? $date : '';
    };
    $current = $iso($value);
    $displayId = $id !== null ? $id.'-display' : null;
    $i18n = [
        'months' => array_values((array) __('datepicker.months')),
        'weekdays' => array_values((array) __('datepicker.weekdays')),
        'today' => __('datepicker.today'),
        'clear' => __('datepicker.clear'),
        'prev' => __('datepicker.prev_month'),
        'next' => __('datepicker.next_month'),
        'month' => __('datepicker.month'),
        'year' => __('datepicker.year'),
        'dialog' => __('datepicker.dialog'),
        'invalid' => __('datepicker.invalid'),
    ];
@endphp
<div class="df-date" data-date-input data-min="{{ $iso($min) }}" data-max="{{ $iso($max) }}" data-i18n="{{ json_encode($i18n, JSON_UNESCAPED_UNICODE) }}">
    <input type="text" @if ($displayId) id="{{ $displayId }}" @endif data-date-display inputmode="numeric" autocomplete="off" maxlength="10"
        value="{{ $current !== '' ? \Illuminate\Support\Carbon::parse($current)->format('d/m/Y') : '' }}"
        placeholder="{{ $placeholder ?? __('datepicker.placeholder') }}" aria-haspopup="dialog" aria-expanded="false"
        @if ($label) aria-label="{{ $label }}" @endif @required($required) @disabled($disabled)
        class="df-date-display pr-9 {{ $inputClass }}">
    <span class="material-symbols-outlined df-date-icon" aria-hidden="true">calendar_month</span>
    <input type="hidden" @if ($name) name="{{ $name }}" @endif @if ($id) id="{{ $id }}" @endif value="{{ $current }}" data-date-value
        @disabled($disabled) {{ $attributes->merge(['class' => $valueClass]) }}>
    <p class="df-date-error" data-date-error role="alert" hidden></p>
</div>
