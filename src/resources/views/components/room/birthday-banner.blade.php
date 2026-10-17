{{-- Rendered by App\View\Components\Room\BirthdayBanner only when a member's birthday is within 3 days.
     The birthday messages scroll slowly from right to left; the call-to-action button stays put. --}}
@php
    $dateOf = static fn (array $celebrant): string => sprintf('%02d/%02d', $celebrant['day'], $celebrant['month']);
    $viewerIsCelebrant = false;
    $messages = [];
    foreach ($celebrants as $celebrant) {
        $isSelf = $viewerCode !== null && hash_equals($celebrant['user_code'], (string) $viewerCode);
        $viewerIsCelebrant = $viewerIsCelebrant || $isSelf;
        $offset = $celebrant['offset'];
        $messages[] = match (true) {
            $offset === 0 && $isSelf => __('birthday.banner_today_self', ['name' => $celebrant['name']]),
            $offset === 0 => __('birthday.banner_today', ['name' => $celebrant['name']]),
            $offset > 0 => trans_choice('birthday.banner_upcoming', $offset, ['name' => $celebrant['name'], 'days' => $offset, 'date' => $dateOf($celebrant)]),
            default => trans_choice('birthday.banner_past', abs($offset), ['name' => $celebrant['name'], 'days' => abs($offset), 'date' => $dateOf($celebrant)]),
        };
    }
    $ticker = implode('   🎉   ', $messages);
    // Slow, readable pace whatever the length: about 6 characters per second plus the run-in.
    $seconds = max(12, (int) round(mb_strlen($ticker) / 6) + 8);
@endphp
@once
<style>
  @keyframes df-bd-marquee{from{transform:translateX(0)}to{transform:translateX(-100%)}}
  .df-bd-ticker{position:relative;overflow:hidden;white-space:nowrap;-webkit-mask-image:linear-gradient(90deg,transparent,#000 4%,#000 96%,transparent);mask-image:linear-gradient(90deg,transparent,#000 4%,#000 96%,transparent)}
  .df-bd-ticker-track{display:inline-block;padding-left:100%;animation:df-bd-marquee var(--df-bd-duration,20s) linear infinite}
  [data-birthday-banner]:hover .df-bd-ticker-track,[data-birthday-banner]:focus-within .df-bd-ticker-track{animation-play-state:paused}
  @keyframes df-bd-cta-pulse{0%,100%{transform:scale(1);box-shadow:0 0 0 0 rgba(255,255,255,.7)}50%{transform:scale(1.06);box-shadow:0 0 0 8px rgba(255,255,255,0)}}
  @keyframes df-bd-cta-shine{0%{left:-60%}60%,100%{left:130%}}
  @keyframes df-bd-cta-arrow{0%,100%{transform:translateX(0)}50%{transform:translateX(3px)}}
  .df-bd-cta{position:relative;overflow:hidden;animation:df-bd-cta-pulse 1.6s ease-in-out infinite}
  .df-bd-cta::after{content:'';position:absolute;top:0;bottom:0;left:-60%;width:40%;transform:skewX(-20deg);background:linear-gradient(90deg,transparent,rgba(255,255,255,.85),transparent);animation:df-bd-cta-shine 2.2s ease-in-out infinite}
  .df-bd-cta .material-symbols-outlined{animation:df-bd-cta-arrow 1s ease-in-out infinite}
  @media (prefers-reduced-motion: reduce){
    .df-bd-ticker{white-space:normal;-webkit-mask-image:none;mask-image:none}
    .df-bd-ticker-track{padding-left:0;animation:none}
    .df-bd-cta,.df-bd-cta::after,.df-bd-cta .material-symbols-outlined{animation:none}
  }
</style>
@endonce
<section class="w-full bg-gradient-to-r from-fuchsia-600 via-rose-500 to-amber-400 text-white shadow-sm" data-birthday-banner aria-label="{{ __('birthday.banner_label') }}">
  <a href="{{ route('user.rooms.birthday', $room) }}" class="max-w-7xl mx-auto px-4 sm:px-6 py-2.5 flex items-center gap-3">
    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-white/20 text-lg" aria-hidden="true"><span class="animate-bounce">🎂</span></span>
    {{-- Screen readers get the plain list; the moving copy is decorative. --}}
    <span class="sr-only">{{ implode('. ', $messages) }}</span>
    <span class="df-bd-ticker min-w-0 flex-1 text-xs sm:text-sm font-semibold" aria-hidden="true">
      <span class="df-bd-ticker-track" style="--df-bd-duration: {{ $seconds }}s">
        @foreach ($celebrants as $index => $celebrant)
          <span data-birthday-link="{{ $celebrant['user_code'] }}">{{ $messages[$index] }}</span>@if (! $loop->last)<span class="px-6" aria-hidden="true">🎉</span>@endif
        @endforeach
      </span>
    </span>
    <span class="df-bd-cta inline-flex shrink-0 items-center gap-1 rounded-full bg-white px-3 py-1 text-xs font-bold text-rose-600 shadow-xs">
      <span class="hidden sm:inline">{{ $viewerIsCelebrant ? __('birthday.banner_cta_self') : __('birthday.banner_cta') }}</span>
      <span class="material-symbols-outlined text-[16px]" aria-hidden="true">arrow_forward</span>
    </span>
  </a>
</section>
