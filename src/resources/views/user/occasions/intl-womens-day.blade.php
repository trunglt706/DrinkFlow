{{-- International Women's Day (8/3): a gift box that, once clicked, throws its lid off and lets a tulip bouquet rise. --}}
@php
    // Tulips: [x, y of the flower head, tilt in degrees, petal colour, light colour, rise delay in seconds].
    $tulips = [
        [104, 150, -14, '#c084fc', '#f0d9ff', 0.55],
        [196, 150, 14, '#fb7185', '#ffd1da', 0.65],
        [150, 118, 0, '#f472b6', '#ffd6ec', 0.45],
    ];
    $sparkles = [[48, 230, 0], [252, 210, 0.8], [70, 120, 1.6], [236, 96, 0.4], [150, 40, 1.2]];
@endphp
<x-occasion.page :room="$room" :occasion="$occasion" :year="$year" :wishes="$wishes" :audio="$audio" :wall="$wall" :back-url="$backUrl ?? null" :preview="$preview ?? false"
  theme="--accent:#f472b6;--accent-2:#fcd34d;--soft:#f5d0fe;--line:rgba(245,208,254,.24);--card-1:rgba(84,26,96,.92);--card-2:rgba(42,12,56,.9);--hint-1:rgba(120,30,110,.85);--hint-2:rgba(80,40,120,.8);--bokeh:245,200,255;--bg:linear-gradient(160deg,#2a0f3d,#5b1a5e 48%,#1c0b2c);--glow-1:rgba(244,114,182,.28);--glow-2:rgba(252,211,77,.18);--glow-3:rgba(168,85,247,.3);"
  :effects="['sounds' => [['whoosh', 0], ['chime', 350], ['bloom', 550]], 'rain' => ['petal', 'petal', 'heart'], 'rainColors' => ['#f472b6', '#c084fc', '#fb7185', '#f5d0fe'], 'burst' => ['petal', 'heart', 'confetti'], 'burstColors' => ['#f472b6', '#c084fc', '#fb7185', '#fcd34d', '#ffffff']]">
  <x-slot:styles>
    <style>
      .gift{transform-box:view-box;transform-origin:150px 400px;animation:iw-wiggle 2.6s ease-in-out infinite}
      .is-open .gift{animation:none}
      .lid{transform-box:view-box;transform-origin:150px 240px;transition:transform .9s cubic-bezier(.3,1.4,.5,1),opacity .6s .5s}
      .is-open .lid{transform:translate(70px,-210px) rotate(28deg);opacity:0}
      .tulip{transform-box:view-box;transform:translateY(190px) scale(.7);transform-origin:150px 300px}
      .is-open .tulip{animation:iw-rise 1.1s cubic-bezier(.2,.9,.3,1.25) forwards;animation-delay:var(--d)}
      .glow{opacity:0;transform-box:fill-box;transform-origin:50% 50%}
      .is-open .glow{animation:iw-glow 1.4s .3s ease-out forwards}
      @keyframes iw-wiggle{0%,70%,100%{transform:rotate(0)}75%{transform:rotate(-3deg)}80%{transform:rotate(3deg)}85%{transform:rotate(-2deg)}90%{transform:rotate(2deg)}}
      @keyframes iw-rise{to{transform:none}}
      @keyframes iw-glow{0%{opacity:0;transform:scale(.4)}40%{opacity:1;transform:scale(1.2)}100%{opacity:.7;transform:scale(1)}}
    </style>
  </x-slot:styles>

  <svg viewBox="0 0 300 440" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
    <defs>
      <radialGradient id="iw-glow"><stop offset="0" stop-color="#fff3c4" stop-opacity=".9"/><stop offset=".5" stop-color="#f9a8d4" stop-opacity=".35"/><stop offset="1" stop-color="#f472b6" stop-opacity="0"/></radialGradient>
      <linearGradient id="iw-box" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#f472b6"/><stop offset="1" stop-color="#9d174d"/></linearGradient>
      <linearGradient id="iw-lid" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#f9a8d4"/><stop offset="1" stop-color="#db2777"/></linearGradient>
      <linearGradient id="iw-ribbon" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#f59e0b"/><stop offset=".5" stop-color="#fde68a"/><stop offset="1" stop-color="#d97706"/></linearGradient>
      <linearGradient id="iw-stem" x1="0" y1="0" x2="1" y2="0"><stop offset="0" stop-color="#2f6b3a"/><stop offset="1" stop-color="#5aa35a"/></linearGradient>
      <clipPath id="iw-inside"><rect x="0" y="-200" width="300" height="595"/></clipPath>
    </defs>

    <circle class="glow" cx="150" cy="200" r="150" fill="url(#iw-glow)"/>

    <g class="gift">
      {{-- Bouquet, hidden inside the box until it rises --}}
      <g clip-path="url(#iw-inside)">
        @foreach ($tulips as [$x, $y, $tilt, $color, $light, $delay])
          <g class="tulip" style="--d:{{ $delay }}s">
            <g transform="translate({{ $x }} {{ $y }}) rotate({{ $tilt }})">
              <path d="M0 0C-2 50 4 100 0 170" fill="none" stroke="url(#iw-stem)" stroke-width="6" stroke-linecap="round"/>
              <path d="M2 120C-26 100-40 70-36 40-20 62-8 86 2 104Z" fill="#4c9a50"/>
              <path d="M-22 0C-25-26-15-44-8-50L0-36 8-50C15-44 25-26 22 0 15 12-15 12-22 0Z" fill="{{ $color }}"/>
              <path d="M-10 4C-15-18-8-40 0-52 8-40 15-18 10 4 6 9-6 9-10 4Z" fill="{{ $light }}" fill-opacity=".55"/>
              <path d="M-22 0C-15 12 15 12 22 0" fill="none" stroke="#000" stroke-opacity=".12" stroke-width="2"/>
            </g>
          </g>
        @endforeach
      </g>

      {{-- Box --}}
      <rect x="64" y="252" width="172" height="146" rx="10" fill="url(#iw-box)"/>
      <rect x="64" y="252" width="172" height="18" fill="#000" fill-opacity=".12"/>
      <rect x="138" y="252" width="24" height="146" fill="url(#iw-ribbon)"/>
      <path d="M80 300h12M86 294v12M206 340h12M212 334v12M96 360h8M100 356v8" stroke="#fff" stroke-opacity=".5" stroke-width="2" stroke-linecap="round"/>

      {{-- Lid and bow (fly off on open) --}}
      <g class="lid">
        <rect x="52" y="222" width="196" height="38" rx="9" fill="url(#iw-lid)"/>
        <rect x="138" y="222" width="24" height="38" fill="url(#iw-ribbon)"/>
        <path d="M150 222C120 180 92 196 104 212 112 222 136 222 150 222Z" fill="url(#iw-ribbon)"/>
        <path d="M150 222C180 180 208 196 196 212 188 222 164 222 150 222Z" fill="url(#iw-ribbon)"/>
        <path d="M150 222C142 236 132 250 126 256M150 222C158 236 168 250 174 256" stroke="#d97706" stroke-width="5" stroke-linecap="round" fill="none"/>
        <circle cx="150" cy="220" r="9" fill="#fde68a" stroke="#d97706" stroke-width="2"/>
      </g>
    </g>

    @foreach ($sparkles as [$x, $y, $delay])
      <g transform="translate({{ $x }} {{ $y }})"><path class="spark" style="--d:{{ $delay }}s" d="M0-7L1.6-1.6 7 0 1.6 1.6 0 7-1.6 1.6-7 0-1.6-1.6Z"/></g>
    @endforeach
  </svg>
</x-occasion.page>
