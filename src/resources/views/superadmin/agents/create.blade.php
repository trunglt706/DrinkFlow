@extends('superadmin.layout', ['title' => __('platform.agents.create_title'), 'active' => 'agents'])
@section('content')
    @php
        $input = 'sa-input !min-w-0 w-full';
        $label = 'block text-xs font-semibold text-on-surface mb-1';
    @endphp
    <x-superadmin.page-header :back="route('superadmin.agents.index')" :back-label="__('platform.agents.title')" :title="__('platform.agents.create_title')" :description="__('platform.agents.create_description')">
    </x-superadmin.page-header>
    <x-superadmin.flash />

    <section class="sa-card sa-section">
        <div class="sa-section-header">
            <div>
                <h2>{{ __('platform.agents.invitation_card_title') }}</h2>
                <p>{{ __('platform.agents.invitation_card_hint') }}</p>
            </div>
        </div>

        @if ($errors->any())
            <div class="rounded-xl bg-error-container p-3 text-xs font-medium text-on-error-container border border-error/20 mb-4" role="alert">
                {{ $errors->first() }}
            </div>
        @endif

        @if ($packages->isEmpty())
            <p class="text-sm text-outline">{{ __('platform.registration.no_packages') }}</p>
        @else
            <form method="POST" action="{{ route('superadmin.agents.store') }}" class="space-y-4" data-loading-form="true">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="agent-name" class="{{ $label }}">{{ __('platform.registration.field_name') }} <span class="text-error">*</span></label>
                        <input id="agent-name" name="name" type="text" value="{{ old('name') }}" maxlength="255" autocomplete="organization-title" required class="{{ $input }}">
                    </div>
                    <div>
                        <label for="agent-company" class="{{ $label }}">{{ __('platform.registration.field_company') }}</label>
                        <input id="agent-company" name="company" type="text" value="{{ old('company') }}" maxlength="255" autocomplete="organization" class="{{ $input }}">
                    </div>
                    <div>
                        <label for="agent-email" class="{{ $label }}">{{ __('platform.registration.field_email') }} <span class="text-error">*</span></label>
                        <input id="agent-email" name="email" type="email" value="{{ old('email') }}" maxlength="255" autocomplete="email" required class="{{ $input }}">
                    </div>
                    <div>
                        <label for="agent-phone" class="{{ $label }}">{{ __('platform.registration.field_phone') }}</label>
                        <input id="agent-phone" name="phone" type="tel" value="{{ old('phone') }}" maxlength="30" autocomplete="tel" class="{{ $input }}">
                    </div>
                </div>

                <fieldset>
                    <legend class="{{ $label }}">{{ __('platform.registration.field_package') }} <span class="text-error">*</span></legend>
                    <p class="text-xs text-outline mb-2">{{ __('platform.agents.package_hint') }}</p>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        @foreach ($packages as $package)
                            <label class="flex items-start gap-3 rounded-xl border border-outline-variant bg-surface-container-lowest p-3 cursor-pointer hover:border-primary/60 has-[:checked]:border-primary has-[:checked]:ring-1 has-[:checked]:ring-primary transition-all">
                                <input type="radio" name="package_id" value="{{ $package->id }}" required class="mt-1 text-primary focus:ring-primary"
                                    @checked((string) old('package_id', $packages->first()?->id) === (string) $package->id)>
                                <span class="min-w-0">
                                    <strong class="block text-sm text-on-surface">{{ $package->name }}</strong>
                                    <span class="block text-xs font-semibold text-primary">{{ __('platform.packages.per_month', ['price' => \App\Support\Helpers\FormatHelper::formatCurrency($package->monthly_price)]) }}</span>
                                    <span class="block text-xs text-outline">{{ __('platform.packages.rooms_limit', ['count' => $package->room_limit]) }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="sa-form-actions">
                    <a href="{{ route('superadmin.agents.index') }}" class="sa-button secondary">{{ __('superadmin.common.cancel') }}</a>
                    <button type="submit" class="sa-button">
                        <span class="material-symbols-outlined text-[16px]">person_add</span>{{ __('platform.agents.create_submit') }}
                    </button>
                </div>
            </form>
        @endif
    </section>
@endsection
