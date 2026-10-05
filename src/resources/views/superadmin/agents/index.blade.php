@extends('superadmin.layout', ['title' => __('platform.agents.title'), 'active' => 'agents'])
@section('content')
    @php
        $money = static fn (int $amount): string => \App\Support\Helpers\FormatHelper::formatCurrency($amount);
    @endphp
    <x-superadmin.page-header :eyebrow="__('platform.nav.agents')" :title="__('platform.agents.title')" :description="__('platform.agents.description')">
            @can('agent.manage')
                <a class="sa-button" href="{{ route('superadmin.agents.create') }}"><span class="material-symbols-outlined text-[16px]">person_add</span>{{ __('platform.agents.create_action') }}</a>
            @endcan
    </x-superadmin.page-header>
    <x-superadmin.flash />
    <section class="sa-card sa-section">
        <div class="sa-section-header">
            <div>
                <h2>{{ __('platform.agents.list') }}</h2>
                <p>{{ __('superadmin.common.accounts_count', ['count' => $agents->total()]) }}</p>
            </div>
            <form method="GET" class="superadmin-actions">
                <x-superadmin.search-input :value="$filters['search']" placeholder="{{ __('platform.registrations.search') }}" />
                <select name="status" class="sa-input" aria-label="{{ __('superadmin.common.status') }}">
                    <option value="">{{ __('platform.agents.all_active_statuses') }}</option>
                    @foreach (\App\Enums\AdminStatus::cases() as $case)
                        <option value="{{ $case->value }}" @selected($filters['status'] === $case->value)>{{ __('superadmin.common.'.$case->value) }}</option>
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
                        <th class="col-status">{{ __('superadmin.common.status') }}</th>
                        <th>{{ __('platform.packages.package') }}</th>
                        <th class="text-right num">{{ __('platform.rooms.quota_title') }}</th>
                        <th class="text-right num">{{ __('platform.billing.remaining') }}</th>
                        <th>{{ __('platform.agents.primary_manager') }}</th>
                        <th class="text-right col-actions">{{ __('superadmin.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($agents as $agent)
                        @php
                            $usage = $usage[$agent->id] ?? null;
                            $primary = $agent->superadmins->first(static fn ($manager) => (bool) $manager->pivot->is_primary);
                        @endphp
                        <tr>
                            <td class="col-primary">
                                <strong class="block truncate">{{ $agent->name }}</strong>
                                <small class="block truncate text-outline">{{ $agent->email }}@if ($agent->company) · {{ $agent->company }}@endif</small>
                            </td>
                            <td class="col-status"><x-superadmin.status-pill :status="$agent->status" /></td>
                            <td>{{ $usage !== null ? ($agent->activeSubscription?->package?->name ?? '—') : '•••' }}</td>
                            <td class="text-right whitespace-nowrap num">{{ $usage !== null ? $usage['used'].' / '.$usage['limit'] : '•••' }}</td>
                            <td class="text-right whitespace-nowrap num">{{ array_key_exists($agent->id, $balances) ? $money($balances[$agent->id]) : '•••' }}</td>
                            <td>{{ $primary?->name ?? '—' }}</td>
                            <td class="text-right col-actions"><a class="sa-button secondary" href="{{ route('superadmin.agents.show', $agent) }}"><span class="material-symbols-outlined text-[16px]">visibility</span>{{ __('superadmin.common.details') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-superadmin.empty-state icon="storefront" :title="__('platform.agents.empty')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="mt-3 text-xs text-outline">{{ __('platform.agents.hidden_hint') }}</p>
        @if ($agents->hasPages())
            {{ $agents->links('components.superadmin.pagination') }}
        @endif
    </section>
@endsection
