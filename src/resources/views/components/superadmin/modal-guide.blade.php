{{-- Instruction callout at the top of a modal body: a short title and the steps / notes as <li> items in the slot.
     `tone`: info (how it works) | warning (consequences to check) | danger (irreversible effects). --}}
@props([
    'title',
    'icon' => null,
    'tone' => 'info',
])

@php
    $icon ??= ['info' => 'lightbulb', 'warning' => 'warning', 'danger' => 'report'][$tone] ?? 'lightbulb';
@endphp
<div {{ $attributes->merge(['class' => 'sa-modal-guide is-'.$tone]) }}>
    <span class="material-symbols-outlined" aria-hidden="true">{{ $icon }}</span>
    <div class="min-w-0">
        <p class="sa-modal-guide-title">{{ $title }}</p>
        <ul>{{ $slot }}</ul>
    </div>
</div>
