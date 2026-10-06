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
                <select name="actor_id" class="sa-input" aria-label="{{ __('superadmin.audit.actor') }}" data-audit-actor-filter>
                    <option value="">{{ __('superadmin.common.all_actors') }}</option>
                    @foreach ($actorOptions as $actor)
                        <option value="{{ $actor->id }}" @selected($filters['actor_id'] === $actor->id)>{{ $actor->name }} ({{ app(\App\Services\Security\SensitiveDataService::class)->mask($actor->email, 'email') }})</option>
                    @endforeach
                </select>
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
                        <th class="text-right col-actions">{{ __('superadmin.audit.detail') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($audits as $audit)
                        @php
                            $actorName = $audit->actorSuperadmin?->name;
                            // Everything the detail modal shows (resources/js/superadmin/pages/audit.js).
                            $auditDetail = [
                                'id' => $audit->id,
                                'time' => $audit->created_at?->toAppDateTime(),
                                'event' => $audit->event,
                                'event_label' => $audit->eventLabel(),
                                'target' => $audit->targetLabel().($audit->target_id ? ' #'.$audit->target_id : ''),
                                'room' => $audit->room?->name ?? __('superadmin.common.global'),
                                'actor' => $actorName ?? '#'.$audit->actor_id,
                                'ip' => $audit->ip_address,
                                'device' => $audit->device_uuid,
                                'user_agent' => $audit->user_agent,
                                'before' => $audit->before_data,
                                'after' => $audit->after_data,
                                'metadata' => $audit->metadata,
                            ];
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
                            <td class="col-actions">
                                <div class="sa-row-actions">
                                    <button type="button" class="sa-icon-btn" data-audit-detail="{{ json_encode($auditDetail, JSON_UNESCAPED_UNICODE) }}"
                                        title="{{ __('superadmin.audit.view_detail') }}" aria-label="{{ __('superadmin.audit.view_detail') }} #{{ $audit->id }}">
                                        <span class="material-symbols-outlined" aria-hidden="true">visibility</span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        @php($isFiltered = $filters['actor_id'] !== null || $filters['event'] !== '' || $filters['target_type'] !== '' || $filters['date_from'] !== '' || $filters['date_to'] !== '')
                        <tr>
                            <td colspan="6">
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

    {{-- Audit entry detail (filled by resources/js/superadmin/pages/audit.js from the row's data-audit-detail). --}}
    <x-superadmin.modal id="audit-detail-modal" icon="manage_search" tone="info" :title="__('superadmin.audit.detail_title')" :description="__('superadmin.audit.detail_description')">
        <div class="space-y-4 text-[13px]" data-audit-detail-body>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-2">
                @foreach (['id' => 'detail_id', 'time' => 'detail_time', 'event_label' => 'event', 'target' => 'target', 'room' => 'detail_room', 'actor' => 'actor', 'ip' => 'detail_ip', 'device' => 'detail_device'] as $field => $labelKey)
                    <div class="min-w-0">
                        <dt class="text-[11px] font-semibold uppercase tracking-wider text-outline">{{ __('superadmin.audit.'.$labelKey) }}</dt>
                        <dd class="mt-0.5 break-words font-medium text-on-surface" data-audit-field="{{ $field }}">—</dd>
                    </div>
                @endforeach
                <div class="min-w-0 sm:col-span-2">
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-outline">{{ __('superadmin.audit.detail_user_agent') }}</dt>
                    <dd class="mt-0.5 break-all font-mono text-[12px] text-on-surface-variant" data-audit-field="user_agent">—</dd>
                </div>
            </dl>
            @foreach (['before' => 'detail_before', 'after' => 'detail_after', 'metadata' => 'detail_metadata'] as $block => $labelKey)
                <section>
                    <h4 class="mb-1 text-[11px] font-semibold uppercase tracking-wider text-outline">{{ __('superadmin.audit.'.$labelKey) }}</h4>
                    <pre class="max-h-64 overflow-auto rounded-lg border border-outline-variant bg-surface-container-low p-3 font-mono text-[12px] leading-relaxed whitespace-pre-wrap break-words" data-audit-block="{{ $block }}" hidden></pre>
                    <p class="text-[12px] text-outline" data-audit-block-empty="{{ $block }}">{{ __('superadmin.audit.detail_empty') }}</p>
                </section>
            @endforeach
            <div class="sa-modal-footer">
                <button type="button" class="sa-button secondary" data-modal-close>{{ __('superadmin.common.close') }}</button>
            </div>
        </div>
    </x-superadmin.modal>
@endsection
@push('scripts')
    <x-superadmin.page-script name="audit" />
@endpush
