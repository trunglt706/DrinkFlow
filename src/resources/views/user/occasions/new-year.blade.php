{{-- New Year's Day (1/1): a champagne bottle that shakes, pops its cork and sprays foam, then fireworks fill the sky. --}}
@php
    $sparkles = [[56, 150, 0], [246, 120, 0.7], [232, 300, 1.4], [60, 330, 2], [150, 40, 1.1], [270, 220, 0.3]];
    // Foam bubbles: [x offset, size, delay] around the bottle mouth.
    $bubbles = [[-14, 9, 0], [8, 12, 0.05], [-4, 7, 0.1], [16, 8, 0.15], [-18, 11, 0.2], [2, 14, 0.25], [12, 6, 0.3], [-8, 10, 0.35]];
@endphp
<x-occasion.page :room="$room" :occasion="$occasion" :year="$year" :wishes="$wishes" :audio="$audio"
  theme="--accent:#a78bfa;--accent-2:#fcd34d;--soft:#fde68a;--line:rgba(253,230,138,.22);--card-1:rgba(36,30,90,.93);--card-2:rgba(16,14,48,.92);--hint-1:rgba(60,40,140,.88);--hint-2:rgba(120,90,20,.8);--bokeh:253,230,138;--bg:linear-gradient(160deg,#0b0b2b,#1e1b4b 50%,#0a0a1f);--glow-1:rgba(167,139,250,.26);--glow-2:rgba(252,211,77,.2);--glow-3:rgba(56,189,248,.18);"
  :effects="['sounds' => [['pop', 450], ['fizz', 500], ['chime', 900]], 'rain' => ['confetti', 'confetti', 'star'], 'rainColors' => ['#fcd34d', '#a78bfa', '#f472b6', '#38bdf8', '#ffffff'], 'rainEvery' => 260, 'burst' => ['confetti', 'star'], 'burstColors' => ['#fcd34d', '#fde68a', '#a78bfa', '#f472b6', '#38bdf8', '#34d399', '#ffffff'], 'fireworks' => true]">
  <x-slot:styles>
    <style>
      .bottle{transform-box:view-box;transform-origin:150px 440px;transform:rotate(-14deg);animation:ny-idle 2.4s ease-in-out infinite}
      .is-open .bottle{animation:ny-shake .5s ease-in-out both,ny-settle .6s .5s ease-out forwards}
      .cork{transform-box:view-box;transform-origin:150px 60px}
      .is-open .cork{animation:ny-pop 1s .45s cubic-bezier(.2,.7,.4,1) forwards}
      .bubble{transform-box:view-box;transform-origin:0 0;opacity:0}
      .is-open .bubble{animation:ny-foam 1.4s ease-out forwards;animation-delay:calc(.5s + var(--d))}
      .spray{opacity:0;transform-box:fill-box;transform-origin:50% 100%}
      .is-open .spray{animation:ny-spray 1.2s .5s ease-out forwards}
      .year{opacity:.9}
      .is-open .year{animation:ny-year 1.8s 1s ease-in-out infinite alternate}
      @keyframes ny-idle{0%,80%,100%{transform:rotate(-14deg)}85%{transform:rotate(-17deg)}90%{transform:rotate(-11deg)}95%{transform:rotate(-15deg)}}
      @keyframes ny-shake{0%,100%{transform:rotate(-14deg)}20%{transform:rotate(-22deg)}40%{transform:rotate(-6deg)}60%{transform:rotate(-20deg)}80%{transform:rotate(-9deg)}}
      @keyframes ny-settle{from{transform:rotate(-14deg)}to{transform:rotate(-24deg)}}
      @keyframes ny-pop{0%{transform:none;opacity:1}100%{transform:translate(40px,-330px) rotate(620deg);opacity:0}}
      @keyframes ny-foam{0%{opacity:0;transform:translate(0,0) scale(.3)}20%{opacity:1}100%{opacity:0;transform:translate(var(--x),-150px) scale(1.4)}}
      @keyframes ny-spray{0%{opacity:0;transform:scaleY(.2)}30%{opacity:.85;transform:scaleY(1.1)}100%{opacity:0;transform:scaleY(1.3)}}
      @keyframes ny-year{to{fill:#fff7cc}}
    </style>
  </x-slot:styles>

  <svg viewBox="0 0 300 470" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
    <defs>
      <linearGradient id="ny-glass" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#052e1c"/><stop offset=".3" stop-color="#14532d"/><stop offset=".45" stop-color="#3f8f62"/><stop offset=".6" stop-color="#14532d"/><stop offset="1" stop-color="#03200f"/></linearGradient>
      <linearGradient id="ny-foil" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#a16207"/><stop offset=".4" stop-color="#fde68a"/><stop offset=".7" stop-color="#eab308"/><stop offset="1" stop-color="#854d0e"/></linearGradient>
      <linearGradient id="ny-label" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fffbeb"/><stop offset="1" stop-color="#fde68a"/></linearGradient>
      <linearGradient id="ny-spray" x1="0" y1="1" x2="0" y2="0"><stop offset="0" stop-color="#fffbeb" stop-opacity=".95"/><stop offset="1" stop-color="#fde68a" stop-opacity="0"/></linearGradient>
    </defs>

    <g class="bottle">
      {{-- Foam spray and bubbles from the mouth --}}
      <path class="spray" d="M150 66C120 20 112-30 128-80 140-40 146-10 150-90 156-10 160-40 172-80 188-30 180 20 150 66Z" fill="url(#ny-spray)"/>
      @foreach ($bubbles as [$dx, $size, $delay])
        <g transform="translate({{ 150 + $dx }} 60)"><circle class="bubble" style="--d:{{ $delay }}s;--x:{{ $dx * 3 }}px" r="{{ $size / 2 }}" fill="#fffbeb" fill-opacity=".9"/></g>
      @endforeach

      {{-- Cork with its wire cage (pops on open) --}}
      <g class="cork">
        <rect x="138" y="36" width="24" height="30" rx="6" fill="#d6a868"/>
        <path d="M138 50h24M140 40l20 22M160 40l-20 22" stroke="#9ca3af" stroke-width="1.6"/>
      </g>

      {{-- Bottle --}}
      <path d="M134 64h32v74c0 22 38 38 38 82v206c0 14-10 24-24 24h-60c-14 0-24-10-24-24V220c0-44 38-60 38-82Z" fill="url(#ny-glass)"/>
      <path d="M132 62h36v74c0 8 6 14 14 20h-64c8-6 14-12 14-20Z" fill="url(#ny-foil)"/>
      <rect x="106" y="270" width="88" height="96" rx="8" fill="url(#ny-label)" stroke="#ca8a04" stroke-width="2"/>
      <path d="M118 290h64M118 348h64" stroke="#ca8a04" stroke-width="1.5"/>
      <text class="year" x="150" y="330" text-anchor="middle" font-family="Dancing Script, cursive" font-weight="700" font-size="34" fill="#a16207">{{ $year }}</text>
      <path d="M112 236c-4 40-4 120 0 200" stroke="#fff" stroke-opacity=".18" stroke-width="6" stroke-linecap="round" fill="none"/>
    </g>

    @foreach ($sparkles as [$x, $y, $delay])
      <g transform="translate({{ $x }} {{ $y }})"><path class="spark" style="--d:{{ $delay }}s" d="M0-7L1.6-1.6 7 0 1.6 1.6 0 7-1.6 1.6-7 0-1.6-1.6Z"/></g>
    @endforeach
  </svg>
</x-occasion.page>
