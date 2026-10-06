{{-- Printable platform invoice (App\Services\Billing\InvoicePdfService): rendered to an A4 PDF by headless
     Chrome (download) and shown inside the admin invoice page (view).
     Self-contained on purpose (inline CSS, no app layout/assets), so the PDF never depends on the asset build. --}}
@php
    $money = static fn (int $amount): string => \App\Support\Helpers\FormatHelper::formatCurrency($amount);
    $admin = $invoice->admin;
    $status = $invoice->status->value;
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('platform.billing.document.title') }} {{ $invoice->number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #fff; color: #0b1c30; font: 13px/1.5 "Segoe UI", Inter, Arial, "Noto Sans", sans-serif; }
        .sheet { max-width: 800px; margin: 0 auto; padding: 32px; }
        .head { display: flex; justify-content: space-between; gap: 24px; align-items: flex-start; border-bottom: 2px solid #006948; padding-bottom: 18px; }
        .brand { display: flex; align-items: center; gap: 10px; }
        .brand-mark { width: 38px; height: 38px; border-radius: 10px; background: #006948; display: flex; align-items: center; justify-content: center; }
        .brand strong { display: block; font-size: 18px; }
        .brand small { color: #006948; font-size: 10px; letter-spacing: .12em; text-transform: uppercase; }
        .doc-title { text-align: right; }
        .doc-title h1 { margin: 0; font-size: 22px; letter-spacing: -.02em; }
        .doc-title .number { font-family: ui-monospace, Consolas, monospace; font-weight: 700; }
        .status { display: inline-block; margin-top: 6px; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: 700; }
        .status-open { background: #fffbeb; color: #92400e; }
        .status-overdue { background: #ffdad6; color: #93000a; }
        .status-paid { background: #ecfdf5; color: #065f46; }
        .status-void { background: #eef2f7; color: #475569; }
        .parties { display: flex; gap: 24px; margin: 22px 0; }
        .party { flex: 1; border: 1px solid #e2e8f0; border-radius: 10px; padding: 12px 14px; }
        .label { color: #64748b; font-size: 10px; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; margin-bottom: 4px; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
        .meta td { padding: 4px 0; }
        .meta td:last-child { text-align: right; font-weight: 600; }
        table.lines { width: 100%; border-collapse: collapse; }
        table.lines th { background: #f1f5f9; color: #475569; font-size: 10px; letter-spacing: .06em; text-transform: uppercase; text-align: left; padding: 8px 10px; }
        table.lines td { border-bottom: 1px solid #e2e8f0; padding: 9px 10px; vertical-align: top; }
        .num { text-align: right; white-space: nowrap; }
        .totals { width: 320px; margin: 14px 0 0 auto; border-collapse: collapse; }
        .totals td { padding: 5px 0; }
        .totals td:last-child { text-align: right; }
        .totals .grand td { border-top: 2px solid #0b1c30; padding-top: 8px; font-size: 15px; font-weight: 800; }
        .totals .due td { color: #b91c1c; font-weight: 700; }
        h2 { font-size: 13px; margin: 26px 0 8px; }
        .muted { color: #64748b; }
        .foot { margin-top: 32px; padding-top: 12px; border-top: 1px solid #e2e8f0; color: #64748b; font-size: 11px; text-align: center; }
        @media print { .sheet { padding: 0; } }
    </style>
</head>
<body>
<main class="sheet">
    <header class="head">
        <div class="brand">
            <span class="brand-mark">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="#ffffff" aria-hidden="true"><path d="M20 3H4v10c0 2.21 1.79 4 4 4h6c2.21 0 4-1.79 4-4v-3h2c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 5h-2V5h2v3zM4 19h16v2H4z"/></svg>
            </span>
            <div>
                <strong>DrinkFlow</strong>
                <small>{{ __('admin.brand_subtitle') }}</small>
            </div>
        </div>
        <div class="doc-title">
            <h1>{{ __('platform.billing.document.title') }}</h1>
            <div class="number">{{ $invoice->number ?: '#'.$invoice->id }}</div>
            <span class="status status-{{ $status }}">{{ __('platform.billing.status.'.$status) }}</span>
        </div>
    </header>

    <section class="parties">
        <div class="party">
            <div class="label">{{ __('platform.billing.document.issuer') }}</div>
            <strong>DrinkFlow</strong>
            <div class="muted">{{ parse_url((string) config('app.url'), PHP_URL_HOST) ?: config('app.url') }}</div>
        </div>
        <div class="party">
            <div class="label">{{ __('platform.billing.document.bill_to') }}</div>
            <strong>{{ $admin?->name }}</strong>
            @if ($admin?->company)<div>{{ $admin->company }}</div>@endif
            <div class="muted">{{ $admin?->email }}@if ($admin?->phone) · {{ $admin->phone }}@endif</div>
        </div>
    </section>

    <table class="meta">
        <tr><td class="muted">{{ __('platform.billing.document.issued_at') }}</td><td>{{ $invoice->issued_at?->toAppDate() }}</td></tr>
        <tr><td class="muted">{{ __('platform.billing.due_at') }}</td><td>{{ $invoice->due_at?->toAppDate() }}</td></tr>
        @if ($invoice->paid_at)
            <tr><td class="muted">{{ __('platform.billing.paid_at') }}</td><td>{{ $invoice->paid_at->toAppDateTime() }}</td></tr>
        @endif
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>{{ __('platform.billing.document.description') }}</th>
                <th>{{ __('platform.subscriptions.period') }}</th>
                <th class="num">{{ __('platform.billing.amount') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ __('platform.billing.document.subscription_line', ['package' => $invoice->package?->name ?? '—']) }}</td>
                <td>{{ $invoice->period_start?->toAppDate() }} → {{ $invoice->period_end?->toAppDate() }}</td>
                <td class="num">{{ $money((int) $invoice->subtotal) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="totals">
        <tr><td class="muted">{{ __('platform.billing.document.subtotal') }}</td><td>{{ $money((int) $invoice->subtotal) }}</td></tr>
        @if ((int) $invoice->credit > 0)
            <tr><td class="muted">{{ __('platform.billing.document.credit') }}</td><td>-{{ $money((int) $invoice->credit) }}</td></tr>
        @endif
        <tr class="grand"><td>{{ __('platform.billing.total') }}</td><td>{{ $money((int) $invoice->total) }}</td></tr>
        <tr><td class="muted">{{ __('platform.billing.paid') }}</td><td>{{ $money((int) $invoice->paid_amount) }}</td></tr>
        @if ($status !== 'void')
            <tr class="due"><td>{{ __('platform.billing.remaining') }}</td><td>{{ $money($invoice->remaining()) }}</td></tr>
        @endif
    </table>

    @if ($status === 'void' && $invoice->void_reason)
        <p class="muted">{{ __('platform.billing.void_reason') }}: {{ $invoice->void_reason }}</p>
    @endif

    @if ($invoice->payments->isNotEmpty())
        <h2>{{ __('platform.billing.payments') }}</h2>
        <table class="lines">
            <thead>
                <tr>
                    <th>{{ __('platform.billing.paid_at') }}</th>
                    <th>{{ __('platform.billing.method') }}</th>
                    <th>{{ __('platform.billing.reference') }}</th>
                    <th class="num">{{ __('platform.billing.amount') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($invoice->payments as $payment)
                    <tr>
                        <td>{{ $payment->paid_at?->toAppDateTime() }}</td>
                        <td>{{ __('platform.billing.methods.'.$payment->method->value) }}</td>
                        <td>{{ $payment->reference ?: '—' }}</td>
                        <td class="num">{{ $money((int) $payment->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <footer class="foot">{{ __('platform.billing.document.footer', ['date' => now()->toAppDateTime()]) }}</footer>
</main>
</body>
</html>
