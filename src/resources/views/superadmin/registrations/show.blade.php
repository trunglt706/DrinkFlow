@extends('superadmin.layout', ['title' => $registration->name, 'active' => 'registrations'])
@section('content')
    @php
        $fieldLabel = 'flex flex-col gap-1.5 text-[13px] font-semibold text-on-surface';
        $fieldInput = 'sa-input !min-w-0 w-full';
        $isPending = $registration->status === \App\Enums\AdminStatus::Pending;
        $rows = [
            [__('platform.registration.field_name'), $registration->name],
            [__('platform.registration.field_company'), $registration->company ?: '—'],
            [__('platform.registrations.registered_at'), $registration->registered_at?->toAppDateTime()],
            [__('platform.registrations.email_status'), $registration->hasVerifiedEmail()
                ? __('platform.registrations.email_verified_on', ['date' => $registration->email_verified_at->toAppDateTime()])
                : __('platform.registrations.email_unverified')],
        ];
    @endphp
    <x-superadmin.page-header :back="route('superadmin.registrations.index')" :back-label="__('platform.registrations.title')" :title="$registration->name">
        <x-slot:meta><p class="sa-heading-meta"><x-superadmin.sensitive :value="$registration->email" /> · <x-superadmin.status-pill :status="$registration->status" /></p></x-slot:meta>
    </x-superadmin.page-header>
    <x-superadmin.flash />

    <div class="sa-split">
        <section class="sa-card sa-section">
            <div class="sa-section-header">
                <div><h2>{{ __('platform.registrations.applicant') }}</h2></div>
            </div>
            <dl class="space-y-3 text-sm">
                @foreach ($rows as [$label, $value])
                    <div class="flex justify-between gap-3"><dt class="text-outline">{{ $label }}</dt><dd class="font-semibold text-right break-all">{{ $value }}</dd></div>
                @endforeach
                {{-- Personal data: masked, revealed one value at a time. --}}
                <div class="flex justify-between gap-3"><dt class="text-outline">{{ __('platform.registration.field_email') }}</dt><dd class="font-semibold text-right break-all"><x-superadmin.sensitive :value="$registration->email" /></dd></div>
                <div class="flex justify-between gap-3"><dt class="text-outline">{{ __('platform.registration.field_phone') }}</dt><dd class="font-semibold text-right break-all"><x-superadmin.sensitive :value="$registration->phone" type="phone" /></dd></div>
                <div class="flex justify-between gap-3">
                    <dt class="text-outline">{{ __('platform.registrations.requested_package') }}</dt>
                    <dd class="font-semibold text-right">
                        @if ($registration->requestedPackage)
                            {{ $registration->requestedPackage->name }}
                            <small class="block text-outline font-normal">{{ __('platform.packages.per_month', ['price' => \App\Support\Helpers\FormatHelper::formatCurrency($registration->requestedPackage->monthly_price)]) }} · {{ __('platform.packages.rooms_limit', ['count' => $registration->requestedPackage->room_limit]) }}</small>
                        @else
                            —
                        @endif
                    </dd>
                </div>
                @unless ($isPending)
                    <div class="flex justify-between gap-3"><dt class="text-outline">{{ __('platform.registrations.reviewed_by') }}</dt><dd class="text-right">{{ $registration->reviewedBy?->name ?? '—' }} · {{ $registration->reviewed_at?->toAppDateTime() }}</dd></div>
                    @if ($registration->rejection_reason)
                        <div class="flex justify-between gap-3"><dt class="text-outline">{{ __('platform.registrations.reason') }}</dt><dd class="text-right">{{ $registration->rejection_reason }}</dd></div>
                    @endif
                @endunless
            </dl>
        </section>

        @if ($isPending)
            <div class="sa-stack">
                @if ($firstInvoice)
                {{-- Pay-to-activate registration: no approval, the package starts when this invoice is paid. --}}
                <section class="sa-card sa-section" data-registration-awaiting-payment>
                    <div class="sa-section-header">
                        <div>
                            <h2>{{ __('platform.registrations.awaiting_payment_title') }}</h2>
                            <p>{{ __('platform.registrations.activates_on_payment') }}</p>
                        </div>
                    </div>
                    <dl class="grid gap-2 text-[13px]">
                        <div class="flex justify-between gap-3"><dt class="text-outline">{{ __('platform.billing.invoice') }}</dt><dd class="font-mono font-semibold">{{ $firstInvoice->number }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-outline">{{ __('platform.emails.cycle_label') }}</dt><dd>{{ \App\Services\Subscription\SubscriptionService::cycleLabel((int) $firstInvoice->period_start->diffInMonths($firstInvoice->period_end, true)) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-outline">{{ __('platform.billing.total') }}</dt><dd class="font-semibold">{{ \App\Support\Helpers\FormatHelper::formatCurrency((int) $firstInvoice->total) }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-outline">{{ __('platform.billing.due_at') }}</dt><dd>{{ $firstInvoice->due_at->toAppDate() }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-outline">{{ __('validation.attributes.status') }}</dt><dd>{{ __('platform.billing.status.'.$firstInvoice->status->value) }}</dd></div>
                    </dl>
                    @if ($canViewBilling)
                        <div class="mt-3 flex justify-end">
                            <a href="{{ route('superadmin.billing.agent', $registration) }}" class="sa-button"><span class="material-symbols-outlined text-[16px]">receipt_long</span>{{ __('platform.registrations.open_billing') }}</a>
                        </div>
                    @endif
                </section>
                @else
                <section class="sa-card sa-section">
                    <div class="sa-section-header">
                        <div>
                            <h2>{{ __('platform.registrations.approve') }}</h2>
                            <p>{{ __('platform.registrations.approve_description') }}</p>
                        </div>
                    </div>
                    @unless ($registration->hasVerifiedEmail())
                        <p class="sa-notice error is-visible mb-3">{{ __('platform.registrations.email_not_verified') }}</p>
                    @endunless
                    <form method="POST" action="{{ route('superadmin.registrations.approve', $registration) }}" class="space-y-4"
                        data-confirm-tone="success" data-confirm-description="{{ __('superadmin.modals.confirm.approve_registration') }}" data-confirm-icon="how_to_reg" data-confirm="{{ __('platform.registrations.approve_confirm', ['name' => $registration->name]) }}">
                        @csrf
                        <fieldset class="space-y-4" @disabled(! $registration->hasVerifiedEmail())>
                            <label class="{{ $fieldLabel }}">{{ __('platform.registration.field_package') }}
                                <select name="package_id" class="{{ $fieldInput }}">
                                    @foreach ($packages as $package)
                                        <option value="{{ $package->id }}" @selected((int) old('package_id', $registration->requested_package_id) === $package->id)>
                                            {{ $package->name }} — {{ __('platform.packages.per_month', ['price' => \App\Support\Helpers\FormatHelper::formatCurrency($package->monthly_price)]) }} · {{ __('platform.packages.rooms_limit', ['count' => $package->room_limit]) }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                            @if ($managedApprover)
                                <p class="text-xs text-outline">{{ __('platform.registrations.managed_approver_notice') }}</p>
                            @else
                                <label class="{{ $fieldLabel }}">{{ __('platform.registrations.manager') }}
                                    <select name="manager_superadmin_id" class="{{ $fieldInput }}">
                                        <option value="">{{ __('platform.registrations.no_manager') }}</option>
                                        @foreach ($managers as $manager)
                                            <option value="{{ $manager->id }}" @selected((string) old('manager_superadmin_id') === (string) $manager->id)>{{ $manager->name }} ({{ app(\App\Services\Security\SensitiveDataService::class)->mask($manager->email, 'email') }})</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif
                            <div class="flex justify-end">
                                <button type="submit" class="sa-button"><span class="material-symbols-outlined text-[16px]">check_circle</span>{{ __('platform.registrations.approve') }}</button>
                            </div>
                        </fieldset>
                    </form>
                </section>
                @endif

                <section class="sa-card sa-section">
                    <div class="sa-section-header">
                        <div>
                            <h2>{{ __('platform.registrations.reject') }}</h2>
                            <p>{{ __('platform.registrations.reject_description') }}</p>
                        </div>
                    </div>
                    <form method="POST" action="{{ route('superadmin.registrations.reject', $registration) }}" class="space-y-4"
                        data-confirm-tone="warning" data-confirm-description="{{ __('superadmin.modals.confirm.reject_registration') }}" data-confirm-icon="block" data-confirm="{{ __('platform.registrations.reject_confirm', ['name' => $registration->name]) }}">
                        @csrf
                        <label class="{{ $fieldLabel }}">{{ __('platform.registrations.reason') }}
                            <textarea name="reason" rows="3" required minlength="3" maxlength="1000" class="{{ $fieldInput }}">{{ old('reason') }}</textarea>
                        </label>
                        <div class="flex justify-end">
                            <button type="submit" class="sa-button danger"><span class="material-symbols-outlined text-[16px]">block</span>{{ __('platform.registrations.reject') }}</button>
                        </div>
                    </form>
                </section>
            </div>
        @endif
    </div>
@endsection
