{{-- SaaS platform widgets (PlatformDashboardService): each one is rendered only when permitted, with scoped figures. --}}
@php
    $money = static fn (int $amount): string => \App\Support\Helpers\FormatHelper::formatCurrency($amount);
    $stat = 'flex justify-between gap-3 text-sm tabular-nums';
@endphp
@if (array_filter($widgets) !== [])
    <section class="sa-grid kpis kpis-3 mb-4" aria-label="{{ __('platform.dashboard.title') }}" data-platform-widgets>
        @if ($widgets['agents'])
            <article class="sa-card sa-section" data-widget="agents">
                <div class="sa-section-header"><div><h2>{{ __('platform.dashboard.agents') }}</h2></div>
                    @can('agent.view')<a class="sa-button secondary sm" href="{{ route('superadmin.agents.index') }}">{{ __('superadmin.common.details') }}</a>@endcan
                </div>
                <dl class="space-y-2">
                    <div class="{{ $stat }}"><dt class="text-outline">{{ __('platform.dashboard.agents_total') }}</dt><dd class="font-semibold">{{ $widgets['agents']['total'] }}</dd></div>
                    <div class="{{ $stat }}"><dt class="text-outline">{{ __('superadmin.common.active') }}</dt><dd>{{ $widgets['agents']['active'] }}</dd></div>
                    <div class="{{ $stat }}"><dt class="text-outline">{{ __('superadmin.common.suspended') }}</dt><dd>{{ $widgets['agents']['suspended'] }}</dd></div>
                    <div class="{{ $stat }}"><dt class="text-outline">{{ __('platform.dashboard.new_month') }}</dt><dd>{{ $widgets['agents']['new_month'] }}</dd></div>
                    <div class="{{ $stat }}"><dt class="text-outline">{{ __('platform.dashboard.with_subscription') }}</dt><dd>{{ $widgets['agents']['with_subscription'] }}</dd></div>
                </dl>
            </article>
        @endif
        @if ($widgets['revenue'])
            <article class="sa-card sa-section" data-widget="revenue">
                <div class="sa-section-header"><div><h2>{{ __('platform.dashboard.revenue') }}</h2></div>
                    <a class="sa-button secondary sm" href="{{ route('superadmin.billing.revenue') }}">{{ __('superadmin.common.details') }}</a>
                </div>
                <dl class="space-y-2">
                    <div class="{{ $stat }}"><dt class="text-outline">{{ __('platform.billing.kpi.mrr') }}</dt><dd class="font-semibold">{{ $money($widgets['revenue']['mrr']) }}</dd></div>
                    <div class="{{ $stat }}"><dt class="text-outline">{{ __('platform.billing.kpi.collected_month') }}</dt><dd>{{ $money($widgets['revenue']['collected_month']) }}</dd></div>
                    <div class="{{ $stat }}"><dt class="text-outline">{{ __('platform.billing.kpi.outstanding') }}</dt><dd>{{ $money($widgets['revenue']['outstanding']) }}</dd></div>
                    <div class="{{ $stat }}"><dt class="text-outline">{{ __('platform.billing.kpi.overdue') }}</dt><dd class="{{ $widgets['revenue']['overdue'] > 0 ? 'text-error font-semibold' : '' }}">{{ $money($widgets['revenue']['overdue']) }}</dd></div>
                </dl>
            </article>
        @endif
        @if ($widgets['registrations'])
            <article class="sa-card sa-section" data-widget="registrations">
                <div class="sa-section-header">
                    <div><h2>{{ __('platform.nav.registrations') }}</h2><p>{{ __('platform.dashboard.pending_count', ['count' => $widgets['registrations']['count'], 'verified' => $widgets['registrations']['verified']]) }}</p></div>
                    <a class="sa-button secondary sm" href="{{ route('superadmin.registrations.index') }}">{{ __('platform.registrations.review') }}</a>
                </div>
                <ul class="space-y-2 text-sm">
                    @forelse ($widgets['registrations']['latest'] as $registration)
                        <li class="flex justify-between gap-2">
                            <a class="sa-link truncate" href="{{ route('superadmin.registrations.show', $registration) }}">{{ $registration->name }}</a>
                            <small class="text-outline whitespace-nowrap">{{ $registration->requestedPackage?->name }}</small>
                        </li>
                    @empty
                        <li class="text-outline">{{ __('platform.registrations.empty_title') }}</li>
                    @endforelse
                </ul>
            </article>
        @endif
        @if ($widgets['rooms'])
            <article class="sa-card sa-section" data-widget="rooms">
                <div class="sa-section-header"><div><h2>{{ __('platform.dashboard.rooms') }}</h2></div>
                    <a class="sa-button secondary sm" href="{{ route('superadmin.rooms.page') }}">{{ __('superadmin.common.details') }}</a>
                </div>
                <dl class="space-y-2">
                    <div class="{{ $stat }}"><dt class="text-outline">{{ __('platform.rooms.status.active') }}</dt><dd class="font-semibold">{{ $widgets['rooms']['active'] }}</dd></div>
                    <div class="{{ $stat }}"><dt class="text-outline">{{ __('platform.rooms.status.inactive') }}</dt><dd>{{ $widgets['rooms']['inactive'] }}</dd></div>
                    <div class="{{ $stat }}"><dt class="text-outline">{{ __('platform.rooms.status.archived') }}</dt><dd>{{ $widgets['rooms']['archived'] }}</dd></div>
                    <div class="{{ $stat }}"><dt class="text-outline">{{ __('platform.dashboard.quota_utilisation') }}</dt><dd>{{ $widgets['rooms']['quota_used'] }} / {{ $widgets['rooms']['quota_limit'] }}</dd></div>
                </dl>
            </article>
        @endif
        @if ($widgets['campaigns'])
            <article class="sa-card sa-section" data-widget="campaigns">
                <div class="sa-section-header"><div><h2>{{ __('platform.dashboard.campaigns') }}</h2></div>
                    <a class="sa-button secondary sm" href="{{ route('superadmin.campaigns.page') }}">{{ __('superadmin.common.details') }}</a>
                </div>
                <dl class="space-y-2">
                    <div class="{{ $stat }}"><dt class="text-outline">{{ __('platform.dashboard.running') }}</dt><dd class="font-semibold">{{ $widgets['campaigns']['running'] }}</dd></div>
                    <div class="{{ $stat }}"><dt class="text-outline">{{ __('platform.dashboard.campaigns_month') }}</dt><dd>{{ $widgets['campaigns']['month'] }}</dd></div>
                    <div class="{{ $stat }}"><dt class="text-outline">{{ __('platform.dashboard.orders_month') }}</dt><dd>{{ $widgets['campaigns']['orders_month'] }}</dd></div>
                    <div class="{{ $stat }}"><dt class="text-outline">{{ __('platform.dashboard.gmv_month') }}</dt><dd>{{ $money($widgets['campaigns']['gmv_month']) }}</dd></div>
                </dl>
            </article>
        @endif
        @if ($widgets['system'])
            <article class="sa-card sa-section" data-widget="system">
                <div class="sa-section-header"><div><h2>{{ __('platform.dashboard.system') }}</h2><p>{{ __('platform.dashboard.last_24h') }}</p></div></div>
                <dl class="space-y-2">
                    @if ($widgets['system']['security_total'] !== null)
                        <div class="{{ $stat }}"><dt class="text-outline">{{ __('platform.dashboard.security_events') }}</dt><dd>{{ $widgets['system']['security_total'] }}</dd></div>
                        <div class="{{ $stat }}"><dt class="text-outline">{{ __('platform.dashboard.security_high') }}</dt><dd class="{{ $widgets['system']['security_high'] > 0 ? 'text-error font-semibold' : '' }}">{{ $widgets['system']['security_high'] }}</dd></div>
                    @endif
                    @if ($widgets['system']['failed_jobs'] !== null)
                        <div class="{{ $stat }}"><dt class="text-outline">{{ __('platform.dashboard.failed_jobs') }}</dt><dd class="{{ $widgets['system']['failed_jobs'] > 0 ? 'text-error font-semibold' : '' }}">{{ $widgets['system']['failed_jobs'] }}</dd></div>
                        <div class="{{ $stat }}"><dt class="text-outline">{{ __('platform.dashboard.pending_jobs') }}</dt><dd>{{ $widgets['system']['pending_jobs'] }}</dd></div>
                    @endif
                </dl>
            </article>
        @endif
    </section>
@endif
