@extends('superadmin.layout', ['title' => $package->name, 'active' => 'packages'])
@section('content')
    @php
        $canManage = \Illuminate\Support\Facades\Gate::allows('package.manage');
    @endphp
    <x-superadmin.page-header :back="route('superadmin.packages.index')" :back-label="__('platform.packages.title')" :title="$package->name">
        <x-slot:meta><p class="sa-heading-meta"><code>{{ $package->code }}</code> · <x-superadmin.status-pill :status="$package->status" /></p></x-slot:meta>
            @if ($canManage)
                <div class="superadmin-actions">
                    @if ($package->status !== \App\Enums\PackageStatus::Archived)
                        <form method="POST" action="{{ route('superadmin.packages.archive', $package) }}"
                            data-confirm-tone="warning" data-confirm-description="{{ __('superadmin.modals.confirm.archive_package') }}" data-confirm-icon="archive" data-confirm-label="{{ __('superadmin.common.archive') }}" data-confirm="{{ __('platform.packages.archive_confirm', ['name' => $package->name]) }}">
                            @csrf
                            <button class="sa-button secondary" type="submit"><span class="material-symbols-outlined text-[16px]">archive</span>{{ __('superadmin.common.archive') }}</button>
                        </form>
                    @endif
                    @unless ($inUse)
                        <form method="POST" action="{{ route('superadmin.packages.destroy', $package) }}"
                            data-confirm-tone="danger" data-confirm-description="{{ __('superadmin.modals.confirm.delete_package') }}" data-confirm-irreversible="1" data-confirm="{{ __('platform.packages.delete_confirm', ['name' => $package->name]) }}">
                            @csrf
                            @method('DELETE')
                            <button class="sa-button danger" type="submit"><span class="material-symbols-outlined text-[16px]">delete</span>{{ __('superadmin.common.delete') }}</button>
                        </form>
                    @endunless
                </div>
            @endif
    </x-superadmin.page-header>
    <x-superadmin.flash />

    <div class="sa-split">
        <section class="sa-card sa-section">
            <div class="sa-section-header">
                <div>
                    <h2>{{ __('platform.packages.settings') }}</h2>
                    <p>{{ __('platform.packages.snapshot_notice') }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('superadmin.packages.update', $package) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <fieldset @disabled(! $canManage)>
                    @include('superadmin.packages._fields', ['package' => $package])
                </fieldset>
                @if ($canManage)
                    <div class="sa-form-actions">
                        <button type="submit" class="sa-button"><span class="material-symbols-outlined text-[16px]">save</span>{{ __('superadmin.common.save') }}</button>
                    </div>
                @endif
            </form>
        </section>

        <section class="sa-card sa-section">
            <div class="sa-section-header">
                <div>
                    <h2>{{ __('platform.packages.usage') }}</h2>
                </div>
            </div>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-outline">{{ __('platform.packages.active_subscriptions') }}</dt><dd class="font-semibold">{{ $activeSubscriptions }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-outline">{{ __('platform.packages.created_at') }}</dt><dd>{{ $package->created_at?->toAppDateTime() }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-outline">{{ __('platform.packages.updated_at') }}</dt><dd>{{ $package->updated_at?->toAppDateTime() }}</dd></div>
            </dl>
            @if ($inUse)
                <p class="mt-4 text-xs text-outline">{{ __('platform.packages.in_use') }}</p>
            @endif
        </section>
    </div>
@endsection
