{{-- Lunar New Year (Tết): a red envelope (lì xì) whose flap opens on click and lets a lucky card slide out, under two
     swinging lanterns while apricot blossoms fall. --}}
@php
    $sparkles = [[44, 160, 0], [258, 140, 0.8], [240, 330, 1.5], [56, 350, 2.1], [150, 54, 1.2]];
@endphp
<x-occasion.page :room="$room" :occasion="$occasion" :year="$year" :wishes="$wishes" :audio="$audio"
  theme="--accent:#ef4444;--accent-2:#fcd34d;--soft:#fde68a;--line:rgba(253,224,71,.26);--card-1:rgba(122,12,18,.93);--card-2:rgba(70,6,10,.92);--hint-1:rgba(150,16,24,.9);--hint-2:rgba(150,100,10,.85);--bokeh:255,214,120;--bg:linear-gradient(160deg,#4a0508,#8b0e14 50%,#3a0306);--glow-1:rgba(252,211,77,.22);--glow-2:rgba(251,146,60,.22);--glow-3:rgba(239,68,68,.25);"
  :effects="['sounds' => [['paper', 0], ['whoosh', 300], ['coin', 650], ['chime', 900]], 'rain' => ['blossom', 'blossom', 'blossom', 'petal'], 'rainColors' => ['#facc15', '#fde047', '#fbbf24', '#fda4af'], 'rainEvery' => 330, 'burst' => ['coin', 'blossom', 'confetti'], 'burstColors' => ['#facc15', '#fde047', '#f59e0b', '#ef4444', '#ffffff']]">
  <x-slot:styles>
    <style>
      .envelope{transform-box:view-box;transform-origin:150px 380px;animation:tet-bob 3s ease-in-out infinite}
      .flap-closed{transform-box:view-box;transform-origin:150px 122px;transition:transform .3s ease-in}
      .is-open .flap-closed{transform:scaleY(0)}
      .flap-open{transform-box:view-box;transform-origin:150px 122px;transform:scaleY(0);transition:transform .35s .3s ease-out}
      .is-open .flap-open{transform:scaleY(1)}
      .card{transform-box:view-box;transition:transform 1s .6s cubic-bezier(.2,.9,.3,1.2)}
      .is-open .card{transform:translateY(-150px)}
      .lanterns{position:fixed;inset:0 0 auto 0;height:0;z-index:2;pointer-events:none}
      .lantern{position:absolute;top:0;width:clamp(56px,9vw,96px);transform-origin:50% 0;animation:tet-swing 3.6s ease-in-out infinite alternate}
      /* The left lantern hangs past the "back to room" button (top left). */
      .lantern.left{left:max(184px,8vw)}
      .lantern.right{right:clamp(8px,6vw,90px);animation-delay:-1.8s}
      @keyframes tet-bob{50%{transform:translateY(-6px) rotate(-1deg)}}
      @keyframes tet-swing{from{transform:rotate(-6deg)}to{transform:rotate(6deg)}}
      @media (max-width:900px){.lantern{width:48px}}
      /* Phones: only the right lantern, left of the sound button (top right). */
      @media (max-width:640px){.lantern.left{display:none}.lantern.right{right:68px}}
    </style>
  </x-slot:styles>

  <x-slot:extra>
    <div class="lanterns" aria-hidden="true">
      @foreach (['left', 'right'] as $side)
        <svg class="lantern {{ $side }}" viewBox="0 0 80 200" xmlns="http://www.w3.org/2000/svg">
          <line x1="40" y1="0" x2="40" y2="44" stroke="#fcd34d" stroke-width="2"/>
          <rect x="28" y="42" width="24" height="10" rx="3" fill="#f59e0b"/>
          <ellipse cx="40" cy="96" rx="36" ry="46" fill="#dc2626"/>
          <path d="M40 50C22 70 22 122 40 142M40 50C58 70 58 122 40 142M14 70C8 88 8 104 14 122M66 70C72 88 72 104 66 122" stroke="#991b1b" stroke-width="2" fill="none"/>
          <ellipse cx="28" cy="80" rx="8" ry="14" fill="#fff" fill-opacity=".15"/>
          <rect x="28" y="140" width="24" height="10" rx="3" fill="#f59e0b"/>
          <path d="M34 150v34M40 150v40M46 150v34" stroke="#facc15" stroke-width="2.5" stroke-linecap="round"/>
        </svg>
      @endforeach
    </div>
  </x-slot:extra>

  <svg viewBox="0 0 300 440" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
    <defs>
      <linearGradient id="tet-red" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#ef4444"/><stop offset="1" stop-color="#991b1b"/></linearGradient>
      <linearGradient id="tet-flap" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#b91c1c"/><stop offset="1" stop-color="#dc2626"/></linearGradient>
      <linearGradient id="tet-gold" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#fde68a"/><stop offset=".5" stop-color="#facc15"/><stop offset="1" stop-color="#ca8a04"/></linearGradient>
      <linearGradient id="tet-card" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fffbeb"/><stop offset="1" stop-color="#fde68a"/></linearGradient>
    </defs>

    <g class="envelope">
      {{-- Back of the envelope and its open flap (behind the card) --}}
      <rect x="60" y="122" width="180" height="258" rx="14" fill="#7f1d1d"/>
      <path class="flap-open" d="M60 122L150 40 240 122Z" fill="url(#tet-flap)" stroke="url(#tet-gold)" stroke-width="3" stroke-linejoin="round"/>

      {{-- Lucky card that slides out --}}
      <g class="card">
        <rect x="76" y="132" width="148" height="200" rx="10" fill="url(#tet-card)" stroke="#ca8a04" stroke-width="2"/>
        <rect x="86" y="142" width="128" height="180" rx="6" fill="none" stroke="#dc2626" stroke-width="1.5" stroke-dasharray="4 4"/>
        <text x="150" y="214" text-anchor="middle" font-family="Dancing Script, cursive" font-weight="700" font-size="46" fill="#b91c1c">{{ __('occasions.lunar_new_year.card') }}</text>
        <text x="150" y="248" text-anchor="middle" font-family="Be Vietnam Pro, sans-serif" font-weight="700" font-size="18" fill="#a16207" letter-spacing="3">{{ $year }}</text>
      </g>

      {{-- Front pocket --}}
      <path d="M60 176L150 236 240 176V366Q240 380 226 380H74Q60 380 60 366Z" fill="url(#tet-red)"/>
      <path d="M72 362h156" stroke="url(#tet-gold)" stroke-width="2"/>
      {{-- Gold apricot blossom medallion --}}
      <g transform="translate(150 300)">
        <circle r="38" fill="none" stroke="url(#tet-gold)" stroke-width="3"/>
        @for ($petal = 0; $petal < 5; $petal++)
          <ellipse cx="0" cy="-14" rx="9" ry="14" fill="url(#tet-gold)" transform="rotate({{ $petal * 72 }})"/>
        @endfor
        <circle r="7" fill="#b91c1c"/>
      </g>

      {{-- Closed flap (folds away on open) --}}
      <path class="flap-closed" d="M60 136Q60 122 74 122H226Q240 122 240 136V176L150 236 60 176Z" fill="url(#tet-flap)" stroke="url(#tet-gold)" stroke-width="3" stroke-linejoin="round"/>
      <circle class="flap-closed" cx="150" cy="214" r="10" fill="url(#tet-gold)"/>
    </g>

    @foreach ($sparkles as [$x, $y, $delay])
      <g transform="translate({{ $x }} {{ $y }})"><path class="spark" style="--d:{{ $delay }}s" d="M0-7L1.6-1.6 7 0 1.6 1.6 0 7-1.6 1.6-7 0-1.6-1.6Z"/></g>
    @endforeach
  </svg>
</x-occasion.page>
