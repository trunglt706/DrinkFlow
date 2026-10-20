{{-- Rendered by App\View\Components\Room\EventsBanner only when an event is shown in the room. One slide per occasion
     being celebrated, then one slide for the birthdays within 3 days (their messages scroll slowly from right to left).
     Several slides move by themselves (paused on hover / focus), with arrows, dots and swipe. --}}
@php
    $gradients = [
        'birthday' => 'linear-gradient(90deg,#c026d3,#f43f5e,#fbbf24)',
        'womens_day' => 'linear-gradient(90deg,#9f1239,#e11d48,#fb7185)',
        'intl_womens_day' => 'linear-gradient(90deg,#86198f,#db2777,#f472b6)',
        'new_year' => 'linear-gradient(90deg,#312e81,#6d28d9,#d97706)',
        'lunar_new_year' => 'linear-gradient(90deg,#991b1b,#dc2626,#f59e0b)',
        'christmas' => 'linear-gradient(90deg,#14532d,#15803d,#b91c1c)',
        'mens_day' => 'linear-gradient(90deg,#1e3a8a,#0369a1,#0891b2)',
    ];
    $ctaColors = ['birthday' => '#e11d48', 'womens_day' => '#be123c', 'intl_womens_day' => '#a21caf', 'new_year' => '#5b21b6', 'lunar_new_year' => '#b91c1c', 'christmas' => '#15803d', 'mens_day' => '#1d4ed8'];

    $slides = [];
    $celebrants = [];
    foreach ($events as $event) {
        if ($event['type'] === 'birthday') {
            $celebrants[] = $event['celebrant'];
            continue;
        }
        /** @var \App\Enums\RoomOccasion $occasion */
        $occasion = $event['occasion'];
        $offset = $event['offset'];
        $key = 'occasions.'.$occasion->value;
        $text = $occasion === \App\Enums\RoomOccasion::LunarNewYear
            ? match (true) {
                $offset > 0 => trans_choice($key.'.banner_before', $offset, ['days' => $offset]),
                $offset === 0 => __($key.'.banner_today'),
                // Days after the first day of Tết: "mùng 2" is one day after.
                default => __($key.'.banner_after', ['day' => abs($offset) + 1]),
            }
            : __($key.'.banner', ['year' => $event['date']->year]);
        $slides[] = ['type' => $occasion->value, 'emoji' => $occasion->emoji(), 'text' => $text, 'url' => route('user.rooms.occasion', [$room, $occasion->slug()]), 'cta' => __('occasions.banner.cta'), 'seconds' => 6];
    }

    // Birthday slide: every celebrant in one slow ticker.
    $messages = [];
    $viewerIsCelebrant = false;
    if ($celebrants !== []) {
        $dateOf = static fn (array $celebrant): string => sprintf('%02d/%02d', $celebrant['day'], $celebrant['month']);
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
        // Slow, readable pace whatever the length: about 6 characters per second plus the run-in.
        $tickerSeconds = max(12, (int) round(mb_strlen(implode('   🎉   ', $messages)) / 6) + 8);
        $slides[] = ['type' => 'birthday', 'emoji' => '🎂', 'text' => null, 'url' => route('user.rooms.birthday', $room),
            'cta' => $viewerIsCelebrant ? __('birthday.banner_cta_self') : __('birthday.banner_cta'), 'seconds' => $tickerSeconds];
    }
    $total = count($slides);
