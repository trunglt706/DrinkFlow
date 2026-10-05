@extends('superadmin.layout', ['title' => __('superadmin.rooms.title'), 'active' => 'rooms'])
@section('content')
    <x-superadmin.page-header :eyebrow="__('superadmin.common.core_system')" :title="__('superadmin.rooms.title')">
            <button class="sa-button" type="button" data-modal-open="create-room-modal">
                <span class="material-symbols-outlined text-[16px]">add_home</span>{{ __('superadmin.rooms.create') }}
            </button>
    </x-superadmin.page-header>
    <div id="notice" class="sa-notice"></div>
    <section class="sa-card sa-section">
        <div class="sa-section-header">
            <div>
                <h2>{{ __('superadmin.rooms.all_rooms') }}</h2>
                <p>{{ __('superadmin.common.rooms_count', ['count' => $rooms->total()]) }}</p>
            </div>
            <form method="GET" class="superadmin-actions">
                <x-superadmin.search-input :value="$filters['search'] ?? ''" placeholder="{{ __('superadmin.rooms.search') }}" />
                <select name="status" class="sa-input">
                    <option value="">{{ __('superadmin.common.all_statuses') }}</option>
                    @foreach (['active', 'inactive', 'archived'] as $value)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ __('superadmin.common.'.$value) }}</option>
                    @endforeach
                </select>
                <select name="sort" class="sa-input">
                    <option value="" @selected(($filters['sort'] ?? '') === '')>{{ __('superadmin.rooms.sort_newest') }}</option>
                    <option value="name" @selected(($filters['sort'] ?? '') === 'name')>{{ __('superadmin.rooms.sort_name') }}</option>
                    <option value="members" @selected(($filters['sort'] ?? '') === 'members')>{{ __('superadmin.rooms.sort_members') }}</option>
                    <option value="campaigns" @selected(($filters['sort'] ?? '') === 'campaigns')>{{ __('superadmin.rooms.sort_campaigns') }}</option>
                </select>
                <button class="sa-button secondary" type="submit"><span class="material-symbols-outlined text-[16px]">filter_list</span>{{ __('superadmin.common.filter') }}</button>
            </form>
        </div>
        <div class="sa-table-wrap">
            <table class="sa-table">
                <thead>
                    <tr>
                        <th class="col-primary">{{ __('superadmin.common.room') }}</th>
                        <th class="col-status">{{ __('superadmin.common.status') }}</th>
                        <th class="whitespace-nowrap num">{{ __('superadmin.rooms.members') }}</th>
                        <th class="whitespace-nowrap num">{{ __('superadmin.common.campaigns') }}</th>
                        <th class="text-right col-actions">{{ __('superadmin.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rooms as $room)
                        <tr>
                            <td class="col-primary">
                                <div class="flex items-center gap-3">
                                    <span class="superadmin-avatar shrink-0">{{ mb_strtoupper(mb_substr($room->name, 0, 1)) }}</span>
                                    <span class="min-w-0">
                                        <strong class="block truncate">{{ $room->name }}</strong>
                                        <small class="block truncate text-outline">{{ $room->slug }}</small>
                                    </span>
                                </div>
                            </td>
                            <td class="col-status"><x-superadmin.status-pill :status="$room->status" /></td>
                            <td class="whitespace-nowrap num">{{ $room->room_users_count }}</td>
                            <td class="whitespace-nowrap num">{{ $room->campaigns_count }}</td>
                            <td class="col-actions">
                                <x-superadmin.row-actions>
                                    <a class="sa-button secondary" href="{{ route('superadmin.rooms.detail.page', $room) }}"><span class="material-symbols-outlined text-[16px]">visibility</span>{{ __('superadmin.common.details') }}</a>
                                    <x-slot:menu>
                                        <button class="sa-menu-item is-danger" type="button" data-action="delete-room" data-room-id="{{ $room->id }}" data-room-name="{{ $room->name }}" role="menuitem"><span class="material-symbols-outlined">delete</span>{{ __('superadmin.common.delete') }}</button>
                                    </x-slot:menu>
                                </x-superadmin.row-actions>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <x-superadmin.empty-state icon="search_off" :title="__('superadmin.rooms.no_results_title')" :description="__('superadmin.rooms.no_results_description')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $rooms->links('components.superadmin.pagination') }}
    </section>

    <x-superadmin.modal id="create-room-modal" icon="add_home" tone="create" :title="__('superadmin.rooms.create')" :description="__('superadmin.rooms.create_description')">
        <form id="create-room-form" class="space-y-4">
            @csrf
            <x-superadmin.modal-guide :title="__('superadmin.modals.room.create_guide_title')">
                <li>{{ __('superadmin.modals.room.guide_slug') }}</li>
                <li>{{ __('superadmin.modals.room.guide_admins') }}</li>
            </x-superadmin.modal-guide>
            <div>
                <label for="create-room-name" class="sa-label">{{ __('superadmin.rooms.field_name') }} <span class="text-error">*</span></label>
                <input type="text" id="create-room-name" name="name" required class="sa-field">
                <p class="sa-hint">{{ __('superadmin.modals.room.name_hint') }}</p>
            </div>
            <div>
                <label for="create-room-slug" class="sa-label">{{ __('superadmin.rooms.field_slug') }} <span class="text-error">*</span></label>
                <input type="text" id="create-room-slug" name="slug" required pattern="[a-z0-9-]+" class="sa-field font-mono">
                <p class="sa-hint">{{ __('superadmin.modals.room.slug_hint') }}</p>
            </div>
            <div>
                <label for="create-room-status" class="sa-label">{{ __('superadmin.rooms.field_status') }}</label>
                <select id="create-room-status" name="status" class="sa-field">
                    @foreach (['active', 'inactive', 'archived'] as $value)
                        <option value="{{ $value }}" @selected($value === 'active')>{{ __('superadmin.common.'.$value) }}</option>
                    @endforeach
                </select>
                <p class="sa-hint">{{ __('superadmin.modals.room.status_hint') }}</p>
            </div>
            <div>
                <label class="sa-label">{{ __('superadmin.rooms.field_admins') }}</label>
                <div id="create-room-admin-options" class="sa-check-list space-y-1">
                    <p class="sa-empty">{{ __('global.common.loading') }}</p>
                </div>
                <p class="sa-hint">{{ __('superadmin.modals.room.admins_hint') }}</p>
            </div>
            <div class="sa-modal-footer">
                <button type="button" class="sa-button secondary" data-modal-close>{{ __('superadmin.common.cancel') }}</button>
                <button type="submit" class="sa-button">
                    <span class="material-symbols-outlined text-[16px]">add_home</span>{{ __('superadmin.rooms.create') }}
                </button>
            </div>
        </form>
    </x-superadmin.modal>
@endsection
@push('scripts')
    <x-superadmin.page-script name="rooms"
        :i18n="['no_admin_options' => __('superadmin.rooms.no_admin_options'), 'confirm_delete' => __('superadmin.rooms.confirm_delete'), 'deleted' => __('superadmin.rooms.deleted'), 'delete_description' => __('superadmin.modals.confirm.room_delete')]"
        :urls="['admins' => route('superadmin.admins.index'), 'store' => route('superadmin.rooms.store')]" />
@endpush
