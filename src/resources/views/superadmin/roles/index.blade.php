@extends('superadmin.layout', ['title' => __('platform.roles.title'), 'active' => 'roles'])
@section('content')
    <x-superadmin.page-header :eyebrow="__('platform.nav.governance')" :title="__('platform.roles.title')" :description="__('platform.roles.description')">
            @can('superadmin.manage')
                <a class="sa-button" href="{{ route('superadmin.roles.create') }}"><span class="material-symbols-outlined text-[16px]">add</span>{{ __('platform.roles.create') }}</a>
            @endcan
    </x-superadmin.page-header>
    <x-superadmin.flash />
    <section class="sa-card sa-section">
        <div class="sa-table-wrap">
            <table class="sa-table">
                <thead>
                    <tr>
                        <th class="col-primary">{{ __('platform.roles.field_name') }}</th>
                        <th class="text-right num">{{ __('superadmin.superadmins.permissions') }}</th>
                        <th class="text-right num">{{ __('platform.roles.superadmins') }}</th>
                        <th class="text-right col-actions">{{ __('superadmin.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($roles as $role)
                        <tr>
                            <td class="col-primary"><strong class="block">{{ $role->name }}</strong><small class="block text-outline font-mono">{{ $role->code }}</small>@if ($role->description)<small class="block text-outline">{{ $role->description }}</small>@endif</td>
                            <td class="text-right num">{{ $role->permissions_count }}</td>
                            <td class="text-right num">{{ $role->superadmins_count }}</td>
                            <td class="text-right col-actions"><a class="sa-button secondary" href="{{ route('superadmin.roles.edit', $role) }}"><span class="material-symbols-outlined text-[16px]">edit</span>{{ __('superadmin.common.details') }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="4"><x-superadmin.empty-state icon="badge" :title="__('platform.roles.empty')" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
