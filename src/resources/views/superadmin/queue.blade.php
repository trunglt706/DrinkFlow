@extends('superadmin.layout', ['title' => __('superadmin.queue.title'), 'active' => 'socket'])
@section('content')
    <x-superadmin.page-header :eyebrow="__('superadmin.common.infra_security')" :title="__('superadmin.queue.title')" :description="__('superadmin.queue.description')" />
    <div id="notice" class="sa-notice"></div>
    <section class="sa-card sa-section">
        <div class="sa-section-header">
            <div>
                <h2>{{ __('superadmin.queue.failed_jobs') }}</h2>
                <p>{{ __('superadmin.common.failed_jobs_count', ['count' => $jobs->total()]) }}</p>
            </div>
            <form method="GET" class="superadmin-actions">
                <x-superadmin.search-input :value="$filters['search'] ?? ''" placeholder="{{ __('superadmin.queue.search') }}" />
                <button class="sa-button secondary" type="submit"><span class="material-symbols-outlined text-[16px]">filter_list</span>{{ __('superadmin.common.filter') }}</button>
            </form>
        </div>
        <div class="sa-table-wrap">
            <table class="sa-table">
                <thead>
                    <tr>
                        <th class="col-date">{{ __('superadmin.queue.failed_at') }}</th>
                        <th class="col-status">{{ __('superadmin.queue.queue') }}</th>
                        <th class="col-mono">UUID</th>
                        <th class="col-wrap">{{ __('superadmin.queue.exception') }}</th>
                        <th class="text-right col-actions">{{ __('superadmin.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($jobs as $job)
                        <tr>
                            <td class="whitespace-nowrap col-date">{{ $job->failed_at }}</td>
                            <td class="whitespace-nowrap col-status">{{ $job->queue }}</td>
                            <td class="col-mono"><small>{{ $job->uuid }}</small></td>
                            <td class="col-wrap"><small>{{ Str::limit($job->exception, 180) }}</small></td>
                            <td class="col-actions">
                                <x-superadmin.row-actions>
                                    <button class="sa-button secondary" type="button" data-action="retry-job" data-job-id="{{ $job->id }}"><span class="material-symbols-outlined text-[16px]">replay</span>{{ __('superadmin.common.retry') }}</button>
                                    <x-slot:menu>
                                        <button class="sa-menu-item is-danger" type="button" data-action="delete-job" data-job-id="{{ $job->id }}" role="menuitem"><span class="material-symbols-outlined">delete</span>{{ __('superadmin.common.delete') }}</button>
                                    </x-slot:menu>
                                </x-superadmin.row-actions>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <x-superadmin.empty-state icon="task_alt" :title="__('superadmin.queue.no_results_title')" :description="__('superadmin.queue.no_results_description')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $jobs->links('components.superadmin.pagination') }}
    </section>
@endsection
@push('scripts')
    <x-superadmin.page-script name="queue" :i18n="[
        'confirm_retry' => __('superadmin.queue.confirm_retry'), 'retry_description' => __('superadmin.queue.retry_description'),
        'retry' => __('superadmin.common.retry'), 'retried' => __('superadmin.queue.retried'),
        'confirm_delete' => __('superadmin.queue.confirm_delete'), 'delete_description' => __('superadmin.queue.delete_description'),
        'delete' => __('superadmin.common.delete'), 'deleted' => __('superadmin.queue.deleted'),
    ]" />
@endpush
