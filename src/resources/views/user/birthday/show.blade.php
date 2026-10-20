{{-- Stand-alone birthday celebration page of a room (no site header / footer), opened from the birthday banner or
     the morning notification. It celebrates every member whose birthday is within 3 days; only names, avatars, day
     and month are shown, never the birth year. --}}
@php
    $names = array_column($celebrants, 'name');
    $lastName = array_pop($names);
    $name = $names === [] ? $lastName : implode(', ', $names).' & '.$lastName;
    $dateLine = static function (array $celebrant): string {
        $offset = $celebrant['offset'];
        $date = sprintf('%02d/%02d', $celebrant['day'], $celebrant['month']);

        return match (true) {
            $offset === 0 => __('birthday.date_today', ['date' => $date]),
            $offset > 0 => trans_choice('birthday.date_upcoming', $offset, ['days' => $offset, 'date' => $date]),
            default => trans_choice('birthday.date_past', abs($offset), ['days' => abs($offset), 'date' => $date]),
        };
    };
    $isSelf = $viewerCode !== '' && in_array($viewerCode, array_column($celebrants, 'user_code'), true);
    // The room's own wishes (admin events page) or the default ones; `:name` becomes the celebrants' names.
    $wishes = array_map(static fn (string $wish): string => str_replace(':name', $name, $wish), $wishes);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
  <title>{{ __('birthday.page_title', ['name' => $name]) }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Dancing+Script:wght@600;700&family=Be+Vietnam+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    :root{--pink:#ff5fa2;--rose:#ff3d6e;--gold:#ffc94d;--violet:#8b5cf6;--sky:#38bdf8;--ink:#2a1238}
    html,body{min-height:100%}
    body{font-family:'Be Vietnam Pro',system-ui,sans-serif;color:#fff;overflow-x:hidden;
      background:radial-gradient(1200px 700px at 15% 10%,#ff7eb3 0%,transparent 60%),radial-gradient(900px 600px at 90% 20%,#a78bfa 0%,transparent 55%),radial-gradient(900px 700px at 50% 110%,#fbbf24 0%,transparent 60%),linear-gradient(160deg,#3b0f4f,#1e1b4b 55%,#0f172a);
      background-attachment:fixed}
    #confetti{position:fixed;inset:0;width:100%;height:100%;pointer-events:none;z-index:5}
    .stars{position:fixed;inset:0;pointer-events:none;z-index:0}
    .stars i{position:absolute;width:3px;height:3px;border-radius:50%;background:#fff;opacity:.7;animation:twinkle 3s ease-in-out infinite}
    .balloons{position:fixed;inset:0;pointer-events:none;overflow:hidden;z-index:1}
    .balloon{position:absolute;bottom:-160px;width:58px;height:72px;border-radius:50% 50% 48% 48%;opacity:.9;animation:rise linear infinite;box-shadow:inset -8px -10px 0 rgba(0,0,0,.12)}
    .balloon::after{content:'';position:absolute;left:50%;top:100%;width:1px;height:90px;background:rgba(255,255,255,.55)}
    .balloon::before{content:'';position:absolute;left:50%;top:calc(100% - 4px);width:10px;height:8px;transform:translateX(-50%);background:inherit;clip-path:polygon(50% 0,100% 100%,0 100%)}
    main{position:relative;z-index:10;min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:26px;padding:48px 20px 64px;text-align:center}
    .eyebrow{display:inline-flex;align-items:center;gap:8px;padding:6px 14px;border-radius:999px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25);font-size:13px;font-weight:600;letter-spacing:.04em;backdrop-filter:blur(6px)}
    .people{display:flex;flex-wrap:wrap;justify-content:center;gap:28px 34px;max-width:900px}
    .person{position:relative;display:flex;flex-direction:column;align-items:center;width:150px;border-radius:50%;outline:none;cursor:pointer;-webkit-tap-highlight-color:transparent}
    .person:hover,.person:focus-within{z-index:15}
    .person .avatar{transition:transform .35s cubic-bezier(.34,1.56,.64,1),box-shadow .35s}
    .person:hover .avatar,.person:focus .avatar{transform:scale(1.12);box-shadow:0 0 60px rgba(255,201,77,.75)}
    .person:focus-visible .avatar{outline:3px solid var(--gold);outline-offset:6px}
    .person-card{position:absolute;top:50%;left:calc(100% + 18px);width:min(280px,70vw);max-height:min(360px,70vh);overflow-y:auto;text-align:left;padding:14px 16px;border-radius:18px;text-align:center;
      background:linear-gradient(135deg,rgba(255,95,162,.95),rgba(139,92,246,.95));box-shadow:0 18px 40px -12px rgba(0,0,0,.6),0 0 0 1px rgba(255,255,255,.35) inset;
      opacity:0;visibility:hidden;transform:translate(-14px,-50%) scale(.85);transform-origin:0 50%;transition:opacity .25s,transform .35s cubic-bezier(.34,1.56,.64,1),visibility .25s;pointer-events:none}
    .person:hover .person-card,.person:focus .person-card,.person:focus-within .person-card{opacity:1;visibility:visible;transform:translate(0,-50%) scale(1)}
    .person.is-flipped .person-card{left:auto;right:calc(100% + 18px);transform:translate(14px,-50%) scale(.85);transform-origin:100% 50%}
    .person.is-flipped:hover .person-card,.person.is-flipped:focus .person-card,.person.is-flipped:focus-within .person-card{transform:translate(0,-50%) scale(1)}
    .person-card .meta{display:flex;align-items:center;gap:6px;margin-top:8px;font-size:12px;color:rgba(255,255,255,.9)}
    .person-card .bio{margin-top:10px;padding-top:10px;border-top:1px solid rgba(255,255,255,.3);font-size:13px;line-height:1.55;overflow-wrap:anywhere}
    .person-card .bio p{margin-bottom:6px}.person-card .bio p:last-child{margin-bottom:0}
    .person-card .bio ul,.person-card .bio ol{padding-left:18px;margin-bottom:6px}.person-card .bio a{color:#fff;text-decoration:underline}
    .person-card .sparkle{display:block;font-size:22px;margin-bottom:4px;animation:bob 1.8s ease-in-out infinite}
    .person-card .badge{display:inline-block;margin-top:8px;padding:3px 10px;border-radius:999px;background:var(--gold);color:var(--ink);font-size:11px;font-weight:800;letter-spacing:.04em;text-transform:uppercase}
    .people.is-many .avatar{width:104px;height:104px}.people.is-many .avatar-inner{font-size:40px}
    .avatar{position:relative;width:132px;height:132px;border-radius:50%;padding:5px;background:conic-gradient(var(--gold),var(--pink),var(--violet),var(--sky),var(--gold));animation:spin 8s linear infinite;box-shadow:0 0 40px rgba(255,95,162,.55)}
    .avatar-inner{position:relative;width:100%;height:100%;border-radius:50%;overflow:hidden;background:#2a1238;display:grid;place-items:center;font-size:52px;font-weight:700;animation:spin 8s linear infinite reverse}
    .avatar img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:1}
    .crown{position:absolute;top:-30px;left:50%;transform:translateX(-50%) rotate(-8deg);font-size:40px;animation:bob 2.4s ease-in-out infinite}
    h1{font-family:'Dancing Script',cursive;font-size:clamp(46px,9vw,92px);line-height:1;background:linear-gradient(90deg,#fff,var(--gold),#fff,var(--pink),#fff);background-size:300% 100%;-webkit-background-clip:text;background-clip:text;color:transparent;animation:shimmer 6s linear infinite;text-shadow:0 0 30px rgba(255,201,77,.25)}
    .name{font-size:clamp(26px,5vw,46px);font-weight:700;letter-spacing:-.01em;text-shadow:0 4px 24px rgba(0,0,0,.35)}
    .date{font-size:15px;font-weight:500;color:rgba(255,255,255,.85)}
    .person-name{display:block;font-size:17px;font-weight:700;line-height:1.3;overflow-wrap:anywhere}
    .person-card .date{display:block;margin-top:4px;font-size:13px;color:#fff}
    .self-note{font-size:14px;font-weight:600;color:var(--gold)}
    .cake-wrap{display:flex;flex-direction:column;align-items:center;gap:10px}
    .cake{position:relative;width:200px;height:170px;border:0;background:none;cursor:pointer;-webkit-tap-highlight-color:transparent}
    .cake:focus-visible{outline:3px solid var(--gold);outline-offset:8px;border-radius:16px}
    .tier{position:absolute;left:50%;transform:translateX(-50%);border-radius:14px 14px 10px 10px}
    .tier.t1{bottom:14px;width:190px;height:66px;background:linear-gradient(#ff8fbf,#ff5fa2)}
    .tier.t2{bottom:78px;width:140px;height:50px;background:linear-gradient(#ffe2a8,#ffc94d)}
    .tier::before{content:'';position:absolute;top:-6px;left:0;right:0;height:18px;border-radius:12px;background:#fff;
      -webkit-mask:radial-gradient(12px 10px at 12px 100%,transparent 98%,#000) 0 0/24px 100%;mask:radial-gradient(12px 10px at 12px 100%,transparent 98%,#000) 0 0/24px 100%}
    .plate{position:absolute;bottom:4px;left:50%;transform:translateX(-50%);width:214px;height:14px;border-radius:50%;background:rgba(255,255,255,.85)}
    .candles{position:absolute;bottom:126px;left:50%;transform:translateX(-50%);display:flex;gap:22px}
    .candle{position:relative;width:10px;height:34px;border-radius:3px;background:repeating-linear-gradient(45deg,#fff 0 6px,var(--sky) 6px 12px)}
    .flame{position:absolute;bottom:100%;left:50%;width:12px;height:20px;margin-left:-6px;border-radius:50% 50% 50% 50%/60% 60% 40% 40%;background:radial-gradient(circle at 50% 70%,#fff 0 20%,#ffe066 35%,#ff9f1c 70%,transparent 72%);animation:flicker .15s ease-in-out infinite alternate;transform-origin:50% 100%}
    .cake.is-blown .flame{animation:out .5s forwards}
    .smoke{position:absolute;bottom:100%;left:50%;width:6px;height:6px;margin-left:-3px;border-radius:50%;background:rgba(255,255,255,.6);opacity:0}
    .cake.is-blown .smoke{animation:smoke 1.6s ease-out forwards}
    .hint{font-size:13px;color:rgba(255,255,255,.75)}
    .card{width:min(100%,560px);padding:24px 26px;border-radius:22px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25);backdrop-filter:blur(10px);box-shadow:0 20px 60px -20px rgba(0,0,0,.5)}
    .card h2{font-size:12px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:var(--gold);margin-bottom:10px}
    .wish{font-size:clamp(16px,2.6vw,19px);line-height:1.6;font-weight:500;min-height:3.2em;transition:opacity .35s}
    .wish.is-fading{opacity:0}
    .actions{display:flex;flex-wrap:wrap;justify-content:center;gap:10px;margin-top:16px}
    .btn{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:999px;border:0;font:600 14px 'Be Vietnam Pro',sans-serif;cursor:pointer;text-decoration:none;transition:transform .15s,filter .15s}
    .btn:hover{transform:translateY(-1px);filter:brightness(1.08)}
    .btn-primary{background:linear-gradient(90deg,var(--rose),var(--pink));color:#fff;box-shadow:0 10px 24px -10px rgba(255,61,110,.8)}
    .btn-ghost{background:rgba(255,255,255,.14);color:#fff;border:1px solid rgba(255,255,255,.3)}
    .signature{font-family:'Dancing Script',cursive;font-size:24px;color:rgba(255,255,255,.9)}
    .back{position:fixed;top:16px;left:16px;z-index:20;font-size:13px}
    .toast{position:fixed;left:50%;bottom:28px;transform:translate(-50%,40px);opacity:0;z-index:30;padding:10px 18px;border-radius:999px;background:#fff;color:var(--ink);font-weight:700;font-size:14px;box-shadow:0 12px 30px rgba(0,0,0,.3);transition:all .35s}
    .toast.is-visible{opacity:1;transform:translate(-50%,0)}
    @keyframes rise{to{transform:translateY(-130vh) translateX(var(--drift,0))}}
    @keyframes twinkle{50%{opacity:.15;transform:scale(.6)}}
    @keyframes spin{to{transform:rotate(360deg)}}
    @keyframes bob{50%{transform:translateX(-50%) translateY(-6px) rotate(6deg)}}
    @keyframes shimmer{to{background-position:300% 0}}
    @keyframes flicker{from{transform:scaleY(1) rotate(-2deg)}to{transform:scaleY(1.12) rotate(2deg)}}
    @keyframes out{to{transform:scale(0);opacity:0}}
    @keyframes smoke{0%{opacity:.8;transform:translateY(0) scale(1)}100%{opacity:0;transform:translateY(-50px) scale(3)}}
    .card-close{display:none}
    /* Mobile: the back button is centred above the content, and a tapped avatar opens its card full screen. */
    @media (max-width:640px){
      .back{position:absolute;top:18px;left:50%;transform:translateX(-50%);z-index:9;white-space:nowrap}
      .back:hover{transform:translateX(-50%) translateY(-1px)}
      main{padding-top:84px}
      .person:hover .avatar{transform:none;box-shadow:0 0 40px rgba(255,95,162,.55)}
      .person .person-card,.person.is-flipped .person-card{position:fixed;inset:0;left:0;right:0;top:0;width:auto;max-height:none;border-radius:0;
        display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:72px 28px 40px;overflow-y:auto;
        transform:scale(.96);transform-origin:50% 50%;z-index:40;pointer-events:none}
      .person:hover .person-card{opacity:0;visibility:hidden}
      .person:focus .person-card,.person:focus-within .person-card,.person.is-flipped:focus .person-card,.person.is-flipped:focus-within .person-card{opacity:1;visibility:visible;transform:none;pointer-events:auto}
      .person-card .sparkle{font-size:56px;margin-bottom:12px}
      .person-card .person-name{font-size:28px}
      .person-card .date{font-size:16px}
      .person-card .meta{justify-content:center;font-size:14px}
      .person-card .bio{max-width:520px;font-size:15px;text-align:left}
      .card-close{display:flex;position:absolute;top:16px;right:16px;width:40px;height:40px;align-items:center;justify-content:center;border:0;border-radius:50%;
        background:rgba(255,255,255,.2);color:#fff;font-size:22px;cursor:pointer}
    }
    @media (prefers-reduced-motion: reduce){*{animation:none!important;transition:none!important}.balloons{display:none}}
  </style>
</head>
<body>
  <canvas id="confetti" aria-hidden="true"></canvas>
  <div class="stars" aria-hidden="true" data-stars></div>
  <div class="balloons" aria-hidden="true" data-balloons></div>

  <a class="btn btn-ghost back" href="{{ route('user.dashboard', $room) }}">← {{ __('birthday.back_to_room') }}</a>
  <x-occasion.audio :audio="$audio" melody="birthday" />

  <main data-birthday-page data-i18n="{{ json_encode(['wishes' => array_values($wishes), 'blown' => __('birthday.blown')], JSON_UNESCAPED_UNICODE) }}">
    <span class="eyebrow">🎉 {{ __('birthday.eyebrow', ['room' => $room->name]) }}</span>

    <div>
      <h1>{{ __('birthday.heading') }}</h1>
      <p class="name">{{ $name }}</p>
      @if ($isSelf)
        <p class="self-note">{{ __('birthday.self_note') }}</p>
      @endif
    </div>

    <ul class="people {{ count($celebrants) > 2 ? 'is-many' : '' }}" style="list-style:none" data-birthday-people>
      @foreach ($celebrants as $celebrant)
        {{-- Hover / focus (tap on touch screens) reveals the member's card; it hides again on mouse leave / blur. --}}
        <li class="person {{ $celebrant['offset'] === 0 ? 'is-today' : '' }}" data-birthday-person="{{ $celebrant['user_code'] }}"
          tabindex="0" aria-describedby="birthday-card-{{ $loop->index }}">
          <div class="avatar">
            @if ($celebrant['offset'] === 0)
              <span class="crown" aria-hidden="true">👑</span>
            @endif
            <div class="avatar-inner">
              @if ($celebrant['avatar_url'] !== '')
                <img src="{{ $celebrant['avatar_url'] }}" alt="{{ __('birthday.avatar_alt', ['name' => $celebrant['name']]) }}" loading="lazy" referrerpolicy="no-referrer"
                  onerror="this.remove()">
              @endif
              <span aria-hidden="true">{{ mb_strtoupper(mb_substr($celebrant['name'], 0, 1)) }}</span>
            </div>
          </div>
          <div class="person-card" role="tooltip" id="birthday-card-{{ $loop->index }}">
            <button type="button" class="card-close" data-card-close aria-label="{{ __('birthday.close_card') }}">✕</button>
            <span class="sparkle" aria-hidden="true">{{ $celebrant['offset'] === 0 ? '🎂' : '🎈' }}</span>
            <span class="person-name">{{ $celebrant['name'] }}</span>
            <span class="date">{{ $dateLine($celebrant) }}</span>
            @if ($celebrant['offset'] === 0)
              <span class="badge">{{ __('birthday.heading') }}</span>
            @endif
            @if ($celebrant['joined_on'] ?? null)
              @php($joined = \Illuminate\Support\Carbon::parse($celebrant['joined_on']))
              <span class="meta">🤝 {{ __('birthday.joined_since', ['date' => $joined->format('d/m/Y'), 'duration' => $joined->diffForHumans(['parts' => 2, 'syntax' => \Carbon\CarbonInterface::DIFF_ABSOLUTE])]) }}</span>
            @endif
            @if (($celebrant['bio_html'] ?? '') !== '')
              <div class="bio">{!! $celebrant['bio_html'] !!}</div>
            @endif
          </div>
        </li>
      @endforeach
    </ul>

    <div class="cake-wrap">
      <button type="button" class="cake" data-cake aria-label="{{ __('birthday.candles_label') }}">
        <span class="candles">
          @foreach (range(1, 3) as $candle)
            <span class="candle"><span class="flame"></span><span class="smoke"></span></span>
          @endforeach
        </span>
        <span class="tier t2"></span>
        <span class="tier t1"></span>
        <span class="plate"></span>
      </button>
      <p class="hint">{{ __('birthday.blow_hint') }}</p>
    </div>

    <section class="card" aria-live="polite">
      <h2>{{ __('birthday.wish_title') }}</h2>
      <p class="wish" data-wish>{{ $wishes[0] ?? '' }}</p>
      <div class="actions">
        <button type="button" class="btn btn-primary" data-next-wish>✨ {{ __('birthday.next_wish') }}</button>
      </div>
    </section>

    <p class="signature">{{ __('birthday.signature', ['room' => $room->name]) }}</p>
  </main>

  <div class="toast" role="status" data-toast></div>

  <script>
    (() => {
      const page = document.querySelector('[data-birthday-page]');
      let i18n = {};
      try { i18n = JSON.parse(page.dataset.i18n || '{}'); } catch { i18n = {}; }
      const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
      const colors = ['#ff5fa2', '#ffc94d', '#8b5cf6', '#38bdf8', '#34d399', '#ff3d6e', '#ffffff'];
      const random = (min, max) => Math.random() * (max - min) + min;

      // Twinkling stars and rising balloons (decoration only).
      const stars = document.querySelector('[data-stars]');
      for (let i = 0; i < 60; i++) {
        const star = document.createElement('i');
        Object.assign(star.style, { left: `${random(0, 100)}%`, top: `${random(0, 100)}%`, animationDelay: `${random(0, 3)}s` });
        stars.append(star);
      }
      const balloons = document.querySelector('[data-balloons]');
      for (let i = 0; i < 14; i++) {
        const balloon = document.createElement('span');
        balloon.className = 'balloon';
        balloon.style.left = `${random(0, 95)}%`;
        balloon.style.background = colors[i % (colors.length - 1)];
        balloon.style.animationDuration = `${random(9, 16)}s`;
        balloon.style.animationDelay = `${random(-14, 0)}s`;
        balloon.style.setProperty('--drift', `${random(-80, 80)}px`);
        balloon.style.transform = `scale(${random(0.7, 1.15)})`;
        balloons.append(balloon);
      }

      // Confetti on a canvas: a burst on load, then a gentle shower.
      const canvas = document.getElementById('confetti');
      const ctx = canvas.getContext('2d');
      let pieces = [];
      const resize = () => { canvas.width = window.innerWidth * devicePixelRatio; canvas.height = window.innerHeight * devicePixelRatio; ctx.setTransform(devicePixelRatio, 0, 0, devicePixelRatio, 0, 0); };
      resize();
      window.addEventListener('resize', resize);
      const burst = (count, x = window.innerWidth / 2, y = window.innerHeight / 3) => {
        if (reduceMotion) return;
        for (let i = 0; i < count; i++) {
          const angle = random(0, Math.PI * 2);
          const speed = random(4, 13);
          pieces.push({ x, y, vx: Math.cos(angle) * speed, vy: Math.sin(angle) * speed - 4, size: random(6, 11), color: colors[Math.floor(random(0, colors.length))], rotation: random(0, 360), spin: random(-12, 12), life: random(110, 190), round: Math.random() < 0.3 });
        }
      };
      const shower = () => {
        if (reduceMotion || document.hidden) return;
        pieces.push({ x: random(0, window.innerWidth), y: -10, vx: random(-1, 1), vy: random(1, 3), size: random(5, 9), color: colors[Math.floor(random(0, colors.length))], rotation: random(0, 360), spin: random(-8, 8), life: 400, round: Math.random() < 0.3 });
      };
      const tick = () => {
        ctx.clearRect(0, 0, window.innerWidth, window.innerHeight);
        pieces = pieces.filter((piece) => piece.life > 0 && piece.y < window.innerHeight + 20);
        for (const piece of pieces) {
          piece.vy += 0.18; piece.vx *= 0.99; piece.x += piece.vx; piece.y += piece.vy; piece.rotation += piece.spin; piece.life--;
          ctx.save(); ctx.translate(piece.x, piece.y); ctx.rotate(piece.rotation * Math.PI / 180); ctx.fillStyle = piece.color;
          if (piece.round) { ctx.beginPath(); ctx.arc(0, 0, piece.size / 2, 0, Math.PI * 2); ctx.fill(); } else { ctx.fillRect(-piece.size / 2, -piece.size / 4, piece.size, piece.size / 2); }
          ctx.restore();
        }
        requestAnimationFrame(tick);
      };
      if (!reduceMotion) {
        requestAnimationFrame(tick);
        burst(180);
        setTimeout(() => burst(120, window.innerWidth * 0.2, window.innerHeight * 0.4), 600);
        setTimeout(() => burst(120, window.innerWidth * 0.8, window.innerHeight * 0.4), 1100);
        setInterval(shower, 140);
      }

      document.querySelectorAll('[data-birthday-person]').forEach((person) => {
        // Open the card on the side with room for it (right by default).
        const place = () => {
          const card = person.querySelector('.person-card');
          const rect = person.getBoundingClientRect();
          person.classList.toggle('is-flipped', rect.right + 18 + card.offsetWidth > window.innerWidth - 8 && rect.left - 18 - card.offsetWidth > 8);
        };
        person.addEventListener('mouseenter', place);
        person.addEventListener('focus', place);
        person.addEventListener('mouseleave', () => { if (document.activeElement === person) person.blur(); });
        person.addEventListener('keydown', (event) => { if (event.key === 'Escape') person.blur(); });
        // Mobile full-screen card: lock the page behind it, close with the ✕ button.
        const mobile = window.matchMedia('(max-width: 640px)');
        person.addEventListener('focusin', () => { if (mobile.matches) document.body.style.overflow = 'hidden'; });
        person.addEventListener('focusout', (event) => { if (!person.contains(event.relatedTarget)) document.body.style.overflow = ''; });
        person.querySelector('[data-card-close]').addEventListener('click', (event) => {
          event.stopPropagation();
          event.currentTarget.blur();
          person.blur();
          document.body.style.overflow = '';
        });
      });

      // Blow out the candles; the page then glides down to the wishes.
      const toast = document.querySelector('[data-toast]');
      const cake = document.querySelector('[data-cake]');
      cake.addEventListener('click', () => {
        if (cake.classList.contains('is-blown')) {
          cake.classList.remove('is-blown');
          return;
        }
        cake.classList.add('is-blown');
        // Blowing the candles starts the Happy Birthday tune (a click is needed for any sound).
        window.EventAudio?.start();
        window.EventAudio?.play('blow');
        window.EventAudio?.play('chime', 450);
        const rect = cake.getBoundingClientRect();
        burst(220, rect.left + rect.width / 2, rect.top);
        toast.textContent = i18n.blown || '';
        toast.classList.add('is-visible');
        setTimeout(() => toast.classList.remove('is-visible'), 2600);
        setTimeout(() => window.scrollTo({ top: document.documentElement.scrollHeight, behavior: reduceMotion ? 'auto' : 'smooth' }), 2000);
      });

      // Cycle through the wishes: every 3 seconds by itself, or on demand (which restarts the 3 seconds).
      const wishes = Array.isArray(i18n.wishes) ? i18n.wishes : [];
      const wish = document.querySelector('[data-wish]');
      let current = 0;
      let wishTimer = null;
      const nextWishButton = document.querySelector('[data-next-wish]');
      const showNextWish = () => {
        if (wishes.length < 2) return;
        current = (current + 1) % wishes.length;
        wish.classList.add('is-fading');
        setTimeout(() => { wish.textContent = wishes[current]; wish.classList.remove('is-fading'); }, 300);
        const rect = nextWishButton.getBoundingClientRect();
        burst(60, rect.left + rect.width / 2, rect.top);
      };
      const restartWishTimer = () => {
        clearInterval(wishTimer);
        wishTimer = setInterval(() => { if (!document.hidden) showNextWish(); }, 3000);
      };
      nextWishButton.addEventListener('click', () => {
        showNextWish();
        restartWishTimer();
      });
      restartWishTimer();
    })();
  </script>
</body>
</html>
