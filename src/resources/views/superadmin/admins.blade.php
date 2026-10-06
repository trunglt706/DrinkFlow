@extends('superadmin.layout', ['title' => __('superadmin.admins.title'), 'active' => 'admins'])
@section('content')
    <x-superadmin.page-header :eyebrow="__('superadmin.common.core_system')" :title="__('superadmin.admins.title')" :description="__('superadmin.admins.description')">
            <button class="sa-button" type="button" data-modal-open="create-admin-modal">
                <span class="material-symbols-outlined text-[16px]">person_add</span>{{ __('superadmin.admins.create') }}
            </button>
    </x-superadmin.page-header>
    <div id="notice" class="sa-notice"></div>
    <section class="sa-card sa-section">
        <div class="sa-section-header">
            <div>
                <h2>{{ __('superadmin.admins.administrators') }}</h2>
                <p>{{ __('superadmin.common.accounts_count', ['count' => $admins->total()]) }}</p>
            </div>
            <form method="GET" class="superadmin-actions">
                <x-superadmin.search-input :value="$filters['search'] ?? ''" placeholder="{{ __('superadmin.admins.search') }}" />
                <select name="status" class="sa-input">
                    <option value="">{{ __('superadmin.common.all_statuses') }}</option>
                    @foreach (array_column(\App\Enums\AdminStatus::cases(), 'value') as $value)
                        <option value="{{ $value }}" @selected(($filters['status'] ?? '') === $value)>{{ __('superadmin.common.'.$value) }}</option>
                    @endforeach
                </select>
                <button class="sa-button secondary" type="submit"><span class="material-symbols-outlined text-[16px]">filter_list</span>{{ __('superadmin.common.filter') }}</button>
            </form>
        </div>
        <div class="sa-table-wrap">
            <table class="sa-table">
                <thead>
                    <tr>
                        <th class="col-primary">{{ __('superadmin.common.admin') }}</th>
                        <th class="col-status">{{ __('superadmin.common.status') }}</th>
                        <th class="num">{{ __('superadmin.admins.assigned_rooms') }}</th>
                        <th class="text-right col-actions">{{ __('superadmin.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($admins as $admin)
                        @php
                            $statusValue = $admin->status instanceof \BackedEnum ? $admin->status->value : (string) $admin->status;
                        @endphp
                        <tr>
                            <td class="col-primary">
                                <div class="flex items-center gap-3">
                                    <span class="superadmin-avatar shrink-0">{{ mb_strtoupper(mb_substr($admin->name, 0, 1)) }}</span>
                                    <span class="min-w-0">
                                        <strong class="block truncate">{{ $admin->name }}</strong>
                                        <small class="block truncate text-outline"><x-superadmin.sensitive :value="$admin->email" /></small>
                                    </span>
                                </div>
                            </td>
                            <td class="col-status"><x-superadmin.status-pill :status="$statusValue" /></td>
                            <td class="whitespace-nowrap num">{{ __('superadmin.common.rooms_count', ['count' => $admin->rooms_count]) }}</td>
                            <td class="col-actions">
                                <x-superadmin.row-actions>
                                    <a class="sa-button secondary" href="{{ route('superadmin.admins.detail.page', $admin) }}"><span class="material-symbols-outlined text-[16px]">visibility</span>{{ __('superadmin.common.details') }}</a>
                                    <x-slot:menu>
                                        @if($statusValue === 'active')
                                            <button class="sa-menu-item is-warning" type="button" data-action="block-admin" data-admin-id="{{ $admin->id }}" data-admin-name="{{ $admin->name }}" role="menuitem"><span class="material-symbols-outlined">lock</span>{{ __('superadmin.common.block') }}</button>
                                            <div class="sa-menu-sep"></div>
                                        @endif
                                        <button class="sa-menu-item is-danger" type="button" data-action="delete-admin" data-admin-id="{{ $admin->id }}" data-admin-name="{{ $admin->name }}" role="menuitem"><span class="material-symbols-outlined">delete</span>{{ __('superadmin.common.delete') }}</button>
                                    </x-slot:menu>
                                </x-superadmin.row-actions>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4">
                                <x-superadmin.empty-state icon="manage_search" :title="__('superadmin.admins.no_results_title')" :description="__('superadmin.admins.no_results_description')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $admins->links('components.superadmin.pagination') }}
    </section>

    <x-superadmin.modal id="create-admin-modal" icon="person_add" tone="create" :title="__('superadmin.admins.create')" :description="__('superadmin.admins.create_description')">
        <form id="create-admin-form" class="space-y-4">
            @csrf
            <x-superadmin.modal-guide :title="__('superadmin.modals.create_admin.guide_title')">
                <li>{{ __('superadmin.modals.create_admin.guide_login') }}</li>
                <li>{{ __('superadmin.modals.create_admin.guide_scope') }}</li>
                <li>{{ __('superadmin.modals.create_admin.guide_share') }}</li>
            </x-superadmin.modal-guide>
            <div>
                <label for="create-admin-name" class="sa-label">{{ __('superadmin.admins.field_name') }} <span class="text-error">*</span></label>
                <input type="text" id="create-admin-name" name="name" required maxlength="255" autocomplete="off" class="sa-field">
            </div>
            <div>
                <label for="create-admin-email" class="sa-label">{{ __('superadmin.admins.field_email') }} <span class="text-error">*</span></label>
                <input type="email" id="create-admin-email" name="email" required maxlength="255" autocomplete="off" class="sa-field">
                <p class="sa-hint">{{ __('superadmin.modals.create_admin.email_hint') }}</p>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="create-admin-password" class="sa-label">{{ __('superadmin.admins.field_password') }} <span class="text-error">*</span></label>
                    <x-superadmin.password-input id="create-admin-password" name="password" required minlength="8" />
                </div>
                <div>
                    <label for="create-admin-password-confirmation" class="sa-label">{{ __('superadmin.admins.field_password_confirmation') }} <span class="text-error">*</span></label>
                    <x-superadmin.password-input id="create-admin-password-confirmation" name="password_confirmation" required minlength="8" />
                </div>
                <p class="sa-hint sm:col-span-2 !-mt-2">{{ __('superadmin.admins.password_hint') }}</p>
            </div>
            <div>
                <label class="sa-label">{{ __('superadmin.admins.field_rooms') }}</label>
                <div id="create-admin-room-options" class="sa-check-list space-y-1">
                    <x-superadmin.empty-state loading :bordered="false" class="!py-6" :title="__('superadmin.common.loading_title')" :description="__('superadmin.admins.loading_rooms')" />
                </div>
                <p class="sa-hint">{{ __('superadmin.modals.create_admin.rooms_hint') }}</p>
            </div>
            <div class="sa-modal-footer">
                <button type="button" class="sa-button secondary" data-modal-close>{{ __('superadmin.common.cancel') }}</button>
                <button type="submit" class="sa-button">
                    <span class="material-symbols-outlined text-[16px]">person_add</span>{{ __('superadmin.admins.create') }}
                </button>
            </div>
        </form>
    </x-superadmin.modal>

    {{-- Empty state cloned by the page script, so its strings stay translated through __(). --}}
    <template id="tpl-no-rooms"><x-superadmin.empty-state icon="domain_disabled" :bordered="false" class="!py-6" :title="__('superadmin.admins.no_rooms_title')" :description="__('superadmin.admins.no_rooms_description')" /></template>
    <template id="tpl-load-failed"><x-superadmin.empty-state icon="error" :bordered="false" class="!py-6" :title="__('superadmin.common.load_failed_title')" description="" /></template>
@endsection
@push('scripts')
    <x-superadmin.page-script name="admins" :i18n="[
        'password_mismatch' => __('superadmin.admins.password_mismatch'),
        'confirm_delete' => __('superadmin.admins.confirm_delete'), 'delete_description' => __('superadmin.admins.delete_description'),
        'delete_account' => __('superadmin.admins.delete_account'), 'deleted' => __('superadmin.admins.deleted'),
        'confirm_block' => __('superadmin.admins.confirm_block'), 'block_description' => __('superadmin.admins.block_description'),
        'block_account' => __('superadmin.admins.block_account'), 'updated' => __('superadmin.admins.updated'),
    ]" :urls="['rooms' => route('superadmin.rooms.index'), 'store' => route('superadmin.admins.store')]" />
@endpush
