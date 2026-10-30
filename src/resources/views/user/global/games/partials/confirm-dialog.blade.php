{{-- Confirmation of the forms carrying data-confirm="message" (resources/js/global/games.js). --}}
<dialog data-confirm-dialog class="!m-auto w-[calc(100%-2rem)] max-w-sm rounded-2xl p-0 backdrop:bg-slate-900/50">
  <div class="p-5">
    <div class="flex items-start gap-3">
      <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-50 text-amber-700">
        <span class="material-symbols-outlined text-[22px]" aria-hidden="true">help</span>
      </span>
      <p class="pt-2 text-sm font-medium text-slate-800" data-confirm-message></p>
    </div>
    <div class="mt-5 flex gap-2">
      <button type="button" data-confirm-cancel class="h-11 flex-1 rounded-xl border border-slate-200 text-sm font-semibold text-slate-700 hover:bg-slate-50">{{ __('games.record.cancel') }}</button>
      <button type="button" data-confirm-ok class="h-11 flex-1 rounded-xl bg-[#006948] text-sm font-semibold text-white hover:bg-[#005137]">{{ __('global.common.confirm') }}</button>
    </div>
  </div>
</dialog>
