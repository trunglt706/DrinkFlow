{{-- International Men's Day (19/11): a navy gift box that, once clicked, throws its lid off and lifts a golden trophy. --}}
@php
    $sparkles = [[46, 220, 0], [256, 200, 0.9], [66, 110, 1.5], [238, 86, 0.5], [150, 30, 1.1]];
@endphp
<x-occasion.page :room="$room" :occasion="$occasion" :year="$year" :wishes="$wishes" :audio="$audio" :wall="$wall" :back-url="$backUrl ?? null" :preview="$preview ?? false"
  theme="--accent:#38bdf8;--accent-2:#fbbf24;--soft:#bae6fd;--line:rgba(186,230,253,.22);--card-1:rgba(14,40,78,.93);--card-2:rgba(8,20,44,.92);--hint-1:rgba(14,60,110,.88);--hint-2:rgba(90,70,20,.8);--bokeh:150,210,255;--bg:linear-gradient(160deg,#071428,#0e2f5a 50%,#081226);--glow-1:rgba(56,189,248,.24);--glow-2:rgba(251,191,36,.2);--glow-3:rgba(99,102,241,.22);"
  :effects="['sounds' => [['whoosh', 0], ['fanfare', 500]], 'rain' => ['confetti', 'star'], 'rainColors' => ['#38bdf8', '#fbbf24', '#e0f2fe', '#60a5fa'], 'rainEvery' => 320, 'burst' => ['star', 'confetti'], 'burstColors' => ['#fbbf24', '#fde68a', '#38bdf8', '#ffffff']]">
  <x-slot:styles>
    <style>
      .gift{transform-box:view-box;transform-origin:150px 400px;animation:md-wiggle 2.6s ease-in-out infinite}
      .is-open .gift{animation:none}
      .lid{transform-box:view-box;transform-origin:150px 240px;transition:transform .9s cubic-bezier(.3,1.4,.5,1),opacity .6s .5s}
      .is-open .lid{transform:translate(-80px,-210px) rotate(-26deg);opacity:0}
      .trophy{transform-box:view-box;transform:translateY(200px) scale(.6);transform-origin:150px 300px}
      .is-open .trophy{animation:md-rise 1.2s .45s cubic-bezier(.2,.9,.3,1.25) forwards}
      .rays{opacity:0;transform-box:view-box;transform-origin:150px 150px}
      .is-open .rays{animation:md-rays-in .8s .9s ease-out forwards,md-spin 18s .9s linear infinite}
      .shine{transform-box:view-box;transform:translateX(-120px)}
      .is-open .shine{animation:md-shine 2.8s 1.8s ease-in-out infinite}
      @keyframes md-wiggle{0%,70%,100%{transform:rotate(0)}75%{transform:rotate(-3deg)}80%{transform:rotate(3deg)}85%{transform:rotate(-2deg)}90%{transform:rotate(2deg)}}
      @keyframes md-rise{to{transform:none}}
      @keyframes md-rays-in{to{opacity:1}}
      @keyframes md-spin{to{transform:rotate(360deg)}}
      @keyframes md-shine{0%{transform:translateX(-120px)}50%,100%{transform:translateX(120px)}}
    </style>
  </x-slot:styles>

  <svg viewBox="0 0 300 440" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
    <defs>
      <linearGradient id="md-gold" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#b45309"/><stop offset=".35" stop-color="#fde68a"/><stop offset=".6" stop-color="#f59e0b"/><stop offset="1" stop-color="#92400e"/></linearGradient>
      <linearGradient id="md-box" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#1e3a8a"/><stop offset="1" stop-color="#0b1a40"/></linearGradient>
      <linearGradient id="md-lid" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#3b5bdb"/><stop offset="1" stop-color="#1e3a8a"/></linearGradient>
      <linearGradient id="md-ray" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fde68a" stop-opacity=".55"/><stop offset="1" stop-color="#fde68a" stop-opacity="0"/></linearGradient>
      <clipPath id="md-inside"><rect x="0" y="-200" width="300" height="595"/></clipPath>
      <clipPath id="md-cup"><path d="M108 84h84v26c0 36-20 58-42 62-22-4-42-26-42-62Z"/></clipPath>
    </defs>

    {{-- Light rays behind the trophy --}}
    <g class="rays">
      @for ($ray = 0; $ray < 12; $ray++)
        <path d="M150 150L140 -40H160Z" fill="url(#md-ray)" transform="rotate({{ $ray * 30 }} 150 150)"/>
      @endfor
    </g>

    <g class="gift">
      {{-- Trophy, hidden inside the box until it rises --}}
      <g clip-path="url(#md-inside)">
        <g class="trophy">
          <path d="M108 92C80 92 76 128 112 140M192 92C220 92 224 128 188 140" fill="none" stroke="url(#md-gold)" stroke-width="9" stroke-linecap="round"/>
          <path d="M108 84h84v26c0 36-20 58-42 62-22-4-42-26-42-62Z" fill="url(#md-gold)"/>
          <g clip-path="url(#md-cup)"><rect class="shine" x="130" y="70" width="18" height="120" fill="#fff" fill-opacity=".55" transform="skewX(-18)"/></g>
          <rect x="140" y="170" width="20" height="30" fill="url(#md-gold)"/>
          <path d="M118 200h64l8 22h-80Z" fill="url(#md-gold)"/>
          <rect x="104" y="222" width="92" height="18" rx="4" fill="#78350f"/>
          <text x="150" y="132" text-anchor="middle" font-family="Be Vietnam Pro, sans-serif" font-weight="800" font-size="22" fill="#7c2d12">{{ __('occasions.mens_day.trophy') }}</text>
        </g>
      </g>

      {{-- Box --}}
      <rect x="64" y="252" width="172" height="146" rx="10" fill="url(#md-box)"/>
      <rect x="64" y="252" width="172" height="18" fill="#000" fill-opacity=".2"/>
      <rect x="138" y="252" width="24" height="146" fill="url(#md-gold)"/>
      <path d="M84 312l6 6 10-12M200 352l6 6 10-12" stroke="#93c5fd" stroke-opacity=".5" stroke-width="2.5" fill="none" stroke-linecap="round"/>

      {{-- Lid and bow (fly off on open) --}}
      <g class="lid">
        <rect x="52" y="222" width="196" height="38" rx="9" fill="url(#md-lid)"/>
        <rect x="138" y="222" width="24" height="38" fill="url(#md-gold)"/>
        <path d="M150 222C122 186 96 200 108 214 116 222 136 222 150 222Z" fill="url(#md-gold)"/>
        <path d="M150 222C178 186 204 200 192 214 184 222 164 222 150 222Z" fill="url(#md-gold)"/>
        <circle cx="150" cy="220" r="9" fill="#fde68a" stroke="#b45309" stroke-width="2"/>
      </g>
    </g>

    @foreach ($sparkles as [$x, $y, $delay])
      <g transform="translate({{ $x }} {{ $y }})"><path class="spark" style="--d:{{ $delay }}s" d="M0-7L1.6-1.6 7 0 1.6 1.6 0 7-1.6 1.6-7 0-1.6-1.6Z"/></g>
    @endforeach
  </svg>
</x-occasion.page>
