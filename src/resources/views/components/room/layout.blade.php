@props([
    'title' => 'DrinkFlow - ' . __('room.dashboard.page_title'),
    'room' => null,
    'roomUser' => null,
    'user' => null,
    'activeTab' => 'overview',
    'unreadNotificationsCount' => 0,
    'userRooms' => collect(),
    'description' => null,
    'ogImage' => null,
])

<!DOCTYPE html>
<html class="light" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>{{ $title }}</title>
  @if ($ogImage)
  <meta name="robots" content="noindex, nofollow">
  @if ($description)
  <meta name="description" content="{{ $description }}">
  @endif
  <meta property="og:site_name" content="DrinkFlow">
  <meta property="og:type" content="website">
  <meta property="og:url" content="{{ url()->current() }}">
  <meta property="og:title" content="{{ $title }}">
  @if ($description)
  <meta property="og:description" content="{{ $description }}">
  @endif
  <meta property="og:image" content="{{ $ogImage }}">
  <meta property="og:image:width" content="1200">
  <meta property="og:image:height" content="630">
  <meta property="og:image:alt" content="{{ $title }}">
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="{{ $title }}">
  <meta name="twitter:image" content="{{ $ogImage }}">
  @endif
  <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
  <link rel="alternate icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
  <link href="https://fonts.googleapis.com" rel="preconnect">
  <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=block" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
  <script id="tailwind-config">
    tailwind.config = {
      darkMode: "class",
      theme: {
        extend: {
          colors: {
            "primary": "#006948",
            "primary-dark": "#005137",
            "primary-light": "#059669",
            "surface": "#F8FAFC",
            "card": "#FFFFFF",
          },
          fontFamily: {
            sans: ["Inter", "-apple-system", "BlinkMacSystemFont", "Segoe UI", "Roboto", "sans-serif"]
          },
          boxShadow: {
            "2xs": "0 1px 2px 0 rgba(0, 0, 0, 0.03)",
            "xs": "0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px -1px rgba(0, 0, 0, 0.05)",
          }
        },
      },
    }
  </script>
  <script src="{{ rtrim(config('services.realtime.public_url', 'http://localhost:3001'), '/') }}/socket.io/socket.io.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/qrcode@1.4.4/build/qrcode.min.js"></script>
  @if (file_exists(public_path('build/manifest.json')) || app()->isLocal())
    @vite(['resources/css/room.css', 'resources/js/room.js'])
  @endif
  <!-- Alpine.js Plugins & Core -->
  <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/intersect@3.14.8/dist/cdn.min.js"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
    <x-devtools-guard />
</head>
<body data-submit-loading-text="{{ __('global.common.loading') }}" class="bg-[#F8FAFC] text-slate-800 antialiased min-h-screen flex flex-col justify-between selection:bg-emerald-100 selection:text-emerald-900"
      @if (app()->isLocal()) data-socket-debug @endif
      data-notifications-url="{{ $room ? route('user.rooms.notifications', $room->slug) : route('user.me.notifications') }}"
      data-room-socket-token-url="{{ $room ? route('user.socket-token', $room) : '' }}"
      data-realtime-url="{{ config('services.realtime.public_url', 'http://localhost:3001') }}">

  <!-- Shared Room Header -->
  <x-room.header
    :room="$room"
    :room-user="$roomUser"
    :user="$user"
    :active-tab="$activeTab"
    :unread-notifications-count="$unreadNotificationsCount"
    :notifications="$notifications ?? null"
    :user-rooms="$userRooms"
  />

  @if ($room)
    <!-- Birthday banner: members whose birthday is within 3 days -->
    <x-room.birthday-banner :room="$room" :viewer-code="$roomUser?->user_code" />
  @endif

  <!-- Main Slot Content -->
  <main class="w-full max-w-7xl mx-auto px-4 sm:px-6 py-6 flex-1 space-y-6">
    {{ $slot }}
  </main>

  <!-- Shared Room Footer -->
  <x-room.footer :room="$room" />

  <!-- Shared Global Logout Modal -->
  <x-global.logout-modal />

  <!-- Shared Global Page Loading & Submit Overlay -->
  <x-global.loading />

  <!-- Shared Global Go To Top Floating Button -->
  <x-global.go-to-top :mobile-actions-in-footer="true" />

  {{ $scripts ?? '' }}
</body>
</html>
