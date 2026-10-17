@props([
    // When true, Join Room & Contact are hidden below `sm`; the page footer renders them instead.
    'mobileActionsInFooter' => false,
])

<!-- FLOATING ACTION BUTTONS (Contact & Go To Top) for Global User -->
<div class="fixed bottom-6 right-6 z-40 flex flex-col items-end gap-3 select-none">
    <!-- Join Room Floating Button (opens the join-room modal) -->
    <div class="relative {{ $mobileActionsInFooter ? 'hidden sm:flex' : 'flex' }} items-center group">
        <span role="tooltip" class="absolute right-full mr-3 whitespace-nowrap px-2.5 py-1 rounded-lg bg-slate-900/90 backdrop-blur-xs text-white text-xs font-medium shadow-lg opacity-0 pointer-events-none translate-x-2 group-hover:opacity-100 group-hover:translate-x-0 transition-all duration-200">
            {{ __('global.join_modal.fab_label') }}
        </span>
        <button type="button" data-join-room-open
                aria-label="{{ __('global.join_modal.fab_label') }}"
                class="w-11 h-11 rounded-full bg-[#006948] hover:bg-[#005137] text-white shadow-lg flex items-center justify-center cursor-pointer transition-all duration-200 hover:scale-110 active:scale-95 focus:outline-none focus:ring-2 focus:ring-[#006948] focus:ring-offset-2">
            <span class="material-symbols-outlined text-[22px] transition-transform duration-200 group-hover:scale-110">meeting_room</span>
        </button>
    </div>

    <!-- Quick Contact Floating Button (Redirects to /contact) -->
    <div class="relative {{ $mobileActionsInFooter ? 'hidden sm:flex' : 'flex' }} items-center group">
        <span role="tooltip" class="absolute right-full mr-3 whitespace-nowrap px-2.5 py-1 rounded-lg bg-slate-900/90 backdrop-blur-xs text-white text-xs font-medium shadow-lg opacity-0 pointer-events-none translate-x-2 group-hover:opacity-100 group-hover:translate-x-0 transition-all duration-200">
            {{ __('public.contact_support') }}
        </span>
        <a href="{{ route('contact') }}"
           aria-label="{{ __('public.contact_support') }}"
           class="w-11 h-11 rounded-full bg-white hover:bg-emerald-50 text-[#006948] border border-slate-200/90 shadow-lg flex items-center justify-center transition-all duration-200 hover:scale-110 active:scale-95 group-hover:border-[#006948]/40 focus:outline-none focus:ring-2 focus:ring-[#006948] focus:ring-offset-2">
            <span class="material-symbols-outlined text-[22px] transition-transform duration-200 group-hover:scale-110">support_agent</span>
        </a>
    </div>

    <!-- Go To Top Floating Button -->
    <div class="relative flex items-center group">
        <span role="tooltip" id="global-go-to-top-tooltip" class="hidden absolute right-full mr-3 whitespace-nowrap px-2.5 py-1 rounded-lg bg-slate-900/90 backdrop-blur-xs text-white text-xs font-medium shadow-lg opacity-0 pointer-events-none translate-x-2 group-hover:opacity-100 group-hover:translate-x-0 transition-all duration-200">
            {{ __('public.go_to_top') }}
        </span>
        <button id="global-go-to-top-btn"
                type="button"
                aria-label="{{ __('public.go_to_top') }}"
                class="w-11 h-11 rounded-full bg-[#006948] hover:bg-[#005137] text-white shadow-lg flex items-center justify-center cursor-pointer transition-all duration-300 opacity-0 pointer-events-none translate-y-3 hover:scale-105 active:scale-95 focus:outline-none focus:ring-2 focus:ring-[#006948] focus:ring-offset-2">
            <span class="material-symbols-outlined text-[20px]">arrow_upward</span>
        </button>
    </div>
</div>

<x-global.join-room-modal />
