@extends('superadmin.layout', ['title' => __('superadmin.security.title'), 'active' => 'security'])
@section('content')
    <x-superadmin.page-header :eyebrow="__('superadmin.common.infra_security')" :title="__('superadmin.security.title')" />
    <section class="sa-card sa-section">
        <div class="sa-section-header">
            <div>
                <h2>{{ __('superadmin.security.events') }}</h2>
                <p>{{ __('superadmin.common.events_count', ['count' => $events->total()]) }}</p>
            </div>
            <form method="GET" class="superadmin-actions">
                <x-superadmin.search-input :value="$filters['search'] ?? ''" placeholder="{{ __('superadmin.audit.filter') }}" />
                <select name="severity" class="sa-input">
                    <option value="">{{ __('superadmin.security.all_severities') }}</option>
                    @foreach(['high','medium','low'] as $value)
                        <option value="{{ $value }}" @selected(($filters['severity'] ?? '') === $value)>{{ __('superadmin.common.'.$value) }}</option>
                    @endforeach
                </select>
                <select name="actor_type" class="sa-input">
                    <option value="">{{ __('superadmin.common.all_actors') }}</option>
                    <option value="admin" @selected(($filters['actor_type'] ?? '') === 'admin')>{{ __('superadmin.common.actor_admin') }}</option>
                    <option value="user" @selected(($filters['actor_type'] ?? '') === 'user')>{{ __('superadmin.common.actor_user') }}</option>
                </select>
                <button class="sa-button secondary" type="submit"><span class="material-symbols-outlined text-[16px]">filter_list</span>{{ __('superadmin.common.filter') }}</button>
            </form>
        </div>
        @php
            $securityTypeIcons = [
                'failed_login' => 'lock',
                'password_reset_otp_requested' => 'mail',
                'password_reset_otp_unknown_account' => 'person_off',
                'password_reset_completed' => 'lock_reset',
                'google_oauth_failure' => 'error',
                'invalid_company_domain' => 'domain_disabled',
            ];
        @endphp
        <div class="sa-table-wrap">
            <table class="sa-table">
                <thead>
                    <tr>
                        <th class="col-date">{{ __('superadmin.common.time') }}</th>
                        <th>{{ __('superadmin.common.type') }}</th>
                        <th class="col-status">{{ __('superadmin.security.severity') }}</th>
                        <th class="col-mono">IP</th>
                        <th class="col-wrap">{{ __('superadmin.security.metadata') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($events as $event)
                        @php
                            $typeKey = 'superadmin.security.types.'.$event->type;
                            $typeLabel = __($typeKey) === $typeKey ? $event->type : __($typeKey);
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap col-date">{{ $event->created_at }}</td>
                            <td class="whitespace-nowrap">
                                <span class="inline-flex items-center gap-1 font-semibold">
                                    <span class="material-symbols-outlined text-[16px]">{{ $securityTypeIcons[$event->type] ?? 'shield_question' }}</span>{{ $typeLabel }}
                                </span>
                            </td>
                            <td class="col-status"><x-superadmin.status-pill :status="$event->severity" /></td>
                            <td class="whitespace-nowrap col-mono">{{ $event->ip_address ?: '—' }}</td>
                            <td class="col-wrap"><small>{{ json_encode($event->metadata ?? []) }}</small></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <x-superadmin.empty-state icon="shield" :title="__('superadmin.security.no_events_title')" :description="__('superadmin.security.no_events_description')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $events->links('components.superadmin.pagination') }}
    </section>
@endsection
