@extends('emails.platform.layout')
@section('title', __('platform.emails.invitation_subject', ['app' => config('app.name', 'DrinkFlow')]))
@section('content')
    <p>{{ __('platform.emails.invitation_intro') }}</p>
    @if ($inviterName !== null)
        <p class="muted">{{ __('platform.emails.invitation_invited_by', ['name' => $inviterName]) }}</p>
    @endif
    <div class="box">
        <p style="margin: 0;"><strong>{{ __('platform.emails.invitation_package_label') }}</strong> {{ $packageName }}</p>
    </div>
    <p>{{ __('platform.emails.invitation_activate_hint') }}</p>
    <p style="text-align: center;"><a class="button" href="{!! \App\Support\Helpers\EmailUrl::html($activationUrl) !!}">{{ __('platform.emails.invitation_button') }}</a></p>
    <p class="muted">{{ __('platform.emails.invitation_validity', ['hours' => $validHours]) }}</p>
    <p class="muted">{{ __('platform.emails.link_fallback') }}<br>{!! \App\Support\Helpers\EmailUrl::html($activationUrl) !!}</p>
    <p class="muted">{{ __('platform.emails.verify_ignore') }}</p>
@endsection
