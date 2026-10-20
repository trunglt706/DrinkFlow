{{-- Christmas (25/12): a dark Christmas tree under falling snow; clicking it lights the star, the garland bulbs and the
     baubles, and gifts pop up at its foot. --}}
@php
    // Baubles: [x, y, colour]; garland bulbs: [x, y, colour, twinkle delay].
    $baubles = [[128, 168, '#ef4444'], [176, 196, '#fbbf24'], [110, 262, '#38bdf8'], [192, 286, '#ef4444'], [148, 318, '#a78bfa'], [86, 352, '#fbbf24'], [214, 372, '#38bdf8'], [150, 236, '#f472b6']];
    $bulbs = [];
    foreach ([[120, 140, 182, 186], [98, 232, 204, 252], [78, 330, 224, 346]] as $row => [$x1, $y1, $x2, $y2]) {
        for ($i = 0; $i <= 6; $i++) {
            $t = $i / 6;
            $bulbs[] = [$x1 + ($x2 - $x1) * $t, $y1 + ($y2 - $y1) * $t + sin($t * M_PI) * 14, ['#fde047', '#f87171', '#4ade80', '#60a5fa'][($i + $row) % 4], round(($i * 0.17 + $row * 0.3), 2)];
        }
    }
@endphp
<x-occasion.page :room="$room" :occasion="$occasion" :year="$year" :audio="$audio"
  theme="--accent:#ef4444;--accent-2:#fde68a;--soft:#bbf7d0;--line:rgba(187,247,208,.22);--card-1:rgba(14,58,52,.93);--card-2:rgba(8,26,36,.92);--hint-1:rgba(20,90,70,.9);--hint-2:rgba(140,30,30,.85);--bokeh:220,240,255;--bg:linear-gradient(160deg,#071a2b,#0b3b3a 50%,#0a1424);--glow-1:rgba(253,230,138,.18);--glow-2:rgba(239,68,68,.18);--glow-3:rgba(56,189,248,.18);"
  :effects="['sounds' => [['bells', 0], ['chime', 300]], 'rain' => ['snow'], 'rainColors' => ['#ffffff', '#e0f2fe', '#f8fafc'], 'rainEvery' => 140, 'rainBeforeOpen' => true, 'burst' => ['star', 'snow', 'confetti'], 'burstColors' => ['#fde68a', '#fde047', '#ef4444', '#4ade80', '#ffffff']]">
  <x-slot:styles>
    <style>
      .tree{transform-box:view-box;transform-origin:150px 420px;animation:xm-sway 5s ease-in-out infinite alternate}
      .bauble{fill:#334155;transition:fill .5s;transition-delay:var(--d)}
      .is-open .bauble{fill:var(--c)}
      .bauble-glow{opacity:0;transition:opacity .6s;transition-delay:var(--d)}
      .is-open .bauble-glow{opacity:.75}
      .bulb{fill:#1f2937}
      .is-open .bulb{fill:var(--c);animation:xm-twinkle 1.2s ease-in-out infinite alternate;animation-delay:calc(.6s + var(--d))}
      .star{fill:#78716c;transform-box:view-box;transform-origin:150px 74px;transition:fill .4s}
      .stage-btn:hover .star{fill:#a8a29e}
      .is-open .star{fill:#fde047;animation:xm-star 1s ease-out}
      .star-rays{opacity:0;transform-box:view-box;transform-origin:150px 74px}
      .is-open .star-rays{animation:xm-rays-in .6s .2s ease-out forwards,xm-spin 14s .2s linear infinite}
      .gift{transform-box:fill-box;transform-origin:50% 100%;transform:scale(0)}
      .is-open .gift{animation:xm-pop .6s cubic-bezier(.3,1.6,.5,1) forwards;animation-delay:var(--d)}
      @keyframes xm-sway{from{transform:rotate(-.8deg)}to{transform:rotate(.8deg)}}
      @keyframes xm-twinkle{from{opacity:1}to{opacity:.35}}
      @keyframes xm-star{0%{transform:scale(1)}40%{transform:scale(1.5)}100%{transform:scale(1)}}
      @keyframes xm-rays-in{to{opacity:1}}
      @keyframes xm-spin{to{transform:rotate(360deg)}}
      @keyframes xm-pop{to{transform:scale(1)}}
    </style>
  </x-slot:styles>

  <svg viewBox="0 0 300 460" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
    <defs>
      <linearGradient id="xm-tree" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#22c55e"/><stop offset=".6" stop-color="#15803d"/><stop offset="1" stop-color="#0f3d24"/></linearGradient>
      <radialGradient id="xm-glow"><stop offset="0" stop-color="#fff7cc" stop-opacity=".9"/><stop offset="1" stop-color="#fde68a" stop-opacity="0"/></radialGradient>
      <linearGradient id="xm-ray" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fde68a" stop-opacity=".7"/><stop offset="1" stop-color="#fde68a" stop-opacity="0"/></linearGradient>
    </defs>

    {{-- Snowy ground --}}
    <ellipse cx="150" cy="430" rx="140" ry="20" fill="#e2e8f0" fill-opacity=".9"/>

    <g class="tree">
      <g class="star-rays">
        @for ($ray = 0; $ray < 10; $ray++)
          <path d="M150 74L145 -6H155Z" fill="url(#xm-ray)" transform="rotate({{ $ray * 36 }} 150 74)"/>
        @endfor
      </g>
      <rect x="136" y="386" width="28" height="40" rx="3" fill="#78350f"/>
      <path d="M150 92L214 186H182L238 282H198L262 392H38L102 282H62L118 186H86Z" fill="url(#xm-tree)"/>
      <path d="M150 92L118 186H86ZM150 92L102 282H62ZM150 92L38 392H102Z" fill="#000" fill-opacity=".12"/>
      {{-- Snow on the branches --}}
      <path d="M86 186Q118 178 150 190 182 178 214 186M62 282Q104 272 150 286 196 272 238 282M38 392Q94 380 150 394 206 380 262 392" stroke="#f8fafc" stroke-width="5" stroke-linecap="round" fill="none" stroke-opacity=".85"/>

      {{-- Garland bulbs --}}
      @foreach ($bulbs as [$x, $y, $color, $delay])
        <circle class="bulb" style="--c:{{ $color }};--d:{{ $delay }}s" cx="{{ round($x, 1) }}" cy="{{ round($y, 1) }}" r="4"/>
      @endforeach

      {{-- Baubles --}}
      @foreach ($baubles as [$x, $y, $color])
        <circle class="bauble-glow" style="--d:{{ 0.3 + $loop->index * 0.08 }}s" cx="{{ $x }}" cy="{{ $y }}" r="16" fill="url(#xm-glow)"/>
        <circle class="bauble" style="--c:{{ $color }};--d:{{ 0.3 + $loop->index * 0.08 }}s" cx="{{ $x }}" cy="{{ $y }}" r="8"/>
        <circle cx="{{ $x - 2.5 }}" cy="{{ $y - 2.5 }}" r="2.2" fill="#fff" fill-opacity=".6"/>
      @endforeach

      {{-- Star on top --}}
      <path class="star" d="M150 50l7 16 17 1.5-13 11 4 17-15-9-15 9 4-17-13-11 17-1.5Z" stroke="#fef9c3" stroke-width="1.5"/>
    </g>

    {{-- Gifts at the foot of the tree (pop up on open) --}}
    <g class="gift" style="--d:.9s"><rect x="58" y="392" width="44" height="36" rx="4" fill="#ef4444"/><rect x="76" y="392" width="8" height="36" fill="#fde047"/><path d="M80 392c-10-14-22-8-14 0M80 392c10-14 22-8 14 0" stroke="#fde047" stroke-width="4" fill="none"/></g>
    <g class="gift" style="--d:1.05s"><rect x="200" y="398" width="40" height="30" rx="4" fill="#3b82f6"/><rect x="216" y="398" width="8" height="30" fill="#f8fafc"/><path d="M220 398c-9-12-20-7-12 0M220 398c9-12 20-7 12 0" stroke="#f8fafc" stroke-width="4" fill="none"/></g>
    <g class="gift" style="--d:1.2s"><rect x="168" y="404" width="30" height="24" rx="3" fill="#a855f7"/><rect x="180" y="404" width="6" height="24" fill="#fde047"/></g>
  </svg>
</x-occasion.page>
