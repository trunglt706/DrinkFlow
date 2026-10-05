@props([
    'title' => null,
    'brandHero' => null,
    // Single centered card on a full-width stage (no brand column), used by the wide registration form.
    'centered' => false,
    // Superadmin sign-in: graphite brand column and the indigo accent of the superadmin console (admin.css .sa-auth).
    'superadmin' => false,
])

@php
    $currentLocale = app()->getLocale();
    $activeLocaleMeta = \App\Constants\AppLocale::get($currentLocale);
@endphp

<!doctype html>
<html class="h-full" lang="{{ $currentLocale }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title . ' · ' : '' }}{{ __('admin.brand_title') }} · DrinkFlow Admin</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=JetBrains+Mono:ital,wght@0,400..700;1,400..700&display=swap"
        rel="stylesheet">
    <link
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200&display=block"
        rel="stylesheet">

    @vite(['resources/css/admin.css', 'resources/js/admin.js'])
</head>

<body data-submit-loading-text="{{ __('global.common.loading') }}"
    class="admin-shell {{ $superadmin ? 'sa-auth' : '' }} h-full bg-surface text-on-surface font-sans antialiased overflow-hidden selection:bg-primary/20">
    <div class="h-screen overflow-hidden flex flex-col lg:flex-row">
        @if ($superadmin && ! $centered)
        <aside class="sa-auth-aside lg:w-5/12 xl:w-1/2 hidden md:flex flex-col justify-between p-6 lg:py-8 lg:px-10 relative overflow-hidden select-none shrink-0">
            <div class="sa-auth-grid" aria-hidden="true"></div>
            <div class="sa-auth-glow" aria-hidden="true"></div>
            <div class="relative z-10 flex items-center gap-3">
                <span class="sa-auth-logo" aria-hidden="true">
                    <svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor"><path d="M20 3H4v10c0 2.21 1.79 4 4 4h6c2.21 0 4-1.79 4-4v-3h2c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 5h-2V5h2v3zM4 19h16v2H4z" /></svg>
                </span>
                <span class="leading-tight">
                    <span class="block text-lg font-bold tracking-tight text-white">DrinkFlow</span>
                    <span class="block text-xs text-zinc-400">{{ __('superadmin.layout.enterprise_superadmin') }}</span>
                </span>
            </div>
            <div class="relative z-10 my-auto max-w-lg space-y-6">
                {{ $brandHero }}
            </div>
            <p class="relative z-10 text-xs text-zinc-500">© {{ date('Y') }} DrinkFlow</p>
        </aside>
        @endif
        @unless ($centered || $superadmin)
        <!-- LEFT COLUMN: Brand & Operations Engine (~46% width) -->
        <aside
            class="lg:w-5/12 xl:w-1/2 bg-gradient-to-br from-emerald-50 via-white to-emerald-100/70 text-on-surface hidden md:flex flex-col p-6 lg:py-8 lg:px-10 relative overflow-hidden border-b lg:border-b-0 lg:border-r border-emerald-100 justify-between select-none shrink-0">
            <div
                class="absolute inset-0 opacity-[0.06] pointer-events-none bg-[radial-gradient(#006948_1px,transparent_1px)] [background-size:20px_20px]">
            </div>
            <div class="absolute -top-32 -right-32 w-96 h-96 rounded-full bg-primary/10 blur-3xl pointer-events-none">
            </div>

            <!-- Top Brand -->
            <div class="relative z-10">
                <div class="flex items-center gap-3">
                    <span
                        class="w-10 h-10 rounded-xl flex items-center justify-center text-on-surface shadow-md shadow-emerald-900/10 ring-1 ring-emerald-600/20 shrink-0"
                        style="background: linear-gradient(135deg, #006948 0%, #047857 100%); background-color: #006948; border: 1px solid #005137;">
                        <svg class="w-5 h-5 text-on-surface fill-current" viewBox="0 0 24 24" aria-hidden="true"
                            style="width: 22px; height: 22px; fill: #ffffff; color: #ffffff;">
                            <path
                                d="M20 3H4v10c0 2.21 1.79 4 4 4h6c2.21 0 4-1.79 4-4v-3h2c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 5h-2V5h2v3zM4 19h16v2H4z" />
                        </svg>
                    </span>
                    <div>
                        <div class="flex items-center gap-2">
                            <span
                                class="text-xl font-bold tracking-tight text-on-surface">{{ __('admin.brand_title') }}</span>
                            {{-- <span class="text-[10px] font-mono font-semibold px-2 py-0.5 rounded bg-emerald-100 text-emerald-700 border border-emerald-200 tracking-wider">{{ __('admin.engine_badge') }}</span> --}}
                        </div>
                        <p class="text-[10px] font-mono tracking-widest text-primary/70 uppercase block mt-0.5">
                            {{ __('admin.brand_subtitle') }}</p>
                    </div>
                </div>
            </div>

            <!-- Value Props Cards / Subsystem Overview -->
            <div class="relative z-10 space-y-4 my-auto max-w-lg">
                @if (isset($brandHero) && $brandHero->isNotEmpty())
                    {{ $brandHero }}
                @else
                    <div>
                        <h1 class="text-2xl lg:text-3xl font-bold text-on-surface leading-tight tracking-tight mb-2">
                            {{ __('admin.auth_hero_title') }}
                        </h1>
                        <p class="text-xs lg:text-sm text-slate-600 leading-relaxed">
                            {{ __('admin.auth_hero_desc') }}
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                        <div class="p-3 rounded-xl bg-white/80 shadow-xs border border-emerald-100 backdrop-blur-xs">
                            <div class="flex items-center gap-2 text-primary mb-1">
                                <span class="material-symbols-outlined text-[18px]">dinner_dining</span>
                                <strong
                                    class="text-xs font-semibold text-on-surface">{{ __('admin.bulk_kitchen_title') }}</strong>
                            </div>
                            <p class="text-[11px] text-slate-500 leading-snug">
                                {{ __('admin.bulk_kitchen_desc') }}
                            </p>
                        </div>

                        <div class="p-3 rounded-xl bg-white/80 shadow-xs border border-emerald-100 backdrop-blur-xs">
                            <div class="flex items-center gap-2 text-primary mb-1">
                                <span class="material-symbols-outlined text-[18px]">qr_code_2</span>
                                <strong
                                    class="text-xs font-semibold text-on-surface">{{ __('admin.vietqr_napas_title') }}</strong>
                            </div>
                            <p class="text-[11px] text-slate-500 leading-snug">
                                {{ __('admin.vietqr_napas_desc') }}
                            </p>
                        </div>

                        <div class="p-3 rounded-xl bg-white/80 shadow-xs border border-emerald-100 backdrop-blur-xs sm:col-span-2">
                            <div class="flex items-center justify-between mb-1">
                                <div class="flex items-center gap-2 text-primary">
                                    <span class="material-symbols-outlined text-[18px]">hub</span>
                                    <strong
                                        class="text-xs font-semibold text-on-surface">{{ __('admin.webhook_chatops_title') }}</strong>
                                </div>
                                <span
                                    class="text-[10px] font-mono font-bold text-emerald-700 px-2 py-0.5 rounded bg-emerald-100">{{ __('admin.latency_badge') }}</span>
                            </div>
                            <p class="text-[11px] text-slate-500 leading-snug">
                                {{ __('admin.webhook_chatops_desc') }}
                            </p>
                        </div>
                    </div>
                @endif
            </div>
        </aside>
        @endunless

        <!-- RIGHT COLUMN: Interactive Form (~54% width), or the whole page when centered -->
        <main
            @class([
                'flex flex-col overflow-y-auto',
                'lg:w-7/12 xl:w-1/2 p-6 lg:py-6 lg:px-12 justify-between bg-surface-container-lowest' => ! $centered,
                'relative w-full min-h-0 flex-1 bg-gradient-to-br from-emerald-50 via-[#f8fcfa] to-emerald-50/80' => $centered,
            ])>
            @if ($centered)
                <div class="pointer-events-none fixed inset-0 opacity-[0.18] bg-[radial-gradient(#8ecfb2_0.8px,transparent_0.8px)] [background-size:20px_20px]" aria-hidden="true"></div>
            @endif
            <!-- Top Controls (Language Switcher + Help) -->
            <div @class([
                'flex items-center justify-between',
                'pb-3' => ! $centered,
                'sticky top-0 z-40 h-16 px-4 sm:px-8 bg-white/90 backdrop-blur border-b border-emerald-100/70' => $centered,
            ])>
                @if ($centered)
                    <a href="{{ route('admin.login.page') }}" class="flex items-center gap-3 no-underline">
                        <span class="w-9 h-9 rounded-xl flex items-center justify-center shadow-md shadow-emerald-900/10 shrink-0" style="background: linear-gradient(135deg, #006948 0%, #047857 100%);">
                            <svg viewBox="0 0 24 24" aria-hidden="true" style="width: 20px; height: 20px; fill: #ffffff;">
                                <path d="M20 3H4v10c0 2.21 1.79 4 4 4h6c2.21 0 4-1.79 4-4v-3h2c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 5h-2V5h2v3zM4 19h16v2H4z" />
                            </svg>
                        </span>
                        <span class="leading-tight">
                            <span class="block text-sm sm:text-base font-bold tracking-tight text-on-surface">{{ __('admin.brand_title') }}</span>
                            <span class="block text-[10px] font-mono tracking-widest text-primary/70 uppercase">{{ __('admin.brand_subtitle') }}</span>
                        </span>
                    </a>
                    <div class="flex items-center gap-3 sm:gap-5">
                @endif
                <!-- Language Selector Dropdown -->
                <div class="relative" id="admin-auth-lang-selector">
                    <button type="button" id="admin-auth-lang-btn" aria-haspopup="true" aria-expanded="false"
                        onclick="document.getElementById('admin-auth-lang-menu')?.classList.toggle('hidden')"
                        class="flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium text-[#545c72] hover:bg-[#eff4ff] transition-colors duration-150 border border-slate-200/80 hover:border-[#bccac0] cursor-pointer">
                        <span>{{ $activeLocaleMeta['flag'] }}</span>
                        <span class="font-semibold text-[#0b1c30]">{{ $activeLocaleMeta['code'] }}</span>
                        <span class="material-symbols-outlined text-[16px] text-[#545c72]">arrow_drop_down</span>
                    </button>

                    <!-- Dropdown Menu -->
                    <div id="admin-auth-lang-menu"
                        class="hidden absolute {{ $centered ? 'right-0' : 'left-0' }} mt-1.5 w-36 bg-white rounded-xl shadow-lg border border-slate-200 py-1.5 z-50 animate-fadeIn">
                        @foreach ($locales as $code => $meta)
                            <a href="{{ route('locale.switch', $code) }}"
                                class="flex items-center justify-between px-3 py-2 text-xs text-slate-700 hover:bg-emerald-50 hover:text-[#006948] transition-colors {{ $currentLocale === $code ? 'font-semibold text-[#006948] bg-emerald-50/50' : '' }}">
                                <div class="flex items-center gap-2">
                                    <span>{{ $meta['flag'] }}</span>
                                    <span>{{ $meta['name'] }}</span>
                                </div>
                                @if ($currentLocale === $code)
                                    <span class="material-symbols-outlined text-[16px] text-[#006948]">check</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>

                <a class="flex items-center gap-1.5 text-xs font-semibold text-secondary hover:text-primary transition-colors no-underline"
                    href="{{ route('contact') }}">
                    <span class="material-symbols-outlined text-[16px]">contact_support</span>
                    <span>{{ __('admin.contact_support') }}</span>
                </a>
                @if ($centered)
                    </div>
                @endif
            </div>

            <!-- Center Form Content Slot -->
            <div @class([
                'w-full mx-auto my-auto',
                'max-w-md py-2' => ! $centered,
                'relative max-w-[1010px] px-3 py-6 sm:px-6 sm:py-10' => $centered,
            ])>
                {{ $slot }}
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('click', function(e) {
            const selector = document.getElementById('admin-auth-lang-selector');
            const menu = document.getElementById('admin-auth-lang-menu');
            if (selector && menu && !selector.contains(e.target)) {
                menu.classList.add('hidden');
            }
        });
    </script>

    {{ $scripts ?? '' }}
    @stack('scripts')
</body>

</html>
