{{-- Public game link that cannot be opened: unknown / revoked token (404) or closed room (410, $closed = true). --}}
@php
  $closed = $closed ?? false;
  $title = $closed ? __('games.public.closed_title') : __('games.public.invalid_title');
@endphp
<x-game.play-layout :title="$title">
  <main class="mx-auto max-w-md px-4 py-12">
    <section class="rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-xs" @if($closed) data-game-link-closed @else data-game-link-invalid @endif>
      <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl {{ $closed ? 'bg-slate-100 text-slate-600' : 'bg-rose-50 text-rose-600' }}">
        <span class="material-symbols-outlined text-[30px]" aria-hidden="true">{{ $closed ? 'lock' : 'link_off' }}</span>
      </span>
      <h1 class="mt-4 text-xl font-bold text-slate-900">{{ $title }}</h1>
      <p class="mt-2 text-sm leading-relaxed text-slate-500">{{ $closed ? __('games.public.closed_desc') : __('games.public.invalid_desc') }}</p>
      <a href="{{ route('landing') }}" class="mt-6 inline-flex h-11 items-center gap-1.5 rounded-xl bg-[#006948] px-5 text-sm font-semibold text-white hover:bg-[#005137]">
        <span class="material-symbols-outlined text-[18px]" aria-hidden="true">home</span>{{ __('games.public.back_home') }}
      </a>
    </section>
  </main>
</x-game.play-layout>
