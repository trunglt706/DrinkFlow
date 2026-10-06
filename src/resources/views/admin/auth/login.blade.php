{{-- Shared by the admin (/admin/login) and superadmin (/superadmin/login) guards; $superadminLogin switches the form.
     The admin sign-in uses the centered card of the registration / activation pages; the superadmin one keeps
     its graphite brand column (admin.css .sa-auth). --}}
@php
    $superadminLogin = (bool) ($superadminLogin ?? false);
    $googleTwoFactorPending = ! $superadminLogin && session()->has('admin_google_2fa_admin_id');
    if ($superadminLogin) {
        $inputWrap = 'relative flex items-center rounded-lg border border-outline-variant bg-surface-container-lowest focus-within:border-primary focus-within:ring-1 focus-within:ring-primary transition-all';
        $input = 'w-full border-0 bg-transparent px-3 py-2.5 text-sm text-on-surface placeholder:text-outline/60 focus:ring-0 outline-none focus:!shadow-none';
        $button = 'w-full h-10 flex items-center justify-center gap-2 bg-primary hover:bg-primary-container text-on-primary text-sm font-semibold rounded shadow-sm hover:shadow transition-all duration-150 active:scale-[0.99] cursor-pointer';
        $toggle = 'pr-3 text-outline hover:text-on-surface focus:outline-none cursor-pointer';
    } else {
        $inputWrap = 'relative flex items-center h-[45px] rounded-[10px] border border-outline-variant bg-surface-container-lowest focus-within:border-primary focus-within:ring-[3px] focus-within:ring-primary/15 transition-all';
        $input = 'w-full min-w-0 border-0 bg-transparent px-2.5 text-sm text-on-surface placeholder:text-outline/60 focus:ring-0 outline-none focus:!shadow-none autofill:[transition:background-color_600000s_0s,color_600000s_0s]';
        $button = 'w-full h-[45px] flex items-center justify-center gap-2 rounded-[10px] bg-primary hover:bg-primary-container text-on-primary text-sm font-semibold shadow-sm hover:shadow transition-all duration-150 active:scale-[0.99] cursor-pointer';
        $toggle = 'h-full px-3 grid place-items-center text-outline hover:text-on-surface focus:outline-none focus-visible:text-primary cursor-pointer';
    }
    $label = 'block text-xs font-semibold text-on-surface mb-1';
