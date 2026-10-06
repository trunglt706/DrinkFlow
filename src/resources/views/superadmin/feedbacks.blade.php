@extends('superadmin.layout', ['title' => __('superadmin.feedbacks.title'), 'active' => 'feedbacks'])
@section('content')
    <x-superadmin.page-header :eyebrow="__('superadmin.common.platform_data')" :title="__('superadmin.feedbacks.title')" :description="__('superadmin.feedbacks.description', ['rating' => \App\Models\Feedback::MIN_VISIBLE_RATING])" />
    <div id="notice" class="sa-notice"></div>
    <section class="sa-card sa-section">
        <div class="sa-section-header">
            <div>
                <h2>{{ __('superadmin.feedbacks.queue') }}</h2>
                <p>{{ __('superadmin.feedbacks.pending_count', ['count' => $pendingCount]) }} · {{ __('superadmin.common.records_count', ['count' => $feedbacks->total()]) }}</p>
            </div>
            <form method="GET" class="superadmin-actions">
                <x-superadmin.search-input :value="$filters['search']" placeholder="{{ __('superadmin.feedbacks.search') }}" />
                <select name="status" class="sa-input" aria-label="{{ __('superadmin.common.status') }}">
                    <option value="all" @selected($filters['status'] === 'all')>{{ __('superadmin.common.all_statuses') }}</option>
                    <option value="inactive" @selected($filters['status'] === 'inactive')>{{ __('superadmin.feedbacks.status_inactive') }}</option>
                    <option value="active" @selected($filters['status'] === 'active')>{{ __('superadmin.feedbacks.status_active') }}</option>
                </select>
                <select name="rating" class="sa-input" aria-label="{{ __('superadmin.feedbacks.rating') }}">
                    <option value="">{{ __('superadmin.feedbacks.all_ratings') }}</option>
                    @for ($stars = 5; $stars >= 1; $stars--)
                        <option value="{{ $stars }}" @selected($filters['rating'] === $stars)>{{ $stars }} ★</option>
                    @endfor
                </select>
                {{-- Feedback from members of the rooms owned by one Agent. --}}
                <x-superadmin.agent-filter permission="agent.view" :selected="$filters['agent']" />
                <button class="sa-button secondary" type="submit"><span class="material-symbols-outlined text-[16px]">filter_list</span>{{ __('superadmin.common.filter') }}</button>
            </form>
        </div>
        <div class="sa-table-wrap">
            <table class="sa-table">
                <thead>
                    <tr>
                        <th class="col-primary">{{ __('superadmin.common.user') }}</th>
                        <th class="whitespace-nowrap col-status">{{ __('superadmin.feedbacks.rating') }}</th>
                        <th class="col-wrap">{{ __('superadmin.feedbacks.content') }}</th>
                        <th class="whitespace-nowrap col-date">{{ __('superadmin.common.time') }}</th>
                        <th class="col-status">{{ __('superadmin.common.status') }}</th>
                        <th class="text-right col-actions">{{ __('superadmin.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($feedbacks as $feedback)
                        @php
                            $isActive = $feedback->status === \App\Enums\FeedbackStatus::Active;
                            $belowMinimum = $feedback->rating < \App\Models\Feedback::MIN_VISIBLE_RATING;
                            $displayName = $feedback->user_display_name ?: __('global.feedback.anonymous_user');
                            $userMeta = collect([$feedback->globalUser?->email, $feedback->department_name])->filter()->join(' · ');
                            $editPayload = ['rating' => $feedback->rating, 'subsystem' => $feedback->subsystem ?: 'all', 'content' => $feedback->content, 'user_display_name' => $feedback->user_display_name];
                        @endphp
                        <tr>
                            <td class="col-primary">
                                <div class="flex items-center gap-3">
                                    <span class="superadmin-avatar shrink-0">{{ mb_strtoupper(mb_substr($displayName, 0, 1)) }}</span>
                                    <span class="min-w-0">
                                        <strong class="block truncate">{{ $displayName }}</strong>
                                        @if ($userMeta !== '')<small class="block truncate text-outline">{{ $userMeta }}</small>@endif
                                    </span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap col-status">
                                <span class="block text-[14px] leading-none tracking-wide" role="img" aria-label="{{ __('superadmin.feedbacks.rating_value', ['rating' => $feedback->rating]) }}">
                                    @for ($star = 1; $star <= 5; $star++)<span class="{{ $star <= $feedback->rating ? 'text-amber-500' : 'text-slate-300' }}" aria-hidden="true">★</span>@endfor
                                </span>
                                @if ($feedback->subsystem_label)<small class="block mt-1 text-outline">{{ $feedback->subsystem_label }}</small>@endif
                            </td>
                            <td class="min-w-[16rem] max-w-md whitespace-pre-line break-words leading-relaxed col-wrap">{{ $feedback->content }}</td>
                            <td class="whitespace-nowrap col-date">
                                <span class="block">{{ $feedback->created_at?->format('d/m/Y') }}</span>
                                <small class="block text-outline">{{ $feedback->created_at?->format('H:i') }}</small>
                            </td>
                            <td class="col-status">
                                <span class="status-pill whitespace-nowrap {{ $isActive ? 'status-active' : 'status-pending' }}"><span class="status-dot"></span>{{ $isActive ? __('superadmin.feedbacks.status_active') : __('superadmin.feedbacks.status_inactive') }}</span>
                                @if ($isActive && $belowMinimum)
                                    <small class="mt-1 flex items-center gap-1 text-outline"><span class="material-symbols-outlined text-[14px]" aria-hidden="true">visibility_off</span>{{ __('superadmin.feedbacks.hidden_low_rating', ['rating' => \App\Models\Feedback::MIN_VISIBLE_RATING]) }}</small>
                                @endif
                            </td>
                            <td class="col-actions">
                                <div class="sa-row-actions whitespace-nowrap">
                                    <button class="sa-icon-btn" type="button" data-action="feedback-edit" data-feedback-id="{{ $feedback->id }}"
                                        data-feedback="{{ json_encode($editPayload, JSON_UNESCAPED_UNICODE) }}"
                                        title="{{ __('superadmin.feedbacks.edit') }}" aria-label="{{ __('superadmin.feedbacks.edit') }}"><span class="material-symbols-outlined text-[18px]">edit</span></button>
                                    <button class="sa-icon-btn text-error" type="button" data-action="feedback-delete" data-feedback-id="{{ $feedback->id }}"
                                        title="{{ __('superadmin.feedbacks.delete') }}" aria-label="{{ __('superadmin.feedbacks.delete') }}"><span class="material-symbols-outlined text-[18px]">delete</span></button>
                                    @if ($isActive)
                                        <button class="sa-button warning" type="button" data-action="feedback-status" data-feedback-id="{{ $feedback->id }}" data-status="inactive"><span class="material-symbols-outlined text-[16px]">unpublished</span>{{ __('superadmin.feedbacks.deactivate') }}</button>
                                    @else
                                        <button class="sa-button" type="button" data-action="feedback-status" data-feedback-id="{{ $feedback->id }}" data-status="active"><span class="material-symbols-outlined text-[16px]">check_circle</span>{{ __('superadmin.feedbacks.approve') }}</button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        @php($isDefaultQueue = $filters['status'] === 'inactive' && $filters['search'] === '' && $filters['rating'] === null)
                        <tr>
                            <td colspan="6">
                                @if ($isDefaultQueue)
                                    <x-superadmin.empty-state icon="task_alt" :title="__('superadmin.feedbacks.no_pending_title')" :description="__('superadmin.feedbacks.no_pending_description')" />
                                @else
                                    <x-superadmin.empty-state icon="rate_review" :title="__('superadmin.feedbacks.empty_title')" :description="__('superadmin.feedbacks.empty_description')" />
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $feedbacks->links('components.superadmin.pagination') }}
    </section>

    <x-superadmin.modal id="edit-feedback-modal" icon="edit" tone="edit" :title="__('superadmin.feedbacks.edit_title')" :description="__('superadmin.feedbacks.edit_description')">
        <form id="edit-feedback-form" class="space-y-4" novalidate>
            @csrf
            <p id="edit-feedback-error" class="sa-notice error hidden" role="alert"></p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label for="edit-feedback-rating" class="sa-label">{{ __('superadmin.feedbacks.rating') }} <span class="text-error">*</span></label>
                    <select id="edit-feedback-rating" name="rating" required class="sa-field">
                        @for ($stars = 5; $stars >= 1; $stars--)
                            <option value="{{ $stars }}">{{ $stars }} ★</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label for="edit-feedback-subsystem" class="sa-label">{{ __('global.feedback.subsystem_label') }} <span class="text-error">*</span></label>
                    <select id="edit-feedback-subsystem" name="subsystem" required class="sa-field">
                        @foreach (\App\Models\Feedback::SUBSYSTEMS as $subsystem)
                            <option value="{{ $subsystem }}">{{ __('global.feedback.subsystem_'.$subsystem) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div>
                <label for="edit-feedback-name" class="sa-label">{{ __('superadmin.feedbacks.display_name') }}</label>
                <input type="text" id="edit-feedback-name" name="user_display_name" maxlength="255" class="sa-field">
            </div>
            <div>
                <label for="edit-feedback-content" class="sa-label">{{ __('superadmin.feedbacks.content') }} <span class="text-error">*</span></label>
                <textarea id="edit-feedback-content" name="content" rows="5" required minlength="3" maxlength="1000" class="sa-field"></textarea>
            </div>
            <div class="sa-modal-footer">
                <button type="button" class="sa-button secondary" data-modal-close>{{ __('superadmin.common.cancel') }}</button>
                <button type="submit" class="sa-button"><span class="material-symbols-outlined text-[16px]">save</span>{{ __('superadmin.common.save') }}</button>
            </div>
        </form>
    </x-superadmin.modal>
@endsection
@push('scripts')
    <x-superadmin.page-script name="feedbacks" :i18n="[
        'status_updated' => __('superadmin.feedbacks.status_updated'), 'updated' => __('superadmin.feedbacks.updated'),
        'deleted' => __('superadmin.feedbacks.deleted'), 'confirm_delete' => __('superadmin.feedbacks.confirm_delete'),
        'delete_description' => __('superadmin.feedbacks.delete_description'), 'delete' => __('superadmin.feedbacks.delete'),
    ]" />
@endpush
