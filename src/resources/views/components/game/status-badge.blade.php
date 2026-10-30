@props(['status'])
{{-- Room status: icon + text, so the state never depends on colour alone. --}}
@php
  $style = match ($status) {
      \App\Enums\GameRoomStatus::Waiting => ['bg-amber-50 text-amber-800 border-amber-200', 'hourglass_top'],
      \App\Enums\GameRoomStatus::Playing => ['bg-emerald-50 text-emerald-800 border-emerald-200', 'play_circle'],
      \App\Enums\GameRoomStatus::Finished => ['bg-slate-100 text-slate-600 border-slate-200', 'flag'],
  };
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap '.$style[0]]) }}>
  <span class="material-symbols-outlined text-[14px]" aria-hidden="true">{{ $style[1] }}</span>
  {{ $status->label() }}
</span>