@endphp
@once
<style>
  .df-ev{position:relative;overflow:hidden;color:#fff;box-shadow:0 1px 2px rgba(0,0,0,.08)}
  .df-ev-track{display:flex;transition:transform .6s cubic-bezier(.2,.8,.2,1);touch-action:pan-y}
  .df-ev-slide{flex:0 0 100%;min-width:0;position:relative;overflow:hidden}
  .df-ev-slide::before{content:'';position:absolute;inset:0;background:radial-gradient(120px 60px at 12% 50%,rgba(255,255,255,.22),transparent 70%);pointer-events:none}
  .df-ev-link{position:relative;max-width:80rem;margin:0 auto;padding:.625rem 1rem;display:flex;align-items:center;gap:.75rem;color:inherit;text-decoration:none}
  .df-ev.has-many .df-ev-link{padding-left:2.75rem;padding-right:2.75rem;padding-bottom:.9rem}
  .df-ev-icon{display:flex;width:2rem;height:2rem;flex-shrink:0;align-items:center;justify-content:center;border-radius:999px;background:rgba(255,255,255,.2);font-size:1.125rem}
  .df-ev-icon span{display:inline-block;animation:df-ev-bob 1.4s ease-in-out infinite}
  .df-ev-text{min-width:0;flex:1;font-size:.75rem;font-weight:600;line-height:1.35;overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
  @media (min-width:640px){.df-ev-text{font-size:.875rem}.df-ev-link{padding-left:1.5rem;padding-right:1.5rem}.df-ev.has-many .df-ev-link{padding-left:3rem;padding-right:3rem}}
  @keyframes df-ev-bob{50%{transform:translateY(-3px) rotate(-6deg)}}
  @keyframes df-bd-marquee{from{transform:translateX(0)}to{transform:translateX(-100%)}}
  .df-bd-ticker{position:relative;overflow:hidden;white-space:nowrap;-webkit-mask-image:linear-gradient(90deg,transparent,#000 4%,#000 96%,transparent);mask-image:linear-gradient(90deg,transparent,#000 4%,#000 96%,transparent)}
  .df-bd-ticker-track{display:inline-block;padding-left:100%;animation:df-bd-marquee var(--df-bd-duration,20s) linear infinite}
  .df-ev:hover .df-bd-ticker-track,.df-ev:focus-within .df-bd-ticker-track{animation-play-state:paused}
  @keyframes df-bd-cta-pulse{0%,100%{transform:scale(1);box-shadow:0 0 0 0 rgba(255,255,255,.7)}50%{transform:scale(1.06);box-shadow:0 0 0 8px rgba(255,255,255,0)}}
  @keyframes df-bd-cta-shine{0%{left:-60%}60%,100%{left:130%}}
  @keyframes df-bd-cta-arrow{0%,100%{transform:translateX(0)}50%{transform:translateX(3px)}}
  .df-bd-cta{position:relative;overflow:hidden;display:inline-flex;flex-shrink:0;align-items:center;gap:.25rem;border-radius:999px;background:#fff;padding:.25rem .75rem;font-size:.75rem;font-weight:700;animation:df-bd-cta-pulse 1.6s ease-in-out infinite}
  .df-bd-cta::after{content:'';position:absolute;top:0;bottom:0;left:-60%;width:40%;transform:skewX(-20deg);background:linear-gradient(90deg,transparent,rgba(255,255,255,.85),transparent);animation:df-bd-cta-shine 2.2s ease-in-out infinite}
  .df-bd-cta .material-symbols-outlined{animation:df-bd-cta-arrow 1s ease-in-out infinite}
  .df-ev-arrow{position:absolute;top:50%;z-index:2;display:flex;width:1.75rem;height:1.75rem;align-items:center;justify-content:center;border:0;border-radius:999px;background:rgba(0,0,0,.22);color:#fff;cursor:pointer;transform:translateY(-50%);transition:background .2s}
  .df-ev-arrow:hover{background:rgba(0,0,0,.38)}
  .df-ev-arrow.prev{left:.5rem}.df-ev-arrow.next{right:.5rem}
  .df-ev-dots{position:absolute;left:50%;bottom:3px;z-index:2;display:flex;gap:4px;transform:translateX(-50%)}
  .df-ev-dot{width:6px;height:6px;padding:0;border:0;border-radius:999px;background:rgba(255,255,255,.5);cursor:pointer;transition:width .3s,background .3s}
  .df-ev-dot[aria-current="true"]{width:16px;background:#fff}
  .df-ev-arrow:focus-visible,.df-ev-dot:focus-visible,.df-ev-link:focus-visible{outline:2px solid #fff;outline-offset:2px}
  @media (prefers-reduced-motion: reduce){
    .df-ev-track{transition:none}
    .df-bd-ticker{white-space:normal;-webkit-mask-image:none;mask-image:none}
    .df-bd-ticker-track{padding-left:0;animation:none}
    .df-ev-icon span,.df-bd-cta,.df-bd-cta::after,.df-bd-cta .material-symbols-outlined{animation:none}
  }
</style>
@endonce
<section class="df-ev w-full {{ $total > 1 ? 'has-many' : '' }}" data-events-banner aria-label="{{ __('occasions.banner.label') }}" aria-roledescription="carousel">
  <div class="df-ev-track" data-events-track>
    @foreach ($slides as $index => $slide)
      <div class="df-ev-slide" style="background: {{ $gradients[$slide['type']] }}" role="group" aria-roledescription="slide"
        aria-label="{{ $index + 1 }} / {{ $total }}" data-events-slide="{{ $slide['type'] }}" data-seconds="{{ $slide['seconds'] }}"
        @if ($slide['type'] === 'birthday') data-birthday-banner @endif @if ($index > 0) inert aria-hidden="true" @endif>
        <a href="{{ $slide['url'] }}" class="df-ev-link">
          <span class="df-ev-icon" aria-hidden="true"><span>{{ $slide['emoji'] }}</span></span>
          @if ($slide['type'] === 'birthday')
            {{-- Screen readers get the plain list; the moving copy is decorative. --}}
            <span class="sr-only">{{ __('birthday.banner_label') }}: {{ implode('. ', $messages) }}</span>
            <span class="df-bd-ticker min-w-0 flex-1 text-xs sm:text-sm font-semibold" aria-hidden="true">
              <span class="df-bd-ticker-track" style="--df-bd-duration: {{ $slide['seconds'] }}s">
                @foreach ($celebrants as $celebrantIndex => $celebrant)
                  <span data-birthday-link="{{ $celebrant['user_code'] }}">{{ $messages[$celebrantIndex] }}</span>@if (! $loop->last)<span class="px-6" aria-hidden="true">🎉</span>@endif
                @endforeach
              </span>
            </span>
          @else
            <span class="df-ev-text">{{ $slide['text'] }}</span>
          @endif
          <span class="df-bd-cta shadow-xs" style="color: {{ $ctaColors[$slide['type']] }}">
            <span class="hidden sm:inline">{{ $slide['cta'] }}</span>
            <span class="material-symbols-outlined text-[16px]" aria-hidden="true">arrow_forward</span>
          </span>
        </a>
      </div>
    @endforeach
  </div>
  @if ($total > 1)
    <button type="button" class="df-ev-arrow prev" data-events-prev aria-label="{{ __('occasions.banner.prev') }}"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">chevron_left</span></button>
    <button type="button" class="df-ev-arrow next" data-events-next aria-label="{{ __('occasions.banner.next') }}"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">chevron_right</span></button>
    <div class="df-ev-dots">
      @foreach ($slides as $index => $slide)
        <button type="button" class="df-ev-dot" data-events-dot="{{ $index }}" aria-label="{{ __('occasions.banner.go_to', ['number' => $index + 1]) }}" aria-current="{{ $index === 0 ? 'true' : 'false' }}"></button>
      @endforeach
    </div>
    <script>
      (() => {
        const banner = document.currentScript.closest('[data-events-banner]');
        const track = banner.querySelector('[data-events-track]');
        const slides = [...banner.querySelectorAll('[data-events-slide]')];
        const dots = [...banner.querySelectorAll('[data-events-dot]')];
        let current = 0;
        let timer = null;
        let paused = false;
        const go = (index) => {
          current = (index + slides.length) % slides.length;
          track.style.transform = `translateX(-${current * 100}%)`;
          slides.forEach((slide, i) => {
            slide.inert = i !== current;
            slide.setAttribute('aria-hidden', i === current ? 'false' : 'true');
          });
          dots.forEach((dot, i) => dot.setAttribute('aria-current', i === current ? 'true' : 'false'));
          schedule();
        };
        // Each slide stays for its own duration (the birthday ticker needs a full pass).
        const schedule = () => {
          clearTimeout(timer);
          if (paused) return;
          timer = setTimeout(() => go(document.hidden ? current : current + 1), Number(slides[current].dataset.seconds || 6) * 1000);
        };
        banner.querySelector('[data-events-prev]').addEventListener('click', () => go(current - 1));
        banner.querySelector('[data-events-next]').addEventListener('click', () => go(current + 1));
        dots.forEach((dot, i) => dot.addEventListener('click', () => go(i)));
        const pause = (value) => { paused = value; schedule(); };
        banner.addEventListener('pointerenter', (event) => { if (event.pointerType === 'mouse') pause(true); });
        banner.addEventListener('pointerleave', (event) => { if (event.pointerType === 'mouse') pause(false); });
        banner.addEventListener('focusin', () => pause(true));
        banner.addEventListener('focusout', (event) => { if (!banner.contains(event.relatedTarget)) pause(false); });
        // Swipe on touch screens; a swipe never follows the link underneath.
        let startX = null;
        let swiped = false;
        track.addEventListener('pointerdown', (event) => { if (event.pointerType !== 'mouse') { startX = event.clientX; swiped = false; } });
        track.addEventListener('pointerup', (event) => {
          if (startX === null) return;
          const dx = event.clientX - startX;
          startX = null;
          if (Math.abs(dx) > 40) { swiped = true; go(current + (dx < 0 ? 1 : -1)); }
        });
        track.addEventListener('click', (event) => { if (swiped) { event.preventDefault(); swiped = false; } }, true);
        schedule();
      })();
    </script>
  @endif
</section>
