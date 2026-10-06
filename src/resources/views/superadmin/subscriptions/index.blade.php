@extends('superadmin.layout', ['title' => __('platform.subscriptions.admin_title'), 'active' => 'subscriptions'])
@section('content')
    <x-superadmin.page-header :eyebrow="__('platform.nav.agents')" :title="__('platform.subscriptions.admin_title')" :description="__('platform.subscriptions.admin_description')" />
    <x-superadmin.flash />
    <section class="sa-card sa-section">
        <div class="sa-section-header">
            <div>
                <h2>{{ __('platform.subscriptions.admin_title') }}</h2>
                <p>{{ __('platform.subscriptions.count', ['count' => $subscriptions->total()]) }}</p>
            </div>
            <form method="GET" class="superadmin-actions">
                <x-superadmin.search-input :value="$filters['search']" placeholder="{{ __('platform.subscriptions.search') }}" />
                <select name="status" class="sa-input" aria-label="{{ __('superadmin.common.status') }}">
                    <option value="">{{ __('superadmin.common.all_statuses') }}</option>
                    @foreach (\App\Enums\SubscriptionStatus::cases() as $case)
                        <option value="{{ $case->value }}" @selected($filters['status'] === $case->value)>{{ __('platform.subscriptions.status.'.$case->value) }}</option>
                    @endforeach
                </select>
                <select name="package" class="sa-input" aria-label="{{ __('platform.packages.package') }}">
                    <option value="">{{ __('platform.subscriptions.all_packages') }}</option>
                    @foreach ($packages as $package)
                        <option value="{{ $package->id }}" @selected($filters['package'] === $package->id)>{{ $package->name }}</option>
                    @endforeach
                </select>
                <button class="sa-button secondary" type="submit"><span class="material-symbols-outlined text-[16px]">filter_list</span>{{ __('superadmin.common.filter') }}</button>
            </form>
        </div>
        <div class="sa-table-wrap">
            <table class="sa-table">
                <thead>
                    <tr>
                        <th class="col-primary">{{ __('platform.subscriptions.agent') }}</th>
                        <th>{{ __('platform.packages.package') }}</th>
                        <th class="text-right num">{{ __('platform.subscriptions.price') }}</th>
                        <th class="text-right num">{{ __('platform.subscriptions.room_limit') }}</th>
                        <th class="col-status">{{ __('superadmin.common.status') }}</th>
                        <th class="col-date">{{ __('platform.subscriptions.period') }}</th>
                        <th class="text-right col-actions">{{ __('superadmin.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($subscriptions as $subscription)
                        <tr>
                            <td class="col-primary">
                                <strong class="block truncate">{{ $subscription->admin?->name }}</strong>
                                <small class="block truncate text-outline"><x-superadmin.sensitive :value="$subscription->admin?->email" /></small>
                            </td>
                            <td>{{ $subscription->package?->name }}</td>
                            <td class="text-right whitespace-nowrap num">{{ \App\Support\Helpers\FormatHelper::formatCurrency($subscription->price_snapshot) }}</td>
                            <td class="text-right num">{{ $subscription->room_limit_snapshot }}</td>
                            <td class="col-status">
                                <span class="status-pill status-{{ $subscription->status->value === 'active' ? 'active' : 'inactive' }}"><span class="status-dot"></span>{{ __('platform.subscriptions.status.'.$subscription->status->value) }}</span>
                                @if ($subscription->cancel_at_period_end)
                                    <small class="block text-outline">{{ __('platform.subscriptions.ending') }}</small>
                                @endif
                            </td>
                            <td class="whitespace-nowrap col-date">{{ $subscription->starts_at->toAppDate() }} → {{ ($subscription->ended_at ?? $subscription->expires_at)?->toAppDate() ?? '—' }}</td>
                            <td class="text-right col-actions">
                                <a class="sa-button secondary" href="{{ route('superadmin.subscriptions.show', $subscription->admin_id) }}"><span class="material-symbols-outlined text-[16px]">visibility</span>{{ __('superadmin.common.details') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7"><x-superadmin.empty-state icon="workspace_premium" :title="__('platform.subscriptions.empty_title')" :description="__('platform.subscriptions.empty_description')" /></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($subscriptions->hasPages())
            {{ $subscriptions->links('components.superadmin.pagination') }}
        @endif
    </section>
@endsection
