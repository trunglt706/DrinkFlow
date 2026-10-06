@props([
    'title' => null,
    'active' => 'dashboard',
    'room' => null,
    'assignedRooms' => null,
    'breadcrumb' => null,
])

@php
    $roomLabel = $room?->name ?? 'DrinkFlow';
    $pageTitle = $title ?: __('admin.dashboard');
    $adminUser = $adminUser ?? auth('admin')->user();
    $adminInitials = mb_strtoupper(mb_substr($adminUser?->name ?? 'AD', 0, 2));
    $adminAvatarUrl = $adminUser?->avatar_url ? route('admin.profile.avatar.show') : null;
    $hasLiveCampaign = $room instanceof \App\Models\Room ? $room->hasActiveCampaign() : false;
@endphp
<!doctype html>
<html lang="{{ app()->getLocale() }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="room-slug" content="{{ $room?->slug }}">
    <title>{{ $pageTitle }} · {{ $roomLabel }} · DrinkFlow Admin</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:ital,wght@0,100..800;1,100..800&display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=block"
        rel="stylesheet">

    <!-- Anti-flicker Sidebar State Initialization -->
    <script>
        (function() {
            try {
                if (localStorage.getItem('df_admin_sidebar_collapsed') === 'true' && window.innerWidth >= 1024) {
                    document.documentElement.classList.add('sidebar-collapsed');
                }
            } catch (e) {}
        })();
    </script>

    {{-- Socket.IO client (window.io) for admin realtime updates; must load before admin.js runs. --}}
    <script src="{{ rtrim(config('services.realtime.public_url', 'http://localhost:3001'), '/') }}/socket.io/socket.io.js"></script>
    @vite(['resources/css/admin.css', 'resources/js/admin.js'])
    <!-- Alpine.js Plugins & Core -->
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/intersect@3.14.8/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.8/dist/cdn.min.js"></script>
</head>

