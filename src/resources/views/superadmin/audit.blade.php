@extends('superadmin.layout', ['title' => __('superadmin.audit.title'), 'active' => 'audit'])
@section('content')
    <x-superadmin.page-header :eyebrow="__('superadmin.common.platform_data')" :title="__('superadmin.audit.title')" :description="__('superadmin.audit.description')" />
    <section class="sa-card sa-section">
        <div class="sa-section-header">
            <div>
                <h2>{{ __('superadmin.audit.events') }}</h2>
                <p>{{ __('superadmin.common.events_count', ['count' => $audits->total()]) }}</p>
            </div>
            <form method="GET" class="superadmin-actions">
                <select name="event" class="sa-input" aria-label="{{ __('superadmin.audit.event') }}">
                    <option value="">{{ __('superadmin.audit.all_events') }}</option>
                    @foreach ($eventOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['event'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <select name="target_type" class="sa-input" aria-label="{{ __('superadmin.audit.target') }}">
                    <option value="">{{ __('platform.audit.all_targets') }}</option>
                    @foreach ($targetOptions as $value => $label)
                        <option value="{{ $value }}" @selected($filters['target_type'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <x-admin.date-range-filter id="audit-date-range" :dateFrom="$filters['date_from']" :dateTo="$filters['date_to']" />
                <button class="sa-button secondary" type="submit"><span class="material-symbols-outlined text-[16px]">filter_list</span>{{ __('superadmin.common.filter') }}</button>
            </form>
        </div>
        <div class="sa-table-wrap">
            <table class="sa-table">
                <thead>
                    <tr>
                        <th class="whitespace-nowrap col-date">{{ __('superadmin.common.time') }}</th>
                        <th>{{ __('superadmin.audit.actor') }}</th>
                        <th>{{ __('superadmin.audit.event') }}</th>
                        <th>{{ __('superadmin.audit.target') }}</th>
                        <th>{{ __('superadmin.common.room') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($audits as $audit)
                        @php
                            $actorName = $audit->actorSuperadmin?->name;
                        @endphp
                        <tr>
                            <td class="whitespace-nowrap col-date">
                                <span class="block">{{ $audit->created_at?->format('d/m/Y') }}</span>
                                <small class="block text-outline">{{ $audit->created_at?->format('H:i:s') }}</small>
                            </td>
                            <td>
                                <div class="flex items-center gap-3">
                                    <span class="superadmin-avatar shrink-0">{{ $actorName ? mb_strtoupper(mb_substr($actorName, 0, 1)) : '#' }}</span>
                                    <span class="min-w-0">
                                        <strong class="block truncate">{{ $actorName ?? '#'.$audit->actor_id }}</strong>
                                        <small class="flex items-center gap-1 text-outline">
                                            <span class="material-symbols-outlined text-[14px]" aria-hidden="true">shield_person</span>{{ __('superadmin.common.actor_superadmin') }}
                                        </small>
                                    </span>
                                </div>
                            </td>
                            <td>
                                <strong class="block">{{ $audit->eventLabel() }}</strong>
                                <small class="block font-mono text-outline">{{ $audit->event }}</small>
                            </td>
                            <td class="whitespace-nowrap">{{ $audit->targetLabel() }} <span class="text-outline">#{{ $audit->target_id ?: '—' }}</span></td>
                            <td>{{ $audit->room?->name ?? __('superadmin.common.global') }}</td>
                        </tr>
                    @empty
                        @php($isFiltered = $filters['event'] !== '' || $filters['target_type'] !== '' || $filters['date_from'] !== '' || $filters['date_to'] !== '')
                        <tr>
                            <td colspan="5">
                                @if ($isFiltered)
                                    <x-superadmin.empty-state icon="manage_search" :title="__('superadmin.audit.no_results_title')" :description="__('superadmin.audit.no_results_description')" />
                                @else
                                    <x-superadmin.empty-state icon="history_toggle_off" :title="__('superadmin.audit.no_events_title')" :description="__('superadmin.audit.no_events_description')" />
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $audits->links('components.superadmin.pagination') }}
    </section>
@endsection
