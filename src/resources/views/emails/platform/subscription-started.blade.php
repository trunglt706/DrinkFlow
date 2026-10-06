@extends('emails.platform.layout')
@section('title', __('platform.emails.subscription_started_subject', ['app' => config('app.name', 'DrinkFlow')]))
@section('content')
    <p>{{ __('platform.emails.subscription_started_intro') }}</p>
    <div class="box">
        <p style="margin: 0;"><strong>{{ __('platform.emails.activated_package_label') }}</strong> {{ $subscription->package?->name }}</p>
        <p style="margin: 6px 0 0;"><strong>{{ __('platform.emails.activated_room_limit_label') }}</strong> {{ $subscription->room_limit_snapshot }}</p>
        <p style="margin: 6px 0 0;"><strong>{{ __('platform.emails.cycle_label') }}</strong> {{ \App\Services\Subscription\SubscriptionService::cycleLabel($subscription->periodMonths()) }}</p>
        <p style="margin: 6px 0 0;"><strong>{{ __('platform.emails.period_label') }}</strong> {{ $subscription->starts_at?->toAppDate() }} → {{ $subscription->expires_at?->toAppDate() }}</p>
    </div>
    <p>{{ __('platform.emails.activated_next_step') }}</p>
    <p style="text-align: center;"><a class="button" href="{{ route('admin.login.page') }}">{{ __('platform.emails.sign_in_button') }}</a></p>
@endsection