<body data-submit-loading-text="{{ __('global.common.loading') }}" data-room-slug="{{ $room?->slug }}" @if (app()->isLocal()) data-socket-debug @endif
    data-processing-text="{{ __('admin.processing') }}"
    {{-- Room admins reload into the maintenance page when it starts. --}}
    data-maintenance-realtime-url="{{ rtrim(config('services.realtime.public_url', 'http://localhost:3001'), '/') }}"
    {{-- Room admins get an authenticated socket on every page for their private live notifications. --}}
    @if ($room)
        data-room-id="{{ $room->id }}"
        data-admin-socket-token-url="{{ route('admin.socket-token', $room) }}"
        data-admin-realtime-i18n="{{ json_encode([
            'deadline_reminder_title' => __('admin.audit_event_campaign_deadline_reminder'),
            'deadline_reminder_body' => __('admin.campaign_deadline_reminder_body'),
            'just_now' => __('admin.just_now'),
        ], JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) }}"
    @endif
    class="admin-shell bg-surface text-on-surface font-sans min-h-screen flex antialiased selection:bg-emerald-100 selection:text-emerald-900">
    <!-- ================= LEFT SIDEBAR ================= -->
    <aside id="admin-sidebar"
        class="fixed top-0 left-0 h-screen w-64 flex flex-col z-30 bg-surface-container-lowest border-r border-outline-variant -translate-x-full lg:translate-x-0">
        <div class="w-full h-full p-3.5 flex flex-col justify-between overflow-y-auto overflow-x-hidden">
            <div>
                <!-- Brand Logo & Header -->
                <div class="flex items-center gap-2 mb-5 px-1 justify-between">
                    <a href="{{ route('admin.landing') }}"
                        class="flex items-center gap-2.5 no-underline group hover:opacity-90 transition-opacity min-w-0"
                        title="{{ __('admin.brand_title') }}">
                        <span class="w-8 h-8 rounded-lg flex items-center justify-center text-white shadow-xs shrink-0"
                            style="background: linear-gradient(135deg, #006948 0%, #047857 100%); background-color: #006948; border: 1px solid #005137; box-shadow: 0 1px 2px rgba(0,0,0,0.08);">
                            <svg class="w-4.5 h-4.5 text-white fill-current" viewBox="0 0 24 24" aria-hidden="true"
                                style="width: 18px; height: 18px; fill: #ffffff; color: #ffffff;">
                                <path
                                    d="M20 3H4v10c0 2.21 1.79 4 4 4h6c2.21 0 4-1.79 4-4v-3h2c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 5h-2V5h2v3zM4 19h16v2H4z" />
                            </svg>
                        </span>
                        <div class="sidebar-text min-w-0">
                            <span
                                class="text-base font-bold text-on-surface tracking-tight block leading-tight truncate">{{ __('admin.brand_title') }}</span>
                            <span
                                class="text-[11px] font-mono text-outline block truncate">{{ __('admin.brand_subtitle') }}</span>
                        </div>
                    </a>
                    <button type="button"
                        class="lg:hidden w-8 h-8 flex items-center justify-center rounded text-outline hover:text-on-surface hover:bg-surface-container-low transition-colors"
                        onclick="document.querySelector('#admin-sidebar')?.classList.toggle('-translate-x-full')">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>

                <!-- Active Workspace Dropdown Trigger (chỉ hiện khi admin quản lý nhiều hơn 1 room) -->
                @if ($assignedRoomsList->count() > 1)
                <div class="mb-5 relative" data-admin-workspace-switcher>
                    <label
                        class="text-[10px] font-mono text-outline block mb-1 uppercase tracking-wider font-semibold sidebar-text">{{ __('admin.active_workspace') }}</label>
                    <button type="button" data-workspace-toggle aria-expanded="false" aria-controls="workspace-menu"
                        class="sidebar-nav-link relative group w-full flex items-center justify-between px-3 py-2 bg-surface border border-outline-variant rounded-xl hover:border-outline text-left transition-colors cursor-pointer"
                        title="{{ $room ? $roomLabel : __('admin.select_room_title') }}">
                        <div class="flex items-center gap-2 truncate">
                            <span
                                class="w-2.5 h-2.5 rounded-full {{ $room ? 'bg-primary' : 'bg-outline' }} inline-block shrink-0"></span>
                            <span
                                class="text-xs font-semibold text-on-surface truncate sidebar-text">{{ $room ? $roomLabel : __('admin.select_room_title') }}</span>
                        </div>
                        <span data-workspace-chevron
                            class="material-symbols-outlined text-[16px] text-outline transition-transform duration-200 sidebar-text">expand_more</span>
                        <div
                            class="sidebar-tooltip pointer-events-none absolute left-full top-1/2 -translate-y-1/2 ml-3 px-2.5 py-1.5 bg-[#0b1c30] text-white text-xs font-semibold rounded-lg shadow-xl whitespace-nowrap z-50 opacity-0 group-hover:opacity-100 transition-opacity hidden">
                            {{ $room ? $roomLabel : __('admin.select_room_title') }}
                        </div>
                    </button>

                    <div id="workspace-menu" data-workspace-menu
                        class="hidden absolute left-0 right-0 top-full mt-1 bg-surface-container-lowest border border-outline-variant rounded-lg shadow-lg z-50 py-1 max-h-56 overflow-y-auto min-w-[200px]">
                        @if ($assignedRoomsList->isNotEmpty())
                            <div
                                class="px-2.5 py-1 text-[10px] font-mono uppercase text-outline tracking-wider font-semibold">
                                {{ __('admin.assigned_rooms') }}</div>
                            @foreach ($assignedRoomsList as $assigned)
                                <a href="{{ route('admin.dashboard.page', $assigned) }}"
                                    class="flex items-center justify-between px-3 py-2 text-xs hover:bg-surface-container-low transition-colors {{ $room && $assigned->id === $room->id ? 'font-bold text-primary bg-primary/5' : 'text-on-surface' }}">
                                    <span class="truncate">{{ $assigned->name }}</span>
                                    @if ($room && $assigned->id === $room->id)
                                        <span class="material-symbols-outlined text-[14px] text-primary">check</span>
                                    @endif
                                </a>
                            @endforeach
                            <div class="border-t border-outline-variant/60 my-1"></div>
                        @endif
                        <a href="{{ route('admin.landing') }}"
                            class="flex items-center gap-2 px-3 py-2 text-xs text-primary font-semibold hover:bg-surface-container-low transition-colors">
                            <span class="material-symbols-outlined text-[16px]">grid_view</span>
                            <span class="truncate">{{ __('admin.all_rooms') }}</span>
                        </a>
                    </div>
                </div>
                @endif

                <!-- Main Nav Links -->
                <nav class="space-y-1 text-xs">
                    @if ($room)
                        <!-- Dashboard -->
                        <a class="sidebar-nav-link relative group flex items-center gap-3 px-3 py-2 rounded-lg font-medium transition-colors {{ $active === 'dashboard' ? 'bg-emerald-50 text-primary ring-1 ring-emerald-100 font-semibold' : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container-low' }}"
                            href="{{ route('admin.dashboard.page', $room) }}" title="{{ __('admin.dashboard') }}">
                            <span class="material-symbols-outlined text-[18px] shrink-0">space_dashboard</span>
                            <span class="sidebar-text truncate">{{ __('admin.dashboard') }}</span>
                            <div
                                class="sidebar-tooltip pointer-events-none absolute left-full top-1/2 -translate-y-1/2 ml-3 px-2.5 py-1.5 bg-[#0b1c30] text-white text-xs font-semibold rounded-lg shadow-xl whitespace-nowrap z-50 opacity-0 group-hover:opacity-100 transition-opacity hidden">
                                {{ __('admin.dashboard') }}
                            </div>
                        </a>

                        <!-- Campaigns -->
                        <a class="sidebar-nav-link relative group flex items-center justify-between px-3 py-2 rounded-lg font-medium transition-colors {{ $active === 'campaigns' ? 'bg-emerald-50 text-primary ring-1 ring-emerald-100 font-semibold' : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container-low' }}"
                            href="{{ route('admin.campaigns.page', $room) }}" title="{{ __('admin.campaigns') }}">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="material-symbols-outlined text-[18px] shrink-0">campaign</span>
                                <span class="sidebar-text truncate">{{ __('admin.campaigns') }}</span>
                            </div>
                            <span id="nav-live-badge"
                                class="px-1.5 py-0.5 rounded-full text-[10px] bg-error-container text-on-error-container font-bold hidden sidebar-text">Live</span>
                            <div
                                class="sidebar-tooltip pointer-events-none absolute left-full top-1/2 -translate-y-1/2 ml-3 px-2.5 py-1.5 bg-[#0b1c30] text-white text-xs font-semibold rounded-lg shadow-xl whitespace-nowrap z-50 opacity-0 group-hover:opacity-100 transition-opacity hidden">
                                {{ __('admin.campaigns') }}
                            </div>
                        </a>

                        @if ($hasLiveCampaign)
                            <!-- Orders -->
                            <a class="sidebar-nav-link relative group flex items-center gap-3 px-3 py-2 rounded-lg font-medium transition-colors {{ $active === 'orders' ? 'bg-emerald-50 text-primary ring-1 ring-emerald-100 font-semibold' : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container-low' }}"
                                href="{{ route('admin.orders.page', $room) }}" title="{{ __('admin.orders') }}">
                                <span class="material-symbols-outlined text-[18px] shrink-0">local_shipping</span>
                                <span class="sidebar-text truncate">{{ __('admin.orders') }}</span>
                                @if (($realtimeOrderCount ?? 0) > 0)
                                    <span
                                        class="absolute top-1 right-1 min-w-5 px-1.5 py-0.5 rounded-full text-[10px] leading-none text-center font-mono bg-primary text-on-primary shadow-sm"
                                        aria-label="{{ __('admin.orders_count_badge', ['count' => $realtimeOrderCount]) }}">{{ $realtimeOrderCount }}</span>
                                @endif
                                <div
                                    class="sidebar-tooltip pointer-events-none absolute left-full top-1/2 -translate-y-1/2 ml-3 px-2.5 py-1.5 bg-[#0b1c30] text-white text-xs font-semibold rounded-lg shadow-xl whitespace-nowrap z-50 opacity-0 group-hover:opacity-100 transition-opacity hidden">
                                    {{ __('admin.orders') }}
                                </div>
                            </a>
                        @endif

                        <!-- Payments & Debt -->
                        <a class="sidebar-nav-link relative group flex items-center justify-between px-3 py-2 rounded-lg font-medium transition-colors {{ in_array($active, ['debts', 'payments']) ? 'bg-emerald-50 text-primary ring-1 ring-emerald-100 font-semibold' : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container-low' }}"
                            href="{{ route('admin.debts.page', $room) }}" title="{{ __('admin.debts') }}">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="material-symbols-outlined text-[18px] shrink-0">payments</span>
                                <span class="sidebar-text truncate">{{ __('admin.debts') }}</span>
                            </div>
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 sidebar-text"
                                title="{{ __('admin.needs_settlement') }}"></span>
                            <div
                                class="sidebar-tooltip pointer-events-none absolute left-full top-1/2 -translate-y-1/2 ml-3 px-2.5 py-1.5 bg-[#0b1c30] text-white text-xs font-semibold rounded-lg shadow-xl whitespace-nowrap z-50 opacity-0 group-hover:opacity-100 transition-opacity hidden">
                                {{ __('admin.debts') }}
                            </div>
                        </a>

                        <!-- Users -->
                        <a class="sidebar-nav-link relative group flex items-center justify-between gap-3 px-3 py-2 rounded-lg font-medium transition-colors {{ $active === 'users' ? 'bg-emerald-50 text-primary ring-1 ring-emerald-100 font-semibold' : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container-low' }}"
                            href="{{ route('admin.room-users.page', $room) }}" title="{{ __('admin.users') }}">
                            <span class="flex min-w-0 items-center gap-3">
                                <span class="material-symbols-outlined text-[18px] shrink-0">group</span>
                                <span class="sidebar-text truncate">{{ __('admin.users') }}</span>
                            </span>
                            @if(($blockedUsersCount ?? 0) > 0)
                                <span class="sidebar-text inline-flex min-w-[20px] items-center justify-center rounded-full bg-red-600 px-1.5 py-0.5 text-[10px] font-bold text-white" title="{{ __('admin.pending_approval_users', ['count' => $blockedUsersCount]) }}">{{ $blockedUsersCount }}</span>
                            @endif
                            <div
                                class="sidebar-tooltip pointer-events-none absolute left-full top-1/2 -translate-y-1/2 ml-3 px-2.5 py-1.5 bg-[#0b1c30] text-white text-xs font-semibold rounded-lg shadow-xl whitespace-nowrap z-50 opacity-0 group-hover:opacity-100 transition-opacity hidden">
                                {{ __('admin.users') }}
                            </div>
                        </a>

                        <!-- Reports -->
                        <a class="sidebar-nav-link relative group flex items-center gap-3 px-3 py-2 rounded-lg font-medium transition-colors {{ $active === 'reports' ? 'bg-emerald-50 text-primary ring-1 ring-emerald-100 font-semibold' : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container-low' }}"
                            href="{{ route('admin.reports.page', $room) }}" title="{{ __('admin.reports_audit') }}">
                            <span class="material-symbols-outlined text-[18px] shrink-0">assessment</span>
                            <span class="sidebar-text truncate">{{ __('admin.reports_audit') }}</span>
                            <div
                                class="sidebar-tooltip pointer-events-none absolute left-full top-1/2 -translate-y-1/2 ml-3 px-2.5 py-1.5 bg-[#0b1c30] text-white text-xs font-semibold rounded-lg shadow-xl whitespace-nowrap z-50 opacity-0 group-hover:opacity-100 transition-opacity hidden">
                                {{ __('admin.reports_audit') }}
                            </div>
                        </a>

                        <!-- Room Settings -->
                        <a class="sidebar-nav-link relative group flex items-center gap-3 px-3 py-2 rounded-lg font-medium transition-colors {{ in_array($active, ['settings', 'notifications']) ? 'bg-emerald-50 text-primary ring-1 ring-emerald-100 font-semibold' : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container-low' }}"
                            href="{{ route('admin.settings.page', $room) }}"
                            title="{{ __('admin.payments_settings') }}">
                            <span class="material-symbols-outlined text-[18px] shrink-0">settings</span>
                            <span class="sidebar-text truncate">{{ __('admin.payments_settings') }}</span>
                            <div
                                class="sidebar-tooltip pointer-events-none absolute left-full top-1/2 -translate-y-1/2 ml-3 px-2.5 py-1.5 bg-[#0b1c30] text-white text-xs font-semibold rounded-lg shadow-xl whitespace-nowrap z-50 opacity-0 group-hover:opacity-100 transition-opacity hidden">
                                {{ __('admin.payments_settings') }}
                            </div>
                        </a>

                        <!-- System Diagnostics -->
                        <a class="sidebar-nav-link relative group flex items-center gap-3 px-3 py-2 rounded-lg font-medium transition-colors {{ $active === 'audit' ? 'bg-emerald-50 text-primary ring-1 ring-emerald-100 font-semibold' : 'text-on-surface-variant hover:text-on-surface hover:bg-surface-container-low' }}"
                            href="{{ route('admin.audit.page', $room) }}"
                            title="{{ __('admin.system_diagnostics') }}">
                            <span class="material-symbols-outlined text-[18px] shrink-0">health_and_safety</span>
                            <span class="sidebar-text truncate">{{ __('admin.system_diagnostics') }}</span>
                            <div
                                class="sidebar-tooltip pointer-events-none absolute left-full top-1/2 -translate-y-1/2 ml-3 px-2.5 py-1.5 bg-[#0b1c30] text-white text-xs font-semibold rounded-lg shadow-xl whitespace-nowrap z-50 opacity-0 group-hover:opacity-100 transition-opacity hidden">
                                {{ __('admin.system_diagnostics') }}
                            </div>
                        </a>
                    @endif
                    <x-admin.account-nav :active="$active" />
                </nav>
            </div>

            <!-- Footer Action Links & Admin Info -->
            <div class="pt-4 border-t border-outline-variant space-y-2 text-xs">
                <!-- Admin Profile Info Chip -->
                <a href="{{ route('admin.profile') }}"
                    class="admin-profile-chip flex items-center gap-2.5 p-2 rounded-lg bg-surface-container border border-outline-variant/60 relative group no-underline"
                    title="{{ __('admin.profile_security') }}">
                    <div data-admin-avatar="sidebar"
                        class="relative w-8 h-8 overflow-hidden rounded-full bg-primary text-white font-mono text-xs flex items-center justify-center font-bold ring-1 ring-emerald-600/30 shrink-0 mx-auto lg:mx-0">
                        <span aria-hidden="true">{{ $adminInitials }}</span>
                        @if ($adminAvatarUrl)
                            <img src="{{ $adminAvatarUrl }}" alt="{{ $adminUser?->name }}" loading="lazy"
                                onerror="this.remove()" class="absolute inset-0 h-full w-full object-cover">
                        @endif
                    </div>
                    <div class="flex flex-col min-w-0 flex-1 sidebar-text">
                        <span
                            class="text-xs font-semibold text-on-surface truncate leading-tight">{{ $adminUser?->name ?? __('global.common.admin') }}</span>
                        <span
                            class="text-[10px] text-outline font-mono truncate leading-tight">{{ $adminUser?->email ?? __('admin.room_dispatcher_role') }}</span>
                    </div>
                    <div
                        class="sidebar-tooltip pointer-events-none absolute left-full top-1/2 -translate-y-1/2 ml-3 px-2.5 py-1.5 bg-[#0b1c30] text-white text-xs font-semibold rounded-lg shadow-xl whitespace-nowrap z-50 opacity-0 group-hover:opacity-100 transition-opacity hidden">
                        {{ $adminUser?->name }} ({{ $adminUser?->email }})
                    </div>
                </a>

                <!-- Logout Button -->
                <button type="button" onclick="openAdminLogoutModal()"
                    class="sidebar-nav-link btn-admin-logout relative group w-full flex items-center gap-2.5 px-3 py-2 text-error hover:bg-error-container/40 rounded-lg font-medium transition-colors text-left cursor-pointer"
                    title="{{ __('admin.logout') }}">
                    <span class="material-symbols-outlined text-[18px] shrink-0">logout</span>
                    <span class="sidebar-text truncate">{{ __('admin.logout') }}</span>
                    <div
                        class="sidebar-tooltip pointer-events-none absolute left-full top-1/2 -translate-y-1/2 ml-3 px-2.5 py-1.5 bg-[#0b1c30] text-white text-xs font-semibold rounded-lg shadow-xl whitespace-nowrap z-50 opacity-0 group-hover:opacity-100 transition-opacity hidden">
                        {{ __('admin.logout') }}
                    </div>
                </button>
            </div>
        </div>
    </aside>

    <!-- ================= MAIN CONTENT WORKSPACE ================= -->
    <div id="admin-main-wrapper" class="flex-1 lg:pl-64 flex flex-col min-w-0 min-h-screen">
        {{-- Superadmins bypass maintenance; remind them when it is on (renders nothing for room admins). --}}
        <x-superadmin.maintenance-banner />
        <!-- Top Navigation Bar -->
        <header
            class="flex justify-between items-center gap-2 w-full px-3 sm:px-6 py-2 h-14 bg-surface-container-lowest border-b border-outline-variant sticky top-0 z-20">
            <!-- Left side: Mobile menu toggle, Desktop sidebar collapse toggle & Breadcrumbs -->
            <div class="flex min-w-0 items-center gap-2 sm:gap-3">
                <button type="button"
                    class="lg:hidden w-8 h-8 flex items-center justify-center rounded text-outline hover:text-on-surface hover:bg-surface-container-low transition-colors cursor-pointer"
                    onclick="document.querySelector('#admin-sidebar')?.classList.toggle('-translate-x-full')">
                    <span class="material-symbols-outlined text-[20px]">menu</span>
                </button>
                <button type="button" onclick="toggleAdminSidebar()"
                    class="hidden lg:flex w-8 h-8 items-center justify-center rounded text-outline hover:text-on-surface hover:bg-surface-container-low transition-colors cursor-pointer"
                    data-tooltip="{{ __('admin.toggle_sidebar') }}" aria-label="{{ __('admin.toggle_sidebar') }}">
                    <span
                        class="material-symbols-outlined text-[20px] sidebar-collapse-toggle-icon">dock_to_left</span>
                </button>
                {{-- On mobile only the current page is shown (Admin / Rooms are hidden) so the header never overflows. --}}
                <nav class="flex min-w-0 items-center gap-2 text-xs text-outline font-medium">
                    <span class="hidden sm:inline">{{ __('global.common.admin') }}</span>
                    <span class="hidden sm:inline">/</span>
                    <a href="{{ route('admin.landing') }}" class="hidden sm:inline hover:text-on-surface transition-colors">{{ __('admin.breadcrumb_rooms') }}</a>
                    @if ($breadcrumb)
                        <span class="hidden sm:inline">/</span>
                        <span class="text-on-surface font-semibold truncate">{{ $breadcrumb }}</span>
                    @elseif (request()->routeIs('admin.profile'))
                        <span class="hidden sm:inline">/</span>
                        <span class="text-on-surface font-semibold truncate">{{ __('admin.profile') }}</span>
                    @elseif($room)
                        <span class="hidden sm:inline">/</span>
                        @if (request()->routeIs('admin.dashboard.page'))
                            <span
                                class="text-on-surface font-semibold truncate max-w-[150px] sm:max-w-none">{{ $roomLabel }}</span>
                        @else
                            <a href="{{ route('admin.dashboard.page', $room) }}"
                                class="hover:text-on-surface transition-colors truncate max-w-[150px] sm:max-w-none">{{ $roomLabel }}</a>
                        @endif
                    @endif
                </nav>
            </div>

            <!-- Right side: Language, Notifications Dropdown, Fast Action -->
            <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                <div class="relative" data-admin-language-switcher>
                    <button type="button" data-language-toggle aria-expanded="false"
                        aria-controls="admin-language-menu"
                        class="flex items-center gap-1.5 px-2 py-1.5 rounded-lg text-xs font-medium text-on-surface-variant hover:bg-surface-container-low transition-colors border border-outline-variant/60 cursor-pointer">
                        <span>{{ $activeLocaleMeta['flag'] }}</span>
                        <span
                            class="hidden sm:inline font-semibold text-on-surface">{{ $activeLocaleMeta['code'] }}</span>
                        <span data-language-chevron
                            class="material-symbols-outlined text-[16px] text-outline transition-transform duration-200">arrow_drop_down</span>
                    </button>

                    <div id="admin-language-menu" data-language-menu
                        class="hidden absolute right-0 top-full mt-2 w-40 bg-surface-container-lowest rounded-xl shadow-xl border border-outline-variant/80 py-1.5 z-50">
                        @foreach ($locales as $code => $meta)
                            <a href="{{ route('locale.switch', $code) }}"
                                class="flex items-center justify-between px-3 py-2 text-xs text-on-surface hover:bg-primary/10 hover:text-primary transition-colors {{ $currentLocale === $code ? 'font-semibold text-primary bg-primary/5' : '' }}">
                                <span class="flex items-center gap-2">
                                    <span>{{ $meta['flag'] }}</span>
                                    <span>{{ $meta['name'] }}</span>
                                </span>
                                @if ($currentLocale === $code)
                                    <span class="material-symbols-outlined text-[16px] text-primary">check</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>

                @if ($room)
                    <!-- Notifications Dropdown -->
                    <div class="relative" data-admin-notifications>
                        <button type="button" data-notifications-toggle aria-expanded="false"
                            aria-controls="admin-notifications-menu"
                            class="w-8 h-8 flex items-center justify-center rounded-lg text-on-surface-variant hover:bg-surface-container-low transition-colors relative cursor-pointer"
                            title="{{ __('admin.notifications') }}">
                            <span class="material-symbols-outlined text-[20px]">notifications</span>
                            @if ($unreadCount > 0 || $unreadNotifications->isNotEmpty())
                                <span data-unread-indicator
                                    class="absolute top-1.5 right-1.5 w-2 h-2 bg-error rounded-full ring-2 ring-surface"></span>
                            @endif
                        </button>

                        <div id="admin-notifications-menu" data-notifications-menu
                            data-mark-all-url="{{ route('admin.notifications.read-all', $room) }}"
                            data-empty-text="{{ __('admin.no_unread_notifications') }}"
                            class="hidden absolute right-0 top-full mt-2 w-80 bg-surface-container-lowest border border-outline-variant rounded-xl shadow-xl z-50 py-2 overflow-hidden">
                            <div
                                class="px-4 py-2 border-b border-outline-variant/60 flex items-center justify-between">
                                <span
                                    class="font-bold text-xs text-on-surface">{{ __('admin.unread_notifications') }}</span>
                                <div class="flex items-center gap-2">
                                    @if ($unreadCount > 0)
                                        <span data-unread-count
                                            class="px-1.5 py-0.5 rounded-full bg-error-container text-error text-[10px] font-mono font-bold">{{ $unreadCount }}</span>
                                    @endif
                                    <button type="button" data-mark-all-read
                                        class="text-[10px] font-semibold text-primary hover:no-underline disabled:opacity-50">{{ __('admin.mark_all_read') }}</button>
                                </div>
                            </div>

                            <div class="max-h-64 overflow-y-auto divide-y divide-outline-variant/40">
                                @forelse($unreadNotifications as $notif)
                                    @php
                                        $notificationPresentation = $notificationPresentations[$notif->getKey()] ?? [
                                            'title' => '',
                                            'body' => '',
                                            'icon' => 'notifications',
                                        ];
                                    @endphp
                                    <div data-unread-notification
                                        class="px-4 py-2.5 hover:bg-surface-container-low transition-colors">
                                        <div class="flex items-start gap-2.5">
                                            <span
                                                class="w-6 h-6 rounded-full bg-primary/10 text-primary flex items-center justify-center shrink-0 mt-0.5">
                                                <span class="material-symbols-outlined text-[14px]">{{ $notificationPresentation['icon'] }}</span>
                                            </span>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-xs font-medium text-on-surface leading-snug">
                                                    {{ $notificationPresentation['title'] }}</p>
                                                @if ($notificationPresentation['body'])
                                                    <p class="mt-0.5 text-[11px] text-outline leading-snug">
                                                        {{ $notificationPresentation['body'] }}</p>
                                                @endif
                                                <span
                                                    class="text-[10px] font-mono text-outline">{{ $notif->created_at ? $notif->created_at->diffForHumans() : __('admin.just_now') }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @empty
                                    <div class="px-4 py-6 text-center text-outline text-xs">
                                        <span
                                            class="material-symbols-outlined text-[28px] text-outline/60 block mx-auto mb-1">notifications_off</span>
                                        <span>{{ __('admin.no_unread_notifications') }}</span>
                                    </div>
                                @endforelse
                            </div>

                            <div
                                class="px-4 py-2 border-t border-outline-variant/60 bg-surface-container-low/50 text-center">
                                <a href="{{ route('admin.audit.page', $room) }}"
                                    class="text-xs text-primary font-semibold no-underline hover:no-underline block">
                                    {{ __('admin.view_all_notifications') }} →
                                </a>
                            </div>
                        </div>
                    </div>

                    @if (!$hasLiveCampaign)
                        <!-- Fast Create Action Button -->
                        <a href="{{ route('admin.campaigns.create', $room) }}"
                            class="bg-primary hover:bg-primary-container text-on-primary px-3 py-1.5 rounded-lg text-xs font-semibold flex items-center gap-1.5 transition-colors shadow-xs no-underline">
                            <span class="material-symbols-outlined text-[16px]">bolt</span>
                            <span class="hidden sm:inline">{{ __('admin.dispatch_action') }}</span>
                        </a>
                    @endif

                    <button type="button" id="admin-broadcast-open" class="inline-flex items-center gap-1.5 rounded-lg border border-secondary/30 bg-secondary/10 px-3 py-1.5 text-xs font-semibold text-secondary transition-colors hover:bg-secondary/20" title="{{ __('admin.broadcast_notification') }}">
                        <span class="material-symbols-outlined text-[16px]">campaign</span>
                        <span class="hidden sm:inline">{{ __('admin.broadcast_notification') }}</span>
                    </button>

                    @if($room->status == "active")
                        <a href="{{ route('user.rooms.show', $room->slug) }}"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-primary/30 bg-primary/5 px-3 py-1.5 text-xs font-semibold text-primary transition-colors hover:bg-primary/10 no-underline"
                            title="{{ __('admin.access_room_as_user') }}">
                            <span class="material-symbols-outlined text-[16px]">open_in_new</span>
                            <span class="hidden sm:inline">{{ __('admin.access_room_as_user') }}</span>
                        </a>
                    @endif
                @endif
            </div>
        </header>

        <!-- Main Scrollable Canvas -->
        <main class="flex-1 min-w-0 w-full max-w-[1440px] mx-auto p-4 sm:p-6 space-y-6">
            {{ $slot }}
        </main>
    </div>

    <!-- Admin Logout Confirmation Modal -->
    <x-admin.logout-modal />

    <!-- Shared Admin Alert Modal (replaces native alert() popups) -->
    <x-admin.alert-modal />
    <x-admin.confirm-modal />

    <!-- Admin Page Navigation & Submit Loading Overlay -->
    <x-admin.loading />

    <!-- Admin Go To Top Floating Button -->
    <x-admin.go-to-top />

    @if ($room)
        @php
            $broadcastTypes = [
                ['value' => 'admin.broadcast', 'icon' => 'campaign', 'label' => __('admin.broadcast_general'), 'title' => __('admin.broadcast_sample_title'), 'body' => __('admin.broadcast_sample_body')],
                ['value' => 'campaign.created', 'icon' => 'local_cafe', 'label' => __('admin.broadcast_campaign'), 'title' => __('admin.broadcast_campaign_title'), 'body' => __('admin.broadcast_campaign_body')],
                ['value' => 'payment.reminder', 'icon' => 'payments', 'label' => __('admin.broadcast_payment'), 'title' => __('admin.broadcast_payment_title'), 'body' => __('admin.broadcast_payment_body')],
            ];
            $broadcastField = 'w-full rounded-xl border border-outline-variant bg-surface px-3 text-xs text-on-surface transition-colors focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20';
        @endphp
        <div id="admin-broadcast-modal" class="hidden fixed inset-0 z-[70] items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
            <div data-broadcast-backdrop class="absolute inset-0"></div>
            <div class="relative z-10 flex max-h-[92vh] w-full max-w-xl flex-col overflow-hidden rounded-2xl border border-outline-variant bg-surface-container-lowest shadow-2xl" role="dialog" aria-modal="true" aria-labelledby="admin-broadcast-heading">
                <div class="flex items-start gap-3 border-b border-outline-variant/60 bg-surface-container-low/60 px-5 py-4">
                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-secondary/20 bg-secondary/10 text-secondary">
                        <span class="material-symbols-outlined text-[22px]" aria-hidden="true">campaign</span>
                    </span>
                    <div class="min-w-0 flex-1">
                        <h3 id="admin-broadcast-heading" class="text-base font-bold text-on-surface">{{ __('admin.broadcast_notification') }}</h3>
                        <p class="mt-0.5 text-xs text-outline">{{ __('admin.broadcast_subtitle', ['room' => $room->name]) }}</p>
                    </div>
                    <button type="button" id="admin-broadcast-close" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-outline transition-colors hover:bg-surface-container hover:text-on-surface" aria-label="{{ __('admin.close_modal_btn') }}">
                        <span class="material-symbols-outlined text-[20px]" aria-hidden="true">close</span>
                    </button>
                </div>
                <form id="admin-broadcast-form" data-url="{{ route('admin.notifications.broadcast', $room) }}" class="flex min-h-0 flex-1 flex-col text-xs" novalidate>
                    <div class="space-y-4 overflow-y-auto p-5">
                        <fieldset>
                            <legend class="mb-1.5 font-semibold text-on-surface">{{ __('admin.broadcast_type') }}<span class="ml-0.5 text-red-600" aria-hidden="true">*</span></legend>
                            {{-- The select stays the source of truth (validation + sample texts); the cards only drive it. --}}
                            <select id="admin-broadcast-type" required aria-required="true" class="sr-only" tabindex="-1" aria-hidden="true">
                                @foreach ($broadcastTypes as $broadcastType)
                                    <option value="{{ $broadcastType['value'] }}" data-title="{{ $broadcastType['title'] }}" data-body="{{ $broadcastType['body'] }}">{{ $broadcastType['label'] }}</option>
                                @endforeach
                            </select>
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-3" role="radiogroup" aria-label="{{ __('admin.broadcast_type') }}">
                                @foreach ($broadcastTypes as $broadcastType)
                                    <button type="button" role="radio" aria-checked="false" data-broadcast-type-card="{{ $broadcastType['value'] }}"
                                        class="flex items-center gap-2 rounded-xl border border-outline-variant bg-surface px-3 py-2.5 text-left font-semibold text-on-surface transition-colors hover:border-primary/50 aria-checked:border-primary aria-checked:bg-primary/5 aria-checked:ring-2 aria-checked:ring-primary/15">
                                        <span class="material-symbols-outlined text-[20px] text-outline" data-broadcast-type-icon aria-hidden="true">{{ $broadcastType['icon'] }}</span>
                                        <span class="min-w-0 leading-snug">{{ $broadcastType['label'] }}</span>
                                    </button>
                                @endforeach
                            </div>
                            <span data-broadcast-error="type" class="mt-1 hidden text-[11px] font-medium text-red-600"></span>
                        </fieldset>
                        <div>
                            <div class="mb-1.5 flex items-center justify-between">
                                <label for="admin-broadcast-title" class="font-semibold text-on-surface">{{ __('admin.broadcast_title') }}<span class="ml-0.5 text-red-600" aria-hidden="true">*</span></label>
                                <span class="font-mono text-[10px] text-outline" data-broadcast-count="title">0/160</span>
                            </div>
                            <input id="admin-broadcast-title" required aria-required="true" maxlength="160" class="{{ $broadcastField }} h-10">
                            <span data-broadcast-error="title" class="mt-1 hidden text-[11px] font-medium text-red-600"></span>
                        </div>
                        <div>
                            <div class="mb-1.5 flex items-center justify-between">
                                <label for="admin-broadcast-body" class="font-semibold text-on-surface">{{ __('admin.broadcast_content') }}</label>
                                <span class="font-mono text-[10px] text-outline" data-broadcast-count="body">0/2000</span>
                            </div>
                            <textarea id="admin-broadcast-body" rows="4" maxlength="2000" class="{{ $broadcastField }} py-2.5 leading-relaxed"></textarea>
                            <span data-broadcast-error="body" class="mt-1 hidden text-[11px] font-medium text-red-600"></span>
                        </div>
                        {{-- Live preview of the notification members will receive. --}}
                        <div>
                            <div class="mb-1.5 font-semibold text-on-surface">{{ __('admin.broadcast_preview') }}</div>
                            <div class="flex items-start gap-3 rounded-xl border border-outline-variant/70 bg-surface-container-low p-3">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                                    <span class="material-symbols-outlined text-[18px]" data-broadcast-preview-icon aria-hidden="true">campaign</span>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p class="break-words font-bold text-on-surface" data-broadcast-preview-title>—</p>
                                    <p class="mt-0.5 whitespace-pre-line break-words leading-relaxed text-secondary line-clamp-4" data-broadcast-preview-body></p>
                                    <p class="mt-1 text-[10px] text-outline">{{ $room->name }} · {{ __('admin.broadcast_preview_now') }}</p>
                                </div>
                            </div>
                        </div>
                        <p id="admin-broadcast-notice" class="hidden rounded-lg px-3 py-2 text-xs"></p>
                    </div>
                    <div class="flex items-center justify-end gap-2 border-t border-outline-variant/60 bg-surface-container-low/60 px-5 py-3.5">
                        <button type="button" id="admin-broadcast-cancel" class="rounded-xl border border-outline-variant px-4 py-2 font-semibold text-on-surface transition-colors hover:bg-surface-container">{{ __('admin.cancel') }}</button>
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 font-semibold text-white shadow-xs transition-colors hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-60">
                            <span class="material-symbols-outlined text-[16px]">send</span>
                            <span>{{ __('admin.broadcast_confirm') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    <!-- Global Admin API Fetch Helper -->
    <script>
        // The session token can rotate while the page stays open (login/logout in another tab), leaving the
        // <meta> token stale. Re-read it from a fresh render of this page; null means the session is gone.
        const dfRefreshCsrfToken = async () => {
            try {
                const page = await fetch(window.location.href, { headers: { 'Accept': 'text/html' }, cache: 'no-store' });
                // A redirect means the admin was logged out (e.g. to the login page): retrying cannot succeed.
                if (!page.ok || page.redirected) return null;
                const html = new DOMParser().parseFromString(await page.text(), 'text/html');
                const token = html.querySelector('meta[name="csrf-token"]')?.content;
                if (!token) return null;
                document.querySelector('meta[name="csrf-token"]')?.setAttribute('content', token);
                return token;
            } catch (e) {
                return null;
            }
        };
        window.dfApi = async (url, options = {}, retried = false) => {
            const headers = {
                'Accept': 'application/json',
                ...(options.headers || {})
            };
            let body = options.body;
            if (body && typeof body !== 'string' && !(body instanceof FormData)) {
                headers['Content-Type'] = 'application/json';
                body = JSON.stringify(body);
            }
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
            if (csrfToken) {
                headers['X-CSRF-TOKEN'] = csrfToken;
            }
            const response = await fetch(url, {
                ...options,
                body,
                headers
            });
            if (response.status === 419) {
                if (!retried && await dfRefreshCsrfToken()) {
                    return window.dfApi(url, options, true);
                }
                const expiredError = new Error(@js(__('admin.session_expired_reload')));
                expiredError.status = 419;
                expiredError.errors = {};
                throw expiredError;
            }
            if (!response.ok) {
                const errData = await response.json().catch(() => ({}));
                const apiError = new Error(errData.message || `HTTP ${response.status}`);
                apiError.status = response.status;
                apiError.errors = errData.errors || {};
                throw apiError;
            }
            return response.json();
        };
    </script>
    @if ($room)
        <script>
            (() => {
                const modal = document.getElementById('admin-broadcast-modal');
                const form = document.getElementById('admin-broadcast-form');
                const type = document.getElementById('admin-broadcast-type');
                const title = document.getElementById('admin-broadcast-title');
                const body = document.getElementById('admin-broadcast-body');
                const notice = document.getElementById('admin-broadcast-notice');
                const cards = Array.from(modal?.querySelectorAll('[data-broadcast-type-card]') || []);
                const previewTitle = modal?.querySelector('[data-broadcast-preview-title]');
                const previewBody = modal?.querySelector('[data-broadcast-preview-body]');
                const previewIcon = modal?.querySelector('[data-broadcast-preview-icon]');
                const counters = { title: modal?.querySelector('[data-broadcast-count="title"]'), body: modal?.querySelector('[data-broadcast-count="body"]') };
                const submitButton = form?.querySelector('button[type="submit"]');
                const submitIcon = submitButton?.querySelector('.material-symbols-outlined');
                const submitLabel = submitButton?.querySelector('span:last-child');
                const submitText = submitLabel?.textContent || '';
                const isOpen = () => modal && !modal.classList.contains('hidden');
                let sending = false;
                // Keep the type cards, character counters and live preview in sync with the form fields.
                const syncCards = () => cards.forEach((card) => {
                    const active = card.dataset.broadcastTypeCard === type.value;
                    card.setAttribute('aria-checked', active ? 'true' : 'false');
                    card.querySelector('[data-broadcast-type-icon]')?.classList.toggle('text-primary', active);
                    card.querySelector('[data-broadcast-type-icon]')?.classList.toggle('text-outline', !active);
                    if (active && previewIcon) previewIcon.textContent = card.querySelector('[data-broadcast-type-icon]')?.textContent || 'campaign';
                });
                const syncPreview = () => {
                    if (counters.title) counters.title.textContent = `${title.value.length}/160`;
                    if (counters.body) counters.body.textContent = `${body.value.length}/2000`;
                    if (previewTitle) previewTitle.textContent = title.value.trim() || '—';
                    if (previewBody) previewBody.textContent = body.value.trim();
                };
                const setSending = (on) => {
                    sending = on;
                    if (submitButton) submitButton.disabled = on;
                    if (submitIcon) { submitIcon.textContent = on ? 'progress_activity' : 'send'; submitIcon.classList.toggle('animate-spin', on); }
                    if (submitLabel) submitLabel.textContent = on ? @js(__('admin.broadcast_sending')) : submitText;
                };
                const open = () => { modal?.classList.remove('hidden'); modal?.classList.add('flex'); type?.dispatchEvent(new Event('change')); title?.focus(); };
                const close = () => { if (sending) return; modal?.classList.add('hidden'); modal?.classList.remove('flex'); };
                document.getElementById('admin-broadcast-open')?.addEventListener('click', open);
                document.getElementById('admin-broadcast-close')?.addEventListener('click', close);
                document.getElementById('admin-broadcast-cancel')?.addEventListener('click', close);
                modal?.querySelector('[data-broadcast-backdrop]')?.addEventListener('click', close);
                document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && isOpen()) close(); });
                cards.forEach((card) => card.addEventListener('click', () => {
                    type.value = card.dataset.broadcastTypeCard;
                    type.dispatchEvent(new Event('change'));
                }));
                [title, body].forEach((field) => field?.addEventListener('input', syncPreview));
                const messages = @js([
                    'typeRequired' => __('validation.required', ['attribute' => __('validation.attributes.type')]),
                    'typeInvalid' => __('validation.in', ['attribute' => __('validation.attributes.type')]),
                    'titleRequired' => __('validation.required', ['attribute' => __('validation.attributes.title')]),
                    'titleMax' => __('validation.max.string', ['attribute' => __('validation.attributes.title'), 'max' => 160]),
                    'bodyMax' => __('validation.max.string', ['attribute' => __('validation.attributes.body'), 'max' => 2000]),
                ]);
                const fields = { type, title, body };
                const errorEls = Object.fromEntries(['type', 'title', 'body'].map((name) => [name, form?.querySelector(`[data-broadcast-error="${name}"]`)]));
                const setError = (name, message) => {
                    const field = fields[name];
                    const el = errorEls[name];
                    if (!field || !el) return;
                    el.textContent = message || '';
                    el.classList.toggle('hidden', !message);
                    field.classList.toggle('border-red-500', Boolean(message));
                    field.classList.toggle('border-outline-variant', !message);
                    field.setAttribute('aria-invalid', message ? 'true' : 'false');
                };
                const clearErrors = () => { ['type', 'title', 'body'].forEach((name) => setError(name, '')); notice?.classList.add('hidden'); };
                const validate = () => {
                    const errors = {};
                    const allowed = Array.from(type.options).map((option) => option.value);
                    if (!type.value) errors.type = messages.typeRequired;
                    else if (!allowed.includes(type.value)) errors.type = messages.typeInvalid;
                    const titleValue = title.value.trim();
                    if (!titleValue) errors.title = messages.titleRequired;
                    else if (titleValue.length > 160) errors.title = messages.titleMax;
                    if (body.value.trim().length > 2000) errors.body = messages.bodyMax;
                    return errors;
                };
                const showErrors = (errors) => {
                    Object.entries(errors).forEach(([name, message]) => setError(name, Array.isArray(message) ? message[0] : message));
                    const firstInvalid = ['type', 'title', 'body'].find((name) => errors[name]);
                    if (firstInvalid) fields[firstInvalid].focus();
                };
                ['type', 'title', 'body'].forEach((name) => fields[name]?.addEventListener('input', () => setError(name, '')));

                type?.addEventListener('change', () => { const option = type.options[type.selectedIndex]; title.value = option.dataset.title || ''; body.value = option.dataset.body || ''; clearErrors(); syncCards(); syncPreview(); });
                form?.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    if (sending) return;
                    clearErrors();
                    const clientErrors = validate();
                    if (Object.keys(clientErrors).length) { showErrors(clientErrors); return; }
                    setSending(true);
                    try {
                        const response = await window.dfApi(form.dataset.url, { method: 'POST', body: { type: type.value, title: title.value.trim(), body: body.value.trim() } });
                        notice.textContent = response.message || '{{ __('admin.broadcast_sent', ['count' => ':count']) }}'; notice.className = 'flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-700'; notice.classList.remove('hidden');
                        setSending(false);
                        window.setTimeout(close, 900);
                    } catch (error) {
                        setSending(false);
                        if (error.status === 422 && error.errors && Object.keys(error.errors).length) { showErrors(error.errors); }
                        else { notice.textContent = error.message || '{{ __('admin.broadcast_failed') }}'; notice.className = 'rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700'; notice.classList.remove('hidden'); }
                    }
                });
            })();
        </script>
    @endif

    {{ $scripts ?? '' }}
    @stack('scripts')
</body>

</html>
