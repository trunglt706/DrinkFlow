{{-- KPI tile: label, big value, optional icon / hint. `value-id` / `hint-id` let page scripts fill values loaded by AJAX. --}}
@props([
    'label',
    'value' => '—',
    'hint' => null,
    'icon' => null,
    'tone' => null,
    'valueId' => null,
    'hintId' => null,
])

<article {{ $attributes->merge(['class' => 'sa-card sa-kpi'.($tone ? ' is-'.$tone : '')]) }}>
    <div class="sa-kpi-head">
        <span class="label">{{ $label }}</span>
        @if ($icon)
            <span class="sa-kpi-icon material-symbols-outlined" aria-hidden="true">{{ $icon }}</span>
        @endif
    </div>
    <strong @if ($valueId) id="{{ $valueId }}" @endif class="value">{{ $value }}</strong>
    @if ($hint !== null || $hintId)
        <span @if ($hintId) id="{{ $hintId }}" @endif class="hint">{{ $hint }}</span>
    @endif
    {{ $slot }}
</article>
