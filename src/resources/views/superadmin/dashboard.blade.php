@extends('superadmin.layout', ['title' => __('superadmin.dashboard.title'), 'active' => 'dashboard'])

@section('content')
    <x-superadmin.page-header :eyebrow="__('superadmin.dashboard.eyebrow')" :title="__('superadmin.dashboard.title')" :description="__('superadmin.dashboard.description')">
        @can('queue.view')
            <a class="sa-button secondary" href="{{ route('superadmin.socket.page') }}"><span class="material-symbols-outlined">hub</span>{{ __('superadmin.dashboard.live_monitoring') }}</a>
        @endcan
        @can('settings.view')
            <a class="sa-button" href="{{ route('superadmin.system.page') }}"><span class="material-symbols-outlined">build_circle</span>{{ __('superadmin.dashboard.system_settings') }}</a>
        @endcan
    </x-superadmin.page-header>
    <div id="notice" class="sa-notice"></div>
    @include('superadmin.dashboard._platform-widgets', ['widgets' => $widgets])
    <section class="sa-grid kpis kpis-4">
        <x-superadmin.kpi :label="__('superadmin.dashboard.total_rooms')" icon="meeting_room" tone="accent" value-id="total-rooms" hint-id="active-rooms" :hint="__('superadmin.common.loading')" />
        <x-superadmin.kpi :label="__('superadmin.dashboard.global_users')" icon="group" value-id="total-users" hint-id="active-users" :hint="__('superadmin.common.loading')" />
        <x-superadmin.kpi :label="__('superadmin.dashboard.admins')" icon="admin_panel_settings" value-id="total-admins" :hint="__('superadmin.dashboard.room_admins')" />
        <x-superadmin.kpi :label="__('superadmin.dashboard.orders_today')" icon="receipt_long" tone="success" value-id="orders-today" :hint="__('superadmin.dashboard.non_cancelled_orders')" />
    </section>
    <div class="sa-split">
        <section class="sa-card sa-section">
            <div class="sa-section-header">
                <div>
                    <h2>{{ __('superadmin.dashboard.system_health') }}</h2>
                    <p>{{ __('superadmin.dashboard.health_description') }}</p>
                </div><span class="status-pill status-ok"><span class="status-dot"></span>{{ __('superadmin.dashboard.telemetry') }}</span>
            </div>
            <div class="sa-health-list">
                <div class="sa-health-row">
                    <div><strong>{{ __('superadmin.dashboard.database') }}</strong><small id="database-status">{{ __('superadmin.dashboard.checking') }}</small></div><span
                        class="material-symbols-outlined">database</span>
                </div>
                
@can('queue.view')<a class="sa-health-row" href="{{ route('superadmin.queue.page') }}">
                    <div><strong>{{ __('superadmin.dashboard.queue') }}</strong><small id="queue-status">{{ __('superadmin.dashboard.checking') }}</small></div><span
                        class="material-symbols-outlined">sync_alt</span>
                </a>
@endcan

                
@can('queue.view')<a class="sa-health-row" href="{{ route('superadmin.socket.page') }}">
                    <div><strong>Socket.IO</strong><small id="socket-status">{{ __('superadmin.dashboard.checking') }}</small></div><span
                        class="material-symbols-outlined">hub</span>
                </a>
@endcan

                
@can('settings.view')<a class="sa-health-row" href="{{ route('superadmin.system.page') }}#mail">
                    <div><strong>{{ __('superadmin.dashboard.mail') }}</strong><small id="mail-status">{{ __('superadmin.dashboard.checking') }}</small></div><span
                        class="material-symbols-outlined">mail</span>
                </a>
@endcan

                
@can('settings.view')<a class="sa-health-row" href="{{ route('superadmin.system.page') }}#storage">
                    <div><strong>{{ __('superadmin.dashboard.storage') }}</strong><small id="storage-status">{{ __('superadmin.dashboard.checking') }}</small><small
                            id="storage-usage" class="sa-health-detail"></small></div><span
                        class="material-symbols-outlined">hard_drive</span>
                </a>
@endcan

                <div class="sa-health-row" id="supervisor-row" hidden>
                    <div><strong>{{ __('superadmin.dashboard.supervisor') }}</strong><small id="supervisor-status">{{ __('superadmin.dashboard.checking') }}</small></div><span
                        class="material-symbols-outlined">engineering</span>
                </div>
            </div>
        </section>
        <section class="sa-card sa-section">
            <div class="sa-section-header">
                <div>
                    <h2>{{ __('superadmin.dashboard.quick_access') }}</h2>
                    <p>{{ __('superadmin.dashboard.quick_access_description') }}</p>
                </div>
            </div>
            <div class="sa-health-list">
