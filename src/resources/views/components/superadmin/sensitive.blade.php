{{-- Masked personal value (email / phone) with an eye button revealing the full text on demand.
     The real value is never in the page: the button carries an encrypted token bound to the viewer
     (App\Services\Security\SensitiveDataService), resolved by resources/js/superadmin/sensitive.js. --}}
@props(['value' => null, 'type' => 'email', 'empty' => '—'])
@php
    $sensitive = app(\App\Services\Security\SensitiveDataService::class);
    $viewer = auth('superadmin')->user();
    $raw = trim((string) $value);
@endphp
@if ($raw === '' || $viewer === null)
    <span {{ $attributes }}>{{ $raw === '' ? $empty : $sensitive->mask($raw, $type) }}</span>
@else
    <span {{ $attributes->merge(['class' => 'sa-sensitive']) }} data-sensitive>
        <span data-sensitive-text>{{ $sensitive->mask($raw, $type) }}</span>
        <button type="button" class="sa-sensitive-toggle" data-sensitive-reveal="{{ $sensitive->token($raw, $type, $viewer) }}"
            title="{{ __('superadmin.sensitive.reveal') }}" aria-label="{{ __('superadmin.sensitive.reveal') }}">
            <span class="material-symbols-outlined" aria-hidden="true">visibility</span>
        </button>
    </span>
@endif
