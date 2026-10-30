@extends('superadmin.layout', ['title' => __('games.admin.title'), 'active' => 'games'])
@section('content')
    <x-superadmin.page-header :eyebrow="__('platform.nav.content')" :title="__('games.admin.title')" :description="__('games.admin.description')">
            @can('game.manage')
                <button class="sa-button" type="button" data-modal-open="create-game-modal">
                    <span class="material-symbols-outlined text-[16px]">add</span>{{ __('games.admin.create') }}
                </button>
            @endcan
    </x-superadmin.page-header>
    <x-superadmin.flash />
    <section class="sa-card sa-section">
        <div class="sa-section-header">
            <div>
                <h2>{{ __('games.admin.list') }}</h2>
                <p>{{ __('games.admin.count', ['count' => $games->count()]) }}</p>
            </div>
        </div>
        <div class="sa-table-wrap">
            <table class="sa-table">
                <thead>
                    <tr>
                        <th class="col-primary">{{ __('games.admin.field_name') }}</th>
                        <th class="col-status">{{ __('superadmin.common.status') }}</th>
                        <th class="text-right num">{{ __('games.admin.field_sort_order') }}</th>
                        <th class="text-right num">{{ __('games.admin.rooms') }}</th>
                        <th class="text-right num">{{ __('games.admin.rule_sets') }}</th>
                        <th class="text-right col-actions">{{ __('superadmin.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($games as $game)
                        <tr>
                            <td class="col-primary">
                                <span class="flex items-center gap-2.5">
                                    <span class="material-symbols-outlined text-[22px] text-primary" aria-hidden="true">{{ $game->icon ?: 'sports_esports' }}</span>
                                    <span>
                                        <strong class="block">{{ $game->name }}</strong>
                                        <small class="block text-outline font-mono">{{ $game->slug }}</small>
                                    </span>
                                </span>
                            </td>
                            <td class="col-status"><x-superadmin.status-pill :status="$game->is_active ? 'active' : 'inactive'" /></td>
                            <td class="text-right num">{{ $game->sort_order }}</td>
                            <td class="text-right num">{{ $game->rooms_count }}</td>
                            <td class="text-right num">{{ $game->rule_sets_count }}</td>
                            <td class="text-right col-actions">
                                <a class="sa-button secondary" href="{{ route('superadmin.games.show', $game) }}"><span class="material-symbols-outlined text-[16px]">edit</span>{{ __('superadmin.common.details') }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">
                                <x-superadmin.empty-state icon="sports_esports" :title="__('games.admin.empty_title')" :description="__('games.admin.empty_description')" />
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @can('game.manage')
        <x-superadmin.modal id="create-game-modal" icon="sports_esports" tone="create" :title="__('games.admin.create')" :description="__('games.admin.create_description')" max-width="max-w-2xl">
            <form method="POST" action="{{ route('superadmin.games.store') }}" class="space-y-4">
                @csrf
                @include('superadmin.games._fields', ['game' => null])
                <div class="sa-modal-footer">
                    <button type="button" class="sa-button secondary" data-modal-close>{{ __('superadmin.common.cancel') }}</button>
                    <button type="submit" class="sa-button"><span class="material-symbols-outlined text-[16px]">add</span>{{ __('games.admin.create') }}</button>
                </div>
            </form>
        </x-superadmin.modal>
    @endcan
@endsection
