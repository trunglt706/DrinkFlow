<x-global.layout
  :title="'DrinkFlow - ' . __('games.channels.title')"
  :user="$user"
  activeTab="games"
  :breadcrumbs="$breadcrumbs"
>
  <div class="space-y-6" data-game-channels>
    @include('user.global.games.partials.flash')

    <section class="flex flex-col gap-4 pb-6 border-b border-slate-200 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight">{{ __('games.channels.title') }}</h1>
        <p class="text-sm text-slate-500 mt-1">{{ __('games.channels.subtitle') }}</p>
      </div>
      <a href="{{ route('user.me.utilities.channels.create') }}" class="inline-flex h-11 items-center justify-center gap-1.5 rounded-xl bg-[#006948] px-5 text-sm font-semibold text-white hover:bg-[#005137]">
        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">add</span>{{ __('games.channels.create') }}
      </a>
    </section>

    @if($channels->isEmpty())
      <div class="p-10 border border-dashed border-slate-200 rounded-2xl bg-slate-50/50 text-center">
        <span class="material-symbols-outlined text-[36px] text-slate-300" aria-hidden="true">notifications_off</span>
        <p class="text-sm font-semibold text-slate-700 mt-2">{{ __('games.channels.empty') }}</p>
        <p class="text-xs text-slate-500 mt-1">{{ __('games.channels.empty_desc') }}</p>
      </div>
    @else
      <ul class="divide-y divide-slate-100 overflow-hidden rounded-2xl border border-slate-200/80 bg-white shadow-xs">
        @foreach($channels as $channel)
          <li class="flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between sm:px-5" data-channel-row="{{ $channel->id }}">
            <div class="flex min-w-0 items-center gap-3">
              <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-emerald-100 bg-emerald-50 text-[#006948]">
                <span class="material-symbols-outlined text-[20px]" aria-hidden="true">{{ $channel->type === 'webhook' ? 'webhook' : 'forum' }}</span>
              </span>
              <div class="min-w-0">
                <p class="text-sm font-bold text-slate-900 break-words">{{ $channel->name }}</p>
                <p class="mt-0.5 text-xs text-slate-500">
                  {{ __('games.channels.types.'.$channel->type) }}
                  · {{ trans_choice('games.channels.rooms_count', $channel->rooms_count, ['count' => $channel->rooms_count]) }}
                  · <span class="{{ $channel->is_active ? 'text-emerald-700' : 'text-slate-400' }}">{{ $channel->is_active ? __('games.channels.active') : __('games.channels.inactive') }}</span>
                </p>
              </div>
            </div>
            <div class="grid grid-cols-3 gap-2 sm:flex sm:flex-wrap">
              <form method="POST" action="{{ route('user.me.utilities.channels.test', $channel) }}">
                @csrf
                <button type="submit" @disabled(! $channel->is_active) class="inline-flex h-9 w-full items-center justify-center gap-1 whitespace-nowrap rounded-lg border border-slate-200 px-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50 sm:w-auto sm:px-3">
                  <span class="material-symbols-outlined text-[16px]" aria-hidden="true">send</span>{{ __('games.channels.test') }}
                </button>
              </form>
              <a href="{{ route('user.me.utilities.channels.edit', $channel) }}" class="inline-flex h-9 w-full items-center justify-center gap-1 whitespace-nowrap rounded-lg border border-slate-200 px-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 sm:w-auto sm:px-3">
                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">edit</span>{{ __('games.channels.edit') }}
              </a>
              <form method="POST" action="{{ route('user.me.utilities.channels.destroy', $channel) }}" data-confirm="{{ __('games.channels.delete_confirm') }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="inline-flex h-9 w-full items-center justify-center gap-1 whitespace-nowrap rounded-lg border border-slate-200 px-2 text-xs font-semibold text-rose-700 hover:bg-rose-50 sm:w-auto sm:px-3">
                  <span class="material-symbols-outlined text-[16px]" aria-hidden="true">delete</span>{{ __('games.channels.delete') }}
                </button>
              </form>
            </div>
          </li>
        @endforeach
      </ul>
    @endif
  </div>

  @include('user.global.games.partials.confirm-dialog')
</x-global.layout>
