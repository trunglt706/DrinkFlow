@extends('superadmin.layout', ['title' => __('platform.billing.revenue_title'), 'active' => 'revenue'])
@section('content')
    @php
        $money = static fn (int $amount): string => \App\Support\Helpers\FormatHelper::formatCurrency($amount);
        $peak = max(1, collect($trend)->flatMap(static fn (array $row): array => [$row['invoiced'], $row['collected']])->max());
        $kpis = [
            ['mrr', 'autorenew', $money($summary['mrr']), __('platform.billing.active_subscriptions', ['count' => $summary['active_subscriptions']])],
            ['invoiced_month', 'receipt_long', $money($summary['invoiced_month']), null],
            ['collected_month', 'savings', $money($summary['collected_month']), null],
            ['outstanding', 'account_balance_wallet', $money($summary['outstanding']), null],
            ['overdue', 'warning', $money($summary['overdue']), __('platform.billing.invoices_count', ['count' => $summary['overdue_count']])],
        ];
    @endphp
    <x-superadmin.page-header :eyebrow="__('platform.nav.platform_finance')" :title="__('platform.billing.revenue_title')" :description="__('platform.billing.revenue_description')" />
    <section class="sa-grid kpis" aria-label="{{ __('platform.billing.revenue_title') }}">
        @foreach ($kpis as [$key, $icon, $value, $hint])
            <x-superadmin.kpi data-kpi="{{ $key }}" :label="__('platform.billing.kpi.'.$key)" :icon="$icon" :value="$value" :hint="$hint"
                :tone="$key === 'overdue' ? 'danger' : ($key === 'mrr' ? 'accent' : null)" />
        @endforeach
    </section>

    <div class="sa-split">
        <section class="sa-card sa-section">
            <div class="sa-section-header"><div><h2>{{ __('platform.billing.trend') }}</h2><p>{{ __('platform.billing.trend_hint') }}</p></div></div>
            <div class="sa-legend mb-4">
                <span><span class="sa-swatch sa-bar-invoiced" aria-hidden="true"></span>{{ __('platform.billing.invoiced') }}</span>
                <span><span class="sa-swatch sa-bar-collected" aria-hidden="true"></span>{{ __('platform.billing.collected') }}</span>
            </div>
            <div class="sa-trend">
                @foreach ($trend as $row)
                    <div class="sa-trend-row">
                        <span class="sa-trend-month">{{ $row['month'] }}</span>
                        <div class="sa-trend-bars">
                            <div class="sa-trend-bar"><div class="sa-trend-track"><span class="sa-bar-invoiced" style="width: {{ max(1, round($row['invoiced'] / $peak * 100)) }}%"></span></div><small>{{ __('platform.billing.invoiced') }}: {{ $money($row['invoiced']) }}</small></div>
                            <div class="sa-trend-bar"><div class="sa-trend-track"><span class="sa-bar-collected" style="width: {{ max(1, round($row['collected'] / $peak * 100)) }}%"></span></div><small>{{ __('platform.billing.collected') }}: {{ $money($row['collected']) }}</small></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
        <section class="sa-card sa-section">
            <div class="sa-section-header"><div><h2>{{ __('platform.billing.by_package') }}</h2></div></div>
            <div class="sa-table-wrap">
                <table class="sa-table">
                    <thead><tr><th class="col-primary">{{ __('platform.packages.package') }}</th><th class="text-right num">{{ __('platform.billing.subscriptions') }}</th><th class="text-right num">{{ __('platform.billing.kpi.mrr') }}</th></tr></thead>
                    <tbody>
                        @forelse ($byPackage as $row)
                            <tr><td class="col-primary">{{ $row['package'] }}</td><td class="text-right num">{{ $row['subscriptions'] }}</td><td class="text-right whitespace-nowrap num">{{ $money($row['mrr']) }}</td></tr>
                        @empty
                            <tr><td colspan="3"><x-superadmin.empty-state icon="payments" :title="__('platform.billing.no_revenue')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
