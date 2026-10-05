@extends('superadmin.layout', ['title' => __('superadmin.campaigns.title'), 'active' => 'campaigns'])
@section('content')
    <x-superadmin.page-header :eyebrow="__('superadmin.common.platform_data')" :title="__('superadmin.campaigns.title')" :description="__('superadmin.campaigns.description')" />
    <div id="notice" class="sa-notice"></div>
    <section class="sa-card sa-section">
        <div class="sa-section-header">
            <div>
                <h2>{{ __('superadmin.campaigns.registry') }}</h2>
                <p>{{ __('superadmin.common.campaigns_count', ['count' => $campaigns->total()]) }}</p>
            </div>
            <form method="GET" class="superadmin-actions">
                <x-superadmin.search-input :value="$filters['search']" placeholder="{{ __('superadmin.campaigns.search') }}" />
                <select name="room_id" class="sa-input" aria-label="{{ __('superadmin.common.room') }}">
                    <option value="">{{ __('superadmin.campaigns.all_rooms') }}</option>
                    @foreach ($rooms as $room)
                        <option value="{{ $room->id }}" @selected($filters['room_id'] === $room->id)>{{ $room->name }}</option>
                    @endforeach
                </select>
                <select name="status" class="sa-input" aria-label="{{ __('superadmin.common.status') }}">
                    <option value="">{{ __('superadmin.common.all_statuses') }}</option>
                    @foreach (\App\Enums\CampaignStatus::cases() as $case)
                        <option value="{{ $case->value }}" @selected($filters['status'] === $case->value)>{{ $case->label() }}</option>
                    @endforeach
                </select>
                <button class="sa-button secondary" type="submit"><span class="material-symbols-outlined text-[16px]">filter_list</span>{{ __('superadmin.common.filter') }}</button>
            </form>
        </div>
        <div class="sa-table-wrap">
            <table class="sa-table">
                <thead>
                    <tr>
                        <th class="col-primary">{{ __('superadmin.common.campaign') }}</th>
                        <th>{{ __('superadmin.common.room') }}</th>
                        <th class="whitespace-nowrap num">{{ __('superadmin.campaigns.orders') }}</th>
                        <th class="col-status">{{ __('superadmin.common.status') }}</th>
                        <th class="text-right col-actions">{{ __('superadmin.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($campaigns as $campaign)
                        @php
                            $status = $campaign->status instanceof \App\Enums\CampaignStatus ? $campaign->status : \App\Enums\CampaignStatus::tryFrom((string) $campaign->status);
                            // Mirrors CloseCampaignAction (active/closing only) and CampaignController::forceCancel.
                            $canForceClose = in_array($status, [\App\Enums\CampaignStatus::Active, \App\Enums\CampaignStatus::Closing], true);
                            $canForceCancel = in_array($status, \App\Actions\Superadmin\ForceArchiveCampaignAction::ARCHIVABLE_STATUSES, true);
                        @endphp
                        <tr>
                            <td class="col-primary">
                                <strong class="block">{{ $campaign->name }}</strong>
                                <small class="block text-outline">{{ $campaign->restaurant }}</small>
                            </td>
                            <td>
                                <strong class="block">{{ $campaign->room?->name ?? '—' }}</strong>
                                @if($campaign->room)<small class="block text-outline">{{ $campaign->room->slug }}</small>@endif
                            </td>
                            <td class="whitespace-nowrap num">{{ $campaign->orders_count }}</td>
                            <td class="col-status">
                                @if($status)
                                    <span class="status-pill status-{{ $status->value }}"><span class="status-dot"></span>{{ $status->label() }}</span>
                                @else
                                    {{ (string) $campaign->status }}
                                @endif
                            </td>
                            <td class="col-actions">
                                @if($canForceClose || $canForceCancel)
                                    <x-superadmin.row-actions>
                                        <x-slot:menu>
                                            @if($canForceClose)
                                                <button class="sa-menu-item is-warning" type="button" data-action="force-close" data-campaign-id="{{ $campaign->id }}" data-campaign-name="{{ $campaign->name }}" role="menuitem"><span class="material-symbols-outlined">stop_circle</span>{{ __('superadmin.campaigns.force_close') }}</button>
                                            @endif
                                            @if($canForceCancel)
                                                <button class="sa-menu-item is-danger" type="button" data-action="force-cancel" data-campaign-id="{{ $campaign->id }}" data-campaign-name="{{ $campaign->name }}" role="menuitem"><span class="material-symbols-outlined">cancel</span>{{ __('superadmin.campaigns.force_cancel') }}</button>
                                            @endif
                                        </x-slot:menu>
                                    </x-superadmin.row-actions>
                                @else
                                    <div class="text-right text-outline">—</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        @php($isFiltered = $filters['search'] !== '' || $filters['room_id'] !== null || $filters['status'] !== '')
                        <tr>
                            <td colspan="5">
                                @if($isFiltered)
                                    <x-superadmin.empty-state icon="manage_search" :title="__('superadmin.campaigns.no_results_title')" :description="__('superadmin.campaigns.no_results_description')" />
                                @else
                                    <x-superadmin.empty-state icon="campaign" :title="__('superadmin.campaigns.no_campaigns_title')" :description="__('superadmin.campaigns.no_campaigns_description')" />
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $campaigns->links('components.superadmin.pagination') }}
    </section>
@endsection
@push('scripts')
    <x-superadmin.page-script name="campaigns" :i18n="[
        'confirm_force_close' => __('superadmin.campaigns.confirm_force_close'), 'force_close_description' => __('superadmin.campaigns.force_close_description'),
        'force_close' => __('superadmin.campaigns.force_close'), 'force_closed' => __('superadmin.campaigns.force_closed'),
        'confirm_force_cancel' => __('superadmin.campaigns.confirm_force_cancel'), 'force_cancel_description' => __('superadmin.campaigns.force_cancel_description'),
        'force_cancel' => __('superadmin.campaigns.force_cancel'), 'force_cancelled' => __('superadmin.campaigns.force_cancelled'),
    ]" />
@endpush
