{{-- Activation of a pending Agent (invited, or self-registered): the account stays pending until the sign-in
     value is chosen. Same centered stage as the registration form and its pending notice. --}}
<x-admin-auth.layout :title="__('platform.activation.page_title')" :centered="true">
    @php
        // A self-registered Agent comes from the two-step registration flow: keep its stepper.
        $selfRegistered = $agent->registered_at !== null && $agent->invited_at === null;
        $inputWrap = 'relative flex items-center h-[45px] rounded-[10px] border border-outline-variant bg-surface-container-lowest focus-within:border-primary focus-within:ring-[3px] focus-within:ring-primary/15 transition-all';
        $input = 'w-full min-w-0 border-0 bg-transparent px-2.5 text-sm text-on-surface placeholder:text-outline/60 focus:ring-0 outline-none focus:!shadow-none font-medium';
        $label = 'block text-xs font-semibold text-on-surface mb-1';
        $toggle = 'h-full px-3 grid place-items-center text-outline hover:text-on-surface focus:outline-none focus-visible:text-primary cursor-pointer';
        $rules = [
            ['length', __('admin.req_min_8_chars')],
            ['match', __('admin.req_match_confirmation')],
        ];
    @endphp

    <div class="mx-auto w-full max-w-[520px] rounded-3xl border border-emerald-100 bg-white/95 shadow-[0_28px_80px_rgba(32,74,60,0.12)] px-5 py-7 sm:px-10 sm:py-9">
        @if ($selfRegistered)
            <ol class="flex items-center justify-center gap-2.5 text-[11px] text-outline mb-7" aria-label="{{ __('platform.registration.steps_label') }}">
                <li class="flex items-center gap-2">
                    <b class="w-6 h-6 grid place-items-center rounded-full bg-emerald-100 text-primary">
                        <span class="material-symbols-outlined !text-[15px] font-bold" aria-hidden="true">check</span>
                    </b>
                    <span class="font-medium">{{ __('platform.registration.step_account') }}</span>
                </li>
                <li class="w-6 sm:w-12 h-px bg-primary/40" aria-hidden="true"></li>
                <li class="flex items-center gap-2" aria-current="step">
                    <b class="w-6 h-6 grid place-items-center rounded-full bg-primary text-on-primary text-[11px]">2</b>
                    <span class="font-semibold text-on-surface">{{ __('platform.registration.step_pending') }}</span>
                </li>
            </ol>
        @endif

        <header class="text-center">
            <span class="mx-auto mb-5 w-[68px] h-[68px] grid place-items-center rounded-[20px] text-white shadow-lg shadow-emerald-900/20"
                style="background: linear-gradient(135deg, #006948 0%, #10a374 100%);" aria-hidden="true">
                <span class="material-symbols-outlined !text-[34px]">key</span>
            </span>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 text-primary text-xs font-bold">
                <span class="material-symbols-outlined !text-[16px]" aria-hidden="true">verified_user</span>
                <span>{{ __('platform.activation.badge') }}</span>
            </span>
            <h1 class="mt-2.5 text-2xl sm:text-[28px] font-bold tracking-tight text-on-surface">{{ __('platform.activation.heading') }}</h1>
            <p class="mt-1.5 text-[13px] text-outline">{{ __('platform.activation.subheading', ['name' => $agent->name]) }}</p>
        </header>

        <section class="mt-6 rounded-2xl border border-emerald-100 bg-emerald-50/40 p-3.5 flex items-center gap-3" aria-label="{{ __('platform.activation.account_label') }}">
            <span class="w-10 h-10 shrink-0 grid place-items-center rounded-full bg-white text-primary font-bold ring-1 ring-emerald-100" aria-hidden="true">
                {{ mb_strtoupper(mb_substr($agent->name, 0, 1)) }}
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-on-surface truncate">{{ $agent->name }}</p>
                <p class="text-xs text-on-surface-variant truncate">
                    <span class="sr-only">{{ __('platform.activation.account_label') }}</span>{{ $agent->email }}
                </p>
                @if ($agent->company)
                    <p class="mt-0.5 flex items-center gap-1 text-[11px] text-outline truncate">
                        <span class="material-symbols-outlined !text-[14px]" aria-hidden="true">apartment</span>
                        <span class="sr-only">{{ __('platform.registration.field_company') }}</span>
                        <span class="truncate">{{ $agent->company }}</span>
                    </p>
                @endif
            </div>
        </section>
        <p class="mt-2 flex items-center gap-1.5 text-[11px] text-outline">
            <span class="material-symbols-outlined !text-[15px] shrink-0" aria-hidden="true">schedule</span>
            <span>{{ __('platform.activation.validity', ['hours' => $validHours]) }}</span>
        </p>

        @if ($errors->any())
            <div class="mt-5 rounded-xl bg-error-container p-3 text-xs font-medium text-on-error-container border border-error/20 flex items-center gap-2" role="alert">
                <span class="material-symbols-outlined !text-[18px] text-error shrink-0" aria-hidden="true">error</span>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <form method="post" action="{{ $submitUrl }}" data-loading-form="true" class="mt-6 space-y-4">
            @csrf
            <div>
                <label for="admin-password" class="{{ $label }}">{{ __('platform.activation.field_value') }}</label>
                <div class="{{ $inputWrap }} @error('sign_in_value') !border-error @enderror">
                    <span class="material-symbols-outlined text-outline pl-3 !text-[18px]" aria-hidden="true">lock</span>
                    <input id="admin-password" name="sign_in_value" type="password" required minlength="8" maxlength="255" autofocus
                        autocomplete="new-password" placeholder="{{ __('platform.registration.placeholder_password') }}" class="{{ $input }}"
                        aria-describedby="activate-rules activate-hint">
                    <button type="button" id="toggle-admin-password" class="{{ $toggle }}" aria-label="{{ __('admin.toggle_password_visibility') }}">
                        <span class="material-symbols-outlined !text-[18px]">visibility</span>
                    </button>
                </div>
            </div>
            <div>
                <label for="admin-password-confirmation" class="{{ $label }}">{{ __('platform.registration.field_password_confirmation') }}</label>
                <div class="{{ $inputWrap }}">
                    <span class="material-symbols-outlined text-outline pl-3 !text-[18px]" aria-hidden="true">lock_reset</span>
                    <input id="admin-password-confirmation" name="sign_in_value_confirmation" type="password" required minlength="8" maxlength="255"
                        autocomplete="new-password" placeholder="{{ __('platform.registration.placeholder_password_confirmation') }}" class="{{ $input }}">
                    <button type="button" id="toggle-admin-password-confirmation" class="{{ $toggle }}" aria-label="{{ __('admin.toggle_password_visibility') }}">
                        <span class="material-symbols-outlined !text-[18px]">visibility</span>
                    </button>
                </div>
            </div>

            {{-- Ticked live by resources/js/admin/auth.js (data-password-rules); the server enforces the same rules. --}}
            <div class="rounded-[11px] bg-surface-container-low/70 border border-outline-variant/40 p-3">
                <p class="text-[11px] font-semibold text-on-surface mb-1.5">{{ __('admin.security_requirements') }}</p>
                <ul id="activate-rules" class="space-y-1" data-password-rules data-password="#admin-password" data-confirmation="#admin-password-confirmation">
                    @foreach ($rules as [$rule, $text])
                        <li class="group flex items-center gap-1.5 text-[11px] text-on-surface-variant data-[met=true]:text-primary transition-colors" data-rule="{{ $rule }}" data-met="false">
                            <span class="material-symbols-outlined !text-[15px] text-outline group-data-[met=true]:!hidden" aria-hidden="true">radio_button_unchecked</span>
                            <span class="material-symbols-outlined !text-[15px] !hidden group-data-[met=true]:!inline-block" aria-hidden="true">check_circle</span>
                            <span>{{ $text }}</span>
                        </li>
                    @endforeach
                </ul>
                <p id="activate-hint" class="mt-2 pt-2 border-t border-outline-variant/40 text-[11px] text-outline">{{ __('platform.activation.value_hint') }}</p>
            </div>

            <button type="submit"
                class="w-full h-[45px] flex items-center justify-center gap-2 rounded-[10px] bg-primary hover:bg-primary-container text-on-primary text-sm font-semibold shadow-sm hover:shadow transition-all duration-150 active:scale-[0.99] cursor-pointer">
                <span class="material-symbols-outlined !text-[18px]" aria-hidden="true">check_circle</span>
                <span>{{ __('platform.activation.submit') }}</span>
            </button>
        </form>

        <p class="mt-6 pt-5 border-t border-emerald-100 text-center text-xs text-outline">
            {{ __('platform.activation.have_account') }}
            <a href="{{ route('admin.login.page') }}" class="font-semibold text-primary hover:underline">{{ __('admin.sign_in') }}</a>
        </p>
    </div>
</x-admin-auth.layout>
