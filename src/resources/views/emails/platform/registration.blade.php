@extends('emails.platform.layout')
@php
    $money = static fn (int $amount): string => \App\Support\Helpers\FormatHelper::formatCurrency($amount);
@endphp
@section('title', __('platform.emails.registration_subject', ['app' => config('app.name', 'DrinkFlow')]))
@section('content')
    <p>{{ __('platform.emails.registration_intro') }}</p>
    <p style="text-align: center;"><a class="button" href="{{ $activationUrl }}">{{ __('platform.emails.registration_button') }}</a></p>
    <p class="muted">{{ __('platform.emails.invitation_validity', ['hours' => $validityHours]) }}</p>
    <div class="box">
        <p style="margin: 0;"><strong>{{ __('platform.emails.invitation_package_label') }}</strong> {{ $packageName }}</p>
        <p style="margin: 6px 0 0;"><strong>{{ __('platform.emails.cycle_label') }}</strong> {{ \App\Services\Subscription\SubscriptionService::cycleLabel($periodMonths) }}</p>
        <p style="margin: 6px 0 0;"><strong>{{ __('platform.emails.invoice_label') }}</strong> {{ $invoice->number }}</p>
        <p style="margin: 6px 0 0;"><strong>{{ __('platform.emails.amount_due_label') }}</strong> {{ $money((int) $invoice->total) }}</p>
        <p style="margin: 6px 0 0;"><strong>{{ __('platform.emails.due_date_label') }}</strong> {{ $invoice->due_at->toAppDate() }}</p>
    </div>
    <p>{{ __('platform.emails.registration_payment_note') }}</p>
    <p class="muted">{{ __('platform.emails.link_fallback') }}<br>{{ $activationUrl }}</p>
    <p class="muted">{{ __('platform.emails.verify_ignore') }}</p>
@endsection
