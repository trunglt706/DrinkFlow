{{-- Public Agent registration (/admin/register): centered card of documents/pages/admin/drinkflow-register-options.html
     (option 2). The first invoice is issued at once; the package starts when it is paid. --}}
<x-admin-auth.layout :title="__('platform.registration.page_title')" :centered="true">
    @php
        $inputWrap = 'relative flex items-center h-[43px] rounded-[10px] border border-outline-variant bg-surface-container-lowest focus-within:border-primary focus-within:ring-[3px] focus-within:ring-primary/15 transition-all';
        $input = 'w-full min-w-0 border-0 bg-transparent px-2.5 text-sm text-on-surface placeholder:text-outline/60 focus:ring-0 outline-none';
        $label = 'block text-xs font-semibold text-on-surface mb-1';
        $panel = 'rounded-2xl border border-emerald-100 bg-white/85 p-4 sm:p-[18px] shadow-[0_5px_15px_rgba(23,76,53,0.06)]';
        $sectionTitle = 'text-xs font-bold tracking-wide uppercase text-on-surface mb-3';
        $fields = [
            ['name', 'text', 'person', 'field_name', true, 'name'],
            ['company', 'text', 'apartment', 'field_company', false, 'organization'],
            ['email', 'email', 'alternate_email', 'field_email', true, 'email'],
            ['phone', 'tel', 'call', 'field_phone', true, 'tel'],
        ];
        // The first offered package is preselected; after a failed submit the visitor's choice is kept.
        $selectedPackage = (string) old('package_id', $packages->first()?->id);
        // Billing cycle of the first invoice and of every renewal (6 months by default).
        $periodQuantity = (int) old('period_quantity', \App\Services\Subscription\SubscriptionService::DEFAULT_CYCLE_QUANTITY);
        $periodUnit = (string) old('period_unit', \App\Services\Subscription\SubscriptionService::CYCLE_UNIT_MONTH);
    @endphp

    <div class="relative rounded-3xl border border-emerald-100 bg-white/95 shadow-[0_28px_80px_rgba(32,74,60,0.12)] px-4 py-6 sm:px-10 sm:py-8">
        <header class="text-center mb-6">
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 text-primary text-xs font-bold">
                <span class="material-symbols-outlined text-[16px]">storefront</span>
                <span>{{ __('platform.registration.badge') }}</span>
            </span>
            <h1 class="mt-2.5 text-2xl sm:text-[30px] font-bold tracking-tight text-on-surface">{{ __('platform.registration.heading') }}</h1>
            <p class="mt-1.5 text-[13px] text-outline">{{ __('platform.registration.subheading') }}</p>
            <ol class="mt-5 flex items-center justify-center gap-2.5 text-[11px] text-outline" aria-label="{{ __('platform.registration.steps_label') }}">
                <li class="flex items-center gap-2" aria-current="step">
                    <b class="w-6 h-6 grid place-items-center rounded-full bg-primary text-on-primary text-[11px]">1</b>
                    <span class="font-semibold text-on-surface">{{ __('platform.registration.step_account') }}</span>
                </li>
                <li class="w-6 sm:w-12 h-px bg-emerald-200" aria-hidden="true"></li>
                <li class="flex items-center gap-2">
                    <b class="w-6 h-6 grid place-items-center rounded-full border border-emerald-200 text-[11px]">2</b>
                    <span>{{ __('platform.registration.step_pending') }}</span>
                </li>
            </ol>
        </header>

        @if ($errors->any())
            <div class="mb-4 rounded-xl bg-error-container p-3 text-xs font-medium text-on-error-container border border-error/20 flex items-center gap-2" role="alert">
                <span class="material-symbols-outlined text-[18px] text-error shrink-0">error</span>
                <span>{{ $errors->first() }}</span>
            </div>
        @endif

        @if ($packages->isEmpty())
            <div class="rounded-xl border border-outline-variant bg-surface-container-low p-4 text-sm text-on-surface-variant">
                {{ __('platform.registration.no_packages') }}
            </div>
        @else
            <form method="post" action="{{ route('admin.register') }}" data-loading-form="true">
                @csrf
                <div class="grid grid-cols-1 lg:grid-cols-[1.1fr_0.9fr] gap-4 items-stretch">
                    <section class="{{ $panel }}" aria-labelledby="register-info-title">
                        <h2 id="register-info-title" class="{{ $sectionTitle }}">{{ __('platform.registration.section_info') }}</h2>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            @foreach ($fields as [$field, $type, $icon, $labelKey, $required, $autocomplete])
                                <div>
                                    <label for="register-{{ $field }}" class="{{ $label }}">{{ __('platform.registration.'.$labelKey) }}@unless ($required) <span class="font-normal text-outline">({{ __('platform.registration.optional') }})</span>@endunless</label>
                                    <div class="{{ $inputWrap }} @error($field) !border-error @enderror">
                                        <span class="material-symbols-outlined text-outline pl-3 text-[18px]">{{ $icon }}</span>
                                        <input id="register-{{ $field }}" name="{{ $field }}" type="{{ $type }}" value="{{ old($field) }}" maxlength="255"
                                            autocomplete="{{ $autocomplete }}" placeholder="{{ __('platform.registration.placeholder_'.$field) }}" @required($required) class="{{ $input }}">
                                    </div>
                                </div>
                            @endforeach
                            <p class="sm:col-span-2 flex gap-2 rounded-[11px] bg-emerald-50 p-3 text-[11px] text-on-surface-variant">
                                <span class="material-symbols-outlined text-[16px] text-primary shrink-0">mark_email_read</span>
                                <span>{{ __('platform.registration.password_by_email_hint') }}</span>
                            </p>
                            <x-admin-auth.captcha variant="field" class="sm:col-span-2" />
                        </div>
                    </section>

                    <section class="{{ $panel }} flex flex-col" aria-labelledby="register-package-title">
                        <h2 id="register-package-title" class="{{ $sectionTitle }}">{{ __('platform.registration.section_package') }}</h2>
                        <fieldset class="grid gap-2">
                            <legend class="sr-only">{{ __('platform.registration.field_package') }}</legend>
                            @foreach ($packages as $package)
                                <label class="grid grid-cols-[18px_1fr_auto] items-center gap-2.5 min-h-[61px] rounded-[11px] border border-outline-variant bg-white px-3 py-2.5 cursor-pointer hover:border-primary/60 has-[:checked]:border-primary has-[:checked]:ring-1 has-[:checked]:ring-primary has-[:checked]:bg-emerald-50/50 transition-all">
                                    <input type="radio" name="package_id" value="{{ $package->id }}" required class="m-0 text-primary focus:ring-primary"
                                        @checked($selectedPackage === (string) $package->id)>
                                    <span class="min-w-0">
                                        <strong class="block text-[13px] text-on-surface">{{ $package->name }}</strong>
                                        <small class="block text-[11px] text-outline">{{ __('platform.packages.rooms_limit', ['count' => $package->room_limit]) }}</small>
                                        @if ($package->description)
                                            <small class="block text-[11px] text-on-surface-variant mt-0.5">{{ $package->description }}</small>
                                        @endif
                                    </span>
                                    <span class="text-[13px] font-bold text-primary whitespace-nowrap">
                                        {{ $package->monthly_price > 0
                                            ? __('platform.packages.per_month', ['price' => \App\Support\Helpers\FormatHelper::formatCurrency($package->monthly_price)])
                                            : __('platform.registration.price_free') }}
                                    </span>
                                </label>
                            @endforeach
                        </fieldset>
                        <div class="mt-3" data-register-cycle>
                            <span id="register-cycle-label" class="{{ $label }}">{{ __('platform.registration.field_cycle') }}</span>
                            <div class="grid grid-cols-[1fr_1.2fr] gap-2" role="group" aria-labelledby="register-cycle-label">
                                <div class="{{ $inputWrap }} @error('period_quantity') !border-error @enderror">
                                    <span class="material-symbols-outlined text-outline pl-3 text-[18px]">event_repeat</span>
                                    <input id="register-period-quantity" name="period_quantity" type="number" min="1" max="24" step="1" required
                                        value="{{ $periodQuantity }}" aria-label="{{ __('platform.registration.field_cycle_quantity') }}" class="{{ $input }}">
                                </div>
                                <div class="{{ $inputWrap }} @error('period_unit') !border-error @enderror">
                                    <select id="register-period-unit" name="period_unit" required aria-label="{{ __('platform.registration.field_cycle_unit') }}"
                                        class="{{ $input }} cursor-pointer">
                                        <option value="{{ \App\Services\Subscription\SubscriptionService::CYCLE_UNIT_MONTH }}" @selected($periodUnit === \App\Services\Subscription\SubscriptionService::CYCLE_UNIT_MONTH)>{{ __('platform.cycle.unit_month') }}</option>
                                        <option value="{{ \App\Services\Subscription\SubscriptionService::CYCLE_UNIT_YEAR }}" @selected($periodUnit === \App\Services\Subscription\SubscriptionService::CYCLE_UNIT_YEAR)>{{ __('platform.cycle.unit_year') }}</option>
                                    </select>
                                </div>
                            </div>
                            <small class="mt-1 block text-[11px] text-outline">{{ __('platform.registration.cycle_hint') }}</small>
                        </div>
                        <p class="mt-3 flex gap-2 rounded-[11px] bg-emerald-50 p-3 text-[11px] text-on-surface-variant">
                            <span class="material-symbols-outlined text-[16px] text-primary shrink-0">verified</span>
                            <span><b class="text-primary">{{ __('platform.registration.package_note_title') }}</b> {{ __('platform.registration.package_hint') }}</span>
                        </p>
                    </section>
                </div>

                <footer class="mt-5 grid grid-cols-1 sm:grid-cols-[1fr_240px] gap-3 items-center">
                    <button type="submit"
                        class="h-[49px] flex items-center justify-center gap-2 rounded-[10px] bg-primary hover:bg-primary-container text-on-primary text-sm font-bold shadow-[0_7px_15px_rgba(0,110,80,0.17)] transition-all active:scale-[0.99] cursor-pointer">
                        <span>{{ __('platform.registration.submit') }}</span>
                        <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                    </button>
                    <span class="flex items-center justify-center sm:justify-start gap-1.5 text-[11px] text-outline">
                        <span class="material-symbols-outlined text-[15px]">shield_lock</span>
                        {{ __('platform.registration.secure_note') }}
                    </span>
                </footer>
            </form>
        @endif

        <p class="mt-4 pt-3 border-t border-emerald-50 text-center text-xs text-outline">
            {{ __('platform.registration.have_account') }}
            <a href="{{ route('admin.login.page') }}" class="font-semibold text-primary hover:underline">{{ __('platform.registration.sign_in_link') }}</a>
        </p>
    </div>
</x-admin-auth.layout>
