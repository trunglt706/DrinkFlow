{{-- Full-screen image viewer driven by resources/js/shared/image-lightbox.js. Opened by images inside a
     .guide-article or by <img data-lightbox="group"> (campaign menu and cart). --}}
@props(['title' => null])

<div data-image-lightbox
  data-i18n="{{ json_encode(['counter' => __('global.lightbox.counter', ['current' => ':current', 'total' => ':total'])], JSON_UNESCAPED_UNICODE) }}"
  class="hidden fixed inset-0 z-[9999] bg-slate-950/90 backdrop-blur-sm items-center justify-center select-none"
  role="dialog" aria-modal="true" aria-label="{{ $title ?? __('global.lightbox.title') }}">
  <img data-image-lightbox-image src="data:image/gif;base64,R0lGODlhAQABAAAAACw=" alt="" loading="lazy"
    class="max-w-[calc(100vw-2rem)] sm:max-w-[calc(100vw-9rem)] max-h-[calc(100vh-12rem)] -translate-y-8 object-contain rounded-lg shadow-2xl bg-white">

  <button type="button" data-image-lightbox-close
    class="absolute top-3 right-3 sm:top-5 sm:right-5 w-10 h-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center cursor-pointer transition-colors"
    title="{{ __('global.lightbox.close') }}" aria-label="{{ __('global.lightbox.close') }}">
    <span class="material-symbols-outlined text-[24px]">close</span>
  </button>

  <button type="button" data-image-lightbox-prev
    class="absolute left-2 sm:left-5 top-1/2 -translate-y-1/2 w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center cursor-pointer transition-colors"
    title="{{ __('global.lightbox.prev') }}" aria-label="{{ __('global.lightbox.prev') }}">
    <span class="material-symbols-outlined text-[28px]">chevron_left</span>
  </button>

  <button type="button" data-image-lightbox-next
    class="absolute right-2 sm:right-5 top-1/2 -translate-y-1/2 w-10 h-10 sm:w-12 sm:h-12 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center cursor-pointer transition-colors"
    title="{{ __('global.lightbox.next') }}" aria-label="{{ __('global.lightbox.next') }}">
    <span class="material-symbols-outlined text-[28px]">chevron_right</span>
  </button>

  <div class="absolute bottom-4 inset-x-4 text-center text-white">
    <p data-image-lightbox-caption class="text-xs sm:text-sm text-white/80 truncate"></p>
    {{-- Filled from data-lightbox-title / -price / -description (campaign menu items). --}}
    <div data-image-lightbox-details class="hidden mx-auto max-w-xl space-y-0.5">
      <p class="flex items-baseline justify-center gap-2 text-sm sm:text-base font-bold">
        <span data-image-lightbox-title class="truncate"></span>
        <span data-image-lightbox-price class="shrink-0 font-mono text-emerald-300"></span>
      </p>
      <p data-image-lightbox-description class="text-xs text-white/70 leading-relaxed line-clamp-3"></p>
    </div>
    <p data-image-lightbox-counter class="text-[11px] text-white/60 mt-0.5 tabular-nums"></p>
  </div>
</div>
