@props(['honors', 'recalculateUrl' => null])

@once
<style>
    .df-honors{color:#eef0f6;background:radial-gradient(ellipse at 50% 0,#263047,#0c1220 75%);border:1px solid #334155;border-radius:20px;padding:28px 18px;text-align:center;overflow:hidden}
    .df-honors *{box-sizing:border-box}.df-honors .honors-eyebrow{color:#e3c48a;font-size:10px;letter-spacing:2px;text-transform:uppercase}.df-honors h2{font-size:24px;font-weight:700;margin:8px 0 2px;text-transform:uppercase}.df-honors .honors-sub{color:#acb7c9;font-size:12px;line-height:1.6;margin:0}
    .df-honors .honors-podium{display:flex;align-items:center;justify-content:center;gap:24px;margin:36px auto 12px;flex-wrap:wrap}.df-honors .honors-sponsor{width:190px;min-width:0}
    .df-honors .gold{order:2;--light:#fff4bc;--main:#e8bd54;--dark:#936313;--ink:#49310b;transform:translateY(-10px)}.df-honors .silver{order:1;--light:#f8fcff;--main:#b8c6d1;--dark:#657586;--ink:#293744}.df-honors .bronze{order:3;--light:#ffe0bb;--main:#cc905e;--dark:#764626;--ink:#4d2b15}
    .df-honors .honors-button{background:none;border:0;padding:0;display:block;margin:auto;perspective:1100px;border-radius:50%;color:var(--ink);cursor:pointer}.df-honors button:focus-visible{outline:3px solid #fff;outline-offset:5px}
    .df-honors .honors-coin{display:block;width:156px;height:156px;position:relative;transform-style:preserve-3d;transition:transform .75s;transform:rotateY(0deg)}.df-honors .gold .honors-coin{width:178px;height:178px}.df-honors .honors-coin.is-flipped{transform:rotateY(180deg)}
    .df-honors .honors-face{position:absolute;inset:0;border-radius:50%;backface-visibility:hidden;background:conic-gradient(from -35deg,var(--light),var(--main),var(--dark),var(--main),var(--light),var(--main),var(--dark),var(--light));padding:11px;box-shadow:0 12px 24px #0006,inset 0 0 0 3px var(--main),inset 0 0 0 5px #ffffff55;display:flex;overflow:hidden}.df-honors .honors-face::before{content:'';position:absolute;inset:7px;border:1px dashed var(--ink);opacity:.4;border-radius:50%}
    .df-honors .honors-inner{position:relative;width:100%;height:100%;border-radius:50%;border:1px solid #0003;background:radial-gradient(circle at 35% 25%,var(--light),var(--main));display:flex;flex-direction:column;justify-content:center;align-items:center;padding:12px}.df-honors .honors-back{transform:rotateY(180deg)}
    .df-honors .honors-stars{font-size:10px;letter-spacing:3px;opacity:.7}.df-honors .honors-coffee{font-size:32px;line-height:1.3;margin:4px 0}.df-honors .honors-rank{font-size:11px;font-weight:800;letter-spacing:1px;text-transform:uppercase}.df-honors .honors-brand{font-size:9px;font-weight:700;letter-spacing:1px;margin-top:6px}.df-honors .honors-edition{font-size:10px;margin-top:3px}.df-honors .honors-message{font-size:10px;line-height:1.5}
    .df-honors h3{font-size:14px;font-weight:700;margin:18px 0 5px;overflow-wrap:anywhere;text-transform:uppercase}
    .df-honors .honors-empty{padding:28px 0 8px;color:#acb7c9;font-size:13px}
    .df-honors .honors-recalculate{display:inline-flex;align-items:center;gap:6px;margin-top:14px;padding:8px 16px;border:1px solid #e3c48a;border-radius:999px;background:#e3c48a1a;color:#e3c48a;font-size:12px;font-weight:600;cursor:pointer;transition:background .2s}.df-honors .honors-recalculate:hover{background:#e3c48a33}.df-honors .honors-recalculate .material-symbols-outlined{font-size:16px}
    .df-honors .honors-flash{margin:14px auto 0;max-width:420px;font-size:12px;line-height:1.5}.df-honors .honors-flash.is-success{color:#86efac}.df-honors .honors-flash.is-error{color:#fca5a5}
    @media(max-width:640px){.df-honors .honors-podium{gap:28px;flex-direction:column}.df-honors .gold{order:0;transform:none}.df-honors .silver{order:1}.df-honors .bronze{order:2}.df-honors h2{font-size:22px}}
    @media(prefers-reduced-motion:reduce){.df-honors .honors-coin{transition:none}}
</style>
@endonce

<section class="df-honors" data-sponsor-honors data-month="{{ $honors['month'] }}" aria-label="{{ __('sponsor_honors.title') }}">
    <p class="honors-eyebrow">{{ __('sponsor_honors.eyebrow') }}</p>
    <h2>{{ __('sponsor_honors.title') }}</h2>
    <p class="honors-sub">{{ __('sponsor_honors.subtitle', ['month' => $honors['month']]) }}</p>
    @if(count($honors['sponsors']) > 0)
        <div class="honors-podium">
            @foreach($honors['sponsors'] as $index => $sponsor)
                @php($metal = ['gold', 'silver', 'bronze'][$index])
                <article class="honors-sponsor {{ $metal }}" data-sponsor-rank="{{ $index + 1 }}"
                    x-data="{ flipped: false }">
                    <button type="button" class="honors-button" @click="flipped = !flipped" :aria-pressed="flipped.toString()" aria-pressed="false"
                        aria-label="{{ __('sponsor_honors.flip', ['name' => $sponsor['name']]) }}">
                        <span class="honors-coin" :class="{ 'is-flipped': flipped }">
                            <span class="honors-face" :aria-hidden="flipped.toString()">
                                <span class="honors-inner">
                                    <span class="honors-stars" aria-hidden="true">✦ ✦ ✦</span>
                                    <span class="material-symbols-outlined honors-coffee" aria-hidden="true">local_cafe</span>
                                    <span class="honors-rank">{{ __('sponsor_honors.'.$metal) }}</span>
                                    <span class="honors-brand">DrinkFlow</span>
                                    <span class="honors-edition">{{ $honors['month'] }}</span>
                                </span>
                            </span>
                            <span class="honors-face honors-back" aria-hidden="true" :aria-hidden="(!flipped).toString()">
                                <span class="honors-inner">
                                    <span class="honors-message">{{ __('sponsor_honors.thanks_'.$metal) }}</span>
                                    <span class="honors-brand">DrinkFlow</span>
                                </span>
                            </span>
                        </span>
                    </button>
                    <h3>{{ $sponsor['name'] }}</h3>
                </article>
            @endforeach
        </div>
    @else
        <p class="honors-empty">{{ __('sponsor_honors.empty') }}</p>
        @if($recalculateUrl)
            <form method="POST" action="{{ $recalculateUrl }}">
                @csrf
                <button type="submit" class="honors-recalculate" data-sponsor-honors-recalculate>
                    <span class="material-symbols-outlined" aria-hidden="true">refresh</span>
                    <span>{{ __('sponsor_honors.recalculate') }}</span>
                </button>
            </form>
        @endif
    @endif
    @if($recalculateUrl && session('sponsor_honors_status'))
        <p class="honors-flash is-success" role="status">{{ session('sponsor_honors_status') }}</p>
    @endif
    @if($recalculateUrl && session('sponsor_honors_error'))
        <p class="honors-flash is-error" role="alert">{{ session('sponsor_honors_error') }}</p>
    @endif
</section>
