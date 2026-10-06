@extends('emails.platform.layout')
@php
    $money = static fn (int $amount): string => \App\Support\Helpers\FormatHelper::formatCurrency($amount);
@endphp
@section('title', __('platform.emails.renewal_subject', ['app' => config('app.name', 'DrinkFlow'), 'date' => $subscription->expires_at?->toAppDate()]))
@section('content')
    <p>{{ __('platform.emails.renewal_intro', ['date' => $subscription->expires_at?->toAppDate()]) }}</p>
    <div class="box">
        <p style="margin: 0;"><strong>{{ __('platform.emails.activated_package_label') }}</strong> {{ $subscription->package?->name }}</p>
        <p style="margin: 6px 0 0;"><strong>{{ __('platform.emails.cycle_label') }}</strong> {{ \App\Services\Subscription\SubscriptionService::cycleLabel($subscription->periodMonths()) }}</p>
        <p style="margin: 6px 0 0;"><strong>{{ __('platform.emails.renewal_amount_label') }}</strong> {{ $money($subscription->periodPrice()) }}</p>
    </div>
    <p>{{ __('platform.emails.renewal_note', ['days' => (int) config('platform.billing.due_days', 7)]) }}</p>
    <p style="text-align: center;"><a class="button" href="{{ route('admin.subscription.show') }}">{{ __('platform.emails.manage_subscription_button') }}</a></p>
@endsection
