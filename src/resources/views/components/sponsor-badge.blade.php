{{-- Icon-only medal for a previous-month top-3 sponsor, shown next to the member's name (rank 1 gold, 2 silver, 3 bronze). --}}
@props(['rank' => null, 'month' => ''])

@if(in_array($rank, [1, 2, 3], true))
    @once
    <style>
        .df-sponsor-badge{position:relative;display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:999px;flex-shrink:0;vertical-align:middle;cursor:default}
        .df-sponsor-badge .material-symbols-outlined{font-size:16px;font-variation-settings:'FILL' 1}
        .df-sponsor-badge.rank-1{background:#fef3c7;color:#b7791f}.df-sponsor-badge.rank-2{background:#e2e8f0;color:#64748b}.df-sponsor-badge.rank-3{background:#fde4cf;color:#a0522d}
        .df-sponsor-badge .df-sponsor-badge-tip{position:absolute;bottom:calc(100% + 6px);left:50%;transform:translateX(-50%);white-space:nowrap;background:#0f172a;color:#fff;font-size:11px;font-weight:600;line-height:1.3;padding:4px 8px;border-radius:6px;opacity:0;visibility:hidden;pointer-events:none;transition:opacity .15s;z-index:60;text-transform:none;letter-spacing:normal}
        .df-sponsor-badge:hover .df-sponsor-badge-tip,.df-sponsor-badge:focus-visible .df-sponsor-badge-tip{opacity:1;visibility:visible}
        .df-sponsor-badge:focus-visible{outline:2px solid #2563eb;outline-offset:2px}
    </style>
    @endonce
    @php($label = __('sponsor_honors.badge_tooltip', ['rank' => $rank, 'month' => $month]))
    <span {{ $attributes->merge(['class' => 'df-sponsor-badge rank-'.$rank]) }} role="img" tabindex="0" aria-label="{{ $label }}" data-sponsor-badge="{{ $rank }}">
        <span class="material-symbols-outlined" aria-hidden="true">workspace_premium</span>
        <span class="df-sponsor-badge-tip" aria-hidden="true">{{ $label }}</span>
    </span>
@endif
