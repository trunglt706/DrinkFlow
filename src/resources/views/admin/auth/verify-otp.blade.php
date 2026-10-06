{{-- Admin password recovery, step 2/3: enter the 6-digit code sent by email (or send a new one). --}}
<x-admin-auth.layout :title="__('admin.verify_otp_title')" :centered="true">
    <div class="mx-auto w-full max-w-[440px] rounded-3xl border border-emerald-100 bg-white/95 shadow-[0_28px_80px_rgba(32,74,60,0.12)] px-5 py-7 sm:px-9 sm:py-9">
        <x-admin-auth.recovery-steps :current="2" />

        <header class="text-center mb-6">
            <span class="mx-auto mb-5 w-[64px] h-[64px] grid place-items-center rounded-[20px] text-white shadow-lg shadow-emerald-900/20"
                style="background: linear-gradient(135deg, #006948 0%, #10a374 100%);" aria-hidden="true">
                <span class="material-symbols-outlined !text-[32px]">mark_email_read</span>
            </span>
            <h1 class="text-2xl sm:text-[26px] font-bold tracking-tight text-on-surface">{{ __('admin.verify_otp_heading') }}</h1>
            <p class="mt-1.5 text-[13px] text-outline">{{ __('admin.verify_otp_subheading') }}</p>
            @if ($email !== '')
                <p class="mt-3 inline-flex max-w-full items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50/70 px-3.5 py-1.5 text-xs font-semibold text-on-surface">
                    <span class="material-symbols-outlined !text-[16px] text-primary shrink-0" aria-hidden="true">alternate_email</span>
                    <span class="truncate">{{ __('admin.otp_sent_to', ['email' => $email]) }}</span>
                </p>
            @endif
        </header>

        @if ($errors->any())
            <div class="mb-4 rounded-xl bg-error-container p-3 text-xs font-medium text-on-error-container border border-error/20 flex items-center gap-2" role="alert">
                <span class="material-symbols-outlined !text-[18px] text-error shrink-0" aria-hidden="true">error</span>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif
        @if (session('status'))
            <div class="mb-4 rounded-xl bg-emerald-50 p-3 text-xs font-medium text-emerald-800 border border-emerald-200 flex items-center gap-2" role="status">
                <span class="material-symbols-outlined !text-[18px] text-emerald-600 shrink-0" aria-hidden="true">check_circle</span>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <form method="post" action="{{ route('admin.verify-otp.submit') }}" data-loading-form="true" class="space-y-4">
            @csrf
            <div>
                <label for="admin-otp" class="block text-xs font-semibold text-on-surface mb-2 text-center">{{ __('admin.enter_6_digit_otp') }}</label>
                <input id="admin-otp" name="otp" type="text" maxlength="6" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" required autofocus
                    oninput="this.value = this.value.replace(/[^0-9]/g, '')"
                    class="block w-full h-[58px] text-center tracking-[0.55em] pl-[0.55em] text-2xl font-mono font-bold rounded-[12px] border-2 border-outline-variant bg-surface-container-lowest text-on-surface placeholder:text-outline/40 focus:border-primary focus:ring-[3px] focus:ring-primary/15 outline-none focus:!shadow-none transition-all @error('otp') !border-error @enderror"
                    placeholder="••••••">
                <p class="mt-2 flex items-center justify-center gap-1.5 text-[11px] text-outline">
                    <span class="material-symbols-outlined !text-[15px]" aria-hidden="true">schedule</span>
                    <span>{{ __('admin.validity_duration') }}: <strong class="text-on-surface">{{ __('admin.duration_15_mins') }}</strong></span>
                </p>
            </div>

            <button type="submit"
                class="w-full h-[45px] flex items-center justify-center gap-2 rounded-[10px] bg-primary hover:bg-primary-container text-on-primary text-sm font-semibold shadow-sm hover:shadow transition-all duration-150 active:scale-[0.99] cursor-pointer">
                <span class="material-symbols-outlined !text-[18px]" aria-hidden="true">lock_open</span>
                <span>{{ __('admin.verify_and_continue') }}</span>
            </button>
        </form>

        <form method="post" action="{{ route('admin.forgot-password.send') }}" data-loading-form="true" class="mt-3 text-center">
            @csrf
            <input type="hidden" name="email" value="{{ $email }}">
            <button type="submit" class="inline-flex items-center gap-1.5 text-xs font-semibold text-primary hover:underline bg-transparent border-0 cursor-pointer">
                <span class="material-symbols-outlined !text-[16px]" aria-hidden="true">refresh</span>{{ __('admin.resend_otp_code') }}
            </button>
        </form>

        <p class="mt-6 pt-5 border-t border-emerald-100 text-center">
            <a href="{{ route('admin.forgot-password.page') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline">
                <span class="material-symbols-outlined !text-[18px]" aria-hidden="true">arrow_back</span>
                <span>{{ __('admin.back') }}</span>
            </a>
        </p>
    </div>
</x-admin-auth.layout>
