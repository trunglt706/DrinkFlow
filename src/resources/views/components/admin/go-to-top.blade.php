<!-- FLOATING ACTION BUTTON (Go To Top) for Admin Workspace -->
<div class="admin-fab fixed bottom-6 right-6 z-40 flex flex-col items-end gap-3 select-none">
    <!-- Quick Support Button -->
    <div class="relative flex items-center group">
        <span role="tooltip" class="absolute right-full mr-3 whitespace-nowrap px-2.5 py-1 rounded-lg bg-slate-900/90 backdrop-blur-xs text-white text-xs font-medium shadow-lg opacity-0 pointer-events-none translate-x-2 group-hover:opacity-100 group-hover:translate-x-0 transition-all duration-200">
            {{ __('admin.contact_support') }}
        </span>
        <a href="{{ route('contact') }}"
           aria-label="{{ __('admin.contact_support') }}"
           class="w-11 h-11 rounded-full bg-surface-container-lowest hover:bg-surface-container text-primary border border-outline-variant shadow-lg flex items-center justify-center transition-all duration-200 hover:scale-110 active:scale-95 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
            <span class="material-symbols-outlined text-[20px] transition-transform duration-200 group-hover:scale-110">support_agent</span>
        </a>
    </div>

    <!-- Go To Top Floating Button -->
    <div class="relative flex items-center group">
        <span role="tooltip" id="admin-go-to-top-tooltip" class="hidden absolute right-full mr-3 whitespace-nowrap px-2.5 py-1 rounded-lg bg-slate-900/90 backdrop-blur-xs text-white text-xs font-medium shadow-lg opacity-0 pointer-events-none translate-x-2 group-hover:opacity-100 group-hover:translate-x-0 transition-all duration-200">
            {{ __('admin.go_to_top') ?? 'Cuộn lên đầu trang' }}
        </span>
        <button id="admin-go-to-top-btn"
                type="button"
                aria-label="{{ __('admin.go_to_top') ?? 'Cuộn lên đầu trang' }}"
                class="w-11 h-11 rounded-full bg-primary hover:bg-primary-container text-on-primary shadow-lg flex items-center justify-center cursor-pointer transition-all duration-300 opacity-0 pointer-events-none translate-y-3 hover:scale-105 active:scale-95 focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
            <span class="material-symbols-outlined text-[20px]">arrow_upward</span>
        </button>
    </div>
</div>
