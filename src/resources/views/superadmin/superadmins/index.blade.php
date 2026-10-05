@extends('superadmin.layout', ['title' => __('superadmin.superadmins.title'), 'active' => 'superadmins'])
@section('content')
    @php
        $fieldLabel = 'flex flex-col gap-1.5 text-[13px] font-semibold text-on-surface';
        $fieldInput = 'sa-input !min-w-0 w-full';
    @endphp
    <x-superadmin.page-header :eyebrow="__('superadmin.common.governance')" :title="__('superadmin.superadmins.title')" :description="__('superadmin.superadmins.description')">
            @can('superadmin.manage')
                <button class="sa-button" type="button" data-modal-open="create-superadmin-modal">
                    <span class="material-symbols-outlined text-[16px]">person_add</span>{{ __('superadmin.superadmins.create') }}
                </button>
            @endcan
    </x-superadmin.page-header>
    <x-superadmin.flash />
    <section class="sa-card sa-section">
        <div class="sa-section-header">
            <div>
                <h2>{{ __('superadmin.superadmins.accounts') }}</h2>
                <p>{{ __('superadmin.common.accounts_count', ['count' => $superadmins->total()]) }}</p>
            </div>
            <form method="GET" class="superadmin-actions">
                <x-superadmin.search-input :value="$filters['search']" placeholder="{{ __('superadmin.superadmins.search') }}" />
                <select name="status" class="sa-input" aria-label="{{ __('superadmin.common.status') }}">
                    <option value="">{{ __('superadmin.common.all_statuses') }}</option>
                    @foreach (\App\Enums\SuperadminStatus::cases() as $case)
                        <option value="{{ $case->value }}" @selected($filters['status'] === $case->value)>{{ __('superadmin.common.'.$case->value) }}</option>
                    @endforeach
                </select>
                <button class="sa-button secondary" type="submit"><span class="material-symbols-outlined text-[16px]">filter_list</span>{{ __('superadmin.common.filter') }}</button>
            </form>
        </div>
        <div class="sa-table-wrap">
            <table class="sa-table">
                <thead>
                    <tr>
                        <th class="col-primary">{{ __('superadmin.superadmins.account') }}</th>
                        <th class="col-status">{{ __('superadmin.common.status') }}</th>
                        <th>{{ __('superadmin.superadmins.permissions') }}</th>
                        <th class="col-date">{{ __('superadmin.superadmins.last_login') }}</th>
                        <th class="text-right col-actions">{{ __('superadmin.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($superadmins as $superadmin)
                        <tr>
                            <td class="col-primary">
                                <div class="flex items-center gap-3">
                                    <span class="superadmin-avatar shrink-0">{{ mb_strtoupper(mb_substr($superadmin->name, 0, 1)) }}</span>
                                    <span class="min-w-0">
                                        <strong class="block truncate">{{ $superadmin->name }}</strong>
                                        <small class="block truncate text-outline">{{ $superadmin->email }}</small>
                                    </span>
                                </div>
                            </td>
                            <td class="col-status"><x-superadmin.status-pill :status="$superadmin->status" /></td>
                            <td class="whitespace-nowrap">{{ __('superadmin.superadmins.permissions_count', ['count' => $superadmin->permissions_count, 'total' => $permissionTotal]) }}</td>
                            <td class="whitespace-nowrap col-date">{{ $superadmin->last_login_at?->format('d/m/Y H:i') ?? __('superadmin.superadmins.never_logged_in') }}</td>
                            <td class="text-right col-actions">
                                <a class="sa-button secondary" href="{{ route('superadmin.superadmins.show', $superadmin) }}"><span class="material-symbols-outlined text-[16px]">visibility</span>{{ __('superadmin.common.details') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5">
                                <x-superadmin.empty-state icon="manage_search" :title="__('superadmin.superadmins.empty_title')" :description="__('superadmin.superadmins.empty_description')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($superadmins->hasPages())
            {{ $superadmins->links('components.superadmin.pagination') }}
        @endif
    </section>

    @can('superadmin.manage')
        <x-superadmin.modal id="create-superadmin-modal" icon="shield_person" tone="security" :title="__('superadmin.superadmins.create')" :description="__('superadmin.superadmins.create_description')">
            <form method="POST" action="{{ route('superadmin.superadmins.store') }}" class="space-y-4">
                @csrf
                <x-superadmin.modal-guide :title="__('superadmin.modals.create_superadmin.guide_title')">
                    <li>{{ __('superadmin.modals.create_superadmin.guide_permissions') }}</li>
                    <li>{{ __('superadmin.modals.create_superadmin.guide_login') }}</li>
                </x-superadmin.modal-guide>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <label class="{{ $fieldLabel }}">{{ __('superadmin.superadmins.field_name') }}
                        <input name="name" type="text" required maxlength="255" value="{{ old('name') }}" class="{{ $fieldInput }}">
                    </label>
                    <label class="{{ $fieldLabel }}">{{ __('superadmin.superadmins.field_email') }}
                        <input name="email" type="email" required maxlength="255" value="{{ old('email') }}" class="{{ $fieldInput }}">
                        <span class="sa-hint !mt-0">{{ __('superadmin.modals.create_superadmin.email_hint') }}</span>
                    </label>
                    <label class="{{ $fieldLabel }}">{{ __('superadmin.superadmins.field_password') }}
                        <x-superadmin.password-input id="create-superadmin-password" name="password" required minlength="8" />
                    </label>
                    <label class="{{ $fieldLabel }}">{{ __('superadmin.superadmins.field_password_confirmation') }}
                        <x-superadmin.password-input id="create-superadmin-password-confirmation" name="password_confirmation" required minlength="8" />
                    </label>
                    <p class="sa-hint sm:col-span-2 !-mt-2">{{ __('superadmin.modals.create_superadmin.password_hint') }}</p>
                </div>
                <div class="sa-modal-footer">
                    <button type="button" class="sa-button secondary" data-modal-close>{{ __('superadmin.common.cancel') }}</button>
                    <button type="submit" class="sa-button"><span class="material-symbols-outlined text-[16px]">person_add</span>{{ __('superadmin.superadmins.create') }}</button>
                </div>
            </form>
        </x-superadmin.modal>
    @endcan
@endsection
