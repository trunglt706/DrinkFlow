@extends('superadmin.layout', ['title' => $agent->name, 'active' => 'subscriptions'])
@section('content')
    @php
        $money = static fn (int $amount): string => \App\Support\Helpers\FormatHelper::formatCurrency($amount);
    @endphp
    <x-superadmin.page-header :back="route('superadmin.subscriptions.index')" :back-label="__('platform.subscriptions.admin_title')" :title="$agent->name">
        <x-slot:meta><p class="sa-heading-meta"><x-superadmin.sensitive :value="$agent->email" /> · <x-superadmin.status-pill :status="$agent->status" /></p></x-slot:meta>
    </x-superadmin.page-header>
    <x-superadmin.flash />

    <div class="sa-split">
        <section class="sa-card sa-section" data-subscription-status="{{ $subscription?->status->value ?? 'none' }}">
            <div class="sa-section-header">
                <div>
                    <h2>{{ __('platform.subscriptions.current_package') }}</h2>
                    <p>{{ __('platform.rooms.quota_usage', ['used' => $usage['used'], 'limit' => $usage['limit']]) }}</p>
                </div>
            </div>
            @if ($subscription)
                <dl class="space-y-3 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-outline">{{ __('platform.packages.package') }}</dt><dd class="font-semibold">{{ $subscription->package?->name }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-outline">{{ __('platform.subscriptions.price') }}</dt><dd class="font-semibold">{{ $money($subscription->price_snapshot) }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-outline">{{ __('platform.subscriptions.room_limit') }}</dt><dd class="font-semibold">{{ $subscription->room_limit_snapshot }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-outline">{{ __('platform.subscriptions.period') }}</dt><dd>{{ $subscription->starts_at->toAppDate() }} → {{ $subscription->expires_at?->toAppDate() ?? '—' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-outline">{{ __('platform.subscriptions.approved_by') }}</dt><dd>{{ $subscription->approvedBy?->name ?? '—' }}</dd></div>
                    @if ($subscription->cancel_at_period_end)
                        <div class="flex justify-between gap-3"><dt class="text-outline">{{ __('superadmin.common.status') }}</dt><dd>{{ __('platform.subscriptions.ending') }}</dd></div>
                    @endif
                    @if ($subscription->scheduledPackage)
                        <div class="flex justify-between gap-3"><dt class="text-outline">{{ __('platform.subscriptions.scheduled_package') }}</dt><dd>{{ $subscription->scheduledPackage->name }}</dd></div>
                    @endif
                </dl>
            @else
                <p class="text-sm text-outline">{{ __('platform.subscriptions.none_superadmin') }}</p>
            @endif
        </section>

        @if ($canManage)
            <section class="sa-card sa-section">
                <div class="sa-section-header">
                    <div>
                        <h2>{{ $subscription ? __('platform.subscriptions.change_title') : __('platform.subscriptions.start_title') }}</h2>
                        <p>{{ __('platform.subscriptions.superadmin_change_hint') }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('superadmin.subscriptions.change', $agent) }}" class="space-y-3"
                    data-confirm-tone="warning" data-confirm-description="{{ __('superadmin.modals.confirm.change_subscription') }}" data-confirm-icon="swap_horiz" data-confirm="{{ __('platform.subscriptions.superadmin_change_confirm', ['name' => $agent->name]) }}">
                    @csrf
                    <select name="package_id" class="sa-input !min-w-0 w-full !py-2" aria-label="{{ __('platform.packages.package') }}">
                        @foreach ($packages as $package)
                            <option value="{{ $package->id }}" @selected($subscription?->package_id === $package->id)>{{ $package->name }} — {{ __('platform.packages.per_month', ['price' => $money($package->monthly_price)]) }} · {{ __('platform.packages.rooms_limit', ['count' => $package->room_limit]) }}</option>
                        @endforeach
                    </select>
                    <div class="flex justify-end">
                        <button type="submit" class="sa-button"><span class="material-symbols-outlined text-[16px]">swap_horiz</span>{{ $subscription ? __('platform.subscriptions.apply_change') : __('platform.subscriptions.start') }}</button>
                    </div>
                </form>
                @if ($subscription)
                    <form method="POST" action="{{ route('superadmin.subscriptions.cancel', $agent) }}" class="mt-4 pt-4 border-t border-outline-variant flex justify-end"
                        data-confirm-tone="danger" data-confirm-description="{{ __('superadmin.modals.confirm.cancel_subscription') }}" data-confirm-icon="cancel" data-confirm="{{ __('platform.subscriptions.cancel_now_confirm', ['name' => $agent->name]) }}">
                        @csrf
                        <button type="submit" class="sa-button danger"><span class="material-symbols-outlined text-[16px]">cancel</span>{{ __('platform.subscriptions.cancel_now') }}</button>
                    </form>
                @endif
            </section>
        @endif
    </div>

    <section class="sa-card sa-section mt-4">
        <div class="sa-section-header"><div><h2>{{ __('platform.subscriptions.history') }}</h2></div></div>
        <div class="sa-table-wrap">
            <table class="sa-table">
                <thead>
                    <tr>
                        <th class="col-primary">{{ __('platform.packages.package') }}</th>
                        <th class="text-right num">{{ __('platform.subscriptions.price') }}</th>
                        <th class="text-right num">{{ __('platform.subscriptions.room_limit') }}</th>
                        <th class="col-status">{{ __('superadmin.common.status') }}</th>
                        <th class="col-date">{{ __('platform.subscriptions.period') }}</th>
                        <th>{{ __('platform.subscriptions.approved_by') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($history as $row)
                        <tr>
                            <td class="col-primary">{{ $row->package?->name }}</td>
                            <td class="text-right whitespace-nowrap num">{{ $money($row->price_snapshot) }}</td>
                            <td class="text-right num">{{ $row->room_limit_snapshot }}</td>
                            <td class="col-status">{{ __('platform.subscriptions.status.'.$row->status->value) }}</td>
                            <td class="whitespace-nowrap col-date">{{ $row->starts_at->toAppDate() }} → {{ ($row->ended_at ?? $row->expires_at)?->toAppDate() ?? '—' }}</td>
                            <td>{{ $row->approvedBy?->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-superadmin.empty-state icon="history" :title="__('platform.subscriptions.history_empty')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
