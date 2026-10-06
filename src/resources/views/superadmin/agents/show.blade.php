@extends('superadmin.layout', ['title' => $agent->name, 'active' => 'agents'])
@section('content')
    @php
        $money = static fn (int $amount): string => \App\Support\Helpers\FormatHelper::formatCurrency($amount);
        $tabIcons = ['overview' => 'dashboard', 'subscription' => 'workspace_premium', 'rooms' => 'meeting_room', 'campaigns' => 'campaign', 'billing' => 'receipt_long', 'activity' => 'history'];
        $fieldInput = 'sa-input !min-w-0 w-full';
    @endphp
    <x-superadmin.page-header :back="route('superadmin.agents.index')" :back-label="__('platform.agents.title')" :title="$agent->name">
        <x-slot:meta><p class="sa-heading-meta"><x-superadmin.sensitive :value="$agent->email" />@if ($agent->company) · {{ $agent->company }}@endif · <x-superadmin.status-pill :status="$agent->status" /></p></x-slot:meta>
    </x-superadmin.page-header>
    <x-superadmin.flash />

    <nav class="sa-tabs" aria-label="{{ __('platform.agents.tabs_label') }}">
        @foreach ($tabs as $key)
            <a href="{{ route('superadmin.agents.show', ['admin' => $agent, 'tab' => $key]) }}"
                class="sa-tab" @if ($tab === $key) aria-current="page" @endif>
                <span class="material-symbols-outlined">{{ $tabIcons[$key] }}</span>{{ __('platform.agents.tabs.'.$key) }}
            </a>
        @endforeach
    </nav>

    @if ($tab === 'overview')
        <div class="sa-split">
            <section class="sa-card sa-section">
                <div class="sa-section-header"><div><h2>{{ __('platform.agents.profile') }}</h2></div></div>
                <dl class="sa-detail-list">
                    <div class="sa-detail-row"><dt>{{ __('platform.registration.field_phone') }}</dt><dd><x-superadmin.sensitive :value="$agent->phone" type="phone" /></dd></div>
                    <div class="sa-detail-row"><dt>{{ __('platform.registrations.registered_at') }}</dt><dd>{{ ($agent->registered_at ?? $agent->created_at)?->toAppDateTime() }}</dd></div>
                    <div class="sa-detail-row"><dt>{{ __('platform.registrations.reviewed_by') }}</dt><dd>{{ $agent->reviewedBy?->name ?? '—' }}</dd></div>
                    <div class="sa-detail-row"><dt>{{ __('platform.agents.last_login') }}</dt><dd>{{ $agent->last_login_at?->toAppDateTime() ?? '—' }}</dd></div>
                    @if ($subscription !== null || $usage !== null)
                        <div class="sa-detail-row"><dt>{{ __('platform.packages.package') }}</dt><dd>{{ $subscription?->package?->name ?? '—' }}</dd></div>
                        <div class="sa-detail-row"><dt>{{ __('platform.rooms.quota_title') }}</dt><dd>{{ $usage['used'] }} / {{ $usage['limit'] }}</dd></div>
                    @endif
                    @if ($balance !== null)
                        <div class="sa-detail-row"><dt>{{ __('platform.billing.remaining') }}</dt><dd>{{ $money($balance) }}</dd></div>
                    @endif
                </dl>
            </section>
            <div class="sa-stack">
                <section class="sa-card sa-section">
                    <div class="sa-section-header"><div><h2>{{ __('platform.agents.managers') }}</h2><p>{{ __('platform.agents.managers_hint') }}</p></div></div>
                    <ul class="space-y-2 text-sm">
                        @forelse ($agent->superadmins as $manager)
                            <li class="flex items-center justify-between gap-2">
                                <span>{{ $manager->name }} <small class="text-outline"><x-superadmin.sensitive :value="$manager->email" /></small>@if ($manager->pivot->is_primary) <span class="status-pill status-active"><span class="status-dot"></span>{{ __('platform.agents.primary') }}</span>@endif</span>
                                @if ($canManage)
                                    <form method="POST" action="{{ route('superadmin.agents.unassign', ['admin' => $agent, 'superadmin' => $manager]) }}" data-confirm-tone="warning" data-confirm-description="{{ __('superadmin.modals.confirm.unassign_manager') }}" data-confirm-icon="person_remove" data-confirm-label="{{ __('platform.agents.unassign') }}" data-confirm="{{ __('platform.agents.unassign_confirm', ['name' => $manager->name]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="sa-button secondary">{{ __('platform.agents.unassign') }}</button>
                                    </form>
                                @endif
                            </li>
                        @empty
                            <li class="text-outline">{{ __('platform.agents.no_managers') }}</li>
                        @endforelse
                    </ul>
                    @if ($canManage)
                        <form method="POST" action="{{ route('superadmin.agents.assign', $agent) }}" class="mt-4 pt-4 border-t border-outline-variant space-y-2">
                            @csrf
                            <select name="superadmin_id" class="{{ $fieldInput }}" aria-label="{{ __('platform.registrations.manager') }}">
                                @foreach ($assignableSuperadmins as $candidate)
                                    <option value="{{ $candidate->id }}">{{ $candidate->name }} ({{ app(\App\Services\Security\SensitiveDataService::class)->mask($candidate->email, 'email') }})</option>
                                @endforeach
                            </select>
                            <label class="flex items-center gap-2 text-xs"><input type="checkbox" name="is_primary" value="1"> {{ __('platform.agents.make_primary') }}</label>
                            <div class="flex justify-end"><button type="submit" class="sa-button"><span class="material-symbols-outlined text-[16px]">person_add</span>{{ __('platform.agents.assign') }}</button></div>
                        </form>
                    @endif
                </section>
                @if ($canManage && $agent->status === \App\Enums\AdminStatus::Pending)
                    <section class="sa-card sa-section">
                        <div class="sa-section-header"><div><h2>{{ __('platform.agents.invitation_title') }}</h2><p>{{ __('platform.agents.invitation_hint') }}</p></div></div>
                        <p class="text-sm"><strong class="text-on-surface">{{ __('platform.agents.invited_at') }}</strong> {{ $agent->invited_at !== null ? \Illuminate\Support\Carbon::parse($agent->invited_at)->toAppDateTime() : __('platform.agents.not_invited') }}</p>
                        <p class="text-xs text-outline mt-1">{{ $agent->email_verified_at === null ? __('platform.agents.activation_pending') : __('platform.agents.activation_ready') }}</p>
                        <form method="POST" action="{{ route('superadmin.agents.resend-activation', $agent) }}" class="mt-4 pt-4 border-t border-outline-variant">
                            @csrf
                            <button type="submit" class="sa-button"><span class="material-symbols-outlined text-[16px]">forward_to_inbox</span>{{ __('platform.agents.resend_invitation') }}</button>
                        </form>
                    </section>
                @endif
                @if ($canManage && in_array($agent->status, [\App\Enums\AdminStatus::Active, \App\Enums\AdminStatus::Suspended], true))
                    <section class="sa-card sa-section">
                        @php $suspended = $agent->status === \App\Enums\AdminStatus::Suspended; @endphp
                        <div class="sa-section-header"><div><h2>{{ $suspended ? __('platform.agents.reactivate') : __('platform.agents.suspend') }}</h2><p>{{ $suspended ? __('platform.agents.reactivate_hint') : __('platform.agents.suspend_hint') }}</p></div></div>
                        <form method="POST" action="{{ route($suspended ? 'superadmin.agents.reactivate' : 'superadmin.agents.suspend', $agent) }}" class="space-y-2"
                            data-confirm-tone="{{ $suspended ? 'success' : 'warning' }}" data-confirm-description="{{ $suspended ? __('superadmin.modals.confirm.reactivate_agent') : __('superadmin.modals.confirm.suspend_agent') }}" data-confirm-icon="{{ $suspended ? 'lock_open' : 'block' }}" data-confirm-label="{{ $suspended ? __('platform.agents.reactivate') : __('platform.agents.suspend') }}" data-confirm="{{ $suspended ? __('platform.agents.reactivate_confirm', ['name' => $agent->name]) : __('platform.agents.suspend_confirm', ['name' => $agent->name]) }}">
                            @csrf
                            <textarea name="reason" rows="2" maxlength="1000" @required(! $suspended) minlength="3" class="{{ $fieldInput }}" placeholder="{{ __('platform.registrations.reason') }}"></textarea>
                            <div class="flex justify-end">
                                <button type="submit" class="sa-button {{ $suspended ? '' : 'danger' }}"><span class="material-symbols-outlined text-[16px]">{{ $suspended ? 'lock_open' : 'block' }}</span>{{ $suspended ? __('platform.agents.reactivate') : __('platform.agents.suspend') }}</button>
                            </div>
                        </form>
                    </section>
                @endif
            </div>
        </div>
    @elseif ($tab === 'subscription')
        <section class="sa-card sa-section">
            <div class="sa-section-header">
                <div><h2>{{ __('platform.subscriptions.current_package') }}</h2><p>{{ __('platform.rooms.quota_usage', ['used' => $usage['used'], 'limit' => $usage['limit']]) }}</p></div>
                <a class="sa-button secondary" href="{{ route('superadmin.subscriptions.show', $agent) }}"><span class="material-symbols-outlined text-[16px]">tune</span>{{ __('platform.agents.manage_subscription') }}</a>
            </div>
            @if ($subscription)
                <p class="text-sm"><strong>{{ $subscription->package?->name }}</strong> · {{ __('platform.packages.per_month', ['price' => $money($subscription->price_snapshot)]) }} · {{ $subscription->starts_at->toAppDate() }} → {{ $subscription->expires_at?->toAppDate() }}</p>
                @if ($subscription->scheduledPackage)
                    <p class="text-xs text-outline mt-1">{{ __('platform.subscriptions.scheduled_notice', ['package' => $subscription->scheduledPackage->name, 'date' => $subscription->expires_at?->toAppDate()]) }}</p>
                @endif
            @else
                <p class="text-sm text-outline">{{ __('platform.subscriptions.none_superadmin') }}</p>
            @endif
            <div class="sa-table-wrap mt-4">
                <table class="sa-table">
                    <thead><tr><th class="col-primary">{{ __('platform.packages.package') }}</th><th class="text-right num">{{ __('platform.subscriptions.price') }}</th><th class="text-right num">{{ __('platform.subscriptions.room_limit') }}</th><th class="col-status">{{ __('superadmin.common.status') }}</th><th class="col-date">{{ __('platform.subscriptions.period') }}</th></tr></thead>
                    <tbody>
                        @foreach ($history as $row)
                            <tr><td class="col-primary">{{ $row->package?->name }}</td><td class="text-right num">{{ $money($row->price_snapshot) }}</td><td class="text-right num">{{ $row->room_limit_snapshot }}</td><td class="col-status">{{ __('platform.subscriptions.status.'.$row->status->value) }}</td><td class="whitespace-nowrap col-date">{{ $row->starts_at->toAppDate() }} → {{ ($row->ended_at ?? $row->expires_at)?->toAppDate() ?? '—' }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @elseif ($tab === 'rooms')
        <section class="sa-card sa-section">
            <div class="sa-section-header"><div><h2>{{ __('platform.rooms.owned') }}</h2><p>{{ __('platform.rooms.quota_usage', ['used' => $usage['used'], 'limit' => $usage['limit']]) }}</p></div></div>
            <div class="sa-table-wrap">
                <table class="sa-table">
                    <thead><tr><th class="col-primary">{{ __('platform.rooms.room') }}</th><th class="col-status">{{ __('superadmin.common.status') }}</th><th class="text-right num">{{ __('platform.rooms.members') }}</th><th class="text-right num">{{ __('superadmin.common.campaigns') }}</th><th class="text-right col-actions">{{ __('superadmin.common.actions') }}</th></tr></thead>
                    <tbody>
                        @forelse ($ownedRooms as $room)
                            <tr>
                                <td class="col-primary"><strong>{{ $room->name }}</strong><small class="block text-outline font-mono">/{{ $room->slug }}</small></td>
                                <td class="col-status"><x-superadmin.status-pill :status="$room->status" /></td>
                                <td class="text-right num">{{ $room->room_users_count }}</td>
                                <td class="text-right num">{{ $room->campaigns_count }}</td>
                                <td class="text-right col-actions"><a class="sa-button secondary" href="{{ route('superadmin.rooms.detail.page', $room) }}">{{ __('superadmin.common.details') }}</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-superadmin.empty-state icon="meeting_room" :title="__('platform.rooms.empty')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($sharedRooms->isNotEmpty())
                <p class="mt-4 text-xs text-outline">{{ __('platform.rooms.shared') }}: {{ $sharedRooms->pluck('name')->implode(', ') }}</p>
            @endif
        </section>
    @elseif ($tab === 'campaigns')
        <section class="sa-card sa-section">
            <div class="sa-section-header"><div><h2>{{ __('platform.agents.tabs.campaigns') }}</h2><p>{{ __('platform.agents.campaigns_hint') }}</p></div></div>
            <div class="sa-table-wrap">
                <table class="sa-table">
                    <thead><tr><th class="col-primary">{{ __('superadmin.common.campaign') }}</th><th>{{ __('superadmin.common.room') }}</th><th class="col-status">{{ __('superadmin.common.status') }}</th><th class="col-date">{{ __('superadmin.common.time') }}</th></tr></thead>
                    <tbody>
                        @forelse ($campaigns as $campaign)
                            <tr>
                                <td class="col-primary"><strong>{{ $campaign->name }}</strong><small class="block text-outline">{{ $campaign->restaurant }}</small></td>
                                <td>{{ $campaign->room?->name }}</td>
                                <td class="col-status"><x-superadmin.status-pill :status="$campaign->status" /></td>
                                <td class="whitespace-nowrap col-date">{{ $campaign->created_at?->toAppDateTime() }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-superadmin.empty-state icon="campaign" :title="__('platform.agents.no_campaigns')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @elseif ($tab === 'billing')
        <section class="sa-card sa-section">
            <div class="sa-section-header">
                <div><h2>{{ __('platform.billing.invoices') }}</h2><p>{{ __('platform.billing.balance', ['amount' => $money($balance)]) }} · {{ __('platform.agents.collected_total', ['amount' => $money($collected)]) }}</p></div>
                <a class="sa-button secondary" href="{{ route('superadmin.billing.agent', $agent) }}"><span class="material-symbols-outlined text-[16px]">price_check</span>{{ __('platform.agents.open_billing') }}</a>
            </div>
            <div class="sa-table-wrap">
                <table class="sa-table">
                    <thead><tr><th class="col-mono">{{ __('platform.billing.invoice') }}</th><th class="col-date">{{ __('platform.subscriptions.period') }}</th><th class="text-right num">{{ __('platform.billing.total') }}</th><th class="text-right num">{{ __('platform.billing.paid') }}</th><th class="col-status">{{ __('superadmin.common.status') }}</th></tr></thead>
                    <tbody>
                        @forelse ($invoices as $invoice)
                            <tr><td class="font-mono col-mono">{{ $invoice->number }}</td><td class="whitespace-nowrap col-date">{{ $invoice->period_start->toAppDate() }} → {{ $invoice->period_end->toAppDate() }}</td><td class="text-right num">{{ $money($invoice->total) }}</td><td class="text-right num">{{ $money($invoice->paid_amount) }}</td><td class="col-status">@include('superadmin.billing._invoice-status', ['invoice' => $invoice])</td></tr>
                        @empty
                            <tr><td colspan="5"><x-superadmin.empty-state icon="receipt_long" :title="__('platform.billing.no_invoices')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @elseif ($tab === 'activity')
        <section class="sa-card sa-section">
            <div class="sa-section-header"><div><h2>{{ __('platform.agents.tabs.activity') }}</h2><p>{{ __('platform.agents.activity_hint') }}</p></div></div>
            <div class="sa-table-wrap">
                <table class="sa-table">
                    <thead><tr><th class="col-date">{{ __('superadmin.common.time') }}</th><th>{{ __('platform.agents.event') }}</th><th>{{ __('platform.agents.actor') }}</th><th class="col-mono">{{ __('platform.agents.ip') }}</th></tr></thead>
                    <tbody>
                        @forelse ($logs as $log)
                            <tr>
                                <td class="whitespace-nowrap col-date">{{ $log->created_at?->toAppDateTime() }}</td>
                                <td data-activity-event="{{ $log->event }}">
                                    <strong class="block text-[13px]">{{ $log->eventLabel() }}</strong>
                                    <small class="block font-mono text-[11px] text-outline">{{ $log->event }}</small>
                                </td>
                                <td>
                                    @if ($log->actor_type === \App\Models\AuditLog::ACTOR_SUPERADMIN && $log->actorSuperadmin)
                                        <strong class="block text-[13px]">{{ $log->actorSuperadmin->name }}</strong>
                                    @elseif ($log->actor_type === \App\Models\AuditLog::ACTOR_ADMIN && (int) $log->actor_id === (int) $agent->id)
                                        <strong class="block text-[13px]">{{ $agent->name }}</strong>
                                    @endif
                                    <small class="block text-outline">{{ __('superadmin.common.actor_'.$log->actor_type) }}</small>
                                </td>
                                <td class="font-mono text-xs col-mono">{{ $log->ip_address ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-superadmin.empty-state icon="history" :title="__('platform.agents.no_activity')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
