@extends('superadmin.layout', ['title' => __('platform.billing.revenue_title'), 'active' => 'revenue'])
@section('content')
    @php
        $money = static fn (int $amount): string => \App\Support\Helpers\FormatHelper::formatCurrency($amount);
        // Chart data for resources/js/superadmin/revenue-charts.js; amounts are pre-formatted like the rest of the page.
        $trendRows = collect($trend)->map(static fn (array $row): array => [
            'label' => \Carbon\Carbon::createFromFormat('!Y-m', $row['month'])->format('m/Y'),
            'invoiced' => $row['invoiced'],
            'collected' => $row['collected'],
            'invoiced_text' => $money($row['invoiced']),
            'collected_text' => $money($row['collected']),
        ])->all();
        // Fixed categorical order (--sa-series-1…8): the 8th slot and beyond fold into "Other packages".
        $packageRows = collect($byPackage);
        if ($packageRows->count() > 8) {
            $rest = $packageRows->slice(7);
            $packageRows = $packageRows->take(7)->push([
                'package' => __('platform.billing.chart_other_packages'),
                'subscriptions' => (int) $rest->sum('subscriptions'),
                'mrr' => (int) $rest->sum('mrr'),
            ]);
        }
        $mrrTotal = (int) $packageRows->sum('mrr');
        $packageRows = $packageRows->values()->map(static fn (array $row, int $index): array => $row + [
            'slot' => $index + 1,
            'share' => $mrrTotal > 0 ? round($row['mrr'] / $mrrTotal * 100, 1) : 0.0,
            'mrr_text' => $money($row['mrr']),
        ]);
        $chartI18n = ['invoiced' => __('platform.billing.invoiced'), 'collected' => __('platform.billing.collected')];
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

    <div class="sa-split sa-revenue-split">
        <section class="sa-card sa-section" aria-labelledby="sa-revenue-trend-title">
            <div class="sa-section-header"><div><h2 id="sa-revenue-trend-title">{{ __('platform.billing.trend') }}</h2><p>{{ __('platform.billing.trend_hint') }}</p></div></div>
            <div class="sa-legend mb-2" aria-hidden="true">
                <span><i class="sa-swatch sa-swatch-bar" style="--swatch: var(--sa-series-1)"></i>{{ __('platform.billing.invoiced') }}</span>
                <span><i class="sa-swatch sa-swatch-line" style="--swatch: var(--sa-series-2)"></i>{{ __('platform.billing.collected') }}</span>
            </div>
            <div class="sa-chart" data-revenue-trend="{{ json_encode($trendRows, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) }}"
                data-i18n="{{ json_encode($chartI18n, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) }}"
                role="img" aria-label="{{ __('platform.billing.chart_trend_label') }}"></div>
            <details class="sa-chart-table">
                <summary>{{ __('platform.billing.chart_show_table') }}</summary>
                <div class="sa-table-wrap">
                    <table class="sa-table">
                        <thead><tr><th class="col-date">{{ __('platform.billing.chart_month') }}</th><th class="num">{{ __('platform.billing.invoiced') }}</th><th class="num">{{ __('platform.billing.collected') }}</th></tr></thead>
                        <tbody>
                            @foreach ($trendRows as $row)
                                <tr><td class="col-date">{{ $row['label'] }}</td><td class="num">{{ $row['invoiced_text'] }}</td><td class="num">{{ $row['collected_text'] }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        </section>
        <section class="sa-card sa-section" aria-labelledby="sa-revenue-package-title">
            <div class="sa-section-header"><div><h2 id="sa-revenue-package-title">{{ __('platform.billing.by_package') }}</h2></div></div>
            @if ($mrrTotal > 0)
                <div class="sa-donut-wrap">
                    <div class="sa-chart sa-donut" data-revenue-donut="{{ json_encode($packageRows->all(), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) }}"
                        role="img" aria-label="{{ __('platform.billing.chart_package_label') }}">
                        <div class="sa-donut-center" aria-hidden="true"><strong>{{ $money($mrrTotal) }}</strong><small>{{ __('platform.billing.chart_mrr_total') }}</small></div>
                    </div>
                </div>
            @endif
            <div class="sa-table-wrap">
                <table class="sa-table" data-revenue-package-table>
                    <thead><tr><th class="col-primary">{{ __('platform.packages.package') }}</th><th class="text-right num">{{ __('platform.billing.subscriptions') }}</th><th class="text-right num" title="{{ __('platform.billing.kpi.mrr') }}">{{ __('platform.billing.chart_mrr_short') }}</th><th class="text-right num">{{ __('platform.billing.chart_share') }}</th></tr></thead>
                    <tbody>
                        @forelse ($packageRows as $row)
                            <tr data-package-slot="{{ $row['slot'] }}">
                                <td class="col-primary"><span class="inline-flex items-center gap-2"><i class="sa-swatch" style="--swatch: var(--sa-series-{{ $row['slot'] }})" aria-hidden="true"></i>{{ $row['package'] }}</span></td>
                                <td class="text-right num">{{ $row['subscriptions'] }}</td>
                                <td class="text-right whitespace-nowrap num">{{ $row['mrr_text'] }}</td>
                                <td class="text-right whitespace-nowrap num">{{ number_format($row['share'], 1, ',', '.') }}%</td>
                            </tr>
                        @empty
                            <tr><td colspan="4"><x-superadmin.empty-state icon="payments" :title="__('platform.billing.no_revenue')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
@endsection
