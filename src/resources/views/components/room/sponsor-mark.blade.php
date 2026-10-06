{{-- Sponsor icon placed after a member's name in the room's order views, with a "Sponsor" tooltip on hover/focus.
     Static markup, so it also works inside Alpine templates (e.g. x-if="order.is_sponsor" in the campaign orders modal). --}}
@props(['label' => null])
@php($label = $label ?: __('room.debts.campaign_sponsor_tooltip'))
@once
<style>
    .df-sponsor-mark{position:relative;display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border-radius:999px;background:#fef3c7;color:#b45309;flex-shrink:0;cursor:default;vertical-align:middle}
    .df-sponsor-mark .material-symbols-outlined{font-size:13px;font-variation-settings:'FILL' 1}
    .df-sponsor-mark .df-sponsor-mark-tip{position:absolute;left:calc(100% + 6px);top:50%;transform:translateY(-50%);white-space:nowrap;background:#0f172a;color:#fff;font-size:11px;font-weight:600;padding:4px 8px;border-radius:6px;opacity:0;visibility:hidden;pointer-events:none;transition:opacity .15s;z-index:30}
    .df-sponsor-mark:hover .df-sponsor-mark-tip,.df-sponsor-mark:focus-visible .df-sponsor-mark-tip{opacity:1;visibility:visible}
    .df-sponsor-mark:focus-visible{outline:2px solid #2563eb;outline-offset:2px}
</style>
@endonce
<span {{ $attributes->merge(['class' => 'df-sponsor-mark', 'role' => 'img', 'tabindex' => '0', 'aria-label' => $label]) }} data-campaign-sponsor-mark>
    <span class="material-symbols-outlined" aria-hidden="true">volunteer_activism</span>
    <span class="df-sponsor-mark-tip" aria-hidden="true">{{ $label }}</span>
</span>
