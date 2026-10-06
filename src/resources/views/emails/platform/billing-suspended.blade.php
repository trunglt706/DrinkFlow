@extends('emails.platform.layout')
@php
    $money = static fn (int $amount): string => \App\Support\Helpers\FormatHelper::formatCurrency($amount);
@endphp
@section('title', __('platform.emails.billing_suspended_subject', ['app' => config('app.name', 'DrinkFlow')]))
@section('content')
    <p>{{ __('platform.emails.billing_suspended_intro') }}</p>
    @if ($invoice)
        <div class="box">
            <p style="margin: 0;"><strong>{{ __('platform.emails.invoice_label') }}</strong> {{ $invoice->number }}</p>
            <p style="margin: 6px 0 0;"><strong>{{ __('platform.emails.amount_due_label') }}</strong> {{ $money($invoice->remaining()) }}</p>
            <p style="margin: 6px 0 0;"><strong>{{ __('platform.emails.due_date_label') }}</strong> {{ $invoice->due_at->toAppDate() }}</p>
        </div>
    @endif
    <p>{{ __('platform.emails.billing_suspended_next_step') }}</p>
    <p class="muted">{{ __('platform.emails.rejected_contact') }}</p>
@endsection
