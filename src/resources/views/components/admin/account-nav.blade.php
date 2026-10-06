{{-- Agent account section of the admin sidebar (outside any room): own rooms, subscription, billing.
     `locked`: first invoice unpaid, only billing is reachable; the other entries are shown disabled. --}}
@props(['active' => null, 'locked' => false])
@php
    $links = array_values(array_filter([
        ['my-rooms', 'admin.rooms.index', 'meeting_room', 'platform.admin_nav.my_rooms'],
        ['subscription', 'admin.subscription.show', 'workspace_premium', 'platform.admin_nav.subscription'],
        ['billing', 'admin.billing.index', 'receipt_long', 'platform.admin_nav.billing'],
    ], static fn (array $link): bool => \Illuminate\Support\Facades\Route::has($link[1])));
@endphp
@if ($links !== [])
    <div class="pt-3 mt-3 border-t border-outline-variant/60 space-y-1">
        <span class="sidebar-text block px-3 pb-1 text-[10px] font-bold uppercase tracking-wider text-outline">{{ __('platform.admin_nav.account') }}</span>
        @foreach ($links as [$key, $routeName, $icon, $labelKey])
            @if ($locked && $key !== 'billing')
                <span class="sidebar-nav-link relative group flex items-center gap-3 px-3 py-2 rounded-lg font-medium text-outline/70 cursor-not-allowed select-none"
                    aria-disabled="true" title="{{ __($labelKey) }} · {{ __('platform.billing.locked_until_paid') }}">
                    <span class="material-symbols-outlined text-[18px] shrink-0">{{ $icon }}</span>
                    <span class="sidebar-text truncate flex-1">{{ __($labelKey) }}</span>
                    <span class="material-symbols-outlined !text-[15px] shrink-0 sidebar-text" aria-hidden="true">lock</span>
                    <div class="sidebar-tooltip pointer-events-none absolute left-full top-1/2 -translate-y-1/2 ml-3 px-2.5 py-1.5 bg-[#0b1c30] text-white text-xs font-semibold rounded-lg shadow-xl whitespace-nowrap z-50 opacity-0 group-hover:opacity-100 transition-opacity hidden">
                        {{ __('platform.billing.locked_until_paid') }}
                    </div>
                </span>
                @continue
            @endif
            <a class="sidebar-nav-link relative group flex items-center gap-3 px-3 py-2 rounded-lg font-medium transition-colors {{ $active === $key ? 'bg-emerald-50 text-primary ring-1 ring-emerald-100 font-semibold' : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container-low' }}"
                href="{{ route($routeName) }}" title="{{ __($labelKey) }}">
                <span class="material-symbols-outlined text-[18px] shrink-0">{{ $icon }}</span>
                <span class="sidebar-text truncate">{{ __($labelKey) }}</span>
                <div class="sidebar-tooltip pointer-events-none absolute left-full top-1/2 -translate-y-1/2 ml-3 px-2.5 py-1.5 bg-[#0b1c30] text-white text-xs font-semibold rounded-lg shadow-xl whitespace-nowrap z-50 opacity-0 group-hover:opacity-100 transition-opacity hidden">
                    {{ __($labelKey) }}
                </div>
            </a>
        @endforeach
    </div>
@endif
