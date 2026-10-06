{{-- Recent top-3 sponsor medals of one member, as flip cards (front: medal, rank, month; back: thank-you note). --}}
@props(['badges' => []])

@if(count($badges) > 0)
    @once
    <style>
        .df-badge-history .badge-card{perspective:900px;background:none;border:0;padding:0;width:100%;cursor:pointer;text-align:center}
        .df-badge-history .badge-card:focus-visible{outline:2px solid #2563eb;outline-offset:3px;border-radius:12px}
        .df-badge-history .badge-flip{display:block;position:relative;height:132px;transform-style:preserve-3d;transition:transform .6s}
        .df-badge-history .badge-flip.is-flipped{transform:rotateY(180deg)}
        .df-badge-history .badge-face{position:absolute;inset:0;backface-visibility:hidden;border-radius:12px;border:1px solid var(--edge);background:linear-gradient(160deg,var(--light),#fff 70%);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px;padding:10px}
        .df-badge-history .badge-back{transform:rotateY(180deg);background:linear-gradient(160deg,#fff,var(--light))}
        .df-badge-history .rank-1{--light:#fef3c7;--edge:#f3d27a;--ink:#b7791f}.df-badge-history .rank-2{--light:#eef2f6;--edge:#cbd5e1;--ink:#64748b}.df-badge-history .rank-3{--light:#fde4cf;--edge:#efb98e;--ink:#a0522d}
        .df-badge-history .badge-medal{width:40px;height:40px;border-radius:999px;background:var(--light);border:2px solid var(--edge);color:var(--ink);display:flex;align-items:center;justify-content:center}
        .df-badge-history .badge-medal .material-symbols-outlined{font-size:24px;font-variation-settings:'FILL' 1}
        .df-badge-history .badge-rank{font-size:13px;font-weight:800;color:var(--ink);letter-spacing:.5px}
        .df-badge-history .badge-month{font-size:11px;color:#64748b}
        .df-badge-history .badge-note{font-size:11px;line-height:1.45;color:#334155}
        @media(prefers-reduced-motion:reduce){.df-badge-history .badge-flip{transition:none}}
    </style>
    @endonce
    <section class="df-badge-history bg-surface-container-lowest rounded-xl p-3.5 sm:p-4 shadow-2xs border border-outline-variant/30 flex flex-col gap-3" data-sponsor-badge-history aria-labelledby="sponsor-badge-history-title">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-primary text-[18px]" aria-hidden="true">workspace_premium</span>
            <h3 id="sponsor-badge-history-title" class="text-xs sm:text-sm text-on-surface font-bold">{{ __('sponsor_honors.history_title') }}</h3>
        </div>
        <p class="text-[11px] text-on-surface-variant -mt-2">{{ __('sponsor_honors.history_hint') }}</p>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5">
            @foreach($badges as $badge)
                @php($metal = ['gold', 'silver', 'bronze'][$badge['rank'] - 1])
                <button type="button" class="badge-card rank-{{ $badge['rank'] }}" x-data="{ flipped: false, hover: window.matchMedia('(hover: hover)').matches }"
                    @mouseenter="if (hover) flipped = true" @mouseleave="if (hover) flipped = false" @click="if (!hover || $event.detail === 0) flipped = !flipped"
                    :aria-pressed="flipped.toString()" aria-pressed="false" data-sponsor-badge-card="{{ $badge['rank'] }}"
                    aria-label="{{ __('sponsor_honors.badge_tooltip', ['rank' => $badge['rank'], 'month' => $badge['month']]) }}">
                    <span class="badge-flip" :class="{ 'is-flipped': flipped }">
                        <span class="badge-face" :aria-hidden="flipped.toString()">
                            <span class="badge-medal"><span class="material-symbols-outlined" aria-hidden="true">workspace_premium</span></span>
                            <span class="badge-rank">{{ __('sponsor_honors.history_rank', ['rank' => $badge['rank']]) }}</span>
                            <span class="badge-month">{{ __('sponsor_honors.history_month', ['month' => $badge['month']]) }}</span>
                        </span>
                        <span class="badge-face badge-back" aria-hidden="true" :aria-hidden="(!flipped).toString()">
                            <span class="badge-rank">{{ __('sponsor_honors.'.$metal) }}</span>
                            <span class="badge-note">{{ __('sponsor_honors.thanks_'.$metal) }}</span>
                        </span>
                    </span>
                </button>
            @endforeach
        </div>
    </section>
@endif
