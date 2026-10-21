{{-- Stand-alone year recap of a member ("Wrapped"): a story of full-screen slides (auto-advance, tap / arrows to move)
     ending on a summary card that can be downloaded or shared as an image (drawn on a canvas in the browser).
     Slides without data (no sponsorship, no proxy order...) are left out. --}}
@php
    $money = static fn (int $amount): string => \App\Support\Helpers\FormatHelper::formatCurrency($amount);
    $name = $member->payerName();
    $params = ['year' => $year, 'room' => $room->name, 'name' => $name];
    $slides = [];
    if ($recap !== null) {
        $slides[] = ['key' => 'cups', 'theme' => 'mint'];
        if ($recap['favorite_item'] !== null) {
            $slides[] = ['key' => 'item', 'theme' => 'peach'];
        }
        if ($recap['favorite_shop'] !== null) {
            $slides[] = ['key' => 'shop', 'theme' => 'sky'];
        }
        if ($recap['busiest_month'] !== null) {
            $slides[] = ['key' => 'month', 'theme' => 'violet'];
        }
        $slides[] = ['key' => 'money', 'theme' => 'gold'];
        if ($recap['sponsored_given'] > 0 || $recap['medals']['total'] > 0) {
            $slides[] = ['key' => 'sponsor', 'theme' => 'rose'];
        }
        if ($recap['proxy_orders'] > 0) {
            $slides[] = ['key' => 'helper', 'theme' => 'teal'];
        }
        $slides[] = ['key' => 'rank', 'theme' => 'indigo'];
        $slides[] = ['key' => 'summary', 'theme' => 'summary'];
    }
    $titleKey = $recap === null ? null : ($recap['patron'] ? 'recap.patron' : 'recap.titles.'.$recap['title']);
    $card = $recap === null ? null : [
        'eyebrow' => __('recap.intro_eyebrow', $params),
        'title' => __($titleKey),
        'name' => $name,
        'room' => $room->name,
        'year' => $year,
        'stats' => [
            ['value' => number_format($recap['cups'], 0, ',', '.'), 'label' => __('recap.card_cups')],
            ['value' => number_format($recap['orders'], 0, ',', '.'), 'label' => __('recap.card_orders')],
            ['value' => $money($recap['spent']), 'label' => __('recap.card_spent')],
        ],
        'lines' => array_values(array_filter([
            $recap['favorite_item'] ? [__('recap.card_item'), $recap['favorite_item']['name']] : null,
            $recap['favorite_shop'] ? [__('recap.card_shop'), $recap['favorite_shop']['name']] : null,
            [__('recap.card_rank'), '#'.$recap['rank'].' / '.$recap['ranked_members']],
        ])),
        'footer' => 'DrinkFlow · '.$room->name,
        'file' => 'drinkflow-recap-'.$year.'.png',
        'shareText' => __('recap.share_text', ['year' => $year, 'cups' => $recap['cups'], 'item' => $recap['favorite_item']['name'] ?? '—']),
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
  <title>{{ __('recap.page_title', $params) }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    html,body{height:100%}
    body{font-family:'Be Vietnam Pro',system-ui,sans-serif;color:#fff;background:#0b1020;overflow:hidden}
    .bg{position:fixed;inset:0;transition:background 1.2s cubic-bezier(.4,0,.2,1)}
    [data-theme="mint"] .bg{background:radial-gradient(900px 600px at 20% 10%,#10b981 0%,transparent 60%),radial-gradient(800px 600px at 90% 90%,#0ea5e9 0%,transparent 60%),#052e2b}
    [data-theme="peach"] .bg{background:radial-gradient(900px 600px at 80% 10%,#fb923c 0%,transparent 60%),radial-gradient(800px 600px at 10% 90%,#f43f5e 0%,transparent 60%),#3b0d14}
    [data-theme="sky"] .bg{background:radial-gradient(900px 600px at 15% 15%,#38bdf8 0%,transparent 60%),radial-gradient(800px 600px at 85% 85%,#6366f1 0%,transparent 60%),#0b1640}
    [data-theme="violet"] .bg{background:radial-gradient(900px 600px at 85% 15%,#a855f7 0%,transparent 60%),radial-gradient(800px 600px at 15% 85%,#ec4899 0%,transparent 60%),#2a0b3d}
    [data-theme="gold"] .bg{background:radial-gradient(900px 600px at 20% 20%,#f59e0b 0%,transparent 60%),radial-gradient(800px 600px at 80% 80%,#84cc16 0%,transparent 60%),#2b1d05}
    [data-theme="rose"] .bg{background:radial-gradient(900px 600px at 80% 20%,#f43f5e 0%,transparent 60%),radial-gradient(800px 600px at 20% 80%,#fbbf24 0%,transparent 60%),#3b0a18}
    [data-theme="teal"] .bg{background:radial-gradient(900px 600px at 20% 80%,#14b8a6 0%,transparent 60%),radial-gradient(800px 600px at 80% 20%,#22c55e 0%,transparent 60%),#06281f}
    [data-theme="indigo"] .bg{background:radial-gradient(900px 600px at 50% 0%,#6366f1 0%,transparent 60%),radial-gradient(800px 600px at 50% 100%,#06b6d4 0%,transparent 60%),#0c0f2e}
    [data-theme="summary"] .bg,[data-theme="intro"] .bg{background:radial-gradient(900px 700px at 15% 10%,#006948 0%,transparent 60%),radial-gradient(800px 600px at 90% 90%,#7c3aed 0%,transparent 60%),#0b1020}
    .story{position:relative;z-index:2;height:100vh;height:100svh;display:flex;flex-direction:column;max-width:560px;margin:0 auto;padding:16px 18px 20px}
    .bars{display:flex;gap:5px}
    .bars span{flex:1;height:4px;border-radius:4px;background:rgba(255,255,255,.25);overflow:hidden}
    .bars i{display:block;height:100%;width:0;background:#fff}
    .bars span.is-done i{width:100%}
    .bars span.is-active i{animation:fill var(--slide-ms,6000ms) linear forwards}
    .story.is-paused .bars span.is-active i{animation-play-state:paused}
    .top{display:flex;align-items:center;justify-content:flex-end;gap:8px;min-height:34px;margin-top:12px;font-size:13px;font-weight:600}
    /* Back to the achievements page: pinned to the left edge of the screen, outside the story column. */
    .back{position:fixed;top:30px;left:16px;z-index:20}
    .back,.icon-btn{color:#fff;text-decoration:none;display:inline-flex;align-items:center;gap:6px;padding:7px 12px;border-radius:999px;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.25);font:600 13px 'Be Vietnam Pro',sans-serif;cursor:pointer}
    .icon-btn:focus-visible,.back:focus-visible,.btn:focus-visible{outline:2px solid #fff;outline-offset:3px}
    .stage{position:relative;flex:1;display:grid}
    .slide{grid-area:1/1;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;gap:14px;padding:12px 4px;
      opacity:0;visibility:hidden;transform:translateY(14px);transition:opacity .9s cubic-bezier(.4,0,.2,1),transform 1s cubic-bezier(.22,1,.36,1),visibility 0s 1s}
    .slide.is-active{opacity:1;visibility:visible;transform:none;transition-delay:.15s}
    .eyebrow{font-size:13px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:rgba(255,255,255,.75)}
    .kicker{font-size:clamp(18px,4.6vw,24px);font-weight:600;color:rgba(255,255,255,.9)}
    .big{font-size:clamp(46px,14vw,84px);font-weight:800;line-height:1.05;letter-spacing:-.02em;text-shadow:0 8px 40px rgba(0,0,0,.35);overflow-wrap:anywhere}
    .mid{font-size:clamp(28px,8vw,46px);font-weight:800;line-height:1.15;overflow-wrap:anywhere}
    .detail{font-size:clamp(15px,3.8vw,18px);color:rgba(255,255,255,.85);line-height:1.5}
    .emoji{font-size:clamp(56px,16vw,88px);line-height:1;animation:bob 3s ease-in-out infinite}
    .pill{display:inline-flex;padding:6px 14px;border-radius:999px;background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.25);font-size:14px;font-weight:600}
    .card{width:100%;padding:22px 20px;border-radius:24px;background:linear-gradient(150deg,rgba(255,255,255,.16),rgba(255,255,255,.06));border:1px solid rgba(255,255,255,.28);box-shadow:0 30px 60px -30px rgba(0,0,0,.6);text-align:left}
    .card .title{font-size:24px;font-weight:800;margin-top:4px}
    .card .who{font-size:14px;color:rgba(255,255,255,.8);margin-top:2px}
    .stats{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-top:16px}
    .stats div{padding:10px;border-radius:14px;background:rgba(0,0,0,.2);text-align:center}
    .stats strong{display:block;font-size:clamp(13px,3.8vw,18px);font-weight:800;white-space:nowrap}
    .stats span{font-size:12px;color:rgba(255,255,255,.75)}
    .lines{margin-top:12px;display:grid;gap:6px;font-size:14px}
    .lines p{display:flex;justify-content:space-between;gap:10px}.lines p span{color:rgba(255,255,255,.7)}.lines p strong{text-align:right;overflow-wrap:anywhere}
    .actions{display:flex;flex-wrap:wrap;justify-content:center;gap:10px;margin-top:16px}
    .btn{display:inline-flex;align-items:center;gap:8px;padding:11px 18px;border-radius:999px;border:0;font:700 14px 'Be Vietnam Pro',sans-serif;cursor:pointer;text-decoration:none}
    .btn-primary{background:#fff;color:#0b1020}
    .btn-ghost{background:rgba(255,255,255,.14);color:#fff;border:1px solid rgba(255,255,255,.3)}
    .btn:disabled{opacity:.6;cursor:wait}
    .nav{position:absolute;top:0;bottom:0;width:30%;background:none;border:0;cursor:pointer;z-index:3}
    .nav.prev{left:0}.nav.next{right:0;width:70%}
    .nav:focus-visible{outline:none;background:linear-gradient(90deg,rgba(255,255,255,.06),transparent)}
    .slide.is-active .actions,.slide.is-active .card{position:relative;z-index:4}
    .toast{position:fixed;left:50%;bottom:24px;transform:translate(-50%,30px);opacity:0;padding:9px 16px;border-radius:999px;background:#fff;color:#0b1020;font-weight:700;font-size:14px;transition:all .35s;z-index:10}
    .toast.is-visible{opacity:1;transform:translate(-50%,0)}
    .empty{margin:auto;text-align:center;display:grid;gap:12px;justify-items:center}
    @keyframes fill{to{width:100%}}
    @keyframes bob{50%{transform:translateY(-8px)}}
    @media (prefers-reduced-motion:reduce){*{animation:none!important;transition:none!important}}
  </style>
</head>
<body data-theme="{{ $slides === [] ? 'summary' : 'intro' }}">
  <div class="bg" aria-hidden="true"></div>
  <a class="back" href="{{ route('user.rooms.achievements', $room) }}" data-recap-back>← {{ __('recap.back') }}</a>

  @if ($recap === null)
    <main class="story">
      <div class="top"></div>
      <div class="empty" data-recap-empty>
        <span class="emoji" aria-hidden="true">🥤</span>
        <h1 class="mid">{{ __('recap.empty_title', $params) }}</h1>
        <p class="detail">{{ __('recap.empty_desc') }}</p>
      </div>
    </main>
  @else
    <main class="story" data-recap data-card="{{ json_encode($card, JSON_UNESCAPED_UNICODE) }}"
      data-i18n="{{ json_encode(['pause' => __('recap.pause'), 'play' => __('recap.play'), 'downloaded' => __('recap.downloaded')], JSON_UNESCAPED_UNICODE) }}">
      <div class="bars" aria-hidden="true">
        <span><i></i></span>
        @foreach ($slides as $slide)
          <span><i></i></span>
        @endforeach
      </div>
      <div class="top">
        <button type="button" class="icon-btn" data-recap-toggle aria-pressed="false" aria-label="{{ __('recap.pause') }}">❚❚</button>
      </div>

      <div class="stage" aria-live="polite">
        <button type="button" class="nav prev" data-recap-prev aria-label="{{ __('recap.prev') }}"></button>
        <button type="button" class="nav next" data-recap-next aria-label="{{ __('recap.next') }}"></button>

        <section class="slide is-active" data-slide data-theme="intro" aria-label="{{ __('recap.slide_label', ['current' => 1, 'total' => count($slides) + 1]) }}">
          <span class="emoji" aria-hidden="true">🧋</span>
          <p class="eyebrow">{{ __('recap.intro_eyebrow', $params) }}</p>
          <h1 class="mid">{{ __('recap.intro_title', $params) }}</h1>
          <span class="pill">{{ __('recap.tap_start') }} →</span>
        </section>

        @foreach ($slides as $slide)
          <section class="slide" data-slide data-theme="{{ $slide['theme'] }}" data-recap-slide="{{ $slide['key'] }}"
            aria-label="{{ __('recap.slide_label', ['current' => $loop->iteration + 1, 'total' => count($slides) + 1]) }}">
            @switch ($slide['key'])
              @case('cups')
                <p class="kicker">{{ __('recap.cups_title', $params) }}</p>
                <p class="big" data-count-to="{{ $recap['cups'] }}">{{ __('recap.cups_value', ['count' => number_format($recap['cups'], 0, ',', '.')]) }}</p>
                <p class="detail">{{ __('recap.cups_detail', ['orders' => $recap['orders'], 'campaigns' => $recap['campaigns']]) }}</p>
                <span class="pill">{{ __('recap.first_order', ['date' => $recap['first_order']]) }}</span>
                @break
              @case('item')
                <span class="emoji" aria-hidden="true">💖</span>
                <p class="kicker">{{ __('recap.item_title') }}</p>
                <p class="mid">{{ $recap['favorite_item']['name'] }}</p>
                <p class="detail">{{ __('recap.item_detail', ['count' => $recap['favorite_item']['cups']]) }}</p>
                @break
              @case('shop')
                <span class="emoji" aria-hidden="true">🏪</span>
                <p class="kicker">{{ __('recap.shop_title') }}</p>
                <p class="mid">{{ $recap['favorite_shop']['name'] }}</p>
                <p class="detail">{{ __('recap.shop_detail', ['count' => $recap['favorite_shop']['orders']]) }}</p>
                @break
              @case('month')
                <span class="emoji" aria-hidden="true">🔥</span>
                <p class="kicker">{{ __('recap.month_title') }}</p>
                <p class="big">{{ __('recap.month_value', ['month' => $recap['busiest_month']['month']]) }}</p>
                <p class="detail">{{ __('recap.month_detail', ['count' => $recap['busiest_month']['cups']]) }}</p>
                @break
              @case('money')
                <span class="emoji" aria-hidden="true">💸</span>
                <p class="kicker">{{ __('recap.money_title') }}</p>
                <p class="big">{{ $money($recap['spent']) }}</p>
                <p class="detail">{{ $recap['sponsored_received'] > 0 ? __('recap.money_detail', ['amount' => $money($recap['sponsored_received'])]) : __('recap.money_no_sponsor') }}</p>
                @break
              @case('sponsor')
                <span class="emoji" aria-hidden="true">🏅</span>
                <p class="kicker">{{ __('recap.sponsor_title') }}</p>
                @if ($recap['sponsored_given'] > 0)
                  <p class="big">{{ $money($recap['sponsored_given']) }}</p>
                  <p class="detail">{{ __('recap.sponsor_detail', ['amount' => $money($recap['sponsored_given']), 'count' => $recap['sponsored_campaigns']]) }}</p>
                @endif
                @if ($recap['medals']['total'] > 0)
                  <span class="pill">🥇 {{ __('recap.medals', $recap['medals']) }}</span>
                @endif
                @break
              @case('helper')
                <span class="emoji" aria-hidden="true">🤝</span>
                <p class="kicker">{{ __('recap.helper_title') }}</p>
                <p class="big">{{ $recap['proxy_orders'] }}</p>
                <p class="detail">{{ __('recap.proxy_detail', ['count' => $recap['proxy_orders']]) }}</p>
                @break
              @case('rank')
                <span class="emoji" aria-hidden="true">🏆</span>
                <p class="kicker">{{ __('recap.rank_title', $params) }}</p>
                <p class="big">{{ __('recap.rank_value', ['rank' => $recap['rank']]) }}</p>
                <p class="detail">{{ __('recap.rank_detail', ['total' => $recap['ranked_members']]) }}</p>
                <span class="pill">{{ __('recap.room_cups', ['count' => number_format($recap['room_cups'], 0, ',', '.')]) }}</span>
                @break
              @case('summary')
                <div class="card" data-recap-card>
                  <p class="eyebrow">{{ __('recap.summary_title', $params) }}</p>
                  <p class="title">{{ $card['title'] }}</p>
                  <p class="who">{{ $name }} · {{ $room->name }}</p>
                  <div class="stats">
                    @foreach ($card['stats'] as $stat)
                      <div><strong>{{ $stat['value'] }}</strong><span>{{ $stat['label'] }}</span></div>
                    @endforeach
                  </div>
                  <div class="lines">
                    @foreach ($card['lines'] as [$label, $value])
                      <p><span>{{ $label }}</span><strong>{{ $value }}</strong></p>
                    @endforeach
                  </div>
                </div>
                <div class="actions">
                  <button type="button" class="btn btn-primary" data-recap-download>⬇ {{ __('recap.download') }}</button>
                  <button type="button" class="btn btn-ghost" data-recap-share hidden>↗ {{ __('recap.share') }}</button>
                  <button type="button" class="btn btn-ghost" data-recap-replay>↺ {{ __('recap.replay') }}</button>
                </div>
                @break
            @endswitch
          </section>
        @endforeach
      </div>
    </main>
    <div class="toast" role="status" data-recap-toast></div>

    <script>
      (() => {
        const story = document.querySelector('[data-recap]');
        const parse = (value) => { try { return JSON.parse(value || '{}'); } catch { return {}; } };
        const card = parse(story.dataset.card);
        const i18n = parse(story.dataset.i18n);
        const slides = [...story.querySelectorAll('[data-slide]')];
        const bars = [...story.querySelectorAll('.bars span')];
        const toggle = story.querySelector('[data-recap-toggle]');
        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        const SLIDE_MS = 6000;
        let current = 0;
        let timer = null;
        let paused = false;

        // Big numbers count up when their slide opens.
        const countUp = (slide) => {
          const el = slide.querySelector('[data-count-to]');
          if (!el || reduceMotion) return;
          const target = Number(el.dataset.countTo) || 0;
          const template = el.textContent.replace(/[\d.]+/, '__N__');
          const started = performance.now();
          const step = (now) => {
            const t = Math.min(1, (now - started) / 1400);
            const value = Math.round(target * (1 - Math.pow(1 - t, 3)));
            el.textContent = template.replace('__N__', value.toLocaleString('vi-VN'));
            if (t < 1) requestAnimationFrame(step);
          };
          requestAnimationFrame(step);
        };

        const show = (index) => {
          current = Math.max(0, Math.min(slides.length - 1, index));
          slides.forEach((slide, i) => slide.classList.toggle('is-active', i === current));
          bars.forEach((bar, i) => {
            bar.classList.toggle('is-done', i < current);
            bar.classList.remove('is-active');
          });
          document.body.dataset.theme = slides[current].dataset.theme;
          countUp(slides[current]);
          schedule();
        };
        const schedule = () => {
          clearTimeout(timer);
          const bar = bars[current];
          void bar.offsetWidth;
          bar.classList.add('is-active');
          story.classList.toggle('is-paused', paused);
          // The summary stays until the viewer leaves or replays.
          if (paused || current === slides.length - 1) {
            if (current === slides.length - 1) bar.classList.add('is-done');
            return;
          }
          timer = setTimeout(() => show(current + 1), SLIDE_MS);
        };

        story.querySelector('[data-recap-next]').addEventListener('click', () => show(current + 1));
        story.querySelector('[data-recap-prev]').addEventListener('click', () => show(current - 1));
        story.querySelector('[data-recap-replay]')?.addEventListener('click', () => show(0));
        toggle.addEventListener('click', () => {
          paused = !paused;
          toggle.setAttribute('aria-pressed', paused ? 'true' : 'false');
          toggle.setAttribute('aria-label', paused ? i18n.play : i18n.pause);
          toggle.textContent = paused ? '▶' : '❚❚';
          schedule();
        });
        document.addEventListener('keydown', (event) => {
          if (event.key === 'ArrowRight') show(current + 1);
          if (event.key === 'ArrowLeft') show(current - 1);
        });

        // Summary card drawn on a canvas (1080 x 1350) for download / share.
        const drawCard = async () => {
          await document.fonts?.ready;
          const W = 1080, H = 1350;
          const canvas = document.createElement('canvas');
          canvas.width = W; canvas.height = H;
          const ctx = canvas.getContext('2d');
          const bg = ctx.createLinearGradient(0, 0, W, H);
          bg.addColorStop(0, '#006948'); bg.addColorStop(.55, '#1e1b4b'); bg.addColorStop(1, '#7c3aed');
          ctx.fillStyle = bg; ctx.fillRect(0, 0, W, H);
          const font = (weight, size) => `${weight} ${size}px 'Be Vietnam Pro', system-ui, sans-serif`;
          const fit = (text, weight, size, max) => { let s = size; ctx.font = font(weight, s); while (ctx.measureText(text).width > max && s > 20) { s -= 2; ctx.font = font(weight, s); } };
          ctx.fillStyle = 'rgba(255,255,255,.75)'; ctx.font = font(700, 34); ctx.fillText(card.eyebrow.toUpperCase(), 80, 140);
          ctx.fillStyle = '#fff'; fit(card.title, 800, 78, W - 160); ctx.fillText(card.title, 80, 250);
          ctx.fillStyle = 'rgba(255,255,255,.85)'; fit(`${card.name} · ${card.room}`, 500, 38, W - 160); ctx.fillText(`${card.name} · ${card.room}`, 80, 315);
          const boxW = (W - 160 - 40) / 3;
          card.stats.forEach((stat, i) => {
            const x = 80 + i * (boxW + 20);
            ctx.fillStyle = 'rgba(0,0,0,.22)';
            ctx.beginPath(); ctx.roundRect?.(x, 380, boxW, 200, 28); ctx.fill();
            ctx.fillStyle = '#fff'; ctx.textAlign = 'center'; fit(stat.value, 800, 64, boxW - 30); ctx.fillText(stat.value, x + boxW / 2, 480);
            ctx.fillStyle = 'rgba(255,255,255,.75)'; ctx.font = font(500, 30); ctx.fillText(stat.label, x + boxW / 2, 540);
            ctx.textAlign = 'left';
          });
          card.lines.forEach(([label, value], i) => {
            const y = 700 + i * 120;
            ctx.fillStyle = 'rgba(255,255,255,.7)'; ctx.font = font(500, 32); ctx.fillText(label, 80, y);
            ctx.fillStyle = '#fff'; fit(value, 700, 50, W - 160); ctx.fillText(value, 80, y + 58);
          });
          ctx.fillStyle = 'rgba(255,255,255,.7)'; ctx.font = font(600, 30); ctx.fillText(card.footer, 80, H - 80);
          return new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));
        };

        const toast = document.querySelector('[data-recap-toast]');
        const notify = (text) => { toast.textContent = text; toast.classList.add('is-visible'); setTimeout(() => toast.classList.remove('is-visible'), 2400); };
        const download = story.querySelector('[data-recap-download]');
        const share = story.querySelector('[data-recap-share]');
        download?.addEventListener('click', async () => {
          download.disabled = true;
          try {
            const blob = await drawCard();
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = card.file;
            link.click();
            setTimeout(() => URL.revokeObjectURL(link.href), 4000);
            notify(i18n.downloaded);
          } finally {
            download.disabled = false;
          }
        });
        // Sharing an image needs the Web Share API with files (mostly phones).
        if (share && navigator.canShare && navigator.canShare({ files: [new File([''], 'x.png', { type: 'image/png' })] })) {
          share.hidden = false;
          share.addEventListener('click', async () => {
            share.disabled = true;
            try {
              const file = new File([await drawCard()], card.file, { type: 'image/png' });
              await navigator.share({ files: [file], text: card.shareText });
            } catch { /* cancelled by the viewer */ } finally {
              share.disabled = false;
            }
          });
        }

        show(0);
      })();
    </script>
  @endif
</body>
</html>
