@extends('superadmin.layout', ['title' => __('platform.billing.outstanding_title'), 'active' => 'outstanding'])
@section('content')
    @php
        $money = static fn (int $amount): string => \App\Support\Helpers\FormatHelper::formatCurrency($amount);
    @endphp
    <x-superadmin.page-header :eyebrow="__('platform.nav.platform_finance')" :title="__('platform.billing.outstanding_title')" :description="__('platform.billing.outstanding_description', ['amount' => $money($totalDue)])" />
    <x-superadmin.flash />
    <section class="sa-card sa-section">
        <div class="sa-section-header">
            <div>
                <h2>{{ __('platform.billing.outstanding_title') }}</h2>
                <p>{{ __('platform.billing.invoices_count', ['count' => $invoices->total()]) }}</p>
            </div>
            <form method="GET" class="superadmin-actions">
                <x-superadmin.search-input :value="$filters['search']" placeholder="{{ __('platform.billing.search') }}" />
                <select name="status" class="sa-input" aria-label="{{ __('superadmin.common.status') }}">
                    <option value="">{{ __('superadmin.common.all_statuses') }}</option>
                    @foreach (['open', 'overdue'] as $value)
                        <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ __('platform.billing.status.'.$value) }}</option>
                    @endforeach
                </select>
                <button class="sa-button secondary" type="submit"><span class="material-symbols-outlined text-[16px]">filter_list</span>{{ __('superadmin.common.filter') }}</button>
            </form>
        </div>
        <div class="sa-table-wrap">
            <table class="sa-table">
                <thead>
                    <tr>
                        <th class="col-mono">{{ __('platform.billing.invoice') }}</th>
                        <th class="col-primary">{{ __('platform.subscriptions.agent') }}</th>
                        <th class="text-right num">{{ __('platform.billing.total') }}</th>
                        <th class="text-right num">{{ __('platform.billing.remaining') }}</th>
                        <th class="col-date">{{ __('platform.billing.due_at') }}</th>
                        <th class="col-status">{{ __('superadmin.common.status') }}</th>
                        <th class="text-right col-actions">{{ __('superadmin.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($invoices as $invoice)
                        <tr>
                            <td class="col-mono"><strong class="font-mono">{{ $invoice->number }}</strong><small class="block text-outline">{{ $invoice->package?->name }}</small></td>
                            <td class="col-primary"><strong class="block truncate">{{ $invoice->admin?->name }}</strong><small class="block truncate text-outline">{{ $invoice->admin?->email }}</small></td>
                            <td class="text-right whitespace-nowrap num">{{ $money($invoice->total) }}</td>
                            <td class="text-right whitespace-nowrap font-semibold num">{{ $money($invoice->remaining()) }}</td>
                            <td class="whitespace-nowrap col-date">{{ $invoice->due_at->toAppDate() }}</td>
                            <td class="col-status">@include('superadmin.billing._invoice-status', ['invoice' => $invoice])</td>
                            <td class="text-right col-actions"><a class="sa-button secondary" href="{{ route('superadmin.billing.agent', $invoice->admin_id) }}"><span class="material-symbols-outlined text-[16px]">visibility</span>{{ __('superadmin.common.details') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="7"><x-superadmin.empty-state icon="task_alt" :title="__('platform.billing.no_outstanding')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($invoices->hasPages())
            {{ $invoices->links('components.superadmin.pagination') }}
        @endif
    </section>
@endsection
