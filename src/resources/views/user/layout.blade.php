<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'DrinkFlow')</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @vite(['resources/css/room.css', 'resources/js/global.js'])
    @stack('head')
    <x-devtools-guard />
</head>

<body class="user-shell">
    @yield('content')
    @stack('scripts')
</body>

</html>
