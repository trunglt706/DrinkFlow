@extends('superadmin.layout', ['title' => __('platform.registrations.title'), 'active' => 'registrations'])
@section('content')
    <x-superadmin.page-header :eyebrow="__('platform.nav.agents')" :title="__('platform.registrations.title')" :description="__('platform.registrations.description')" />
    <x-superadmin.flash />
    <section class="sa-card sa-section">
        <div class="sa-section-header">
            <div>
                <h2>{{ __('platform.registrations.queue') }}</h2>
                <p>{{ __('platform.registrations.count', ['count' => $registrations->total()]) }}</p>
            </div>
            <form method="GET" class="superadmin-actions">
                <x-superadmin.search-input :value="$filters['search']" placeholder="{{ __('platform.registrations.search') }}" />
                <select name="verification" class="sa-input" aria-label="{{ __('platform.registrations.email_status') }}">
                    <option value="">{{ __('platform.registrations.all_emails') }}</option>
                    <option value="verified" @selected($filters['verification'] === 'verified')>{{ __('platform.registrations.email_verified') }}</option>
                    <option value="unverified" @selected($filters['verification'] === 'unverified')>{{ __('platform.registrations.email_unverified') }}</option>
                </select>
                <button class="sa-button secondary" type="submit"><span class="material-symbols-outlined text-[16px]">filter_list</span>{{ __('superadmin.common.filter') }}</button>
            </form>
        </div>
        <div class="sa-table-wrap">
            <table class="sa-table">
                <thead>
                    <tr>
                        <th class="col-primary">{{ __('platform.registrations.applicant') }}</th>
                        <th>{{ __('platform.registration.field_package') }}</th>
                        <th class="col-status">{{ __('platform.registrations.email_status') }}</th>
                        <th class="col-date">{{ __('platform.registrations.registered_at') }}</th>
                        <th class="text-right col-actions">{{ __('superadmin.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($registrations as $registration)
                        <tr>
                            <td class="col-primary">
                                <strong class="block truncate">{{ $registration->name }}</strong>
                                <small class="block truncate text-outline">{{ $registration->email }}@if ($registration->company) · {{ $registration->company }}@endif</small>
                            </td>
                            <td>{{ $registration->requestedPackage?->name ?? '—' }}</td>
                            <td class="col-status">
                                @if ($registration->hasVerifiedEmail())
                                    <span class="status-pill status-active"><span class="status-dot"></span>{{ __('platform.registrations.email_verified') }}</span>
                                @else
                                    <span class="status-pill status-pending"><span class="status-dot"></span>{{ __('platform.registrations.email_unverified') }}</span>
                                @endif
                            </td>
                            <td class="whitespace-nowrap col-date">{{ $registration->registered_at?->toAppDateTime() }}</td>
                            <td class="text-right col-actions">
                                <a class="sa-button secondary" href="{{ route('superadmin.registrations.show', $registration) }}"><span class="material-symbols-outlined text-[16px]">fact_check</span>{{ __('platform.registrations.review') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <x-superadmin.empty-state icon="how_to_reg" :title="__('platform.registrations.empty_title')" :description="__('platform.registrations.empty_description')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($registrations->hasPages())
            {{ $registrations->links('components.superadmin.pagination') }}
        @endif
    </section>
@endsection
