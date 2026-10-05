@extends('superadmin.layout', ['title' => __('superadmin.users.title'), 'active' => 'users'])
@section('content')
    <x-superadmin.page-header :eyebrow="__('superadmin.common.identity_layer')" :title="__('superadmin.users.title')" :description="__('superadmin.users.description')" />
    <div id="notice" class="sa-notice"></div>
    <section class="sa-card sa-section">
        <div class="sa-section-header">
            <div>
                <h2>{{ __('superadmin.users.global_users') }}</h2>
                <p>{{ trans_choice('superadmin.common.global_users_count', $users->total(), ['count' => $users->total()]) }}</p>
            </div>
            <form method="get" class="sa-filters">
                <x-superadmin.search-input :value="request('q')" placeholder="{{ __('superadmin.users.search') }}" />
                <select name="status" class="sa-input" aria-label="{{ __('superadmin.common.status') }}">
                    <option value="">{{ __('superadmin.common.all_statuses') }}</option>
                    @foreach (\App\Enums\GlobalUserStatus::cases() as $statusOption)
                        <option value="{{ $statusOption->value }}" @selected(($filters['status'] ?? '') === $statusOption->value)>{{ __('superadmin.common.'.$statusOption->value) }}</option>
                    @endforeach
                </select>
                <button class="sa-button secondary" type="submit"><span class="material-symbols-outlined text-[16px]">filter_list</span>{{ __('superadmin.common.filter') }}</button>
            </form>
        </div>
        <div class="sa-table-wrap">
            <table class="sa-table">
                <thead>
                    <tr>
                        <th class="col-primary">{{ __('superadmin.common.user') }}</th>
                        <th class="whitespace-nowrap">{{ __('superadmin.users.oauth') }}</th>
                        <th class="whitespace-nowrap num">{{ __('superadmin.users.memberships') }}</th>
                        <th class="col-status">{{ __('superadmin.common.status') }}</th>
                        <th class="text-right col-actions">{{ __('superadmin.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($users as $user)
                        @php
                            $statusValue = $user->status?->value ?? (string) $user->status;
                            $isBlocked = $statusValue === \App\Enums\GlobalUserStatus::Blocked->value;
                            $isDeleted = $statusValue === \App\Enums\GlobalUserStatus::Deleted->value;
                        @endphp
                        <tr>
                            <td class="col-primary">
                                <div class="flex items-center gap-3">
                                    <span class="superadmin-avatar shrink-0">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                                    <span class="min-w-0">
                                        <strong class="block truncate">{{ $user->name }}</strong>
                                        <small class="block truncate text-outline">{{ $user->email }}</small>
                                    </span>
                                </div>
                            </td>
                            <td class="whitespace-nowrap">{{ $user->oauthIdentities->pluck('provider')->join(', ') ?: '—' }}</td>
                            <td class="whitespace-nowrap num">{{ $user->room_users_count }}</td>
                            <td class="col-status"><x-superadmin.status-pill :status="$statusValue" /></td>
                            <td class="col-actions">
                                <x-superadmin.row-actions>
                                    <a class="sa-button secondary" href="{{ route('superadmin.global-users.detail.page', $user) }}"><span class="material-symbols-outlined text-[16px]">visibility</span>{{ __('superadmin.common.details') }}</a>
                                    @unless ($isDeleted)
                                    <x-slot:menu>
                                        <button class="sa-menu-item {{ $isBlocked ? '' : 'is-warning' }}" type="button" data-action="toggle-user-status" data-user-id="{{ $user->id }}" data-user-name="{{ $user->name }}" data-current-status="{{ $statusValue }}" role="menuitem">
                                            <span class="material-symbols-outlined">{{ $isBlocked ? 'lock_open' : 'lock' }}</span>{{ $isBlocked ? __('superadmin.common.unblock') : __('superadmin.common.block') }}
                                        </button>
                                        <div class="sa-menu-sep"></div>
                                        <button class="sa-menu-item is-danger" type="button" data-action="delete-user" data-user-id="{{ $user->id }}" data-user-name="{{ $user->name }}" role="menuitem"><span class="material-symbols-outlined">delete</span>{{ __('superadmin.common.delete') }}</button>
                                    </x-slot:menu>
                                    @endunless
                                </x-superadmin.row-actions>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <x-superadmin.empty-state icon="person_search" :title="__('superadmin.users.no_results_title')" :description="__('superadmin.users.no_results_description')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $users->withQueryString()->links('components.superadmin.pagination') }}
    </section>
@endsection
@push('scripts')
    <x-superadmin.page-script name="users" :i18n="[
        'confirm_block' => __('superadmin.users.confirm_block'), 'confirm_unblock' => __('superadmin.users.confirm_unblock'),
        'block_description' => __('superadmin.users.block_description'), 'unblock_description' => __('superadmin.users.unblock_description'),
        'block' => __('superadmin.common.block'), 'unblock' => __('superadmin.common.unblock'),
        'status_updated' => __('superadmin.users.status_updated'), 'confirm_delete' => __('superadmin.users.confirm_delete'),
        'delete' => __('superadmin.common.delete'), 'deleted' => __('superadmin.users.deleted'),
        'delete_description' => __('superadmin.modals.confirm.user_delete'),
    ]" />
@endpush