@can('security.view')<a class="sa-health-row" href="{{ route('superadmin.security.page') }}">
                    <div><strong>{{ __('superadmin.layout.security_center') }}</strong><small>{{ __('superadmin.dashboard.failed_login_events') }}</small></div><span
                        class="material-symbols-outlined">arrow_forward</span>
                </a>
@endcan

@can('audit.view')<a class="sa-health-row" href="{{ route('superadmin.audit.page') }}">
                    <div><strong>{{ __('superadmin.audit.title') }}</strong><small>{{ __('superadmin.dashboard.global_change_history') }}</small></div><span
                        class="material-symbols-outlined">arrow_forward</span>
                </a>
@endcan

@can('queue.view')<a class="sa-health-row" href="{{ route('superadmin.queue.page') }}">
                    <div><strong>{{ __('superadmin.dashboard.failed_jobs') }}</strong><small>{{ __('superadmin.dashboard.failed_jobs_description') }}</small></div><span
                        class="material-symbols-outlined">arrow_forward</span>
                </a>
@endcan
</div>
        </section>
    </div>

    {{-- Full Superadmins see the platform-wide analytics; a restricted one sees the operations tab only,
         computed over the rooms of its managed Agents (security and trends are platform-wide figures). --}}
    @if ($platformAnalytics || $scopedAnalytics)
    @php($analyticsDays = \App\Services\Dashboard\SuperadminDashboardService::PERIOD_DAYS)
    <section id="sa-analytics" class="sa-analytics" aria-labelledby="sa-analytics-title"
        data-url="{{ route('superadmin.dashboard.analytics') }}" data-platform="{{ $platformAnalytics ? '1' : '0' }}"
        data-room-url="{{ route('superadmin.rooms.detail.page', ['room' => '__SLUG__']) }}"
        data-campaigns-url="{{ route('superadmin.campaigns.page') }}"
        data-insights-url="{{ route('superadmin.dashboard.insights') }}"
        data-trends-url="{{ route('superadmin.dashboard.trends') }}"
        data-contact-topics="{{ json_encode(__('contact.form.topics'), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) }}"
        data-security-url="{{ route('superadmin.security.page') }}"
        data-admin-url="{{ route('superadmin.admins.detail.page', ['admin' => '__ID__']) }}"
        data-security-types="{{ json_encode(__('superadmin.security.types'), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) }}"
        data-insight-thresholds="{{ json_encode([
            'stale_days' => \App\Services\Dashboard\SuperadminInsightsService::STALE_ADMIN_DAYS,
            'security_days' => \App\Services\Dashboard\SuperadminInsightsService::SECURITY_DAYS,
            'activity_days' => \App\Services\Dashboard\SuperadminInsightsService::ACTIVITY_DAYS,
            'ip_hours' => \App\Services\Dashboard\SuperadminInsightsService::IP_WINDOW_HOURS,
            'contact_days' => \App\Services\Dashboard\SuperadminTrendsService::CONTACT_DAYS,
        ]) }}"
        data-thresholds="{{ json_encode([
            'dormant_days' => \App\Services\Dashboard\SuperadminDashboardService::DORMANT_DAYS,
            'overdue_days' => \App\Services\Dashboard\SuperadminDashboardService::OVERDUE_DEBT_DAYS,
            'bad_debt_percent' => (int) round(\App\Services\Dashboard\SuperadminDashboardService::BAD_DEBT_RATIO * 100),
            'high_cancel_percent' => (int) round(\App\Services\Dashboard\SuperadminDashboardService::HIGH_CANCEL_RATIO * 100),
        ]) }}"
        data-i18n="{{ json_encode(__('superadmin.analytics'), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) }}">
        <div class="sa-analytics-heading">
            <div>
                <h2 id="sa-analytics-title">{{ __('superadmin.analytics.title') }}</h2>
                <p>{{ __('superadmin.analytics.description', ['days' => $analyticsDays]) }}</p>
            </div>
            <div class="sa-analytics-toolbar">
                <small id="sa-analytics-updated" class="sa-analytics-updated" aria-live="polite"></small>
                <button type="button" class="sa-button secondary" data-analytics-refresh><span
                        class="material-symbols-outlined">refresh</span>{{ __('superadmin.analytics.refresh') }}</button>
            </div>
        </div>
        <div class="sa-tabs" role="tablist" aria-label="{{ __('superadmin.analytics.tabs_label') }}" data-sa-tablist>
            <button type="button" class="sa-tab" role="tab" id="sa-tab-operations" aria-controls="sa-panel-operations" aria-selected="true" data-sa-tab="operations"><span class="material-symbols-outlined" aria-hidden="true">monitoring</span>{{ __('superadmin.analytics.tab_operations') }}</button>
            <button type="button" class="sa-tab" role="tab" id="sa-tab-security" @unless ($platformAnalytics) hidden @endunless aria-controls="sa-panel-security" aria-selected="false" tabindex="-1" data-sa-tab="security"><span class="material-symbols-outlined" aria-hidden="true">shield</span>{{ __('superadmin.analytics.tab_security') }}</button>
            <button type="button" class="sa-tab" role="tab" id="sa-tab-trends" @unless ($platformAnalytics) hidden @endunless aria-controls="sa-panel-trends" aria-selected="false" tabindex="-1" data-sa-tab="trends"><span class="material-symbols-outlined" aria-hidden="true">insights</span>{{ __('superadmin.analytics.tab_trends') }}</button>
        </div>

        <div class="sa-tab-panel" role="tabpanel" id="sa-panel-operations" aria-labelledby="sa-tab-operations" data-sa-tab-panel="operations">
        <div id="sa-analytics-notice" class="sa-notice error" role="alert"></div>

        <div class="sa-grid kpis kpis-3">
            @foreach ([
                'active_rooms' => ['kpi_active_rooms', 'kpi_active_rooms_hint'],
                'active_users' => ['kpi_active_users', 'kpi_active_users_hint'],
                'gmv' => ['kpi_gmv', 'kpi_gmv_hint'],
                'collection_rate' => ['kpi_collection_rate', 'kpi_collection_rate_hint'],
                'outstanding_debt' => ['kpi_outstanding_debt', null],
                'high_security_events' => ['kpi_high_security', 'kpi_high_security_hint'],
            ] as $kpi => [$labelKey, $hintKey])
                <article class="sa-card sa-kpi" data-kpi="{{ $kpi }}">
                    <span class="label">{{ __('superadmin.analytics.' . $labelKey) }}</span>
                    <strong class="value" data-kpi-value>—</strong>
                    <span class="sa-kpi-delta" data-kpi-delta></span>
                    <span class="hint" data-kpi-hint>{{ $hintKey ? __('superadmin.analytics.' . $hintKey, ['days' => $analyticsDays]) : '' }}</span>
                </article>
            @endforeach
        </div>

        <div class="sa-split">
            <section class="sa-card sa-section" aria-labelledby="sa-trend-title">
                <div class="sa-section-header">
                    <div>
                        <h2 id="sa-trend-title">{{ __('superadmin.analytics.trend_title') }}</h2>
                        <p>{{ __('superadmin.analytics.trend_description') }} <strong id="sa-trend-total"></strong></p>
                    </div>
                    <div class="sa-segmented" role="group" aria-label="{{ __('superadmin.analytics.trend_title') }}">
                        <button type="button" class="is-active" data-trend-metric="orders" aria-pressed="true">{{ __('superadmin.analytics.metric_orders') }}</button>
                        <button type="button" data-trend-metric="gmv" aria-pressed="false">{{ __('superadmin.analytics.metric_gmv') }}</button>
                    </div>
                </div>
                <div id="sa-trend-chart" class="sa-chart" role="img" aria-labelledby="sa-trend-title"></div>
                <details class="sa-chart-table">
                    <summary>{{ __('superadmin.analytics.show_table') }}</summary>
                    <div class="sa-table-wrap">
                        <table class="sa-table">
                            <thead>
                                <tr>
                                    <th class="col-date">{{ __('superadmin.analytics.date') }}</th>
                                    <th class="num">{{ __('superadmin.analytics.metric_orders') }}</th>
                                    <th class="num">{{ __('superadmin.analytics.metric_gmv') }}</th>
                                </tr>
                            </thead>
                            <tbody id="sa-trend-table"></tbody>
                        </table>
                    </div>
                </details>
            </section>
            <section class="sa-card sa-section" aria-labelledby="sa-aging-title">
                <div class="sa-section-header">
                    <div>
                        <h2 id="sa-aging-title">{{ __('superadmin.analytics.aging_title') }}</h2>
                        <p>{{ __('superadmin.analytics.aging_description') }}</p>
                    </div>
                </div>
                <div id="sa-aging" class="sa-aging">
                    <p class="sa-empty">{{ __('superadmin.common.loading') }}</p>
                </div>
            </section>
        </div>

        <section class="sa-card sa-section" aria-labelledby="sa-stuck-title">
            <div class="sa-section-header">
                <div>
                    <h2 id="sa-stuck-title">{{ __('superadmin.analytics.stuck_title') }}</h2>
                    <p>{{ __('superadmin.analytics.stuck_description', ['hours' => \App\Services\Dashboard\SuperadminDashboardService::STUCK_CAMPAIGN_GRACE_HOURS]) }}</p>
                </div>
            </div>
            <div class="sa-table-wrap">
                <table class="sa-table">
                    <thead>
                        <tr>
                            <th>{{ __('superadmin.common.campaign') }}</th>
                            <th>{{ __('superadmin.common.room') }}</th>
                            <th class="col-status">{{ __('superadmin.common.status') }}</th>
                            <th class="col-date">{{ __('superadmin.analytics.col_deadline') }}</th>
                            <th class="col-date">{{ __('superadmin.analytics.col_overdue_by') }}</th>
                            <th class="num">{{ __('superadmin.analytics.col_orders') }}</th>
                            <th class="col-actions"><span class="sa-sr-only">{{ __('superadmin.common.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody id="sa-stuck-body">
                        <tr><td colspan="7" class="sa-empty">{{ __('superadmin.common.loading') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="sa-card sa-section" aria-labelledby="sa-rooms-title">
            <div class="sa-section-header">
                <div>
                    <h2 id="sa-rooms-title">{{ __('superadmin.analytics.rooms_title') }}</h2>
                    <p>{{ __('superadmin.analytics.rooms_description', ['days' => $analyticsDays]) }}</p>
                </div>
            </div>
            <div class="sa-table-wrap">
                <table class="sa-table sa-room-health">
                    <thead>
                        <tr>
                            <th>{{ __('superadmin.common.room') }}</th>
                            <th class="num">{{ __('superadmin.analytics.col_members') }}</th>
                            <th class="num">{{ __('superadmin.analytics.col_campaigns') }}</th>
                            <th class="num">{{ __('superadmin.analytics.col_orders') }}</th>
                            <th class="num">{{ __('superadmin.analytics.col_gmv') }}</th>
                            <th class="num">{{ __('superadmin.analytics.col_outstanding') }}</th>
                            <th class="num">{{ __('superadmin.analytics.col_overdue', ['days' => \App\Services\Dashboard\SuperadminDashboardService::OVERDUE_DEBT_DAYS]) }}</th>
                            <th class="col-date">{{ __('superadmin.analytics.col_last_activity') }}</th>
                            <th>{{ __('superadmin.analytics.col_flags') }}</th>
                        </tr>
                    </thead>
                    <tbody id="sa-rooms-body">
                        <tr><td colspan="9" class="sa-empty">{{ __('superadmin.common.loading') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        </div>

        <div class="sa-tab-panel" role="tabpanel" id="sa-panel-security" aria-labelledby="sa-tab-security" data-sa-tab-panel="security" hidden>
        @php($insights = \App\Services\Dashboard\SuperadminInsightsService::class)
        <div id="sa-insights-notice" class="sa-notice error" role="alert"></div>
        <section class="sa-card sa-section" aria-labelledby="sa-heatmap-title">
            <div class="sa-section-header">
                <div>
                    <h2 id="sa-heatmap-title">{{ __('superadmin.analytics.heatmap_title') }}</h2>
                    <p>{{ __('superadmin.analytics.heatmap_description', ['days' => $insights::HEATMAP_DAYS, 'timezone' => config('app.display_timezone')]) }}
                        <strong id="sa-heatmap-peak"></strong></p>
                </div>
            </div>
            <div id="sa-heatmap" class="sa-heatmap-wrap">
                <p class="sa-empty">{{ __('superadmin.common.loading') }}</p>
            </div>
        </section>

        <div class="sa-split">
            <section class="sa-card sa-section" aria-labelledby="sa-security-title">
                <div class="sa-section-header">
                    <div>
                        <h2 id="sa-security-title">{{ __('superadmin.analytics.security_title') }}</h2>
                        <p>{{ __('superadmin.analytics.security_description', ['days' => $insights::SECURITY_DAYS]) }}</p>
                    </div>
                    <div class="sa-legend" aria-hidden="true">
                        @foreach (['low', 'medium', 'high'] as $severity)
                            <span><i class="sa-swatch sev-{{ $severity }}"></i>{{ __('superadmin.analytics.sev_' . $severity) }}</span>
                        @endforeach
                    </div>
                </div>
                <div id="sa-security-chart" class="sa-chart" role="img" aria-labelledby="sa-security-title"></div>
                <details class="sa-chart-table">
                    <summary>{{ __('superadmin.analytics.show_table') }}</summary>
                    <div class="sa-table-wrap">
                        <table class="sa-table">
                            <thead>
                                <tr>
                                    <th class="col-date">{{ __('superadmin.analytics.date') }}</th>
                                    @foreach (['low', 'medium', 'high'] as $severity)
                                        <th class="num">{{ __('superadmin.analytics.sev_' . $severity) }}</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody id="sa-security-table"></tbody>
                        </table>
                    </div>
                </details>
            </section>
            <section class="sa-card sa-section" aria-labelledby="sa-types-title">
                <div class="sa-section-header">
                    <div>
                        <h2 id="sa-types-title">{{ __('superadmin.analytics.types_title') }}</h2>
                        <p>{{ __('superadmin.analytics.types_description', ['days' => $insights::ACTIVITY_DAYS]) }}</p>
                    </div>
                </div>
                <div id="sa-security-types" class="sa-aging">
                    <p class="sa-empty">{{ __('superadmin.common.loading') }}</p>
                </div>
            </section>
        </div>

        <section class="sa-card sa-section" aria-labelledby="sa-ips-title">
            <div class="sa-section-header">
                <div>
                    <h2 id="sa-ips-title">{{ __('superadmin.analytics.ips_title') }}</h2>
                    <p>{{ __('superadmin.analytics.ips_description', ['hours' => $insights::IP_WINDOW_HOURS, 'count' => $insights::SUSPICIOUS_FAILED_LOGINS]) }}</p>
                </div>
            </div>
            <div class="sa-table-wrap">
                <table class="sa-table">
                    <thead>
                        <tr>
                            <th class="col-mono">{{ __('superadmin.analytics.col_ip') }}</th>
                            <th class="num">{{ __('superadmin.analytics.col_events') }}</th>
                            <th class="num">{{ __('superadmin.analytics.col_failed_logins') }}</th>
                            <th class="num">{{ __('superadmin.analytics.col_high') }}</th>
                            <th class="col-date">{{ __('superadmin.analytics.col_last_seen') }}</th>
                            <th class="col-status">{{ __('superadmin.common.status') }}</th>
                            <th class="col-actions"><span class="sa-sr-only">{{ __('superadmin.common.actions') }}</span></th>
                        </tr>
                    </thead>
                    <tbody id="sa-ips-body">
                        <tr><td colspan="7" class="sa-empty">{{ __('superadmin.common.loading') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="sa-card sa-section" aria-labelledby="sa-admins-title">
            <div class="sa-section-header">
                <div>
                    <h2 id="sa-admins-title">{{ __('superadmin.analytics.admins_title') }}</h2>
                    <p>{{ __('superadmin.analytics.admins_description', ['days' => $insights::ACTIVITY_DAYS]) }}</p>
                </div>
            </div>
            <div class="sa-table-wrap">
                <table class="sa-table">
                    <thead>
                        <tr>
                            <th>{{ __('superadmin.analytics.col_admin') }}</th>
                            <th class="num">{{ __('superadmin.analytics.col_rooms') }}</th>
                            <th class="num">{{ __('superadmin.analytics.col_campaigns_created') }}</th>
                            <th class="num">{{ __('superadmin.analytics.col_payments') }}</th>
                            <th class="num">{{ __('superadmin.analytics.col_audit_actions') }}</th>
                            <th class="col-date">{{ __('superadmin.analytics.col_last_login') }}</th>
                            <th>{{ __('superadmin.analytics.col_flags') }}</th>
                        </tr>
                    </thead>
                    <tbody id="sa-admins-body">
                        <tr><td colspan="7" class="sa-empty">{{ __('superadmin.common.loading') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        </div>

        <div class="sa-tab-panel" role="tabpanel" id="sa-panel-trends" aria-labelledby="sa-tab-trends" data-sa-tab-panel="trends" hidden>
        @php($trends = \App\Services\Dashboard\SuperadminTrendsService::class)
        <div id="sa-trends-notice" class="sa-notice error" role="alert"></div>
        <section class="sa-card sa-section" aria-labelledby="sa-cohort-title">
            <div class="sa-section-header">
                <div>
                    <h2 id="sa-cohort-title">{{ __('superadmin.analytics.cohort_title') }}</h2>
                    <p>{{ __('superadmin.analytics.cohort_description', ['months' => $trends::COHORT_MONTHS]) }}</p>
                </div>
            </div>
            <div class="sa-table-wrap">
                <table class="sa-table sa-cohort-table">
                    <thead>
                        <tr>
                            <th>{{ __('superadmin.analytics.col_cohort') }}</th>
                            <th class="num">{{ __('superadmin.analytics.col_cohort_size') }}</th>
                            @for ($offset = 0; $offset < $trends::COHORT_MONTHS; $offset++)
                                <th class="num">{{ __('superadmin.analytics.month_offset', ['offset' => $offset]) }}</th>
                            @endfor
                        </tr>
                    </thead>
                    <tbody id="sa-cohort-body">
                        <tr><td colspan="{{ $trends::COHORT_MONTHS + 2 }}" class="sa-empty">{{ __('superadmin.common.loading') }}</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <div class="sa-split">
            <section class="sa-card sa-section" aria-labelledby="sa-feedback-title">
                <div class="sa-section-header">
                    <div>
                        <h2 id="sa-feedback-title">{{ __('superadmin.analytics.feedback_title') }}</h2>
                        <p>{{ __('superadmin.analytics.feedback_description', ['months' => $trends::FEEDBACK_MONTHS]) }}
                            <strong id="sa-feedback-summary"></strong></p>
                    </div>
                </div>
                <div id="sa-feedback" class="sa-feedback">
                    <p class="sa-empty">{{ __('superadmin.common.loading') }}</p>
                </div>
            </section>
            <section class="sa-card sa-section" aria-labelledby="sa-contact-title">
                <div class="sa-section-header">
                    <div>
                        <h2 id="sa-contact-title">{{ __('superadmin.analytics.contact_title') }}</h2>
                        <p>{{ __('superadmin.analytics.contact_description', ['days' => $trends::CONTACT_DAYS]) }}</p>
                    </div>
                </div>
                <div id="sa-contact-topics" class="sa-aging">
                    <p class="sa-empty">{{ __('superadmin.common.loading') }}</p>
                </div>
            </section>
        </div>

        <section class="sa-card sa-section" aria-labelledby="sa-infra-title">
            <div class="sa-section-header">
                <div>
                    <h2 id="sa-infra-title">{{ __('superadmin.analytics.infra_title') }}</h2>
                    <p>{{ __('superadmin.analytics.infra_description', ['days' => $trends::HISTORY_DAYS]) }}</p>
                </div>
            </div>
            <div class="sa-infra-stats">
                <div><span>{{ __('superadmin.analytics.db_uptime') }}</span><strong id="sa-infra-db">—</strong></div>
                <div><span>{{ __('superadmin.analytics.socket_uptime') }}</span><strong id="sa-infra-socket">—</strong></div>
                <div><span>{{ __('superadmin.analytics.storage_forecast') }}</span><strong id="sa-infra-storage">—</strong></div>
            </div>
            <div id="sa-infra-empty" class="sa-empty" hidden>{{ __('superadmin.analytics.infra_empty') }}</div>
            <div id="sa-infra-charts" class="sa-infra-charts">
                @foreach (['failed_jobs', 'pending_jobs', 'socket', 'storage'] as $chart)
                    <figure class="sa-infra-chart">
                        <figcaption>{{ __('superadmin.analytics.chart_' . $chart) }}</figcaption>
                        <div class="sa-chart sa-chart-small" data-infra-chart="{{ $chart }}" role="img"
                            aria-label="{{ __('superadmin.analytics.chart_' . $chart) }}"></div>
                    </figure>
                @endforeach
            </div>
        </section>
        </div>
    </section>
    @else
        <p class="sa-notice is-visible mt-4" data-platform-analytics-hidden>{{ __('platform.dashboard.analytics_hidden') }}</p>
    @endif
@endsection

@push('scripts')
    <x-superadmin.page-script name="dashboard" :i18n="[
        'active_rooms' => __('superadmin.dashboard.active_rooms'), 'active_users' => __('superadmin.dashboard.active_users'),
        'failed_count' => __('superadmin.dashboard.failed_count'), 'storage_usage' => __('superadmin.dashboard.storage_usage'),
        'storage_usage_quota' => __('superadmin.dashboard.storage_usage_quota'), 'storage_usage_unknown' => __('superadmin.dashboard.storage_usage_unknown'),
    ]" :urls="['summary' => route('superadmin.dashboard')]" />
@endpush
