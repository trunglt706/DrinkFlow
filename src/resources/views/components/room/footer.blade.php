@props([
    'room' => null,
])

<footer class="w-full bg-white border-t border-slate-200/80 py-6 mt-12 text-xs text-slate-500">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 flex flex-col sm:flex-row items-center justify-between gap-4">
    {{-- Mobile only: these replace the Join Room / Contact floating buttons (hidden below sm in x-global.go-to-top). --}}
    <div class="grid grid-cols-2 gap-2 w-full sm:hidden">
      <button type="button" data-join-room-open
              class="h-9 rounded-lg bg-[#006948] hover:bg-[#005137] text-white font-semibold flex items-center justify-center gap-1.5 transition-colors cursor-pointer">
        <span class="material-symbols-outlined text-[16px]" aria-hidden="true">meeting_room</span>
        <span class="truncate">{{ __('global.join_modal.fab_label') }}</span>
      </button>
      <a href="{{ route('contact') }}"
         class="h-9 rounded-lg bg-white hover:bg-emerald-50 border border-slate-200 text-[#006948] font-semibold flex items-center justify-center gap-1.5 transition-colors">
        <span class="material-symbols-outlined text-[16px]" aria-hidden="true">support_agent</span>
        <span class="truncate">{{ __('public.contact_support') }}</span>
      </a>
    </div>
    <div class="flex items-center gap-2">
      <span>{{ __('room.footer.copyright', ['year' => date('Y')]) }}</span>
    </div>
    <div class="flex items-center gap-4">
      <a class="hover:text-slate-900 transition-colors" href="{{ route('terms') }}">{{ __('room.footer.order_policy') }}</a>
      <a class="hover:text-slate-900 transition-colors" href="{{ route('contact') }}">{{ __('room.footer.internal_support') }}</a>
    </div>
  </div>
</footer>
