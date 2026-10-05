<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="light">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Superadmin' }} · DrinkFlow</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400..700,0..1,-50..200&display=block"
        rel="stylesheet">
    {{-- Apply the remembered theme and collapsed sidebar before first paint (resources/js/superadmin/shell.js, sidebar.js). --}}
    <script>
        (function () {
            var root = document.documentElement, pref = 'system';
            try {
                pref = localStorage.getItem('df_superadmin_theme') || 'system';
                if (localStorage.getItem('df_superadmin_sidebar_collapsed') === 'true') root.classList.add('sa-sidebar-collapsed');
            } catch (e) {}
            var dark = pref === 'dark' || (pref === 'system' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
            root.setAttribute('data-theme', dark ? 'dark' : 'light');
            root.setAttribute('data-theme-pref', pref);
        })();
    </script>
    @if (file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/superadmin.css', 'resources/js/app.js'])
    @endif
</head>

<body data-submit-loading-text="{{ __('global.common.loading') }}"
    data-sa-realtime-url="{{ rtrim((string) config('services.realtime.public_url', 'http://localhost:3001'), '/') }}"
    @auth('superadmin') data-sa-socket-token-url="{{ route('superadmin.socket-token') }}" @endauth
    data-status-labels="{{ json_encode([
        'active' => __('superadmin.common.active'), 'disabled' => __('superadmin.common.disabled'),
        'inactive' => __('superadmin.common.inactive'),
        'archived' => __('superadmin.common.archived'), 'blocked' => __('superadmin.common.blocked'),
        'suspended' => __('superadmin.common.suspended'), 'rejected' => __('superadmin.common.rejected'),
        'deleted' => __('superadmin.common.deleted'), 'removed' => __('superadmin.common.removed'),
        'pending' => __('superadmin.common.pending'), 'scheduled' => __('superadmin.common.scheduled'),
        'closed' => __('superadmin.common.closed'), 'cancelled' => __('superadmin.common.cancelled'),
        'draft' => __('admin.status_draft'), 'closing' => __('admin.status_closing'),
        'high' => __('superadmin.common.high'), 'medium' => __('superadmin.common.medium'),
        'low' => __('superadmin.common.low'),
    ] + __('superadmin.health'), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) }}" class="superadmin-shell">
    <x-superadmin.loading />
    @php
        $viewer = request()->user('superadmin');
        $viewerName = (string) ($viewer?->name ?? '');
        $currentPath = '/'.ltrim(request()->path(), '/');
    @endphp
    <div class="sa-sidebar-backdrop" data-sa-drawer-close hidden></div>
    <aside class="superadmin-sidebar" id="superadmin-sidebar" aria-label="{{ __('superadmin.layout.console') }}">
        {{-- Brand mark (cup icon) shared with the room admin console (components/admin/layout.blade.php). --}}
        <div class="superadmin-sidebar-head">
            <a class="superadmin-brand" href="{{ route('superadmin.dashboard') }}" title="DrinkFlow">
                <span class="superadmin-logo" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor">
                        <path d="M20 3H4v10c0 2.21 1.79 4 4 4h6c2.21 0 4-1.79 4-4v-3h2c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 5h-2V5h2v3zM4 19h16v2H4z" />
                    </svg>
                </span>
                <div class="superadmin-brand-text"><strong>DrinkFlow</strong><span>{{ __('superadmin.layout.enterprise_superadmin') }}</span></div>
            </a>
            <button type="button" class="sa-icon-btn superadmin-drawer-close" data-sa-drawer-close aria-label="{{ __('superadmin.layout.close_menu') }}">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="superadmin-nav-filter">
            <span class="material-symbols-outlined" aria-hidden="true">filter_list</span>
            <input type="search" data-sa-nav-filter autocomplete="off" placeholder="{{ __('superadmin.layout.filter_menu') }}" aria-label="{{ __('superadmin.layout.filter_menu') }}">
        </div>
        <nav class="superadmin-nav" data-sa-nav>
            @foreach ($navSections as $section)
                <div class="superadmin-nav-group" data-sa-nav-group>
                    @if ($section['label'] !== null)
                        <span class="superadmin-nav-label">{{ $section['label'] }}</span>
                    @endif
                    @foreach ($section['links'] as $link)
                        <a class="{{ ($active ?? '') === $link['key'] ? 'is-active' : '' }}" href="{{ $link['url'] }}"
                            @if (($active ?? '') === $link['key']) aria-current="page" @endif
                            aria-label="{{ $link['label'] }}" data-sa-nav-link><span class="material-symbols-outlined">{{ $link['icon'] }}</span><span
                                class="superadmin-nav-text">{{ $link['label'] }}</span><span class="superadmin-nav-tooltip"
                                aria-hidden="true">{{ $link['label'] }}</span></a>
                    @endforeach
                </div>
            @endforeach
            <p class="superadmin-nav-empty" data-sa-nav-empty hidden>{{ __('superadmin.layout.command_empty') }}</p>
        </nav>
        <div class="superadmin-sidebar-foot">
            <span class="superadmin-clearance" title="{{ __('superadmin.layout.clearance') }}"><span class="material-symbols-outlined" aria-hidden="true">verified_user</span><span class="superadmin-nav-text">{{ __('superadmin.layout.clearance') }}</span></span>
        </div>
    </aside>
    <div class="superadmin-main">
        <x-superadmin.maintenance-banner />
        <header class="superadmin-topbar">
            <button type="button" class="sa-icon-btn superadmin-menu" data-sa-drawer-open aria-controls="superadmin-sidebar"
                aria-label="{{ __('superadmin.layout.open_menu') }}"><span class="material-symbols-outlined">menu</span></button>
            <button type="button" class="sa-icon-btn superadmin-collapse-toggle" data-sidebar-toggle
                title="{{ __('superadmin.layout.toggle_sidebar') }}" aria-label="{{ __('superadmin.layout.toggle_sidebar') }}"><span
                    class="material-symbols-outlined" data-sidebar-toggle-icon>dock_to_left</span></button>
            <nav class="sa-breadcrumb" aria-label="Breadcrumb">
                <a href="{{ route('superadmin.dashboard') }}">{{ __('superadmin.layout.console') }}</a>
                @if ($navCurrent !== null)
                    @if ($navCurrent['section'])
                        <span class="sa-breadcrumb-sep" aria-hidden="true">/</span><span class="sa-breadcrumb-muted">{{ $navCurrent['section'] }}</span>
                    @endif
                    <span class="sa-breadcrumb-sep" aria-hidden="true">/</span>
                    @if (($title ?? null) && $title !== $navCurrent['link']['label'])
                        <a href="{{ $navCurrent['link']['url'] }}">{{ $navCurrent['link']['label'] }}</a>
                        <span class="sa-breadcrumb-sep" aria-hidden="true">/</span><span aria-current="page">{{ $title }}</span>
                    @else
                        <span aria-current="page">{{ $navCurrent['link']['label'] }}</span>
                    @endif
                @elseif ($title ?? null)
                    <span class="sa-breadcrumb-sep" aria-hidden="true">/</span><span aria-current="page">{{ $title }}</span>
                @endif
            </nav>
            <button type="button" class="sa-command-trigger" data-sa-command-open aria-haspopup="dialog" aria-controls="sa-command">
                <span class="material-symbols-outlined" aria-hidden="true">search</span>
                <span class="sa-command-trigger-text">{{ __('superadmin.layout.search') }}</span>
                <kbd data-sa-command-kbd>Ctrl K</kbd>
            </button>
            <div class="superadmin-profile">
                <div class="sa-dropdown" data-sa-dropdown>
                    <button type="button" class="sa-icon-btn" data-sa-dropdown-toggle aria-haspopup="menu" aria-expanded="false"
                        title="{{ __('superadmin.layout.theme') }}" aria-label="{{ __('superadmin.layout.theme') }}">
                        <span class="material-symbols-outlined" data-sa-theme-icon>contrast</span>
                    </button>
                    <div class="sa-menu" role="menu" data-sa-dropdown-menu hidden>
                        <p class="sa-menu-label">{{ __('superadmin.layout.theme') }}</p>
                        @foreach (['light' => 'light_mode', 'dark' => 'dark_mode', 'system' => 'contrast'] as $themeKey => $themeIcon)
                            <button type="button" class="sa-menu-item" role="menuitemradio" aria-checked="false" data-sa-theme="{{ $themeKey }}">
                                <span class="material-symbols-outlined" aria-hidden="true">{{ $themeIcon }}</span>{{ __('superadmin.layout.theme_'.$themeKey) }}
                                <span class="material-symbols-outlined sa-menu-check" aria-hidden="true">check</span>
                            </button>
                        @endforeach
                    </div>
                </div>
                <div class="sa-dropdown" data-sa-dropdown>
                    <button type="button" class="sa-icon-btn sa-lang-toggle" data-sa-dropdown-toggle aria-haspopup="menu" aria-expanded="false"
                        title="{{ __('superadmin.layout.language') }}" aria-label="{{ __('superadmin.layout.language') }}">
                        <span class="sa-lang-code">{{ $locales[app()->getLocale()]['code'] ?? strtoupper(app()->getLocale()) }}</span>
                    </button>
                    <div class="sa-menu" role="menu" data-sa-dropdown-menu hidden>
                        <p class="sa-menu-label">{{ __('superadmin.layout.language') }}</p>
                        @foreach ($locales as $localeCode => $localeInfo)
                            <a class="sa-menu-item" role="menuitemradio" aria-checked="{{ app()->getLocale() === $localeCode ? 'true' : 'false' }}"
                                href="{{ route('locale.switch', ['locale' => $localeCode, 'to' => $currentPath]) }}" lang="{{ $localeCode }}">
                                <span class="sa-lang-code" aria-hidden="true">{{ $localeInfo['code'] }}</span>{{ $localeInfo['name'] }}
                                <span class="material-symbols-outlined sa-menu-check" aria-hidden="true">check</span>
                            </a>
                        @endforeach
                    </div>
                </div>
                <x-superadmin.notification-bell :notifications="$headerNotifications" :presentations="$headerNotificationPresentations" :unread-count="$headerUnreadCount" />
                <div class="sa-dropdown" data-sa-dropdown>
                    <button type="button" class="sa-user-toggle" data-sa-dropdown-toggle aria-haspopup="menu" aria-expanded="false"
                        aria-label="{{ __('superadmin.layout.account') }}">
                        <span class="superadmin-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($viewerName, 0, 1)) }}</span>
                        <span class="sa-user-name">{{ $viewerName }}</span>
                        <span class="material-symbols-outlined" aria-hidden="true">expand_more</span>
                    </button>
                    <div class="sa-menu sa-menu-account" role="menu" data-sa-dropdown-menu hidden>
                        <div class="sa-menu-account-head">
                            <span class="superadmin-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($viewerName, 0, 1)) }}</span>
                            <span><strong>{{ $viewerName }}</strong><small>{{ $viewer?->email }}</small></span>
                        </div>
                        <div class="sa-menu-sep"></div>
                        <button type="button" role="menuitem" class="sa-menu-item is-danger" data-modal-open="superadmin-logout-modal">
                            <span class="material-symbols-outlined" aria-hidden="true">logout</span>{{ __('superadmin.layout.logout') }}
                        </button>
                    </div>
                </div>
            </div>
        </header>
        <main class="superadmin-content">@yield('content')</main>
    </div>

    {{-- Command palette (Ctrl/⌘ K): jump to any menu page the account may open. resources/js/superadmin/shell.js --}}
    <div class="sa-command" id="sa-command" role="dialog" aria-modal="true" aria-label="{{ __('superadmin.layout.command_title') }}" data-sa-command hidden>
        <div class="sa-command-backdrop" data-sa-command-close></div>
        <div class="sa-command-panel">
            <div class="sa-command-search">
                <span class="material-symbols-outlined" aria-hidden="true">search</span>
                <input type="text" data-sa-command-input autocomplete="off" role="combobox" aria-expanded="true" aria-controls="sa-command-list"
                    placeholder="{{ __('superadmin.layout.command_placeholder') }}" aria-label="{{ __('superadmin.layout.command_placeholder') }}">
                <kbd>Esc</kbd>
            </div>
            <div class="sa-command-list" id="sa-command-list" role="listbox" data-sa-command-list>
                @foreach ($navSections as $section)
                    <div class="sa-command-group" data-sa-command-group>
                        <p class="sa-command-group-label">{{ $section['label'] ?? __('superadmin.layout.console') }}</p>
                        @foreach ($section['links'] as $link)
                            <a class="sa-command-item" role="option" aria-selected="false" href="{{ $link['url'] }}" data-sa-command-item
                                data-search="{{ mb_strtolower($link['label'].' '.($section['label'] ?? '')) }}">
                                <span class="material-symbols-outlined" aria-hidden="true">{{ $link['icon'] }}</span>
                                <span>{{ $link['label'] }}</span>
                                <span class="material-symbols-outlined sa-command-enter" aria-hidden="true">keyboard_return</span>
                            </a>
                        @endforeach
                    </div>
                @endforeach
                <p class="sa-command-empty" data-sa-command-empty hidden>{{ __('superadmin.layout.command_empty') }}</p>
            </div>
            <p class="sa-command-hint">{{ __('superadmin.layout.command_hint') }}</p>
        </div>
    </div>
    <div class="sa-toast-region" data-sa-toasts aria-live="polite" aria-atomic="false"></div>

    <x-superadmin.confirm-modal />
    <x-superadmin.logout-modal />
    {{-- Socket.IO client served by the realtime gateway (live inbox notifications, resources/js/superadmin/realtime.js). --}}
    @auth('superadmin')
        <script src="{{ rtrim((string) config('services.realtime.public_url', 'http://localhost:3001'), '/') }}/socket.io/socket.io.js" defer></script>
    @endauth
    @stack('scripts')
</body>

</html>
