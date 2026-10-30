@props(['title' => null, 'backUrl' => null, 'backLabel' => null])
{{--
  Stand-alone, mobile-first layout of the public game room pages (/play/{token}): no site header, footer or room
  navigation, just a compact top bar (brand + language) so the scoreboard and the record button fill the screen.
--}}
@php
  $locale = app()->getLocale();
  $currentPath = '/'.ltrim(request()->path(), '/');
@endphp
<!DOCTYPE html>
<html class="light" lang="{{ str_replace('_', '-', $locale) }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <meta name="robots" content="noindex, nofollow">
  <meta name="theme-color" content="#006948">
  <title>{{ $title ? $title.' - DrinkFlow' : 'DrinkFlow' }}</title>
  <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
  <link rel="alternate icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=block" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com?plugins=forms"></script>
  <script src="https://cdn.jsdelivr.net/npm/qrcode@1.4.4/build/qrcode.min.js"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { sans: ["Inter", "-apple-system", "BlinkMacSystemFont", "Segoe UI", "Roboto", "sans-serif"] },
          boxShadow: { "xs": "0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px -1px rgba(0, 0, 0, 0.05)" },
        },
      },
    }
  </script>
  @if (file_exists(public_path('build/manifest.json')) || app()->isLocal())
    @vite(['resources/js/game-play.js'])
  @endif
</head>
<body data-submit-loading-text="{{ __('global.common.loading') }}" class="min-h-screen bg-[#F8FAFC] font-sans text-slate-800 antialiased selection:bg-emerald-100">
  @php $locales = \App\Constants\AppLocale::SUPPORTED; @endphp
  <div class="mx-auto flex max-w-3xl items-center justify-between gap-2 px-4 pt-3 pb-1">
    <div class="flex min-w-0 items-center gap-2">
      @if($backUrl)
        <a href="{{ $backUrl }}" data-play-back class="inline-flex h-9 shrink-0 items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-700 hover:bg-slate-50" aria-label="{{ $backLabel ?? __('games.play.back_label') }}" title="{{ $backLabel ?? __('games.play.back_label') }}">
          <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>{{ __('games.play.back') }}
        </a>
      @endif
      <span class="inline-flex items-center gap-1.5 text-sm font-bold text-[#006948]">
        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">local_cafe</span>DrinkFlow
      </span>
    </div>
    {{-- Language dropdown --}}
    <details class="relative shrink-0" data-play-language>
      <summary class="flex h-9 cursor-pointer list-none items-center gap-1 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-semibold text-slate-700" aria-label="{{ __('games.play.language') }}">
        <span aria-hidden="true">{{ $locales[$locale]['flag'] ?? '' }}</span>{{ $locales[$locale]['code'] ?? strtoupper($locale) }}
        <span class="material-symbols-outlined text-[16px]" aria-hidden="true">expand_more</span>
      </summary>
      <ul class="absolute right-0 z-40 mt-1 w-40 rounded-xl border border-slate-200 bg-white p-1 shadow-lg">
        @foreach($locales as $code => $meta)
          <li>
            <a href="{{ route('locale.switch', ['locale' => $code, 'to' => $currentPath]) }}" hreflang="{{ $code }}" lang="{{ $code }}"
               @if($code === $locale) aria-current="true" @endif
               class="flex h-10 items-center gap-2 rounded-lg px-3 text-sm {{ $code === $locale ? 'bg-emerald-50 font-semibold text-[#006948]' : 'text-slate-700 hover:bg-slate-50' }}">
              <span aria-hidden="true">{{ $meta['flag'] }}</span>{{ $meta['name'] }}
            </a>
          </li>
        @endforeach
      </ul>
    </details>
  </div>

  {{ $slot }}
</body>
</html>
