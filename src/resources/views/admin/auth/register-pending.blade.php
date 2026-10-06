{{-- Shown after registering (step 2 of /admin/register): activate the account from the email, pay the first
     invoice, then the package starts. Same centered stage and stepper as the registration form. --}}
<x-admin-auth.layout :title="__('platform.registration.pending_title')" :centered="true">
    @php
        $steps = [
            ['mark_email_read', 'step_verify_title', 'step_verify'],
            ['receipt_long', 'step_review_title', 'step_review'],
            ['rocket_launch', 'step_sign_in_title', 'step_sign_in'],
        ];
    @endphp

    <div class="mx-auto w-full max-w-[580px] rounded-3xl border border-emerald-100 bg-white/95 shadow-[0_28px_80px_rgba(32,74,60,0.12)] px-5 py-7 sm:px-10 sm:py-9">
        <ol class="flex items-center justify-center gap-2.5 text-[11px] text-outline mb-7" aria-label="{{ __('platform.registration.steps_label') }}">
            <li class="flex items-center gap-2">
                <b class="w-6 h-6 grid place-items-center rounded-full bg-emerald-100 text-primary">
                    <span class="material-symbols-outlined text-[15px] font-bold" aria-hidden="true">check</span>
                </b>
                <span class="font-medium">{{ __('platform.registration.step_account') }}</span>
            </li>
            <li class="w-6 sm:w-12 h-px bg-primary/40" aria-hidden="true"></li>
            <li class="flex items-center gap-2" aria-current="step">
                <b class="w-6 h-6 grid place-items-center rounded-full bg-primary text-on-primary text-[11px]">2</b>
                <span class="font-semibold text-on-surface">{{ __('platform.registration.step_pending') }}</span>
            </li>
        </ol>

        <header class="text-center">
            <div class="relative mx-auto mb-5 w-[76px] h-[76px]" aria-hidden="true">
                <span class="absolute inset-0 rounded-[22px] bg-primary/15 animate-ping [animation-duration:2.4s]"></span>
                <span class="relative w-full h-full grid place-items-center rounded-[22px] text-white shadow-lg shadow-emerald-900/20"
                    style="background: linear-gradient(135deg, #006948 0%, #10a374 100%);">
                    <span class="material-symbols-outlined !text-[38px]">mark_email_unread</span>
                </span>
            </div>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 text-primary text-xs font-bold">
                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">task_alt</span>
                <span>{{ __('platform.registration.pending_badge') }}</span>
            </span>
            <h1 class="mt-2.5 text-2xl sm:text-[28px] font-bold tracking-tight text-on-surface">{{ __('platform.registration.pending_title') }}</h1>

            @if ($email !== '')
                <p class="mt-1.5 text-[13px] text-outline">{{ __('platform.registration.pending_email_label') }}</p>
                <p class="mt-2 inline-flex max-w-full items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50/70 px-3.5 py-1.5 text-sm font-semibold text-on-surface">
                    <span class="material-symbols-outlined text-[18px] text-primary shrink-0" aria-hidden="true">alternate_email</span>
                    <span class="truncate">{{ $email }}</span>
                </p>
            @else
                <p class="mt-1.5 text-[13px] text-outline">{{ __('platform.registration.pending_generic') }}</p>
            @endif
        </header>

        @if (session('status'))
            <div class="mt-6 rounded-xl bg-emerald-50 p-3 text-xs font-medium text-emerald-800 border border-emerald-200 flex items-center gap-2" role="status">
                <span class="material-symbols-outlined text-[18px] text-emerald-600 shrink-0" aria-hidden="true">check_circle</span>
                <span>{{ session('status') }}</span>
            </div>
        @endif
        @if ($errors->any())
            <div class="mt-6 rounded-xl bg-error-container p-3 text-xs font-medium text-on-error-container border border-error/20 flex items-center gap-2" role="alert">
                <span class="material-symbols-outlined text-[18px] text-error shrink-0" aria-hidden="true">error</span>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <section class="mt-7" aria-labelledby="pending-next-title">
            <h2 id="pending-next-title" class="text-xs font-bold tracking-wide uppercase text-on-surface mb-3">{{ __('platform.registration.next_steps') }}</h2>
            <ol class="rounded-2xl border border-emerald-100 bg-white p-4 sm:p-5 shadow-[0_5px_15px_rgba(23,76,53,0.06)]">
                @foreach ($steps as [$icon, $titleKey, $bodyKey])
                    <li class="relative flex gap-3.5 {{ $loop->last ? '' : 'pb-5' }}">
                        @unless ($loop->last)
                            <span class="absolute left-[17px] top-10 bottom-1 w-px bg-emerald-100" aria-hidden="true"></span>
                        @endunless
                        <span @class([
                            'relative w-9 h-9 shrink-0 grid place-items-center rounded-xl',
                            'bg-primary text-on-primary shadow-sm shadow-emerald-900/20' => $loop->first,
                            'bg-emerald-50 text-primary ring-1 ring-emerald-100' => ! $loop->first,
                        ])>
                            <span class="material-symbols-outlined text-[19px]" aria-hidden="true">{{ $icon }}</span>
                        </span>
                        <div class="min-w-0 pt-0.5">
                            <p class="text-sm font-semibold text-on-surface">
                                <span class="font-mono text-[11px] text-outline mr-1">0{{ $loop->iteration }}</span>
                                {{ __('platform.registration.'.$titleKey) }}
                            </p>
                            <p class="mt-0.5 text-xs leading-relaxed text-on-surface-variant">{{ __('platform.registration.'.$bodyKey) }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>

        <p class="mt-4 flex gap-2 rounded-[11px] bg-amber-50 border border-amber-100 p-3 text-[11px] leading-relaxed text-amber-900">
            <span class="material-symbols-outlined text-[16px] text-amber-600 shrink-0" aria-hidden="true">lightbulb</span>
            <span>{{ __('platform.registration.pending_spam_hint') }}</span>
        </p>

        <form method="post" action="{{ route('admin.register.resend') }}" data-loading-form="true"
            class="mt-6 rounded-2xl bg-surface-container-low/70 border border-outline-variant/50 p-4 sm:p-5">
            @csrf
            <p class="text-sm font-semibold text-on-surface">{{ __('platform.registration.resend_title') }}</p>
            <label for="resend-email" class="block mt-0.5 mb-3 text-xs text-on-surface-variant">{{ __('platform.registration.resend_hint') }}</label>
            <div class="flex flex-col sm:flex-row gap-2">
                <div class="relative flex flex-1 min-w-0 items-center h-[43px] rounded-[10px] border border-outline-variant bg-surface-container-lowest focus-within:border-primary focus-within:ring-[3px] focus-within:ring-primary/15 transition-all @error('email') !border-error @enderror">
                    <span class="material-symbols-outlined text-outline pl-3 text-[18px]" aria-hidden="true">alternate_email</span>
                    <input id="resend-email" name="email" type="email" required maxlength="255" autocomplete="email"
                        value="{{ old('email', $email) }}" placeholder="{{ __('platform.registration.placeholder_email') }}"
                        class="w-full min-w-0 border-0 bg-transparent px-2.5 text-sm text-on-surface placeholder:text-outline/60 focus:ring-0 outline-none focus:!shadow-none">
                </div>
                <button type="submit"
                    class="shrink-0 h-[43px] px-5 inline-flex items-center justify-center gap-2 rounded-[10px] bg-primary hover:bg-primary-container text-on-primary text-sm font-semibold shadow-sm hover:shadow transition-all duration-150 active:scale-[0.99] cursor-pointer">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">send</span>
                    <span>{{ __('platform.registration.resend') }}</span>
                </button>
            </div>
        </form>

        <div class="mt-6 pt-5 border-t border-emerald-100 text-center">
            <a href="{{ route('admin.login.page') }}"
                class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>
                <span>{{ __('platform.registration.back_to_login') }}</span>
            </a>
        </div>
    </div>
</x-admin-auth.layout>
