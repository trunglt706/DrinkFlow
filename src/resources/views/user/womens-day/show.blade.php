{{-- Stand-alone Vietnamese Women's Day page of a room (no site header / footer), opened from the 20/10 greeting
     notification. Only a rose bud is shown at first; clicking it makes the rose bloom and reveals the wishes, one per
     slide, moving to the next slide every 2 seconds. --}}
@php
    $wishes = array_values((array) __('womens_day.wishes'));
    $total = count($wishes);
    // Bloomed rose head: rings of petals drawn from the outside in, so the inner (darker) ones sit on top. Each ring:
    // [petal count, petal length, rotation offset in degrees, gradient id, unfurl delay of the ring in seconds].
    $rings = [
        [5, 96, 0, 'wd-petal-1', 0.75],
        [5, 80, 36, 'wd-petal-2', 0.6],
        [5, 62, 12, 'wd-petal-3', 0.45],
        [4, 45, 55, 'wd-petal-4', 0.3],
        [3, 30, 20, 'wd-petal-5', 0.15],
    ];
    $petalPath = static function (float $length): string {
        $l = $length;

        return sprintf('M0 0C%1$.1f %2$.1f %3$.1f %4$.1f 0 %5$.1fC%6$.1f %4$.1f %7$.1f %2$.1f 0 0Z',
            -0.62 * $l, -0.2 * $l, -0.58 * $l, -0.98 * $l, -$l, 0.58 * $l, 0.62 * $l);
    };
    $sparkles = [[70, 120, 0], [232, 96, 0.7], [214, 236, 1.4], [84, 262, 2.1], [150, 52, 1.1], [258, 176, 1.8], [44, 190, 0.4]];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
  <meta name="theme-color" content="#2b0a1e">
  <title>{{ __('womens_day.page_title', ['room' => $room->name]) }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&family=Playfair+Display:ital,wght@1,500&family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    :root{--cream:#fff4ef;--rose:#ff4f7b;--blush:#ffb3c6;--gold:#f7c873;--wine:#2b0a1e;--glass:rgba(255,255,255,.08);--line:rgba(255,214,226,.22)}
    html,body{min-height:100%}
    body{font-family:'Be Vietnam Pro',system-ui,sans-serif;color:var(--cream);overflow-x:hidden;background:var(--wine)}
    .bg{position:fixed;inset:0;z-index:0;pointer-events:none;
      background:radial-gradient(700px 520px at 50% 38%,rgba(255,79,123,.28),transparent 70%),radial-gradient(900px 600px at 85% 105%,rgba(247,200,115,.22),transparent 65%),radial-gradient(800px 600px at 5% 0%,rgba(168,85,247,.22),transparent 60%),linear-gradient(160deg,#2b0a1e,#4a0f2c 48%,#1d0b24)}
    .bokeh{position:fixed;inset:0;z-index:1;pointer-events:none;overflow:hidden}
    .bokeh i{position:absolute;border-radius:50%;background:radial-gradient(circle,rgba(255,190,210,.55),rgba(255,190,210,0) 70%);animation:float linear infinite;opacity:.6}
    #wd-petals{position:fixed;inset:0;width:100%;height:100%;z-index:3;pointer-events:none}
    .back{position:fixed;top:16px;left:16px;z-index:20;display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:999px;font-size:13px;font-weight:600;color:var(--cream);text-decoration:none;
      background:var(--glass);border:1px solid var(--line);backdrop-filter:blur(8px);transition:background .2s,transform .2s}
    .back:hover{background:rgba(255,255,255,.16);transform:translateY(-1px)}

    main{position:relative;z-index:5;min-height:100vh;min-height:100svh;display:flex;align-items:center;justify-content:center;gap:clamp(16px,4vw,64px);padding:72px 20px 40px}
    .rose-wrap{display:flex;flex-direction:column;align-items:center;gap:14px;flex:none;transform-origin:50% 50%}
    .rose{display:block;border:0;background:none;cursor:pointer;-webkit-tap-highlight-color:transparent;border-radius:24px;line-height:0}
    .rose:focus-visible{outline:3px solid var(--gold);outline-offset:6px}
    .rose svg{display:block;height:min(66vh,560px);width:auto;overflow:visible}
    .is-bloomed .rose{cursor:default}
    .is-bloomed .rose svg{height:min(56vh,480px)}
    .tap-hint{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:999px;font-size:15px;font-weight:600;letter-spacing:.01em;
      background:linear-gradient(90deg,rgba(120,22,58,.85),rgba(110,62,40,.8));border:1px solid var(--line);
      cursor:pointer;animation:hint 2.2s ease-in-out infinite;box-shadow:0 0 0 0 rgba(255,79,123,.5)}
    .tap-hint .hand{display:inline-block;animation:tap 1.1s ease-in-out infinite}
    .is-bloomed .tap-hint{display:none}

    /* Rose: the whole branch sways; the bud closes away while the petals of the bloom unfurl from the centre out. */
    .branch{transform-box:view-box;transform-origin:150px 612px;animation:sway 4.5s ease-in-out infinite alternate}
    .halo{transform-box:fill-box;transform-origin:50% 50%;animation:halo 3s ease-in-out infinite}
    .is-bloomed .halo{animation:halo-burst 1.2s ease-out both,halo 4s 1.2s ease-in-out infinite}
    .bud{transform-box:view-box;transform-origin:150px 208px;transition:transform .55s cubic-bezier(.6,-.3,.7,1),opacity .45s .1s}
    .rose:hover .bud{transform:scale(1.04)}
    .is-bloomed .bud{transform:scale(.4) translateY(30px);opacity:0}
    .petal,.sepal{transform-box:view-box;transform-origin:0 0;transform:scale(0);opacity:0}
    .is-bloomed .petal{animation:unfurl 1.1s cubic-bezier(.2,.8,.25,1.2) forwards;animation-delay:var(--d)}
    .is-bloomed .sepal{animation:unfurl .8s ease-out forwards;animation-delay:var(--d)}
    .breathe{transform-box:view-box;transform-origin:0 0}
    .is-bloomed .breathe{animation:breathe 5s 2.4s ease-in-out infinite}
    .spark{transform-box:view-box;transform-origin:0 0;fill:#ffe9b0;animation:twinkle 2.4s ease-in-out infinite;animation-delay:var(--d)}
    .is-bloomed .spark{fill:#fff}

    /* Message: heading + slideshow, shown once the rose has bloomed. */
    .message{display:none;width:min(560px,100%);text-align:center}
    .is-revealed .message{display:block;animation:rise .9s cubic-bezier(.2,.8,.2,1) both}
    .eyebrow{display:inline-flex;align-items:center;gap:8px;padding:6px 14px;border-radius:999px;font-size:12px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--gold);background:rgba(247,200,115,.1);border:1px solid rgba(247,200,115,.3)}
    h1{margin-top:14px;font-family:'Dancing Script',cursive;font-weight:700;font-size:clamp(48px,8vw,84px);line-height:1.05;
      background:linear-gradient(90deg,#fff,var(--gold),#ffd1dc,#fff,var(--blush),#fff);background-size:300% 100%;-webkit-background-clip:text;background-clip:text;color:transparent;
      animation:shimmer 7s linear infinite}
    .sub{margin-top:6px;font-size:clamp(15px,2.2vw,17px);color:rgba(255,244,239,.82)}
    /* Opaque-ish card instead of backdrop-filter: petals fall behind it, which would re-blur the backdrop every frame. */
    .carousel{position:relative;margin-top:26px;padding:30px 28px 22px;border-radius:26px;background:linear-gradient(145deg,rgba(92,22,52,.92),rgba(52,14,40,.9));border:1px solid var(--line);
      box-shadow:0 30px 70px -30px rgba(0,0,0,.7),inset 0 1px 0 rgba(255,255,255,.18);overflow:hidden}
    .carousel::before{content:'';position:absolute;inset:-40% -20% auto auto;width:260px;height:260px;border-radius:50%;background:radial-gradient(circle,rgba(255,79,123,.35),transparent 70%);pointer-events:none}
    .slides{display:grid;list-style:none;position:relative}
    .slide{grid-area:1/1;display:flex;flex-direction:column;align-items:center;gap:12px;opacity:0;visibility:hidden;transform:translateX(48px) rotate(1.5deg) scale(.96);filter:blur(6px);
      transition:opacity .6s ease,transform .7s cubic-bezier(.2,.8,.2,1),filter .6s ease,visibility 0s .7s}
    .slide.is-active{opacity:1;visibility:visible;transform:none;filter:none;transition-delay:0s}
    .slide.is-leaving{opacity:0;visibility:visible;transform:translateX(-48px) rotate(-1.5deg) scale(.96);filter:blur(6px)}
    .quote{font-family:'Playfair Display',Georgia,serif;font-size:60px;line-height:1;height:30px;color:var(--gold);opacity:.85}
    .wish{font-family:'Playfair Display',Georgia,serif;font-style:italic;font-weight:500;font-size:clamp(18px,2.8vw,23px);line-height:1.55;min-height:4.65em;display:flex;align-items:center;text-wrap:balance}
    .count{font-size:12px;font-weight:600;letter-spacing:.2em;color:rgba(255,244,239,.55)}
    .progress{position:relative;height:3px;margin-top:18px;border-radius:3px;background:rgba(255,255,255,.14);overflow:hidden}
    .progress span{position:absolute;inset:0;transform-origin:0 50%;transform:scaleX(0);background:linear-gradient(90deg,var(--rose),var(--gold))}
    .progress span.is-running{animation:progress var(--slide-ms,2000ms) linear forwards}
    .carousel.is-paused .progress span{animation-play-state:paused}
    .controls{display:flex;align-items:center;justify-content:center;gap:10px;margin-top:16px}
    .icon-btn{width:38px;height:38px;display:grid;place-items:center;border-radius:50%;border:1px solid var(--line);background:rgba(255,255,255,.08);color:var(--cream);font-size:15px;cursor:pointer;transition:background .2s,transform .2s}
    .icon-btn:hover{background:rgba(255,255,255,.18);transform:scale(1.06)}
    .icon-btn:focus-visible,.dot:focus-visible,.btn:focus-visible{outline:2px solid var(--gold);outline-offset:3px}
    .dots{display:flex;gap:7px;padding:0 6px}
    .dot{width:9px;height:9px;padding:0;border:0;border-radius:999px;background:rgba(255,255,255,.3);cursor:pointer;transition:width .35s,background .35s}
    .dot[aria-current="true"]{width:26px;background:linear-gradient(90deg,var(--rose),var(--gold))}
    .footer{display:flex;flex-direction:column;align-items:center;gap:12px;margin-top:22px}
    .signature{font-family:'Dancing Script',cursive;font-size:26px;color:rgba(255,244,239,.92)}
    .btn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border-radius:999px;border:1px solid var(--line);background:rgba(255,255,255,.08);color:var(--cream);font:600 14px 'Be Vietnam Pro',sans-serif;cursor:pointer;transition:background .2s,transform .2s}
    .btn:hover{background:rgba(255,255,255,.16);transform:translateY(-1px)}

    @keyframes sway{from{transform:rotate(-1.6deg)}to{transform:rotate(1.6deg)}}
    @keyframes halo{50%{opacity:.55;transform:scale(1.08)}}
    @keyframes halo-burst{0%{transform:scale(.6);opacity:.2}35%{transform:scale(1.5);opacity:1}100%{transform:scale(1);opacity:.85}}
    @keyframes unfurl{0%{transform:scale(.05) rotate(-40deg);opacity:0}45%{opacity:1}100%{transform:scale(1) rotate(0);opacity:1}}
    @keyframes breathe{50%{transform:scale(1.035) rotate(-1deg)}}
    @keyframes twinkle{0%,100%{transform:scale(.2);opacity:0}50%{transform:scale(1) rotate(45deg);opacity:1}}
    @keyframes hint{50%{box-shadow:0 0 0 12px rgba(255,79,123,0);transform:translateY(-3px)}}
    @keyframes tap{50%{transform:translateY(-4px) scale(1.1)}}
    @keyframes float{from{transform:translateY(0)}to{transform:translateY(-120vh)}}
    @keyframes rise{from{opacity:0;transform:translateY(28px)}to{opacity:1;transform:none}}
    @keyframes shimmer{to{background-position:300% 0}}
    @keyframes progress{to{transform:scaleX(1)}}

    @media (max-width:900px){
      main{flex-direction:column;justify-content:center;padding-top:76px}
      .is-revealed main{justify-content:flex-start}
      .is-bloomed .rose svg{height:min(34vh,300px)}
      .carousel{padding:24px 18px 18px;margin-top:20px}
    }
    @media (prefers-reduced-motion:reduce){
      *,*::before,*::after{animation-duration:.01ms!important;animation-iteration-count:1!important;animation-delay:0s!important;transition-duration:.01ms!important}
      .bokeh{display:none}
    }
  </style>
</head>
<body>
  <div class="bg" aria-hidden="true"></div>
  <div class="bokeh" aria-hidden="true" data-bokeh></div>
  <canvas id="wd-petals" aria-hidden="true"></canvas>

  <a class="back" href="{{ route('user.dashboard', $room) }}">← {{ __('womens_day.back_to_room') }}</a>

  <main data-womens-day data-i18n="{{ json_encode(['pause' => __('womens_day.pause'), 'play' => __('womens_day.play')], JSON_UNESCAPED_UNICODE) }}">
    <div class="rose-wrap" data-rose-wrap>
      <button type="button" class="rose" data-rose aria-label="{{ __('womens_day.bud_label') }}" aria-expanded="false" aria-controls="wd-message">
        <svg viewBox="0 0 300 620" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
          <defs>
            <radialGradient id="wd-halo"><stop offset="0" stop-color="#ff6f93" stop-opacity=".55"/><stop offset=".55" stop-color="#ff4f7b" stop-opacity=".16"/><stop offset="1" stop-color="#ff4f7b" stop-opacity="0"/></radialGradient>
            <linearGradient id="wd-stem" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#2f6b3a"/><stop offset=".5" stop-color="#4f9a55"/><stop offset="1" stop-color="#255a30"/></linearGradient>
            <linearGradient id="wd-leaf" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#6fbf6a"/><stop offset="1" stop-color="#25633a"/></linearGradient>
            <linearGradient id="wd-sepal" x1="0" y1="1" x2="0" y2="0"><stop offset="0" stop-color="#2c6b37"/><stop offset="1" stop-color="#6bb865"/></linearGradient>
            <linearGradient id="wd-bud-back" x1="0" y1="1" x2="0" y2="0"><stop offset="0" stop-color="#7a0b2b"/><stop offset="1" stop-color="#e0315b"/></linearGradient>
            <linearGradient id="wd-bud-front" x1="0" y1="1" x2=".6" y2="0"><stop offset="0" stop-color="#a3143a"/><stop offset="1" stop-color="#ff6d8f"/></linearGradient>
            @foreach ([['#a3143a', '#ff6d8f'], ['#8f1033', '#f4466d'], ['#7c0c2d', '#e2335c'], ['#6a0925', '#cc2650'], ['#560720', '#b51d45']] as $index => [$base, $tip])
              <linearGradient id="wd-petal-{{ $index + 1 }}" x1="0" y1="1" x2="0" y2="0"><stop offset="0" stop-color="{{ $base }}"/><stop offset=".7" stop-color="{{ $tip }}"/><stop offset="1" stop-color="#ffc2d1"/></linearGradient>
            @endforeach
          </defs>

          <circle class="halo" cx="150" cy="160" r="135" fill="url(#wd-halo)"/>

          <g class="branch">
            {{-- Stem, thorns and leaves --}}
            <path d="M150 612C140 520 162 430 148 340C141 292 152 252 150 206" fill="none" stroke="url(#wd-stem)" stroke-width="7" stroke-linecap="round"/>
            <path d="M151 384l9-6-7 11z M147 462l-9-5 6 11z M153 286l8-6-6 10z" fill="#2f6b3a"/>
            <g transform="translate(146 428) rotate(-150) scale(1 -1)">
              <path d="M0 0C18-22 58-26 88-4 58 18 20 16 0 0Z" fill="url(#wd-leaf)"/>
              <path d="M3 0C30-6 58-7 86-4" fill="none" stroke="#1f4f2b" stroke-width="1.4" stroke-opacity=".6"/>
            </g>
            <g transform="translate(150 338) rotate(-28)">
              <path d="M0 0C18-22 58-26 88-4 58 18 20 16 0 0Z" fill="url(#wd-leaf)"/>
              <path d="M3 0C30-6 58-7 86-4" fill="none" stroke="#1f4f2b" stroke-width="1.4" stroke-opacity=".6"/>
            </g>
            <g transform="translate(148 520) rotate(-158) scale(.7 -.7)">
              <path d="M0 0C18-22 58-26 88-4 58 18 20 16 0 0Z" fill="url(#wd-leaf)"/>
              <path d="M3 0C30-6 58-7 86-4" fill="none" stroke="#1f4f2b" stroke-width="1.4" stroke-opacity=".6"/>
            </g>

            {{-- Bud (shown first) --}}
            <g class="bud">
              <g transform="translate(150 208) scale(1.25)">
                <path d="M0 0C-30-12-36-62-4-110 12-90 36-62 31-28 28-10 14-2 0 0Z" fill="url(#wd-bud-back)"/>
                <path d="M0 0C-27-6-33-46-14-82-6-55 8-36 31-27 25-8 12-1 0 0Z" fill="url(#wd-bud-front)"/>
                <path d="M-14-82C-4-58 9-39 29-28" fill="none" stroke="#ffc2d1" stroke-width="1.6" stroke-opacity=".7" stroke-linecap="round"/>
                <path d="M-4-110C-10-96-16-80-14-66" fill="none" stroke="#ffd6e0" stroke-width="1.2" stroke-opacity=".5" stroke-linecap="round"/>
                <path d="M0 3C-15-1-28-18-28-48-17-30-8-17 5-8Z" fill="url(#wd-sepal)"/>
                <path d="M0 3C15-1 30-16 32-44 21-28 11-15-5-8Z" fill="url(#wd-sepal)"/>
                <path d="M0 5C-7-6-4-26 3-40 9-24 9-7 0 5Z" fill="url(#wd-sepal)"/>
                <ellipse cx="0" cy="5" rx="10" ry="7" fill="#2f6b3a"/>
              </g>
            </g>

            {{-- Bloom (unfurls on click) --}}
            <g transform="translate(150 150) scale(1.2)">
              <g class="breathe">
                <g transform="scale(1 .86)">
                  @foreach ([150, 205, 178, 128, 230] as $index => $angle)
                    <g transform="rotate({{ $angle }})"><path class="sepal" style="--d:{{ 0.5 + $index * 0.05 }}s" d="M0 0C-12-30-10-80 0-112 10-80 12-30 0 0Z" fill="url(#wd-sepal)"/></g>
                  @endforeach
                  @foreach ($rings as [$count, $length, $offset, $gradient, $delay])
                    @for ($petal = 0; $petal < $count; $petal++)
                      <g transform="rotate({{ $offset + $petal * 360 / $count }})"><path class="petal" style="--d:{{ $delay + $petal * 0.04 }}s" d="{{ $petalPath($length) }}" fill="url(#{{ $gradient }})" stroke="#4a0519" stroke-opacity=".35" stroke-width="1"/></g>
                    @endfor
                  @endforeach
                  <g><path class="petal" style="--d:.05s" d="M0-2C6-2 7 6 0 7-9 8-10-6-2-10 8-14 15-4 13 5 11 13 2 17-6 15" fill="#5a071f" stroke="#ff9fb6" stroke-opacity=".55" stroke-width="2" stroke-linecap="round"/></g>
                </g>
              </g>
            </g>
          </g>

          {{-- Twinkles around the rose --}}
          @foreach ($sparkles as [$x, $y, $delay])
            <g transform="translate({{ $x }} {{ $y }})"><path class="spark" style="--d:{{ $delay }}s" d="M0-7L1.6-1.6 7 0 1.6 1.6 0 7-1.6 1.6-7 0-1.6-1.6Z"/></g>
          @endforeach
        </svg>
      </button>
      <p class="tap-hint" data-hint><span class="hand" aria-hidden="true">👆</span> {{ __('womens_day.tap_hint') }}</p>
    </div>

    <section class="message" id="wd-message" data-message aria-hidden="true">
      <span class="eyebrow">🌹 {{ __('womens_day.eyebrow') }}</span>
      <h1>{{ __('womens_day.heading') }}</h1>
      <p class="sub">{{ __('womens_day.subheading', ['room' => $room->name]) }}</p>

      <div class="carousel" role="region" aria-roledescription="carousel" aria-label="{{ __('womens_day.slides_label') }}" data-carousel>
        <ul class="slides" aria-live="off" data-slides>
          @foreach ($wishes as $wish)
            <li class="slide {{ $loop->first ? 'is-active' : '' }}" role="group" aria-roledescription="slide"
              aria-label="{{ __('womens_day.slide_label', ['current' => $loop->iteration, 'total' => $total]) }}" data-slide>
              <span class="quote" aria-hidden="true">“</span>
              <p class="wish">{{ $wish }}</p>
              <span class="count" aria-hidden="true">{{ sprintf('%02d / %02d', $loop->iteration, $total) }}</span>
            </li>
          @endforeach
        </ul>
        <div class="progress" aria-hidden="true"><span data-progress></span></div>
        <div class="controls">
          <button type="button" class="icon-btn" data-prev aria-label="{{ __('womens_day.prev') }}">‹</button>
          <div class="dots">
            @foreach ($wishes as $wish)
              <button type="button" class="dot" data-dot="{{ $loop->index }}" aria-label="{{ __('womens_day.go_to', ['number' => $loop->iteration]) }}" aria-current="{{ $loop->first ? 'true' : 'false' }}"></button>
            @endforeach
          </div>
          <button type="button" class="icon-btn" data-next aria-label="{{ __('womens_day.next') }}">›</button>
          <button type="button" class="icon-btn" data-toggle aria-label="{{ __('womens_day.pause') }}" aria-pressed="false">❚❚</button>
        </div>
      </div>

      <div class="footer">
        <p class="signature">{{ __('womens_day.signature', ['room' => $room->name]) }}</p>
        <button type="button" class="btn" data-replay>🌹 {{ __('womens_day.replay') }}</button>
      </div>
    </section>
  </main>

  <script>
    (() => {
      const SLIDE_MS = 2000;
      const page = document.querySelector('[data-womens-day]');
      let i18n = {};
      try { i18n = JSON.parse(page.dataset.i18n || '{}'); } catch { i18n = {}; }
      const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      const random = (min, max) => Math.random() * (max - min) + min;
      const rose = page.querySelector('[data-rose]');
      const roseWrap = page.querySelector('[data-rose-wrap]');
      const message = page.querySelector('[data-message]');

      // Soft bokeh lights drifting up (decoration only).
      const bokeh = document.querySelector('[data-bokeh]');
      for (let i = 0; i < 18; i++) {
        const light = document.createElement('i');
        const size = random(30, 120);
        Object.assign(light.style, { width: `${size}px`, height: `${size}px`, left: `${random(0, 100)}%`, top: `${random(10, 110)}%`, animationDuration: `${random(18, 34)}s`, animationDelay: `${random(-30, 0)}s` });
        bokeh.append(light);
      }

      // Canvas particles: falling rose petals once the rose has bloomed, and a burst of petals and hearts on bloom.
      const canvas = document.getElementById('wd-petals');
      const ctx = canvas.getContext('2d');
      const colors = ['#ff4f7b', '#ff7d9c', '#ffb3c6', '#e0315b', '#f7c873', '#ffffff'];
      let particles = [];
      let raining = false;
      const resize = () => { canvas.width = innerWidth * devicePixelRatio; canvas.height = innerHeight * devicePixelRatio; ctx.setTransform(devicePixelRatio, 0, 0, devicePixelRatio, 0, 0); };
      resize();
      addEventListener('resize', resize);
      const fallingPetal = () => ({ kind: 'petal', x: random(0, innerWidth), y: -20, vx: random(-0.4, 0.4), vy: random(0.7, 1.6), size: random(8, 15), color: colors[Math.floor(random(0, 4))], rotation: random(0, 360), spin: random(-2, 2), sway: random(0, Math.PI * 2), life: Infinity, gravity: 0 });
      const burst = (x, y) => {
        for (let i = 0; i < 90; i++) {
          const angle = random(0, Math.PI * 2);
          const speed = random(2.5, 9);
          particles.push({ kind: Math.random() < 0.35 ? 'heart' : 'petal', x, y, vx: Math.cos(angle) * speed, vy: Math.sin(angle) * speed - 2.5, size: random(8, 16), color: colors[Math.floor(random(0, colors.length))], rotation: random(0, 360), spin: random(-8, 8), sway: 0, life: random(90, 160), gravity: 0.12 });
        }
      };
      const drawPetal = (size) => { ctx.beginPath(); ctx.moveTo(0, -size / 2); ctx.bezierCurveTo(size / 2, -size / 2, size / 2, size / 3, 0, size / 2); ctx.bezierCurveTo(-size / 2, size / 3, -size / 2, -size / 2, 0, -size / 2); ctx.fill(); };
      const drawHeart = (size) => { const s = size / 2; ctx.beginPath(); ctx.moveTo(0, s * 0.6); ctx.bezierCurveTo(-s * 1.4, -s * 0.3, -s * 0.5, -s * 1.3, 0, -s * 0.45); ctx.bezierCurveTo(s * 0.5, -s * 1.3, s * 1.4, -s * 0.3, 0, s * 0.6); ctx.fill(); };
      let lastSpawn = 0;
      const tick = (time) => {
        ctx.clearRect(0, 0, innerWidth, innerHeight);
        if (raining && !document.hidden && time - lastSpawn > 380 && particles.length < 120) { particles.push(fallingPetal()); lastSpawn = time; }
        particles = particles.filter((p) => p.life > 0 && p.y < innerHeight + 30);
        for (const p of particles) {
          p.vy += p.gravity; p.vx *= p.gravity ? 0.985 : 1; p.sway += 0.03;
          p.x += p.vx + (p.gravity ? 0 : Math.sin(p.sway) * 0.8); p.y += p.vy; p.rotation += p.spin; p.life--;
          ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.rotation * Math.PI / 180); ctx.globalAlpha = Number.isFinite(p.life) ? Math.min(1, p.life / 40) : 0.85; ctx.fillStyle = p.color;
          p.kind === 'heart' ? drawHeart(p.size) : drawPetal(p.size);
          ctx.restore();
        }
        requestAnimationFrame(tick);
      };
      if (!reduceMotion) requestAnimationFrame(tick);

      // Slideshow of wishes: next slide every SLIDE_MS, paused on hover / focus, with prev / next / dots / pause.
      const carousel = page.querySelector('[data-carousel]');
      const slides = [...page.querySelectorAll('[data-slide]')];
      const dots = [...page.querySelectorAll('[data-dot]')];
      const progress = page.querySelector('[data-progress]');
      const toggle = page.querySelector('[data-toggle]');
      let current = 0;
      let timer = null;
      let started = false;
      let userPaused = false;
      let hoverPaused = false;
      const show = (index) => {
        const next = (index + slides.length) % slides.length;
        if (next !== current) {
          const leaving = slides[current];
          leaving.classList.remove('is-active');
          leaving.classList.add('is-leaving');
          setTimeout(() => leaving.classList.remove('is-leaving'), 700);
        }
        current = next;
        slides[current].classList.remove('is-leaving');
        slides[current].classList.add('is-active');
        dots.forEach((dot, i) => dot.setAttribute('aria-current', i === current ? 'true' : 'false'));
        schedule();
      };
      const schedule = () => {
        clearTimeout(timer);
        progress.classList.remove('is-running');
        const paused = userPaused || hoverPaused;
        carousel.classList.toggle('is-paused', paused);
        if (!started || paused || slides.length < 2) return;
        void progress.offsetWidth;
        progress.classList.add('is-running');
        timer = setTimeout(() => show(document.hidden ? current : current + 1), SLIDE_MS);
      };
      carousel.style.setProperty('--slide-ms', `${SLIDE_MS}ms`);
      page.querySelector('[data-prev]').addEventListener('click', () => show(current - 1));
      page.querySelector('[data-next]').addEventListener('click', () => show(current + 1));
      dots.forEach((dot, i) => dot.addEventListener('click', () => show(i)));
      toggle.addEventListener('click', () => {
        userPaused = !userPaused;
        toggle.setAttribute('aria-pressed', userPaused ? 'true' : 'false');
        toggle.setAttribute('aria-label', userPaused ? (i18n.play || '') : (i18n.pause || ''));
        toggle.textContent = userPaused ? '▶' : '❚❚';
        schedule();
      });
      // Only a mouse pauses on hover: a tap on a touch screen never sends the matching "leave".
      carousel.addEventListener('pointerenter', (event) => { if (event.pointerType === 'mouse') { hoverPaused = true; schedule(); } });
      carousel.addEventListener('pointerleave', (event) => { if (event.pointerType === 'mouse') { hoverPaused = false; schedule(); } });
      document.addEventListener('visibilitychange', schedule);

      // Smoothly move the rose to its new place / size when the layout changes (FLIP).
      const withFlip = (change) => {
        const before = roseWrap.getBoundingClientRect();
        change();
        if (reduceMotion) return;
        const after = roseWrap.getBoundingClientRect();
        const dx = before.left + before.width / 2 - (after.left + after.width / 2);
        const dy = before.top + before.height / 2 - (after.top + after.height / 2);
        const scale = before.height / after.height;
        roseWrap.animate([{ transform: `translate(${dx}px, ${dy}px) scale(${scale})` }, { transform: 'none' }], { duration: 900, easing: 'cubic-bezier(.2,.8,.2,1)' });
      };

      // Bloom: the bud opens into a rose, petals burst out, then the wishes appear and start sliding.
      const timeouts = [];
      const bloom = () => {
        if (page.classList.contains('is-bloomed')) return;
        page.classList.add('is-bloomed');
        rose.setAttribute('aria-expanded', 'true');
        rose.disabled = true;
        const head = rose.querySelector('svg').getBoundingClientRect();
        timeouts.push(setTimeout(() => { if (!reduceMotion) burst(head.left + head.width / 2, head.top + head.height * 0.25); raining = true; }, reduceMotion ? 0 : 500));
        timeouts.push(setTimeout(() => {
          withFlip(() => page.classList.add('is-revealed'));
          message.setAttribute('aria-hidden', 'false');
        }, reduceMotion ? 0 : 1300));
        timeouts.push(setTimeout(() => { started = true; show(0); }, reduceMotion ? 0 : 2200));
      };
      const replay = () => {
        timeouts.splice(0).forEach(clearTimeout);
        started = false;
        raining = false;
        withFlip(() => page.classList.remove('is-revealed', 'is-bloomed'));
        message.setAttribute('aria-hidden', 'true');
        rose.setAttribute('aria-expanded', 'false');
        rose.disabled = false;
        show(0);
        rose.focus();
        scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
      };
      rose.addEventListener('click', bloom);
      page.querySelector('[data-hint]').addEventListener('click', bloom);
      page.querySelector('[data-replay]').addEventListener('click', replay);
    })();
  </script>
</body>
</html>
