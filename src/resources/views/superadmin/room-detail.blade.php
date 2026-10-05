@extends('superadmin.layout', ['active' => 'rooms', 'title' => __('superadmin.rooms.profile')])

@section('content')
    <x-superadmin.page-header :back="route('superadmin.rooms.page')" :back-label="__('superadmin.layout.room_management')"
        :title="__('superadmin.rooms.profile')" title-id="room-name" :description="__('superadmin.rooms.loading_profile')" description-id="room-meta">
        <button class="sa-button secondary" data-modal-open="edit-room-modal" type="button"><span class="material-symbols-outlined text-[16px]">edit</span>{{ __('superadmin.rooms.edit_room') }}</button>
        <button class="sa-button secondary" id="room-status-action" type="button"><span class="material-symbols-outlined text-[16px]">toggle_on</span>{{ __('superadmin.rooms.update_status') }}</button>
        <button class="sa-button danger" data-action="delete-room-profile" type="button"><span class="material-symbols-outlined text-[16px]">delete</span>{{ __('superadmin.rooms.delete_room') }}</button>
    </x-superadmin.page-header>
    <div id="notice" class="sa-notice" role="status"></div>
    <section class="sa-grid kpis kpis-4">
        <x-superadmin.kpi :label="__('superadmin.rooms.members')" icon="group" value-id="room-members" :hint="__('superadmin.rooms.total_members')" />
        <x-superadmin.kpi :label="__('superadmin.rooms.active_members')" icon="how_to_reg" tone="success" value-id="room-active-members" :hint="__('superadmin.common.active')" />
        <x-superadmin.kpi :label="__('superadmin.rooms.room_campaigns')" icon="campaign" value-id="room-campaigns" />
        <x-superadmin.kpi :label="__('superadmin.dashboard.admins')" icon="admin_panel_settings" tone="accent" value-id="room-admins" :hint="__('superadmin.rooms.authorized')" />
    </section>
    <section class="sa-card sa-section">
        <div class="sa-section-header">
            <div>
                <h2>{{ __('superadmin.rooms.assigned_admins') }}</h2>
                <p>{{ __('superadmin.rooms.assigned_admins_description') }}</p>
            </div>
        </div>
        <div class="sa-table-wrap">
            <table class="sa-table">
                <thead>
                    <tr>
                        <th class="col-primary">{{ __('superadmin.common.admin') }}</th>
                        <th class="whitespace-nowrap col-status">{{ __('superadmin.common.status') }}</th>
                    </tr>
                </thead>
                <tbody id="room-admin-list">
                    <tr>
                        <td colspan="3">
                            <x-superadmin.empty-state loading :title="__('superadmin.common.loading_title')" :description="__('superadmin.common.loading_description')" />
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>

    {{-- Empty states cloned by the page script, so every string stays translated through __(). --}}
    <template id="tpl-no-admins"><x-superadmin.empty-state icon="person_off" :title="__('superadmin.rooms.no_admins_title')" :description="__('superadmin.rooms.no_admins_description')" /></template>
    <template id="tpl-load-failed"><x-superadmin.empty-state icon="error" :title="__('superadmin.common.load_failed_title')" description="" /></template>

    <x-superadmin.modal id="edit-room-modal" icon="edit" tone="edit" :title="__('superadmin.rooms.edit_room')" :description="__('superadmin.rooms.edit_description')">
        <form id="edit-room-form" class="space-y-4">
            @csrf
            <x-superadmin.modal-guide :title="__('superadmin.modals.room.edit_guide_title')" tone="warning">
                <li>{{ __('superadmin.modals.room.guide_slug_change') }}</li>
                <li>{{ __('superadmin.modals.room.guide_status_change') }}</li>
                <li>{{ __('superadmin.modals.room.guide_admins_change') }}</li>
            </x-superadmin.modal-guide>
            <div>
                <label for="edit-room-name" class="sa-label">{{ __('superadmin.rooms.field_name') }} <span class="text-error">*</span></label>
                <input type="text" id="edit-room-name" name="name" required class="sa-field">
                <p class="sa-hint">{{ __('superadmin.modals.room.name_hint') }}</p>
            </div>
            <div>
                <label for="edit-room-slug" class="sa-label">{{ __('superadmin.rooms.field_slug') }} <span class="text-error">*</span></label>
                <input type="text" id="edit-room-slug" name="slug" required pattern="[a-z0-9-]+" class="sa-field font-mono">
                <p class="sa-hint">{{ __('superadmin.modals.room.slug_hint') }}</p>
            </div>
            <div>
                <label for="edit-room-status" class="sa-label">{{ __('superadmin.rooms.field_status') }}</label>
                <select id="edit-room-status" name="status" class="sa-field">
                    @foreach (['active', 'inactive', 'archived'] as $value)
                        <option value="{{ $value }}">{{ __('superadmin.common.'.$value) }}</option>
                    @endforeach
                </select>
                <p class="sa-hint">{{ __('superadmin.modals.room.status_hint') }}</p>
            </div>
            <div>
                <label class="sa-label">{{ __('superadmin.rooms.field_admins') }}</label>
                <div id="edit-room-admin-options" class="sa-check-list space-y-1">
                    <p class="sa-empty">{{ __('global.common.loading') }}</p>
                </div>
                <p class="sa-hint">{{ __('superadmin.modals.room.admins_hint') }}</p>
            </div>
            <div class="sa-modal-footer">
                <button type="button" class="sa-button secondary" data-modal-close>{{ __('superadmin.common.cancel') }}</button>
                <button type="submit" class="sa-button">
                    <span class="material-symbols-outlined text-[16px]">save</span>{{ __('superadmin.common.save') }}
                </button>
            </div>
        </form>
    </x-superadmin.modal>
@endsection

@push('scripts')
    <x-superadmin.page-script name="room-detail" :i18n="[
        'no_admin_options' => __('superadmin.rooms.no_admin_options'), 'enable_room' => __('superadmin.rooms.enable_room'),
        'disable_room' => __('superadmin.rooms.disable_room'), 'updated' => __('superadmin.rooms.updated'),
        'status_updated' => __('superadmin.rooms.status_updated'), 'confirm_delete' => __('superadmin.rooms.confirm_delete'),
        'deleted' => __('superadmin.rooms.deleted'), 'delete_description' => __('superadmin.modals.confirm.room_delete'),
    ]" :urls="['room_id' => (string) request()->route('room'), 'admins' => route('superadmin.admins.index'), 'list' => route('superadmin.rooms.page')]" />
@endpush
