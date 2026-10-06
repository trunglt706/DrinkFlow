{{-- Admin password recovery, step 3/3: choose the new password. The checklist is ticked live by
     resources/js/admin/auth.js (data-password-rules) and mirrors ResetPasswordRequest (MIN_LENGTH + confirmation). --}}
@php
    $minLength = \App\Http\Requests\ResetPasswordRequest::MIN_LENGTH;
    $inputWrap = 'relative flex items-center h-[45px] rounded-[10px] border border-outline-variant bg-surface-container-lowest focus-within:border-primary focus-within:ring-[3px] focus-within:ring-primary/15 transition-all';
    $input = 'w-full min-w-0 border-0 bg-transparent px-2.5 text-sm text-on-surface placeholder:text-outline/60 focus:ring-0 outline-none focus:!shadow-none font-medium';
    $toggle = 'h-full px-3 grid place-items-center text-outline hover:text-on-surface focus:outline-none focus-visible:text-primary cursor-pointer';
    $rules = [
        ['length', __('admin.req_min_chars', ['count' => $minLength])],
        ['match', __('admin.req_match_confirmation')],
    ];
@endphp
<x-admin-auth.layout :title="__('admin.reset_password_title')" :centered="true">
    <div class="mx-auto w-full max-w-[440px] rounded-3xl border border-emerald-100 bg-white/95 shadow-[0_28px_80px_rgba(32,74,60,0.12)] px-5 py-7 sm:px-9 sm:py-9">
        <x-admin-auth.recovery-steps :current="3" />

        <header class="text-center mb-6">
            <span class="mx-auto mb-5 w-[64px] h-[64px] grid place-items-center rounded-[20px] text-white shadow-lg shadow-emerald-900/20"
                style="background: linear-gradient(135deg, #006948 0%, #10a374 100%);" aria-hidden="true">
                <span class="material-symbols-outlined !text-[32px]">password</span>
            </span>
            <h1 class="text-2xl sm:text-[26px] font-bold tracking-tight text-on-surface">{{ __('admin.reset_password_heading') }}</h1>
            <p class="mt-1.5 text-[13px] text-outline">{{ __('admin.reset_password_subheading', ['count' => $minLength]) }}</p>
            @if ($email !== '')
                <p class="mt-3 inline-flex max-w-full items-center gap-2 rounded-full border border-emerald-200 bg-emerald-50/70 px-3.5 py-1.5 text-xs font-semibold text-on-surface">
                    <span class="material-symbols-outlined !text-[16px] text-primary shrink-0" aria-hidden="true">person</span>
                    <span class="truncate">{{ $email }}</span>
                </p>
            @endif
        </header>

        @if ($errors->any())
            <div class="mb-4 rounded-xl bg-error-container p-3 text-xs font-medium text-on-error-container border border-error/20 flex items-center gap-2" role="alert">
                <span class="material-symbols-outlined !text-[18px] text-error shrink-0" aria-hidden="true">error</span>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        <form method="post" action="{{ route('admin.reset-password.submit', request()->query()) }}" data-loading-form="true" class="space-y-4">
            @csrf
            <div>
                <label for="admin-password" class="block text-xs font-semibold text-on-surface mb-1">{{ __('admin.new_password') }}</label>
                <div class="{{ $inputWrap }} @error('password') !border-error @enderror">
                    <span class="material-symbols-outlined text-outline pl-3 !text-[18px]" aria-hidden="true">lock</span>
                    <input id="admin-password" name="password" type="password" required autofocus minlength="{{ $minLength }}" autocomplete="new-password"
                        class="{{ $input }}" placeholder="••••••••••••" aria-describedby="reset-rules">
                    <button type="button" id="toggle-admin-password" class="{{ $toggle }}" aria-label="{{ __('admin.toggle_password_visibility') }}">
                        <span class="material-symbols-outlined !text-[18px]">visibility</span>
                    </button>
                </div>
            </div>
            <div>
                <label for="admin-password-confirmation" class="block text-xs font-semibold text-on-surface mb-1">{{ __('admin.confirm_new_password') }}</label>
                <div class="{{ $inputWrap }}">
                    <span class="material-symbols-outlined text-outline pl-3 !text-[18px]" aria-hidden="true">lock_reset</span>
                    <input id="admin-password-confirmation" name="password_confirmation" type="password" required minlength="{{ $minLength }}" autocomplete="new-password"
                        class="{{ $input }}" placeholder="••••••••••••">
                    <button type="button" id="toggle-admin-password-confirmation" class="{{ $toggle }}" aria-label="{{ __('admin.toggle_password_visibility') }}">
                        <span class="material-symbols-outlined !text-[18px]">visibility</span>
                    </button>
                </div>
            </div>

            <div class="rounded-[11px] bg-surface-container-low/70 border border-outline-variant/40 p-3">
                <p class="text-[11px] font-semibold text-on-surface mb-1.5">{{ __('admin.security_requirements') }}</p>
                <ul id="reset-rules" class="space-y-1" data-password-rules data-password="#admin-password" data-confirmation="#admin-password-confirmation">
                    @foreach ($rules as [$rule, $text])
                        <li class="group flex items-center gap-1.5 text-[11px] text-on-surface-variant data-[met=true]:text-primary transition-colors" data-rule="{{ $rule }}" data-met="false">
                            <span class="material-symbols-outlined !text-[15px] text-outline group-data-[met=true]:!hidden" aria-hidden="true">radio_button_unchecked</span>
                            <span class="material-symbols-outlined !text-[15px] !hidden group-data-[met=true]:!inline-block" aria-hidden="true">check_circle</span>
                            <span>{{ $text }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <button type="submit"
                class="w-full h-[45px] flex items-center justify-center gap-2 rounded-[10px] bg-primary hover:bg-primary-container text-on-primary text-sm font-semibold shadow-sm hover:shadow transition-all duration-150 active:scale-[0.99] cursor-pointer">
                <span class="material-symbols-outlined !text-[18px]" aria-hidden="true">check_circle</span>
                <span>{{ __('admin.save_new_password') }}</span>
            </button>
        </form>

        <p class="mt-6 pt-5 border-t border-emerald-100 text-center">
            <a href="{{ route('admin.login.page') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline">
                <span class="material-symbols-outlined !text-[18px]" aria-hidden="true">arrow_back</span>
                <span>{{ __('admin.back_to_login') }}</span>
            </a>
        </p>
    </div>
</x-admin-auth.layout>
