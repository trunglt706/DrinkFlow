@extends('superadmin.layout', ['title' => __('platform.packages.title'), 'active' => 'packages'])
@section('content')
    <x-superadmin.page-header :eyebrow="__('platform.nav.plans')" :title="__('platform.packages.title')" :description="__('platform.packages.description')">
            @can('package.manage')
                <button class="sa-button" type="button" data-modal-open="create-package-modal">
                    <span class="material-symbols-outlined text-[16px]">add</span>{{ __('platform.packages.create') }}
                </button>
            @endcan
    </x-superadmin.page-header>
    <x-superadmin.flash />
    <section class="sa-card sa-section">
        <div class="sa-section-header">
            <div>
                <h2>{{ __('platform.packages.list') }}</h2>
                <p>{{ __('platform.packages.count', ['count' => $packages->count()]) }}</p>
            </div>
            <form method="GET" class="superadmin-actions">
                <select name="status" class="sa-input" aria-label="{{ __('superadmin.common.status') }}">
                    <option value="">{{ __('superadmin.common.all_statuses') }}</option>
                    @foreach (\App\Enums\PackageStatus::cases() as $case)
                        <option value="{{ $case->value }}" @selected($filters['status'] === $case->value)>{{ __('superadmin.common.'.$case->value) }}</option>
                    @endforeach
                </select>
                <button class="sa-button secondary" type="submit"><span class="material-symbols-outlined text-[16px]">filter_list</span>{{ __('superadmin.common.filter') }}</button>
            </form>
        </div>
        <div class="sa-table-wrap">
            <table class="sa-table">
                <thead>
                    <tr>
                        <th class="col-primary">{{ __('platform.packages.package') }}</th>
                        <th class="text-right num">{{ __('platform.packages.field_monthly_price') }}</th>
                        <th class="text-right num">{{ __('platform.packages.field_room_limit') }}</th>
                        <th class="col-status">{{ __('superadmin.common.status') }}</th>
                        <th class="text-right num">{{ __('platform.packages.active_subscriptions') }}</th>
                        <th class="text-right col-actions">{{ __('superadmin.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($packages as $package)
                        <tr>
                            <td class="col-primary">
                                <strong class="block">{{ $package->name }}</strong>
                                <small class="block text-outline font-mono">{{ $package->code }}</small>
                            </td>
                            <td class="text-right whitespace-nowrap num">{{ \App\Support\Helpers\FormatHelper::formatCurrency($package->monthly_price) }}</td>
                            <td class="text-right num">{{ $package->room_limit }}</td>
                            <td class="col-status"><x-superadmin.status-pill :status="$package->status" /></td>
                            <td class="text-right num">{{ $package->active_subscriptions_count }}</td>
                            <td class="text-right col-actions">
                                <a class="sa-button secondary" href="{{ route('superadmin.packages.show', $package) }}"><span class="material-symbols-outlined text-[16px]">visibility</span>{{ __('superadmin.common.details') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-superadmin.empty-state icon="inventory_2" :title="__('platform.packages.empty_title')" :description="__('platform.packages.empty_description')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @can('package.manage')
        <x-superadmin.modal id="create-package-modal" icon="inventory_2" tone="create" :title="__('platform.packages.create')" :description="__('platform.packages.create_description')" max-width="max-w-2xl">
            <form method="POST" action="{{ route('superadmin.packages.store') }}" class="space-y-4">
                @csrf
                <x-superadmin.modal-guide :title="__('superadmin.modals.package.guide_title')">
                    <li>{{ __('superadmin.modals.package.guide_price') }}</li>
                    <li>{{ __('superadmin.modals.package.guide_limit') }}</li>
                    <li>{{ __('superadmin.modals.package.guide_snapshot') }}</li>
                </x-superadmin.modal-guide>
                @include('superadmin.packages._fields', ['package' => null])
                <div class="sa-modal-footer">
                    <button type="button" class="sa-button secondary" data-modal-close>{{ __('superadmin.common.cancel') }}</button>
                    <button type="submit" class="sa-button"><span class="material-symbols-outlined text-[16px]">add</span>{{ __('platform.packages.create') }}</button>
                </div>
            </form>
        </x-superadmin.modal>
    @endcan
@endsection
