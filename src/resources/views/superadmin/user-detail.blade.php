@extends('superadmin.layout', ['active' => 'users', 'title' => __('superadmin.users.profile')])

@section('content')
    <x-superadmin.page-header :back="route('superadmin.global-users.page')" :back-label="__('superadmin.layout.global_users')"
        :title="__('superadmin.users.profile')" title-id="user-name" :description="__('superadmin.users.loading_profile')" description-id="user-meta">
            <button class="sa-button secondary" id="user-status-action" type="button"><span class="material-symbols-outlined text-[16px]">toggle_on</span>{{ __('superadmin.users.update_status') }}</button>
            <button class="sa-button danger" id="user-delete-action" type="button"><span class="material-symbols-outlined text-[16px]">delete</span>{{ __('superadmin.users.delete_user') }}</button>
    </x-superadmin.page-header>
    <div id="notice" class="sa-notice" role="status"></div>
    <div class="sa-split">
        <section class="sa-card sa-section">
            <div class="sa-section-header">
                <div>
                    <h2>{{ __('superadmin.users.identity_metadata') }}</h2>
                    <p>{{ __('superadmin.users.identity_description') }}</p>
                </div>
            </div>
            <div id="identity-list" class="sa-detail-list">
                <x-superadmin.empty-state loading :title="__('superadmin.common.loading_title')" :description="__('superadmin.common.loading_description')" />
            </div>
        </section>
        <section class="sa-card sa-section">
            <div class="sa-section-header">
                <div>
                    <h2>{{ __('superadmin.users.room_memberships') }}</h2>
                    <p>{{ __('superadmin.users.membership_description') }}</p>
                </div>
            </div>
            <div class="sa-table-wrap">
                <table class="sa-table">
                    <thead>
                        <tr>
                            <th class="col-primary">{{ __('superadmin.common.room') }}</th>
                            <th class="col-status">{{ __('superadmin.common.status') }}</th>
                            <th class="whitespace-nowrap num">{{ __('superadmin.users.devices') }}</th>
                            <th class="text-right col-actions">{{ __('superadmin.common.actions') }}</th>
                        </tr>
                    </thead>
                    <tbody id="membership-list">
                        <tr><td colspan="4"><x-superadmin.empty-state loading :title="__('superadmin.common.loading_title')" :description="__('superadmin.common.loading_description')" /></td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    {{-- Empty states cloned by the page script, so every string stays translated through __(). --}}
    <template id="tpl-no-memberships"><x-superadmin.empty-state icon="meeting_room" :title="__('superadmin.users.no_memberships_title')" :description="__('superadmin.users.no_memberships_description')" /></template>
    <template id="tpl-load-failed"><x-superadmin.empty-state icon="error" :title="__('superadmin.common.load_failed_title')" description="" /></template>
@endsection

@push('scripts')
    <x-superadmin.page-script name="user-detail" :i18n="[
        'name' => __('superadmin.common.name'), 'email' => __('superadmin.common.email'), 'status' => __('superadmin.common.status'),
        'created_at' => __('superadmin.users.created_at'), 'last_login' => __('superadmin.users.last_login'),
        'never_logged_in' => __('superadmin.users.never_logged_in'), 'rooms_joined' => __('superadmin.users.rooms_joined'),
        'total_orders' => __('superadmin.users.total_orders'), 'linked_identity' => __('superadmin.users.linked_identity'),
        'no_login_metadata' => __('superadmin.users.no_login_metadata'), 'remove' => __('superadmin.users.remove'),
        'block' => __('superadmin.common.block'), 'unblock' => __('superadmin.common.unblock'),
        'confirm_block' => __('superadmin.users.confirm_block'), 'confirm_unblock' => __('superadmin.users.confirm_unblock'),
        'block_description' => __('superadmin.users.block_description'), 'unblock_description' => __('superadmin.users.unblock_description'),
        'status_updated' => __('superadmin.users.status_updated'), 'confirm_remove_membership' => __('superadmin.users.confirm_remove_membership'),
        'membership_removed' => __('superadmin.users.membership_removed'), 'confirm_delete' => __('superadmin.users.confirm_delete'),
        'delete' => __('superadmin.common.delete'), 'deleted' => __('superadmin.users.deleted'),
        'delete_description' => __('superadmin.modals.confirm.user_delete'), 'membership_remove_description' => __('superadmin.modals.confirm.membership_remove'),
    ]" :urls="['user_id' => (string) request()->route('globalUser'), 'list' => route('superadmin.global-users.page')]" />
@endpush
