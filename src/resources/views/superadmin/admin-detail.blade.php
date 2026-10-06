@extends('superadmin.layout', ['active' => 'admins', 'title' => __('superadmin.admins.profile')])

@section('content')
    <x-superadmin.page-header :back="route('superadmin.admins.page')" :back-label="__('superadmin.admins.title')"
        :title="__('superadmin.admins.profile')" title-id="admin-name" :description="__('superadmin.admins.loading_profile')" description-id="admin-meta">
            <button class="sa-button secondary" data-modal-open="reset-password-modal" type="button"><span class="material-symbols-outlined text-[16px]">lock_reset</span>{{ __('superadmin.admins.reset_password') }}</button>
            <button class="sa-button warning" id="admin-status" type="button"><span class="material-symbols-outlined text-[16px]">lock</span>{{ __('superadmin.admins.update_status') }}</button>
            <button class="sa-button danger" id="admin-delete" type="button"><span class="material-symbols-outlined text-[16px]">delete</span>{{ __('superadmin.admins.delete_account') }}</button>
    </x-superadmin.page-header>
    <div id="notice" class="sa-notice" role="status"></div>
    <div class="sa-split">
        <section class="sa-card sa-section">
            <div class="sa-section-header">
                <div>
                    <h2>{{ __('superadmin.admins.account_role') }}</h2>
                    <p>{{ __('superadmin.admins.account_role_description') }}</p>
                </div>
            </div>
            <div id="admin-summary" class="sa-detail-list">
                <x-superadmin.empty-state loading :title="__('superadmin.common.loading_title')" :description="__('superadmin.common.loading_description')" />
            </div>
        </section>
        <section class="sa-card sa-section">
            <div class="sa-section-header">
                <div>
                    <h2>{{ __('superadmin.admins.room_assignment') }}</h2>
                    <p>{{ __('superadmin.admins.room_assignment_description') }}</p>
                </div>
                <button class="sa-button secondary" id="edit-rooms" type="button"><span class="material-symbols-outlined text-[16px]">tune</span>{{ __('superadmin.admins.manage_access') }}</button>
            </div>
            <div id="admin-rooms" class="sa-health-list">
                <x-superadmin.empty-state loading :title="__('superadmin.common.loading_title')" :description="__('superadmin.common.loading_description')" />
            </div>
        </section>
    </div>

    <x-superadmin.modal id="room-access-modal" icon="meeting_room" tone="security" :title="__('superadmin.admins.room_access')" :description="__('superadmin.admins.room_access_description')">
        <form id="room-access-form" class="space-y-4">
            @csrf
            <x-superadmin.modal-guide :title="__('superadmin.modals.room_access.guide_title')">
                <li>{{ __('superadmin.modals.room_access.guide_check') }}</li>
                <li>{{ __('superadmin.modals.room_access.guide_apply') }}</li>
            </x-superadmin.modal-guide>
            <div id="room-options" class="sa-check-list !max-h-80 space-y-1">
                <x-superadmin.empty-state loading :bordered="false" :title="__('superadmin.common.loading_title')" :description="__('superadmin.admins.loading_rooms')" />
            </div>
            <div class="sa-modal-footer">
                <button type="button" class="sa-button secondary" data-modal-close>{{ __('superadmin.common.cancel') }}</button>
                <button type="submit" class="sa-button">
                    <span class="material-symbols-outlined text-[16px]">save</span>{{ __('superadmin.admins.save_assignment') }}
                </button>
            </div>
        </form>
    </x-superadmin.modal>

    <x-superadmin.modal id="reset-password-modal" icon="lock_reset" tone="security" :title="__('superadmin.admins.reset_password')" max-width="max-w-md">
        <form id="reset-password-form" class="space-y-4">
            @csrf
            <p id="reset-password-description" class="sa-confirm-message !text-sm"></p>
            <x-superadmin.modal-guide :title="__('superadmin.modals.reset_password.guide_title')" tone="warning">
                <li>{{ __('superadmin.modals.reset_password.guide_old') }}</li>
                <li>{{ __('superadmin.modals.reset_password.guide_share') }}</li>
            </x-superadmin.modal-guide>
            <div>
                <label for="reset-password" class="sa-label">{{ __('superadmin.admins.field_password') }} <span class="text-error">*</span></label>
                <x-superadmin.password-input id="reset-password" name="password" required minlength="8" />
            </div>
            <div>
                <label for="reset-password-confirmation" class="sa-label">{{ __('superadmin.admins.field_password_confirmation') }} <span class="text-error">*</span></label>
                <x-superadmin.password-input id="reset-password-confirmation" name="password_confirmation" required minlength="8" />
                <p class="sa-hint">{{ __('superadmin.admins.password_hint') }}</p>
            </div>
            <div class="sa-modal-footer">
                <button type="button" class="sa-button secondary" data-modal-close>{{ __('superadmin.common.cancel') }}</button>
                <button type="submit" class="sa-button">
                    <span class="material-symbols-outlined text-[16px]">lock_reset</span>{{ __('superadmin.admins.save_password') }}
                </button>
            </div>
        </form>
    </x-superadmin.modal>

    {{-- Empty states cloned by the page script, so every string stays translated through __(). --}}
    <template id="tpl-rooms-loading"><x-superadmin.empty-state loading :bordered="false" :title="__('superadmin.common.loading_title')" :description="__('superadmin.admins.loading_rooms')" /></template>
    <template id="tpl-no-assigned-rooms"><x-superadmin.empty-state icon="meeting_room" :title="__('superadmin.admins.no_assigned_rooms_title')" :description="__('superadmin.admins.no_assigned_rooms_description')" /></template>
    <template id="tpl-no-rooms"><x-superadmin.empty-state icon="domain_disabled" :bordered="false" :title="__('superadmin.admins.no_rooms_title')" :description="__('superadmin.admins.no_rooms_description')" /></template>
    <template id="tpl-load-failed"><x-superadmin.empty-state icon="error" :title="__('superadmin.common.load_failed_title')" description="" /></template>
@endsection

@push('scripts')
    <x-superadmin.page-script name="admin-detail" :i18n="[
        'name' => __('superadmin.common.name'), 'email' => __('superadmin.common.email'), 'phone' => __('superadmin.admins.field_phone'),
        'not_provided' => __('superadmin.admins.not_provided'), 'status' => __('superadmin.common.status'),
        'created_at' => __('superadmin.admins.created_at'), 'last_login' => __('superadmin.admins.last_login'),
        'never_logged_in' => __('superadmin.admins.never_logged_in'), 'assigned' => __('superadmin.common.assigned'),
        'block_account' => __('superadmin.admins.block_account'), 'unblock_account' => __('superadmin.admins.unblock_account'),
        'reset_password_description' => __('superadmin.admins.reset_password_description'),
        'assignment_updated' => __('superadmin.admins.assignment_updated'), 'password_mismatch' => __('superadmin.admins.password_mismatch'),
        'password_reset' => __('superadmin.admins.password_reset'), 'confirm_block' => __('superadmin.admins.confirm_block'),
        'block_description' => __('superadmin.admins.block_description'), 'confirm_delete' => __('superadmin.admins.confirm_delete'),
        'delete_description' => __('superadmin.admins.delete_description'), 'delete_account' => __('superadmin.admins.delete_account'),
        'deleted' => __('superadmin.admins.deleted'), 'updated' => __('superadmin.admins.updated'),
    ]" :urls="['admin_id' => (string) $adminId, 'rooms' => route('superadmin.rooms.index'), 'list' => route('superadmin.admins.page')]" />
@endpush
