{{-- Vietnamese Women's Day (20/10): a rose bud on its branch blooms on click. --}}
@php
    // Bloomed rose head: rings of petals drawn from the outside in, so the inner (darker) ones sit on top. Each ring:
    // [petal count, petal length, rotation offset in degrees, gradient id, unfurl delay of the ring in seconds].
    $rings = [
        [5, 96, 0, 'wd-petal-1', 0.75],
        [5, 80, 36, 'wd-petal-2', 0.6],
        [5, 62, 12, 'wd-petal-3', 0.45],
        [4, 45, 55, 'wd-petal-4', 0.3],
        [3, 30, 20, 'wd-petal-5', 0.15],
    ];
    $petalPath = static fn (float $l): string => sprintf('M0 0C%1$.1f %2$.1f %3$.1f %4$.1f 0 %5$.1fC%6$.1f %4$.1f %7$.1f %2$.1f 0 0Z',
        -0.62 * $l, -0.2 * $l, -0.58 * $l, -0.98 * $l, -$l, 0.58 * $l, 0.62 * $l);
    $sparkles = [[70, 120, 0], [232, 96, 0.7], [214, 236, 1.4], [84, 262, 2.1], [150, 52, 1.1], [258, 176, 1.8], [44, 190, 0.4]];
@endphp
<x-occasion.page :room="$room" :occasion="$occasion" :year="$year" :wishes="$wishes" :audio="$audio"
  :effects="['sounds' => [['bloom', 0], ['chime', 700]], 'rain' => ['petal'], 'rainColors' => ['#ff4f7b', '#ff7d9c', '#ffb3c6', '#e0315b'], 'burst' => ['petal', 'heart'], 'burstColors' => ['#ff4f7b', '#ff7d9c', '#ffb3c6', '#e0315b', '#f7c873', '#ffffff']]">
  <x-slot:styles>
    <style>
      .branch{transform-box:view-box;transform-origin:150px 612px;animation:wd-sway 4.5s ease-in-out infinite alternate}
      .halo{transform-box:fill-box;transform-origin:50% 50%;animation:wd-halo 3s ease-in-out infinite}
      .is-open .halo{animation:wd-halo-burst 1.2s ease-out both,wd-halo 4s 1.2s ease-in-out infinite}
      .bud{transform-box:view-box;transform-origin:150px 208px;transition:transform .55s cubic-bezier(.6,-.3,.7,1),opacity .45s .1s}
      .stage-btn:hover .bud{transform:scale(1.04)}
      .is-open .bud{transform:scale(.4) translateY(30px);opacity:0}
      .petal,.sepal{transform-box:view-box;transform-origin:0 0;transform:scale(0);opacity:0}
      .is-open .petal{animation:wd-unfurl 1.1s cubic-bezier(.2,.8,.25,1.2) forwards;animation-delay:var(--d)}
      .is-open .sepal{animation:wd-unfurl .8s ease-out forwards;animation-delay:var(--d)}
      .breathe{transform-box:view-box;transform-origin:0 0}
      .is-open .breathe{animation:wd-breathe 5s 2.4s ease-in-out infinite}
      .is-open .spark{fill:#fff}
      @keyframes wd-sway{from{transform:rotate(-1.6deg)}to{transform:rotate(1.6deg)}}
      @keyframes wd-halo{50%{opacity:.55;transform:scale(1.08)}}
      @keyframes wd-halo-burst{0%{transform:scale(.6);opacity:.2}35%{transform:scale(1.5);opacity:1}100%{transform:scale(1);opacity:.85}}
      @keyframes wd-unfurl{0%{transform:scale(.05) rotate(-40deg);opacity:0}45%{opacity:1}100%{transform:scale(1) rotate(0);opacity:1}}
      @keyframes wd-breathe{50%{transform:scale(1.035) rotate(-1deg)}}
    </style>
  </x-slot:styles>

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
      @foreach (['translate(146 428) rotate(-150) scale(1 -1)', 'translate(150 338) rotate(-28)', 'translate(148 520) rotate(-158) scale(.7 -.7)'] as $leaf)
        <g transform="{{ $leaf }}">
          <path d="M0 0C18-22 58-26 88-4 58 18 20 16 0 0Z" fill="url(#wd-leaf)"/>
          <path d="M3 0C30-6 58-7 86-4" fill="none" stroke="#1f4f2b" stroke-width="1.4" stroke-opacity=".6"/>
        </g>
      @endforeach

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

    @foreach ($sparkles as [$x, $y, $delay])
      <g transform="translate({{ $x }} {{ $y }})"><path class="spark" style="--d:{{ $delay }}s" d="M0-7L1.6-1.6 7 0 1.6 1.6 0 7-1.6 1.6-7 0-1.6-1.6Z"/></g>
    @endforeach
  </svg>
</x-occasion.page>
