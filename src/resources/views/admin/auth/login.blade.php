{{-- Shared by the admin (/admin/login) and superadmin (/superadmin/login) guards; $superadminLogin switches the form. --}}
@php
    $superadminLogin = (bool) ($superadminLogin ?? false);
@endphp
<x-admin-auth.layout :title="$superadminLogin ? __('superadmin.auth.login_page_title') : __('admin.login_page_title')" :superadmin="$superadminLogin">
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
    @php
        $googleTwoFactorPending = ! $superadminLogin && session()->has('admin_google_2fa_admin_id');
    @endphp

    <div class="mb-5">
        <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-secondary-container text-on-secondary-container text-xs font-mono font-semibold mb-2">
            <span class="material-symbols-outlined text-[15px]">admin_panel_settings</span>
            <span>{{ __('admin.restricted_access_area') }}</span>
        </div>
        <h2 class="text-2xl font-bold text-on-surface tracking-tight">{{ $superadminLogin ? __('superadmin.auth.login_heading') : __('admin.login_heading') }}</h2>
    </div>

    @if ($errors->any() || session('login_error'))
        <div class="mb-4 rounded-xl bg-error-container p-3 text-xs font-medium text-on-error-container border border-error/20 flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px] text-error shrink-0">error</span>
            <span>{{ session('login_error') ?: $errors->first() }}</span>
        </div>
    @endif

    @if (session('status'))
        <div class="mb-4 rounded-xl bg-emerald-50 p-3 text-xs font-medium text-emerald-800 border border-emerald-200 flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px] text-emerald-600 shrink-0">check_circle</span>
            <span>{{ session('status') }}</span>
        </div>
    @endif

    @if($googleTwoFactorPending)
        <div class="mb-4 rounded-xl border border-primary/25 bg-primary/10 p-4 space-y-3">
            <div class="flex items-start gap-2 text-xs text-on-primary-fixed-variant">
                <span class="material-symbols-outlined text-primary">verified_user</span>
                <span>{{ __('admin.google_workspace_continue') }}</span>
            </div>
            <a href="{{ route('auth.google') }}" class="w-full h-10 rounded-lg bg-primary text-on-primary text-sm font-semibold hover:bg-primary-container transition-colors inline-flex items-center justify-center gap-2 no-underline cursor-pointer">
                <span class="material-symbols-outlined text-[18px]">account_circle</span>
                {{ __('admin.sign_in_google_workspace') }}
            </a>
            <form method="post" action="{{ route('admin.login.two-factor.cancel') }}" data-loading-form="true">
                @csrf
                <button type="submit"
                    class="w-full h-10 rounded-lg border border-outline-variant bg-surface text-on-surface text-sm font-semibold hover:bg-surface-container transition-colors inline-flex items-center justify-center gap-2 cursor-pointer">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                    {{ __('admin.cancel') }}
                </button>
            </form>
        </div>
    @endif

    @unless($googleTwoFactorPending)
    <form method="post" action="{{ $superadminLogin ? route('superadmin.login') : route('admin.login') }}" data-loading-form="true" class="space-y-4">
        @csrf
        <div>
            <label for="admin-email" class="block text-xs font-semibold text-on-surface mb-1">
                {{ __('admin.email_address') }}
            </label>
            <div class="relative flex items-center rounded-lg border border-outline-variant bg-surface-container-lowest focus-within:border-primary focus-within:ring-1 focus-within:ring-primary transition-all">
                <span class="material-symbols-outlined text-outline pl-3 text-[18px]">alternate_email</span>
                <input id="admin-email" name="email" type="email" value="{{ old('email') }}" required autofocus
                    class="w-full border-0 bg-transparent px-3 py-2.5 text-sm text-on-surface placeholder:text-outline/60 focus:ring-0 outline-none font-medium"
                    placeholder="admin@company.com">
            </div>
        </div>

        <div>
            <label for="admin-password" class="block text-xs font-semibold text-on-surface mb-1">
                {{ __('admin.password') }}
            </label>
            <div class="relative flex items-center rounded-lg border border-outline-variant bg-surface-container-lowest focus-within:border-primary focus-within:ring-1 focus-within:ring-primary transition-all">
                <span class="material-symbols-outlined text-outline pl-3 text-[18px]">lock</span>
                <input id="admin-password" name="password" type="password" required
                    class="w-full border-0 bg-transparent px-3 py-2.5 text-sm text-on-surface placeholder:text-outline/60 focus:ring-0 outline-none font-mono"
                    placeholder="••••••••••••">
                <button type="button" id="toggle-admin-password" class="pr-3 text-outline hover:text-on-surface focus:outline-none cursor-pointer" aria-label="{{ __('admin.toggle_password_visibility') }}">
                    <span class="material-symbols-outlined text-[18px]">visibility</span>
                </button>
            </div>
        </div>

        <x-admin-auth.captcha />

        <div class="flex items-center justify-between pt-1">
            <label class="flex items-center gap-2 cursor-pointer select-none text-xs text-outline hover:text-on-surface transition-colors">
                <input name="remember" type="checkbox" value="1" class="rounded border-outline-variant text-primary focus:ring-primary">
                <span>{{ __('admin.remember_session') }}</span>
            </label>
            @unless($superadminLogin)
                <a href="{{ route('admin.forgot-password.page') }}" class="text-xs font-semibold text-primary hover:underline no-underline">
                    {{ __('admin.forgot_password') }}
                </a>
            @endunless
        </div>

        <button type="submit"
            class="w-full h-10 flex items-center justify-center gap-2 bg-primary hover:bg-primary-container text-on-primary text-sm font-semibold rounded shadow-sm hover:shadow transition-all duration-150 active:scale-[0.99] cursor-pointer">
            <span class="material-symbols-outlined text-[18px]">login</span>
            <span>{{ __('admin.sign_in') }}</span>
        </button>
    </form>
    @endunless

    @unless($superadminLogin)
        <p class="text-center text-xs text-outline mt-4">
            {{ __('platform.registration.no_account') }}
            <a href="{{ route('admin.register.page') }}" class="font-semibold text-primary hover:underline">{{ __('platform.registration.register_link') }}</a>
        </p>
    @endunless

    <p class="text-center text-[10px] text-outline leading-tight mt-3">
        {{ __('admin.security_warning_footer') }}
    </p>
</x-admin-auth.layout>
