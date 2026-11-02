@php
  $types = \App\Models\GameNotificationChannel::TYPES;
  $selectedType = (string) old('type', $channel?->type ?? 'telegram');
  $configured = $editable['secrets_configured'];
  $values = $editable['config'];
  $isActive = (bool) old('is_active', $channel?->is_active ?? true);
  // Fields of every driver; each one is shown only for the types that use it (CSS group-has on the selected type).
  $fields = [
      'webhook_url' => ['types' => ['slack', 'webhook'], 'secret' => true, 'input' => 'url'],
      'secret_token' => ['types' => ['webhook'], 'secret' => true, 'input' => 'password'],
      'bot_token' => ['types' => ['telegram'], 'secret' => true, 'input' => 'password'],
      'chat_id' => ['types' => ['telegram'], 'secret' => false, 'input' => 'text'],
      'api_token' => ['types' => ['chatwork'], 'secret' => true, 'input' => 'password'],
      'room_id' => ['types' => ['chatwork'], 'secret' => false, 'input' => 'text'],
  ];
  $visibility = [
      'slack' => 'group-has-[[data-channel-type=slack]:checked]:block',
      'webhook' => 'group-has-[[data-channel-type=webhook]:checked]:block',
      'telegram' => 'group-has-[[data-channel-type=telegram]:checked]:block',
      'chatwork' => 'group-has-[[data-channel-type=chatwork]:checked]:block',
  ];
@endphp
<x-global.layout
  :title="'DrinkFlow - ' . ($channel ? $channel->name : __('games.channels.create_title'))"
  :user="$user"
  activeTab="games"
  :breadcrumbs="$breadcrumbs"
>
  <div class="mx-auto max-w-2xl space-y-6">
    @include('user.global.games.partials.flash')

    <section class="pb-6 border-b border-slate-200">
      <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 tracking-tight">{{ $channel ? __('games.channels.edit_title') : __('games.channels.create_title') }}</h1>
      <p class="text-sm text-slate-500 mt-1">{{ __('games.channels.form_hint') }}</p>
    </section>

    <form method="POST" action="{{ $channel ? route('user.me.utilities.channels.update', $channel) : route('user.me.utilities.channels.store') }}" class="group space-y-5" data-channel-form autocomplete="off">
      @csrf
      @if($channel) @method('PUT') @endif
      @if($returnTo) <input type="hidden" name="return" value="{{ $returnTo }}"> @endif

      <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-xs space-y-4">
        <div>
          <label for="channel-name" class="text-sm font-semibold text-slate-800">{{ __('games.channels.name') }}</label>
          <input id="channel-name" type="text" name="name" value="{{ old('name', $channel?->name) }}" maxlength="120" required
                 placeholder="{{ __('games.channels.name_placeholder') }}"
                 class="mt-1.5 h-12 w-full rounded-xl border border-slate-200 bg-slate-50/70 px-4 text-base text-slate-900 focus:border-[#006948] focus:ring-1 focus:ring-[#006948]">
        </div>

        <fieldset>
          <legend class="text-sm font-semibold text-slate-800">{{ __('games.channels.type') }}</legend>
          <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
            @foreach($types as $type)
              <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200 p-3 text-sm font-semibold text-slate-700 has-[:checked]:border-[#006948] has-[:checked]:bg-emerald-50/60">
                <input type="radio" name="type" value="{{ $type }}" data-channel-type="{{ $type }}" @checked($selectedType === $type) class="h-4 w-4 text-[#006948] focus:ring-[#006948]" required>
                {{ __('games.channels.types.'.$type) }}
              </label>
            @endforeach
          </div>
        </fieldset>

        @foreach($fields as $key => $field)
          @php
            $hasStored = in_array($key, $configured, true);
            $classes = collect($field['types'])->map(fn ($type) => $visibility[$type])->implode(' ');
          @endphp
          <div class="hidden {{ $classes }}" data-channel-field="{{ $key }}">
            <label for="channel-{{ $key }}" class="text-sm font-semibold text-slate-800">{{ __('games.channels.fields.'.$key) }}</label>
            <input id="channel-{{ $key }}" type="{{ $field['input'] }}" name="config[{{ $key }}]"
                   value="{{ $field['secret'] ? '' : old('config.'.$key, $values[$key] ?? '') }}" maxlength="2000"
                   autocomplete="{{ $field['secret'] ? 'new-password' : 'off' }}" spellcheck="false"
                   placeholder="{{ $hasStored ? __('games.channels.keep_secret') : __('games.channels.placeholders.'.$key) }}"
                   class="mt-1.5 h-12 w-full rounded-xl border border-slate-200 bg-slate-50/70 px-4 font-mono text-sm text-slate-900 focus:border-[#006948] focus:ring-1 focus:ring-[#006948]">
            <p class="mt-1 text-xs text-slate-500">{{ __('games.channels.hints.'.$key) }}</p>
          </div>
        @endforeach

        <label class="flex items-center gap-2.5 text-sm font-medium text-slate-700">
          <input type="hidden" name="is_active" value="0">
          <input type="checkbox" name="is_active" value="1" @checked($isActive) class="h-5 w-5 rounded text-[#006948] focus:ring-[#006948]">
          {{ __('games.channels.is_active') }}
        </label>
      </div>

      <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
        <a href="{{ route('user.me.utilities.channels.index') }}" class="inline-flex h-12 items-center justify-center rounded-xl border border-slate-200 bg-white px-5 text-sm font-semibold text-slate-700 hover:bg-slate-50">{{ __('games.channels.cancel') }}</a>
        <button type="submit" class="inline-flex h-12 items-center justify-center gap-1.5 rounded-xl bg-[#006948] px-6 text-sm font-semibold text-white hover:bg-[#005137]">
          <span class="material-symbols-outlined text-[18px]" aria-hidden="true">save</span>{{ __('games.channels.save') }}
        </button>
      </div>
    </form>
  </div>
</x-global.layout>
