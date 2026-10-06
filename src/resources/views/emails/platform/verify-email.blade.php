@extends('emails.platform.layout')
@section('title', __('platform.emails.verify_subject', ['app' => config('app.name', 'DrinkFlow')]))
@section('content')
    <p>{{ __('platform.emails.verify_intro') }}</p>
    <p style="text-align: center;"><a class="button" href="{!! \App\Support\Helpers\EmailUrl::html($verificationUrl) !!}">{{ __('platform.emails.verify_button') }}</a></p>
    <p class="muted">{{ __('platform.emails.verify_validity', ['hours' => $validHours]) }}</p>
    <p class="muted">{{ __('platform.emails.link_fallback') }}<br>{!! \App\Support\Helpers\EmailUrl::html($verificationUrl) !!}</p>
    <p class="muted">{{ __('platform.emails.verify_ignore') }}</p>
@endsection