@endphp
<x-admin-auth.layout :title="$superadminLogin ? __('superadmin.auth.login_page_title') : __('admin.login_page_title')"
    :superadmin="$superadminLogin" :centered="! $superadminLogin">
    @if ($superadminLogin)
        <x-slot:brandHero>
            <span class="sa-auth-badge"><span class="material-symbols-outlined text-[16px]" aria-hidden="true">verified_user</span>{{ __('superadmin.auth.hero_badge') }}</span>
            <div>
                <h1 class="text-3xl font-bold leading-tight tracking-tight text-white mb-3">{{ __('superadmin.auth.hero_title') }}</h1>
                <p class="text-sm leading-relaxed text-zinc-400">{{ __('superadmin.auth.hero_desc') }}</p>
            </div>
            <ul class="space-y-3">
                @foreach (['agents' => 'storefront', 'security' => 'shield_locked', 'infra' => 'hub'] as $feature => $featureIcon)
                    <li class="sa-auth-feature">
                        <span class="material-symbols-outlined" aria-hidden="true">{{ $featureIcon }}</span>
                        <span><strong>{{ __('superadmin.auth.feature_'.$feature) }}</strong><small>{{ __('superadmin.auth.feature_'.$feature.'_desc') }}</small></span>
                    </li>
                @endforeach
            </ul>
        </x-slot:brandHero>
    @endif

    @unless ($superadminLogin)
    <div class="mx-auto w-full max-w-[440px] rounded-3xl border border-emerald-100 bg-white/95 shadow-[0_28px_80px_rgba(32,74,60,0.12)] px-5 py-7 sm:px-9 sm:py-9">
        <header class="text-center mb-6">
            <span class="mx-auto mb-5 w-[64px] h-[64px] grid place-items-center rounded-[20px] text-white shadow-lg shadow-emerald-900/20"
                style="background: linear-gradient(135deg, #006948 0%, #10a374 100%);" aria-hidden="true">
                <span class="material-symbols-outlined !text-[32px]">admin_panel_settings</span>
            </span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 text-primary text-[11px] font-bold tracking-wide">
                <span class="material-symbols-outlined !text-[15px]" aria-hidden="true">shield_lock</span>
                <span>{{ __('admin.restricted_access_area') }}</span>
            </span>
            <h1 class="mt-2.5 text-2xl sm:text-[26px] font-bold tracking-tight text-on-surface">{{ __('admin.login_heading') }}</h1>
            <p class="mt-1.5 text-[13px] text-outline">{{ __('admin.login_subheading') }}</p>
        </header>
    @else
    <div class="mb-5">
        <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-secondary-container text-on-secondary-container text-xs font-mono font-semibold mb-2">
            <span class="material-symbols-outlined text-[15px]">admin_panel_settings</span>
            <span>{{ __('admin.restricted_access_area') }}</span>
        </div>
        <h2 class="text-2xl font-bold text-on-surface tracking-tight">{{ __('superadmin.auth.login_heading') }}</h2>
    </div>
    @endunless

    @if ($errors->any() || session('login_error'))
        <div class="mb-4 rounded-xl bg-error-container p-3 text-xs font-medium text-on-error-container border border-error/20 flex items-center gap-2" role="alert">
            <span class="material-symbols-outlined !text-[18px] text-error shrink-0" aria-hidden="true">error</span>
            <span>{{ session('login_error') ?: $errors->first() }}</span>
        </div>
    @endif

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 p-3 text-xs font-medium text-emerald-800 border border-emerald-200 flex items-center gap-2" role="status">
            <span class="material-symbols-outlined !text-[18px] text-emerald-600 shrink-0" aria-hidden="true">check_circle</span>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if ($googleTwoFactorPending)
        <div class="mb-4 rounded-2xl border border-primary/25 bg-primary/10 p-4 space-y-3">
            <div class="flex items-start gap-2 text-xs text-on-primary-fixed-variant">
                <span class="material-symbols-outlined text-primary" aria-hidden="true">verified_user</span>
                <span>{{ __('admin.google_workspace_continue') }}</span>
            </div>
            <a href="{{ route('auth.google') }}" class="{{ $button }} no-underline">
                <span class="material-symbols-outlined !text-[18px]" aria-hidden="true">account_circle</span>
                {{ __('admin.sign_in_google_workspace') }}
            </a>
            <form method="post" action="{{ route('admin.login.two-factor.cancel') }}" data-loading-form="true">
                @csrf
                <button type="submit"
                    class="w-full h-[45px] rounded-[10px] border border-outline-variant bg-surface text-on-surface text-sm font-semibold hover:bg-surface-container transition-colors inline-flex items-center justify-center gap-2 cursor-pointer">
                    <span class="material-symbols-outlined !text-[18px]" aria-hidden="true">close</span>
                    {{ __('admin.cancel') }}
                </button>
            </form>
        </div>
    @endif

    @unless ($googleTwoFactorPending)
    <form method="post" action="{{ $superadminLogin ? route('superadmin.login') : route('admin.login') }}" data-loading-form="true" class="space-y-4">
        @csrf
        <div>
            <label for="admin-email" class="{{ $label }}">{{ __('admin.email_address') }}</label>
            <div class="{{ $inputWrap }} @error('email') !border-error @enderror">
                <span class="material-symbols-outlined text-outline pl-3 !text-[18px]" aria-hidden="true">alternate_email</span>
                <input id="admin-email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                    class="{{ $input }} font-medium" placeholder="admin@company.com">
            </div>
        </div>

        <div>
            <div class="flex items-center justify-between mb-1">
                <label for="admin-password" class="block text-xs font-semibold text-on-surface">{{ __('admin.password') }}</label>
                @unless ($superadminLogin)
                    <a href="{{ route('admin.forgot-password.page') }}" class="text-xs font-semibold text-primary hover:underline">{{ __('admin.forgot_password') }}</a>
                @endunless
            </div>
            <div class="{{ $inputWrap }}">
                <span class="material-symbols-outlined text-outline pl-3 !text-[18px]" aria-hidden="true">lock</span>
                <input id="admin-password" name="password" type="password" required autocomplete="current-password"
                    class="{{ $input }} font-mono" placeholder="••••••••••••">
                <button type="button" id="toggle-admin-password" class="{{ $toggle }}" aria-label="{{ __('admin.toggle_password_visibility') }}">
                    <span class="material-symbols-outlined !text-[18px]">visibility</span>
                </button>
            </div>
        </div>

        <x-admin-auth.captcha :variant="$superadminLogin ? 'card' : 'field'" />

        <label class="flex items-center gap-2 pt-0.5 cursor-pointer select-none text-xs text-outline hover:text-on-surface transition-colors w-fit">
            <input name="remember" type="checkbox" value="1" class="rounded border-outline-variant text-primary focus:ring-primary">
            <span>{{ __('admin.remember_session') }}</span>
        </label>

        <button type="submit" class="{{ $button }}">
            <span class="material-symbols-outlined !text-[18px]" aria-hidden="true">login</span>
            <span>{{ __('admin.sign_in') }}</span>
        </button>
    </form>
    @endunless

    @unless ($superadminLogin)
        <p class="mt-6 pt-5 border-t border-emerald-100 text-center text-xs text-outline">
            {{ __('platform.registration.no_account') }}
            <a href="{{ route('admin.register.page') }}" class="font-semibold text-primary hover:underline">{{ __('platform.registration.register_link') }}</a>
        </p>
        <p class="mt-3 text-center text-[10px] text-outline leading-snug">
            <span class="material-symbols-outlined !text-[13px] align-[-2px] mr-0.5" aria-hidden="true">policy</span>{{ __('admin.security_warning_footer') }}
        </p>
    </div>
    @else
        <p class="text-center text-[10px] text-outline leading-tight mt-3">
            {{ __('admin.security_warning_footer') }}
        </p>
    @endunless
</x-admin-auth.layout>
