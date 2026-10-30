@extends('superadmin.layout', ['title' => $game->name, 'active' => 'games'])
@section('content')
    @php
        $canManage = \Illuminate\Support\Facades\Gate::allows('game.manage');
    @endphp
    <x-superadmin.page-header :back="route('superadmin.games.index')" :back-label="__('games.admin.title')" :title="$game->name">
        <x-slot:meta><p class="sa-heading-meta"><code>{{ $game->slug }}</code> · <x-superadmin.status-pill :status="$game->is_active ? 'active' : 'inactive'" /></p></x-slot:meta>
            @if ($canManage && ! $inUse)
                <div class="superadmin-actions">
                    <form method="POST" action="{{ route('superadmin.games.destroy', $game) }}"
                        data-confirm-tone="danger" data-confirm-description="{{ __('games.admin.delete_description') }}" data-confirm-irreversible="1" data-confirm="{{ __('games.admin.delete_confirm', ['name' => $game->name]) }}">
                        @csrf
                        @method('DELETE')
                        <button class="sa-button danger" type="submit" data-delete-game><span class="material-symbols-outlined text-[16px]">delete</span>{{ __('superadmin.common.delete') }}</button>
                    </form>
                </div>
            @endif
    </x-superadmin.page-header>
    <x-superadmin.flash />

    <div class="sa-split">
        <section class="sa-card sa-section">
            <div class="sa-section-header">
                <div>
                    <h2>{{ __('games.admin.settings') }}</h2>
                </div>
            </div>
            <form method="POST" action="{{ route('superadmin.games.update', $game) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <fieldset @disabled(! $canManage)>
                    @include('superadmin.games._fields', ['game' => $game])
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
                    <h2>{{ __('games.admin.usage') }}</h2>
                </div>
            </div>
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-outline">{{ __('games.admin.rooms') }}</dt><dd class="font-semibold">{{ $game->rooms_count }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-outline">{{ __('games.admin.rule_sets') }}</dt><dd class="font-semibold">{{ $game->rule_sets_count }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-outline">{{ __('games.admin.created_at') }}</dt><dd>{{ $game->created_at?->toAppDateTime() }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-outline">{{ __('games.admin.updated_at') }}</dt><dd>{{ $game->updated_at?->toAppDateTime() }}</dd></div>
            </dl>
            @if ($inUse)
                <p class="mt-4 text-xs text-outline">{{ __('games.admin.in_use') }}</p>
            @endif
        </section>
    </div>
@endsection
