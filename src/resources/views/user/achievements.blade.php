{{-- Achievements of the viewer in the room: TOP 1-3 sponsor medals of each month of the current year, the medal
     totals and a word of encouragement that depends on how many medals were earned. --}}
@php
    $year = $achievements['year'];
    $totals = $achievements['totals'];
    $metals = [1 => 'gold', 2 => 'silver', 3 => 'bronze'];
    $settledOn = now()->startOfMonth()->addMonth()->format('d/m/Y');
@endphp
<x-room.layout :room="$room" :room-user="$roomUser" active-tab="achievements" :title="__('achievements.page_title').' · '.$room->name">
  <style>
    .df-ach .medal-gold{--light:#fef3c7;--edge:#f3d27a;--ink:#b7791f}
    .df-ach .medal-silver{--light:#eef2f6;--edge:#cbd5e1;--ink:#64748b}
    .df-ach .medal-bronze{--light:#fde4cf;--edge:#efb98e;--ink:#a0522d}
    .df-ach .medal-icon{display:inline-flex;align-items:center;justify-content:center;border-radius:999px;background:var(--light);border:2px solid var(--edge);color:var(--ink)}
    .df-ach .medal-icon .material-symbols-outlined{font-variation-settings:'FILL' 1}
    .df-ach .month-medal{background:linear-gradient(160deg,var(--light),#fff 75%);border-color:var(--edge)}
    .df-ach .month-medal .medal-icon{animation:df-ach-shine 3s ease-in-out infinite}
    .df-ach .trophy{animation:df-ach-bob 2.6s ease-in-out infinite}
    @keyframes df-ach-shine{0%,100%{box-shadow:0 0 0 0 rgba(251,191,36,0)}50%{box-shadow:0 0 0 6px rgba(251,191,36,.18)}}
    @keyframes df-ach-bob{50%{transform:translateY(-4px) rotate(-4deg)}}
    @media (prefers-reduced-motion:reduce){.df-ach .month-medal .medal-icon,.df-ach .trophy{animation:none}}
  </style>

  <div class="df-ach space-y-5" data-achievements data-achievements-total="{{ $totals['total'] }}">
    {{-- Year summary --}}
    <section class="relative overflow-hidden rounded-2xl border border-amber-200/70 p-5 sm:p-6 shadow-2xs" style="background:linear-gradient(135deg,#fffbeb,#ffffff 55%,#fff1f2)">
      <div class="pointer-events-none absolute -right-10 -top-12 h-44 w-44 rounded-full bg-amber-200/40 blur-2xl" aria-hidden="true"></div>
      <div class="relative flex flex-col gap-5 md:flex-row md:items-center md:justify-between">
        <div>
          <p class="inline-flex items-center gap-1.5 rounded-full bg-white/80 px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-amber-700 ring-1 ring-amber-200">
            <span class="material-symbols-outlined text-[14px]" aria-hidden="true">workspace_premium</span>{{ __('achievements.eyebrow') }}
          </p>
          <h1 class="mt-3 text-2xl sm:text-3xl font-bold tracking-tight text-slate-900">{{ __('achievements.title', ['year' => $year]) }}</h1>
          <p class="mt-1 text-sm text-slate-600">{{ __('achievements.subtitle', ['room' => $room->name]) }}</p>
        </div>
        <div class="flex items-center gap-4 sm:gap-6">
          <div class="flex items-center gap-3">
            <span class="trophy flex h-14 w-14 items-center justify-center rounded-2xl text-white shadow-md" style="background:linear-gradient(135deg,#fcd34d,#f59e0b)" aria-hidden="true">
              <span class="material-symbols-outlined text-[32px]" style="font-variation-settings:'FILL' 1">emoji_events</span>
            </span>
            <div>
              <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500">{{ __('achievements.total_label') }}</p>
              <p class="text-4xl font-extrabold leading-none text-slate-900" data-achievements-total-value>{{ $totals['total'] }}</p>
            </div>
          </div>
          <ul class="flex items-center gap-2 sm:gap-3">
            @foreach ($metals as $rank => $metal)
              <li class="flex flex-col items-center gap-1 rounded-xl bg-white/80 px-2.5 py-2 ring-1 ring-slate-200/70 medal-{{ $metal }}" data-achievements-count="{{ $metal }}">
                <span class="medal-icon h-8 w-8"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">workspace_premium</span></span>
                <span class="text-sm font-bold text-slate-900">{{ $totals[$metal] }}</span>
                <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ __('achievements.'.$metal) }}</span>
              </li>
            @endforeach
          </ul>
        </div>
      </div>
    </section>

    {{-- Encouragement --}}
    <section class="flex items-start gap-3 rounded-2xl border border-emerald-200 bg-emerald-50/70 p-4 sm:p-5" data-achievements-cheer>
      <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-white text-xl shadow-2xs" aria-hidden="true">{{ $totals['total'] > 0 ? '🎉' : '💚' }}</span>
      <div>
        <h2 class="text-sm font-bold text-emerald-900">{{ __('achievements.cheer_title') }}</h2>
        <p class="mt-1 text-sm leading-relaxed text-emerald-900/90">{{ trans_choice('achievements.cheer', $totals['total'], ['count' => $totals['total']]) }}</p>
      </div>
    </section>

    {{-- Months --}}
    <section class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-2xs">
      <h2 class="mb-4 flex items-center gap-2 text-sm font-bold text-slate-900">
        <span class="material-symbols-outlined text-[18px] text-[#006948]" aria-hidden="true">calendar_month</span>{{ __('achievements.months_title') }}
      </h2>
      <ol class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
        @foreach ($achievements['months'] as $item)
          @php
            $metal = $item['rank'] !== null ? $metals[$item['rank']] : null;
          @endphp
          <li data-achievement-month="{{ $item['month'] }}" data-status="{{ $item['status'] }}"
            class="flex min-h-[148px] flex-col items-center justify-center gap-1.5 rounded-xl border p-3 text-center
              {{ match ($item['status']) {
                  'medal' => 'month-medal medal-'.$metal,
                  'current' => 'border-dashed border-emerald-300 bg-emerald-50/40',
                  'upcoming' => 'border-slate-200 bg-slate-50/60 opacity-70',
                  default => 'border-slate-200 bg-white',
              } }}">
            <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500">{{ __('achievements.month', ['month' => $item['month']]) }}</span>
            @switch ($item['status'])
              @case ('medal')
                <span class="medal-icon h-12 w-12"><span class="material-symbols-outlined text-[28px]" aria-hidden="true">workspace_premium</span></span>
                <span class="text-sm font-extrabold" style="color: var(--ink)">{{ __('achievements.rank', ['rank' => $item['rank']]) }} · {{ __('achievements.'.$metal) }}</span>
                <span class="text-[11px] text-slate-600">{{ __('achievements.sponsored', ['amount' => \App\Support\Helpers\FormatHelper::formatCurrency($item['amount'])]) }}</span>
                @break
              @case ('current')
                <span class="material-symbols-outlined text-[28px] text-emerald-600" aria-hidden="true">hourglass_top</span>
                <span class="text-sm font-bold text-emerald-700">{{ __('achievements.current') }}</span>
                <span class="text-[11px] text-slate-500">{{ __('achievements.current_hint', ['date' => $settledOn]) }}</span>
                @break
              @case ('upcoming')
                <span class="material-symbols-outlined text-[26px] text-slate-300" aria-hidden="true">lock_clock</span>
                <span class="text-xs font-semibold text-slate-400">{{ __('achievements.upcoming') }}</span>
                @break
              @default
                <span class="material-symbols-outlined text-[26px] text-slate-300" aria-hidden="true">military_tech</span>
                <span class="text-xs font-semibold text-slate-500">{{ __('achievements.no_medal') }}</span>
                <span class="text-[11px] text-slate-400">
                  {{ $item['position'] !== null
                      ? __('achievements.position', ['position' => $item['position']]).' · '.\App\Support\Helpers\FormatHelper::formatCurrency($item['amount'])
                      : __('achievements.no_sponsor') }}
                </span>
            @endswitch
          </li>
        @endforeach
      </ol>
    </section>

    {{-- How medals are earned --}}
    <section class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 text-sm text-slate-600">
      <span class="material-symbols-outlined text-[20px] text-slate-400" aria-hidden="true">info</span>
      <p><strong class="text-slate-800">{{ __('achievements.how_title') }}:</strong> {{ __('achievements.how_body') }}</p>
    </section>
  </div>
</x-room.layout>
