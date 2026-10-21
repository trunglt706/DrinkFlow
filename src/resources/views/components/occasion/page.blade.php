{{-- Shared frame of the stand-alone occasion pages (no site header / footer): only the centerpiece and its hint are
     shown at first; clicking it plays the page's reveal animation ("is-open"), bursts particles, then shows the
     heading and the wishes, one per slide, moving to the next slide once it had time to be read (5 to 10 seconds).
     Each page passes its centerpiece SVG (slot), its own CSS (`styles` slot), its palette (`theme`: CSS custom
     properties), its particles and sound effects played on open (`effects`, `sounds`: [[effect, delay ms], ...]),
     the room's music settings (`audio`) and the wishes wall (`wall`, null to leave it out). Texts come from
     lang/{locale}/occasions.php under the occasion key. --}}
@props(['room', 'occasion', 'year', 'wishes' => null, 'wall' => null, 'backUrl' => null, 'preview' => false, 'audio' => \App\Services\Occasion\RoomEventSettingsService::defaults(), 'theme' => '', 'effects' => [], 'styles' => null, 'extra' => null])
@php
    /** @var \App\Enums\RoomOccasion $occasion */
    $key = 'occasions.'.$occasion->value;
    // The room's own wishes (admin events page), otherwise the default sample wishes of the occasion.
    $wishes = array_values($wishes ?? (array) __($key.'.wishes'));
    $total = count($wishes);
    $params = ['room' => $room->name, 'year' => $year];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
  <title>{{ __($key.'.page_title', $params) }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&family=Playfair+Display:ital,wght@1,500&family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    :root{--text:#fff4ef;--accent:#ff4f7b;--accent-2:#f7c873;--soft:#ffb3c6;--line:rgba(255,214,226,.22);--glass:rgba(255,255,255,.08);
      --card-1:rgba(92,22,52,.92);--card-2:rgba(52,14,40,.9);--hint-1:rgba(120,22,58,.85);--hint-2:rgba(110,62,40,.8);--bokeh:255,190,210;
      --bg:linear-gradient(160deg,#2b0a1e,#4a0f2c 48%,#1d0b24);--glow-1:rgba(255,79,123,.28);--glow-2:rgba(247,200,115,.22);--glow-3:rgba(168,85,247,.22);
      {!! $theme !!}}
    html,body{min-height:100%}
    body{font-family:'Be Vietnam Pro',system-ui,sans-serif;color:var(--text);overflow-x:hidden;background:#140812}
    .bg{position:fixed;inset:0;z-index:0;pointer-events:none;
      background:radial-gradient(700px 520px at 50% 38%,var(--glow-1),transparent 70%),radial-gradient(900px 600px at 85% 105%,var(--glow-2),transparent 65%),radial-gradient(800px 600px at 5% 0%,var(--glow-3),transparent 60%),var(--bg)}
    .bokeh{position:fixed;inset:0;z-index:1;pointer-events:none;overflow:hidden}
    .bokeh i{position:absolute;border-radius:50%;background:radial-gradient(circle,rgba(var(--bokeh),.55),rgba(var(--bokeh),0) 70%);animation:oc-float linear infinite;opacity:.6}
    #oc-particles{position:fixed;inset:0;width:100%;height:100%;z-index:3;pointer-events:none}
    .back{position:fixed;top:16px;left:16px;z-index:20;display:inline-flex;align-items:center;gap:6px;padding:9px 16px;border-radius:999px;font-size:13px;font-weight:600;color:var(--text);text-decoration:none;
      background:rgba(0,0,0,.28);border:1px solid var(--line);transition:background .2s,transform .2s}
    .back:hover{background:rgba(255,255,255,.16);transform:translateY(-1px)}

    main{position:relative;z-index:5;min-height:100vh;min-height:100svh;display:flex;align-items:center;justify-content:center;gap:clamp(16px,4vw,64px);padding:72px 20px 40px}
    .stage{display:flex;flex-direction:column;align-items:center;gap:14px;flex:none;transform-origin:50% 50%}
    .stage-btn{display:block;border:0;background:none;cursor:pointer;-webkit-tap-highlight-color:transparent;border-radius:24px;line-height:0;color:inherit}
    .stage-btn:focus-visible{outline:3px solid var(--accent-2);outline-offset:6px}
    .stage-btn svg{display:block;height:min(66vh,560px);width:auto;max-width:calc(100vw - 40px);overflow:visible}
    .is-open .stage-btn{cursor:default}
    .is-open .stage-btn svg{height:min(56vh,480px)}
    .tap-hint{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:999px;font-size:15px;font-weight:600;letter-spacing:.01em;text-align:center;
      background:linear-gradient(90deg,var(--hint-1),var(--hint-2));border:1px solid var(--line);cursor:pointer;animation:oc-hint 2.2s ease-in-out infinite}
    .tap-hint .hand{display:inline-block;animation:oc-tap 1.1s ease-in-out infinite}
    .is-open .tap-hint{display:none}

    .message{display:none;width:min(560px,100%);text-align:center}
    .is-revealed .message{display:block;animation:oc-rise .9s cubic-bezier(.2,.8,.2,1) both}
    .eyebrow{display:inline-flex;align-items:center;gap:8px;padding:6px 14px;border-radius:999px;font-size:12px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--accent-2);background:rgba(255,255,255,.06);border:1px solid var(--line)}
    h1{margin-top:14px;font-family:'Dancing Script',cursive;font-weight:700;font-size:clamp(48px,8vw,84px);line-height:1.05;
      background:linear-gradient(90deg,#fff,var(--accent-2),var(--soft),#fff,var(--accent-2),#fff);background-size:300% 100%;-webkit-background-clip:text;background-clip:text;color:transparent;
      animation:oc-shimmer 7s linear infinite}
    .sub{margin-top:6px;font-size:clamp(15px,2.2vw,17px);color:rgba(255,255,255,.82)}
    /* Opaque-ish card instead of backdrop-filter: particles fall behind it, which would re-blur the backdrop every frame. */
    .carousel{position:relative;margin-top:26px;padding:30px 28px 22px;border-radius:26px;background:linear-gradient(145deg,var(--card-1),var(--card-2));border:1px solid var(--line);
      box-shadow:0 30px 70px -30px rgba(0,0,0,.7),inset 0 1px 0 rgba(255,255,255,.18);overflow:hidden}
    .carousel::before{content:'';position:absolute;inset:-40% -20% auto auto;width:260px;height:260px;border-radius:50%;background:radial-gradient(circle,var(--glow-1),transparent 70%);pointer-events:none}
    .slides{display:grid;list-style:none;position:relative}
    .slide{grid-area:1/1;display:flex;flex-direction:column;align-items:center;gap:12px;opacity:0;visibility:hidden;transform:translateY(10px);
      transition:opacity 1.1s cubic-bezier(.4,0,.2,1),transform 1.2s cubic-bezier(.22,1,.36,1),visibility 0s 1.2s}
    .slide.is-active{opacity:1;visibility:visible;transform:none;transition-delay:.25s}
    .slide.is-leaving{opacity:0;visibility:visible;transform:translateY(-10px)}
    .quote{font-family:'Playfair Display',Georgia,serif;font-size:60px;line-height:1;height:30px;color:var(--accent-2);opacity:.85}
    .wish{font-family:'Playfair Display',Georgia,serif;font-style:italic;font-weight:500;font-size:clamp(18px,2.8vw,23px);line-height:1.55;min-height:4.65em;display:flex;align-items:center;text-wrap:balance}
    .count{font-size:12px;font-weight:600;letter-spacing:.2em;color:rgba(255,255,255,.55)}
    .progress{position:relative;height:3px;margin-top:18px;border-radius:3px;background:rgba(255,255,255,.14);overflow:hidden}
    .progress span{position:absolute;inset:0;transform-origin:0 50%;transform:scaleX(0);background:linear-gradient(90deg,var(--accent),var(--accent-2))}
    .progress span.is-running{animation:oc-progress var(--slide-ms,6000ms) linear forwards}
    .carousel.is-paused .progress span{animation-play-state:paused}
    .controls{display:flex;align-items:center;justify-content:center;gap:10px;margin-top:16px}
    .icon-btn{width:38px;height:38px;display:grid;place-items:center;border-radius:50%;border:1px solid var(--line);background:rgba(255,255,255,.08);color:var(--text);font-size:15px;cursor:pointer;transition:background .2s,transform .2s}
    .icon-btn:hover{background:rgba(255,255,255,.18);transform:scale(1.06)}
    .icon-btn:focus-visible,.dot:focus-visible,.btn:focus-visible{outline:2px solid var(--accent-2);outline-offset:3px}
    .dots{display:flex;gap:7px;padding:0 6px}
    .dot{width:9px;height:9px;padding:0;border:0;border-radius:999px;background:rgba(255,255,255,.3);cursor:pointer;transition:width .35s,background .35s}
    .dot[aria-current="true"]{width:26px;background:linear-gradient(90deg,var(--accent),var(--accent-2))}
    .footer{display:flex;flex-direction:column;align-items:center;gap:12px;margin-top:22px}
    .signature{font-family:'Dancing Script',cursive;font-size:26px;color:rgba(255,255,255,.92)}
    .btn{display:inline-flex;align-items:center;gap:8px;padding:10px 20px;border-radius:999px;border:1px solid var(--line);background:rgba(255,255,255,.08);color:var(--text);font:600 14px 'Be Vietnam Pro',sans-serif;cursor:pointer;transition:background .2s,transform .2s}
    .btn:hover{background:rgba(255,255,255,.16);transform:translateY(-1px)}

    @keyframes oc-hint{50%{transform:translateY(-3px);box-shadow:0 0 0 10px rgba(255,255,255,0)}}
    @keyframes oc-tap{50%{transform:translateY(-4px) scale(1.1)}}
    @keyframes oc-float{from{transform:translateY(0)}to{transform:translateY(-120vh)}}
    @keyframes oc-rise{from{opacity:0;transform:translateY(28px)}to{opacity:1;transform:none}}
    @keyframes oc-shimmer{to{background-position:300% 0}}
    @keyframes oc-progress{to{transform:scaleX(1)}}
    @keyframes oc-twinkle{0%,100%{transform:scale(.2);opacity:0}50%{transform:scale(1) rotate(45deg);opacity:1}}
    .spark{transform-box:view-box;transform-origin:0 0;fill:#ffe9b0;animation:oc-twinkle 2.4s ease-in-out infinite;animation-delay:var(--d)}

    /* Desktop: the revealed page fits one screen (sizes follow the window height); longer content just scrolls. */
    @media (min-width:901px){
      main{padding:56px 20px 24px}
      h1{margin-top:10px;font-size:clamp(44px,min(8vw,9vh),84px)}
      .carousel{margin-top:clamp(12px,2.5vh,26px);padding:clamp(18px,3vh,30px) 28px clamp(14px,2vh,22px)}
      .wish{font-size:clamp(17px,min(2.8vw,2.7vh),23px)}
      .controls{margin-top:clamp(10px,1.6vh,16px)}
      .footer{flex-direction:row;flex-wrap:wrap;justify-content:center;gap:10px;margin-top:clamp(12px,2.2vh,22px)}
      .footer .signature{flex-basis:100%;text-align:center}
      .is-open .stage-btn svg{height:min(56vh,480px)}
    }
    @media (min-width:901px) and (max-height:760px){
      .eyebrow{padding:4px 12px}
      .sub{margin-top:2px}
      .quote{font-size:44px;height:22px}
      .wish{min-height:3.2em}
      .progress{margin-top:12px}
    }
    @media (max-width:900px){
      main{flex-direction:column;justify-content:center;padding-top:76px}
      main.is-revealed{justify-content:flex-start}
      .is-open .stage-btn svg{height:min(34vh,300px)}
      .carousel{padding:24px 18px 18px;margin-top:20px}
    }
    @media (prefers-reduced-motion:reduce){
      *,*::before,*::after{animation-duration:.01ms!important;animation-iteration-count:1!important;animation-delay:0s!important;transition-duration:.01ms!important}
      .bokeh{display:none}
    }
  </style>
  {{ $styles }}
</head>
<body>
  <div class="bg" aria-hidden="true"></div>
  <div class="bokeh" aria-hidden="true" data-bokeh></div>
  {{ $extra }}
  <canvas id="oc-particles" aria-hidden="true"></canvas>

  <a class="back" href="{{ $backUrl ?? route('user.dashboard', $room) }}">← {{ $preview ? __('occasions.ui.back_to_admin') : __('occasions.ui.back_to_room') }}</a>
  @if ($preview)
    <span class="preview-badge" role="status" style="position:fixed;left:50%;bottom:16px;transform:translateX(-50%);z-index:30;padding:6px 14px;border-radius:999px;background:rgba(0,0,0,.6);border:1px solid rgba(255,255,255,.35);color:#fff;font:600 12px 'Be Vietnam Pro',sans-serif;pointer-events:none">👁 {{ __('occasions.ui.preview_badge') }}</span>
  @endif
  <x-occasion.audio :audio="$audio" :melody="$occasion->value" />

  <main data-occasion-page="{{ $occasion->value }}"
    data-effects="{{ json_encode($effects, JSON_UNESCAPED_UNICODE) }}"
    data-i18n="{{ json_encode(['pause' => __('occasions.ui.pause'), 'play' => __('occasions.ui.play')], JSON_UNESCAPED_UNICODE) }}">
    <div class="stage" data-stage>
      <button type="button" class="stage-btn" data-stage-button aria-label="{{ __($key.'.reveal_label') }}" aria-expanded="false" aria-controls="oc-message">
        {{ $slot }}
      </button>
      <p class="tap-hint" data-hint><span class="hand" aria-hidden="true">👆</span> {{ __($key.'.tap_hint') }}</p>
    </div>

    <section class="message" id="oc-message" data-message aria-hidden="true">
      <span class="eyebrow">{{ $occasion->emoji() }} {{ __($key.'.eyebrow') }}</span>
      <h1>{{ __($key.'.heading', $params) }}</h1>
      <p class="sub">{{ __($key.'.subheading', $params) }}</p>

      <div class="carousel" role="region" aria-roledescription="carousel" aria-label="{{ __('occasions.ui.slides_label') }}" data-carousel>
        <ul class="slides" aria-live="off" data-slides>
          @foreach ($wishes as $wish)
            <li class="slide {{ $loop->first ? 'is-active' : '' }}" role="group" aria-roledescription="slide"
              aria-label="{{ __('occasions.ui.slide_label', ['current' => $loop->iteration, 'total' => $total]) }}" data-slide>
              <span class="quote" aria-hidden="true">“</span>
              <p class="wish">{{ $wish }}</p>
              <span class="count" aria-hidden="true">{{ sprintf('%02d / %02d', $loop->iteration, $total) }}</span>
            </li>
          @endforeach
        </ul>
        <div class="progress" aria-hidden="true"><span data-progress></span></div>
        <div class="controls">
          <button type="button" class="icon-btn" data-prev aria-label="{{ __('occasions.ui.prev') }}">‹</button>
          <div class="dots">
            @foreach ($wishes as $wish)
              <button type="button" class="dot" data-dot="{{ $loop->index }}" aria-label="{{ __('occasions.ui.go_to', ['number' => $loop->iteration]) }}" aria-current="{{ $loop->first ? 'true' : 'false' }}"></button>
            @endforeach
          </div>
          <button type="button" class="icon-btn" data-next aria-label="{{ __('occasions.ui.next') }}">›</button>
          <button type="button" class="icon-btn" data-toggle aria-label="{{ __('occasions.ui.pause') }}" aria-pressed="false">❚❚</button>
        </div>
      </div>

      <div class="footer">
        <p class="signature">{{ __($key.'.signature', $params) }}</p>
        <button type="button" class="btn" data-replay>{{ $occasion->emoji() }} {{ __($key.'.replay') }}</button>
        @if ($wall !== null)
          <a class="btn" href="#event-wish-wall" data-wall-link>💬 {{ __('occasions.wall.open') }}</a>
        @endif
      </div>
    </section>
  </main>
  @if ($wall !== null)
    <x-occasion.wish-wall :room="$room" :type="$occasion->value" :wall="$wall" />
  @endif

  <script>
    (() => {
      // Time on each wish: long enough to read it (about 55 ms per character), between 5 and 10 seconds.
      const slideMs = (slide) => Math.min(10000, Math.max(5000, (slide?.textContent || '').trim().length * 55));
      const page = document.querySelector('[data-occasion-page]');
      const parse = (value) => { try { return JSON.parse(value || '{}'); } catch { return {}; } };
      const i18n = parse(page.dataset.i18n);
      const fx = Object.assign({ rain: ['petal'], rainColors: ['#ff4f7b', '#ffb3c6'], rainEvery: 380, rainBeforeOpen: false, burst: ['petal'], burstColors: ['#ff4f7b', '#f7c873', '#ffffff'], fireworks: false, sounds: [] }, parse(page.dataset.effects));
      const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      const random = (min, max) => Math.random() * (max - min) + min;
      const pick = (list) => list[Math.floor(Math.random() * list.length)];
      const stageButton = page.querySelector('[data-stage-button]');
      const stage = page.querySelector('[data-stage]');
      const message = page.querySelector('[data-message]');

      // Soft bokeh lights drifting up (decoration only).
      const bokeh = document.querySelector('[data-bokeh]');
      for (let i = 0; i < 18; i++) {
        const light = document.createElement('i');
        const size = random(30, 120);
        Object.assign(light.style, { width: `${size}px`, height: `${size}px`, left: `${random(0, 100)}%`, top: `${random(10, 110)}%`, animationDuration: `${random(18, 34)}s`, animationDelay: `${random(-30, 0)}s` });
        bokeh.append(light);
      }

      // Canvas particles: a gentle rain (petals, snow, blossoms, confetti...), bursts on open and optional fireworks.
      const canvas = document.getElementById('oc-particles');
      const ctx = canvas.getContext('2d');
      let particles = [];
      let raining = fx.rainBeforeOpen;
      let fireworks = false;
      const resize = () => { canvas.width = innerWidth * devicePixelRatio; canvas.height = innerHeight * devicePixelRatio; ctx.setTransform(devicePixelRatio, 0, 0, devicePixelRatio, 0, 0); };
      resize();
      addEventListener('resize', resize);
      const shapes = {
        petal: (s) => { ctx.beginPath(); ctx.moveTo(0, -s / 2); ctx.bezierCurveTo(s / 2, -s / 2, s / 2, s / 3, 0, s / 2); ctx.bezierCurveTo(-s / 2, s / 3, -s / 2, -s / 2, 0, -s / 2); ctx.fill(); },
        heart: (size) => { const s = size / 2; ctx.beginPath(); ctx.moveTo(0, s * 0.6); ctx.bezierCurveTo(-s * 1.4, -s * 0.3, -s * 0.5, -s * 1.3, 0, -s * 0.45); ctx.bezierCurveTo(s * 0.5, -s * 1.3, s * 1.4, -s * 0.3, 0, s * 0.6); ctx.fill(); },
        snow: (s) => { ctx.beginPath(); ctx.arc(0, 0, s / 4, 0, Math.PI * 2); ctx.fill(); },
        confetti: (s) => { ctx.fillRect(-s / 2, -s / 5, s, s / 2.5); },
        coin: (s) => { ctx.beginPath(); ctx.ellipse(0, 0, s / 2, s / 2.6, 0, 0, Math.PI * 2); ctx.fill(); ctx.fillStyle = 'rgba(120,60,0,.35)'; ctx.fillRect(-s / 8, -s / 8, s / 4, s / 4); },
        star: (s) => { ctx.beginPath(); for (let i = 0; i < 10; i++) { const r = i % 2 ? s / 5 : s / 2; const a = (i * Math.PI) / 5 - Math.PI / 2; ctx.lineTo(Math.cos(a) * r, Math.sin(a) * r); } ctx.closePath(); ctx.fill(); },
        blossom: (s) => { for (let i = 0; i < 5; i++) { ctx.save(); ctx.rotate((i * Math.PI * 2) / 5); ctx.beginPath(); ctx.ellipse(0, -s / 3.2, s / 4.6, s / 3.2, 0, 0, Math.PI * 2); ctx.fill(); ctx.restore(); } ctx.fillStyle = '#c2410c'; ctx.beginPath(); ctx.arc(0, 0, s / 9, 0, Math.PI * 2); ctx.fill(); },
        spark: (s) => { ctx.beginPath(); ctx.arc(0, 0, s / 5, 0, Math.PI * 2); ctx.fill(); },
      };
      const rainDrop = () => {
        const kind = pick(fx.rain);
        const snow = kind === 'snow';
        return { kind, x: random(0, innerWidth), y: -20, vx: random(-0.4, 0.4), vy: snow ? random(0.5, 1.3) : random(0.7, 1.6), size: snow ? random(6, 13) : random(8, 15), color: pick(fx.rainColors), rotation: random(0, 360), spin: snow ? 0 : random(-2, 2), sway: random(0, Math.PI * 2), life: Infinity, gravity: 0, alpha: snow ? random(0.5, 0.95) : 0.85 };
      };
      const burst = (x, y, count = 90, kinds = fx.burst, colors = fx.burstColors, speedMax = 9) => {
        for (let i = 0; i < count; i++) {
          const angle = random(0, Math.PI * 2);
          const speed = random(2.5, speedMax);
          particles.push({ kind: pick(kinds), x, y, vx: Math.cos(angle) * speed, vy: Math.sin(angle) * speed - 2.5, size: random(8, 16), color: pick(colors), rotation: random(0, 360), spin: random(-8, 8), sway: 0, life: random(90, 160), gravity: 0.12, alpha: 1 });
        }
      };
      let fireworkSounds = 0;
      const firework = () => {
        // Only the first bangs are heard, so the page does not stay noisy.
        if (fireworkSounds < 6) { fireworkSounds++; window.EventAudio?.play('boom'); }
        const x = random(innerWidth * 0.12, innerWidth * 0.88);
        const y = random(innerHeight * 0.1, innerHeight * 0.45);
        const color = pick(fx.burstColors);
        for (let i = 0; i < 70; i++) {
          const angle = (i / 70) * Math.PI * 2;
          const speed = random(2.2, 4.6);
          particles.push({ kind: 'spark', x, y, vx: Math.cos(angle) * speed, vy: Math.sin(angle) * speed, size: random(9, 14), color, rotation: 0, spin: 0, sway: 0, life: random(60, 90), gravity: 0.05, alpha: 1, glow: true });
        }
      };
      let lastSpawn = 0;
      let lastFirework = 0;
      const tick = (time) => {
        ctx.clearRect(0, 0, innerWidth, innerHeight);
        if (!document.hidden) {
          if (raining && time - lastSpawn > fx.rainEvery && particles.length < 160) { particles.push(rainDrop()); lastSpawn = time; }
          if (fireworks && time - lastFirework > 900) { firework(); lastFirework = time; }
        }
        particles = particles.filter((p) => p.life > 0 && p.y < innerHeight + 30);
        for (const p of particles) {
          p.vy += p.gravity; p.vx *= p.gravity ? 0.985 : 1; p.sway += 0.03;
          p.x += p.vx + (p.gravity ? 0 : Math.sin(p.sway) * 0.8); p.y += p.vy; p.rotation += p.spin; p.life--;
          ctx.save(); ctx.translate(p.x, p.y); ctx.rotate(p.rotation * Math.PI / 180);
          ctx.globalAlpha = (Number.isFinite(p.life) ? Math.min(1, p.life / 40) : 1) * p.alpha;
          ctx.fillStyle = p.color;
          if (p.glow) { ctx.shadowColor = p.color; ctx.shadowBlur = 8; }
          (shapes[p.kind] || shapes.petal)(p.size);
          ctx.restore();
        }
        requestAnimationFrame(tick);
      };
      if (!reduceMotion) requestAnimationFrame(tick);

      // Slideshow of wishes: next slide once the current one had time to be read, paused on mouse hover, with prev / next / dots / pause.
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
          setTimeout(() => leaving.classList.remove('is-leaving'), 1200);
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
        const duration = slideMs(slides[current]);
        carousel.style.setProperty('--slide-ms', `${duration}ms`);
        void progress.offsetWidth;
        progress.classList.add('is-running');
        timer = setTimeout(() => show(document.hidden ? current : current + 1), duration);
      };
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

      // Smoothly move the centerpiece to its new place / size when the layout changes (FLIP).
      const withFlip = (change) => {
        const before = stage.getBoundingClientRect();
        change();
        if (reduceMotion) return;
        const after = stage.getBoundingClientRect();
        const dx = before.left + before.width / 2 - (after.left + after.width / 2);
        const dy = before.top + before.height / 2 - (after.top + after.height / 2);
        const scale = before.height / after.height;
        stage.animate([{ transform: `translate(${dx}px, ${dy}px) scale(${scale})` }, { transform: 'none' }], { duration: 900, easing: 'cubic-bezier(.2,.8,.2,1)' });
      };

      // Open: the page's reveal animation plays, particles burst out, then the wishes appear and start sliding.
      const timeouts = [];
      const open = () => {
        if (page.classList.contains('is-open')) return;
        page.classList.add('is-open');
        window.EventAudio?.start();
        fx.sounds.forEach(([name, delay]) => window.EventAudio?.play(name, delay));
        fireworkSounds = 0;
        stageButton.setAttribute('aria-expanded', 'true');
        stageButton.disabled = true;
        const box = stageButton.querySelector('svg').getBoundingClientRect();
        timeouts.push(setTimeout(() => {
          if (!reduceMotion) burst(box.left + box.width / 2, box.top + box.height * 0.3);
          raining = true;
          fireworks = fx.fireworks && !reduceMotion;
        }, reduceMotion ? 0 : 500));
        timeouts.push(setTimeout(() => {
          withFlip(() => page.classList.add('is-revealed'));
          message.setAttribute('aria-hidden', 'false');
        }, reduceMotion ? 0 : 1300));
        timeouts.push(setTimeout(() => { started = true; show(0); }, reduceMotion ? 0 : 2200));
      };
      const replay = () => {
        timeouts.splice(0).forEach(clearTimeout);
        started = false;
        raining = fx.rainBeforeOpen;
        fireworks = false;
        withFlip(() => page.classList.remove('is-revealed', 'is-open'));
        message.setAttribute('aria-hidden', 'true');
        stageButton.setAttribute('aria-expanded', 'false');
        stageButton.disabled = false;
        show(0);
        stageButton.focus();
        scrollTo({ top: 0, behavior: reduceMotion ? 'auto' : 'smooth' });
      };
      stageButton.addEventListener('click', open);
      page.querySelector('[data-hint]').addEventListener('click', open);
      page.querySelector('[data-replay]').addEventListener('click', replay);
    })();
  </script>
</body>
</html>
