{{-- Live sponsor draw stage, shared by the admin draw page and the members' landing page.
     Filled and kept in sync by resources/js/sponsor-draw/stage.js from the backend state + realtime events;
     the backend alone picks winners. Only display names are ever rendered (no email / phone / IDs).
     Layout follows documents/upgrade/stitch_campaign_sponsor_lucky_draw (results · wheel · snapshot). --}}
@props(['state', 'mode' => 'viewer', 'urls' => [], 'timezone' => null, 'actions' => null])
@php
    // A shop draw renames the member-oriented labels ("members" → "shops", "winner" → "chosen shop").
    $isShops = ($state['type'] ?? 'sponsors') === \App\Enums\SponsorDrawType::Shops->value;
    $stage = static fn (string $key, array $replace = []): string => __(
        $isShops && \Illuminate\Support\Facades\Lang::has('sponsor_draw.stage_shops.'.$key) ? 'sponsor_draw.stage_shops.'.$key : 'sponsor_draw.stage.'.$key,
        $replace,
    );
    $i18n = $isShops ? array_replace(__('sponsor_draw.js'), __('sponsor_draw.js_shops')) : __('sponsor_draw.js');
    $config = ['mode' => $mode, 'urls' => $urls, 'timezone' => $timezone ?: config('app.display_timezone', config('app.timezone')), 'locale' => str_replace('_', '-', app()->getLocale()), 'realtime' => rtrim((string) config('services.realtime.public_url', 'http://localhost:3001'), '/')];
@endphp
@once
<link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<style>
    .sd-stage{--sd-bg:#0b1117;--sd-panel:#121b24;--sd-panel-2:#18232e;--sd-line:#1e2d3b;--sd-text:#dde3ec;--sd-muted:#859585;--sd-soft:#bacbb9;--sd-primary:#00e676;--sd-primary-soft:#75ff9e;--sd-secondary:#4edea3;--sd-tertiary:#4affd8;
        display:grid;gap:20px;padding:20px;border-radius:16px;background:radial-gradient(circle at 50% 35%,rgba(0,230,118,.07),transparent 55%),var(--sd-bg);color:var(--sd-text);font-family:Inter,system-ui,sans-serif;color-scheme:dark}
    .sd-stage .sd-mono{font-family:'JetBrains Mono',ui-monospace,monospace}
    .sd-stage .sd-display{font-family:'Space Grotesk',Inter,sans-serif}
    .sd-top{display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;padding-bottom:14px;border-bottom:1px solid rgba(59,74,61,.45)}
    .sd-brand{display:flex;align-items:center;gap:12px;min-width:0}
    .sd-logo{flex-shrink:0;display:grid;place-items:center;width:40px;height:40px;border-radius:10px;background:#2f353c;border:1px solid rgba(117,255,158,.4);color:var(--sd-primary-soft);box-shadow:0 0 14px rgba(0,230,118,.25)}
    .sd-title{margin:0;font-size:20px;font-weight:700;letter-spacing:-.01em;line-height:1.25;word-break:break-word}
    .sd-sub{margin:2px 0 0;font-size:11.5px;color:var(--sd-muted)}
    .sd-top-meta{display:flex;flex-wrap:wrap;align-items:center;gap:8px}
    .sd-chip{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:4px 12px;font-size:11.5px;font-weight:600;background:#1a2027;border:1px solid rgba(59,74,61,.6);color:var(--sd-soft);white-space:nowrap}
    .sd-chip strong{color:var(--sd-primary-soft)}
    .sd-pill{display:inline-flex;align-items:center;gap:6px;border-radius:999px;padding:4px 12px;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;background:rgba(148,163,184,.1);border:1px solid rgba(148,163,184,.3);color:#cbd5e1;white-space:nowrap}
    .sd-pill[data-tone=live]{background:rgba(0,230,118,.12);border-color:rgba(117,255,158,.4);color:var(--sd-primary-soft)}
    .sd-pill[data-tone=warn]{background:rgba(245,158,11,.12);border-color:rgba(245,158,11,.45);color:#fcd34d}
    .sd-pill[data-tone=bad]{background:rgba(239,68,68,.12);border-color:rgba(239,68,68,.5);color:#fca5a5}
    .sd-pill[data-tone=done]{background:rgba(74,255,216,.1);border-color:rgba(74,255,216,.4);color:var(--sd-tertiary)}
    .sd-dot{width:8px;height:8px;border-radius:999px;background:currentColor;flex-shrink:0}
    .sd-pill[data-tone=live] .sd-dot{animation:sd-pulse 2.2s cubic-bezier(.4,0,.6,1) infinite}
    .sd-progress{height:4px;border-radius:999px;background:var(--sd-line);overflow:hidden;margin-top:-6px}.sd-progress span{display:block;height:100%;background:linear-gradient(90deg,var(--sd-secondary),var(--sd-primary));box-shadow:0 0 12px rgba(0,230,118,.6);transition:width .5s}
    .sd-banner{border-radius:10px;padding:10px 14px;font-size:13px;font-weight:600;border:1px solid transparent}
    .sd-banner[data-tone=warn]{background:rgba(245,158,11,.1);border-color:rgba(245,158,11,.35);color:#fcd34d}
    .sd-banner[data-tone=bad]{background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.4);color:#fca5a5}
    .sd-banner[data-tone=ok]{background:rgba(0,230,118,.08);border-color:rgba(0,230,118,.35);color:var(--sd-primary-soft)}
    .sd-bento{display:grid;grid-template-columns:minmax(0,1fr);gap:20px;align-items:stretch}
    @media (min-width:1100px){.sd-bento{grid-template-columns:minmax(0,3fr) minmax(0,6fr) minmax(0,3fr)}}
    @media (max-width:1099px){.sd-col-center{order:-1}}
    .sd-panel{display:flex;flex-direction:column;background:var(--sd-panel);border:1px solid rgba(255,255,255,.06);border-radius:16px;padding:18px;box-shadow:0 10px 30px -12px rgba(0,0,0,.6);min-width:0}
    .sd-panel-head{display:flex;align-items:center;justify-content:space-between;gap:8px;padding-bottom:12px;margin-bottom:14px;border-bottom:1px solid rgba(59,74,61,.35)}
    .sd-h{display:flex;align-items:center;gap:8px;margin:0;font-size:17px;font-weight:600}
    .sd-h .material-symbols-outlined{color:var(--sd-primary-soft);font-size:22px}
    .sd-panel-foot{display:flex;flex-wrap:wrap;justify-content:space-between;gap:6px;margin-top:auto;padding-top:12px;border-top:1px solid rgba(59,74,61,.35);font-size:11px;color:var(--sd-muted)}
    .sd-panel-foot .is-accent{color:var(--sd-secondary);font-weight:600}
    .sd-slots{list-style:none;margin:0 0 14px;padding:0;display:grid;gap:10px}
    .sd-slot{position:relative;display:flex;align-items:center;gap:12px;padding:11px 12px;border-radius:12px;background:#1a2027;border:1px solid rgba(59,74,61,.5);overflow:hidden;min-width:0}
    .sd-slot-no{display:grid;place-items:center;flex-shrink:0;width:30px;height:30px;border-radius:999px;background:#2f353c;border:1px solid rgba(59,74,61,.8);color:var(--sd-muted);font-size:11.5px;font-weight:700}
    .sd-slot-body{flex:1;min-width:0}
    .sd-slot-name{display:block;font-weight:600;font-size:14.5px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .sd-slot-meta{display:block;margin-top:1px;font-size:10.5px;color:var(--sd-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .sd-slot-icon{flex-shrink:0;color:var(--sd-secondary);font-size:20px}
    .sd-slot.is-latest{background:#252b31;border:2px solid rgba(117,255,158,.7);box-shadow:0 8px 24px -10px rgba(0,230,118,.35);padding-left:16px}
    .sd-slot.is-latest::before{content:'';position:absolute;left:0;top:0;bottom:0;width:5px;background:var(--sd-primary-soft)}
    .sd-slot.is-latest .sd-slot-no{background:var(--sd-primary-soft);border-color:transparent;color:#003918}
    .sd-slot.is-latest .sd-slot-name{color:var(--sd-primary-soft);font-weight:700}
    .sd-slot.is-latest .sd-slot-meta{color:rgba(117,255,158,.8);text-transform:uppercase;letter-spacing:.06em}
    .sd-slot.is-latest .sd-slot-icon{color:var(--sd-primary-soft)}
    .sd-slot.is-current{background:rgba(0,230,118,.05);border:1px dashed rgba(117,255,158,.5)}
    .sd-slot.is-current .sd-slot-no{color:var(--sd-primary-soft);border-color:rgba(117,255,158,.4)}
    .sd-slot.is-current .sd-slot-name{color:var(--sd-primary-soft);font-style:italic;font-weight:500}
    .sd-slot.is-pending{background:rgba(9,15,21,.4);border:1px dashed rgba(59,74,61,.5);opacity:.55}
    .sd-slot.is-pending .sd-slot-name{color:var(--sd-muted);font-style:italic;font-weight:500;font-size:13px}
    .sd-slot.is-pending .sd-slot-icon,.sd-slot.is-current .sd-slot-icon{color:var(--sd-muted);font-size:17px}
    .sd-ping{width:10px;height:10px;border-radius:999px;background:var(--sd-primary-soft);flex-shrink:0;animation:sd-ping 1s cubic-bezier(0,0,.2,1) infinite}
    .sd-wheel-card{position:relative;overflow:hidden;align-items:center;background:var(--sd-panel);border-color:rgba(59,74,61,.6)}
    .sd-wheel-card::before{content:'';position:absolute;left:50%;top:50%;width:420px;height:420px;transform:translate(-50%,-50%);border-radius:999px;background:radial-gradient(circle,rgba(16,185,129,.2),rgba(16,185,129,0) 70%);pointer-events:none}
    .sd-wheel-top{position:relative;z-index:1;width:100%;display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:8px;margin-bottom:10px}
    .sd-wheel-label{display:flex;align-items:center;gap:8px;font-size:11px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--sd-primary-soft)}
    .sd-wheel-label .sd-dot{background:var(--sd-primary-soft);animation:sd-pulse 2.2s cubic-bezier(.4,0,.6,1) infinite}
    .sd-turn{font-size:11px;font-weight:600;color:var(--sd-muted);background:#2f353c;border:1px solid rgba(59,74,61,.5);border-radius:999px;padding:4px 12px;text-transform:uppercase}
    .sd-wheel{position:relative;width:min(100%,380px);aspect-ratio:1;margin:6px auto;display:grid;place-items:center;user-select:none}
    .sd-ring-a{position:absolute;inset:0;will-change:transform;border-radius:999px;border:1px dashed rgba(117,255,158,.3);animation:sd-spin 28s linear infinite}
    .sd-ring-b{position:absolute;inset:3.2%;will-change:transform;border-radius:999px;border:2px solid rgba(59,74,61,.6);animation:sd-spin 36s linear infinite reverse}
    .sd-ring-b i{position:absolute;width:8px;height:8px;border-radius:999px;background:var(--sd-primary-soft);box-shadow:0 0 10px var(--sd-primary)}
    .sd-ring-b i:nth-child(1){top:0;left:50%;transform:translate(-50%,-50%)}.sd-ring-b i:nth-child(2){bottom:0;left:50%;transform:translate(-50%,50%)}
    .sd-ring-b i:nth-child(3){left:0;top:50%;transform:translate(-50%,-50%);background:var(--sd-tertiary);box-shadow:0 0 10px var(--sd-tertiary)}.sd-ring-b i:nth-child(4){right:0;top:50%;transform:translate(50%,-50%);background:var(--sd-tertiary);box-shadow:0 0 10px var(--sd-tertiary)}
    .sd-disc{position:absolute;inset:7.5%;will-change:transform;border-radius:999px;overflow:hidden;background:linear-gradient(45deg,#090f15,#252b31 55%,#2f353c);border:4px solid rgba(117,255,158,.45);box-shadow:0 0 35px rgba(0,230,118,.22)}
    .sd-disc svg{position:absolute;inset:0;width:100%;height:100%;opacity:.4}
    .sd-disc-name{position:absolute;max-width:34%;font-family:'JetBrains Mono',monospace;font-size:11px;font-weight:700;letter-spacing:.04em;color:rgba(186,203,185,.8);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
    .sd-disc-name[data-pos='0']{top:4%;left:50%;transform:translateX(-50%)}.sd-disc-name[data-pos='1']{bottom:4%;left:50%;transform:translateX(-50%)}
    .sd-disc-name[data-pos='2']{left:4%;top:50%;transform:translate(-40%,-50%) rotate(90deg)}.sd-disc-name[data-pos='3']{right:4%;top:50%;transform:translate(40%,-50%) rotate(-90deg)}
    .sd-pointer{position:absolute;top:-2px;left:50%;transform:translateX(-50%);z-index:3;width:20px;height:28px;background:linear-gradient(180deg,var(--sd-primary-soft),#34d399 55%,#059669);clip-path:polygon(0 0,100% 0,50% 100%);filter:drop-shadow(0 0 8px rgba(0,230,118,.9))}
    .sd-hub{position:relative;z-index:2;display:flex;flex-direction:column;align-items:center;justify-content:center;width:50%;aspect-ratio:1;padding:12px;border-radius:999px;text-align:center;background:#090f15;border:2px solid var(--sd-primary-soft);box-shadow:0 0 32px rgba(0,230,118,.35)}
    .sd-hub::before{content:'';position:absolute;inset:8px;border-radius:999px;border:1px solid rgba(117,255,158,.25);pointer-events:none}
    .sd-hub-label{display:flex;align-items:center;gap:4px;font-size:9.5px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:var(--sd-primary-soft)}
    .sd-hub-name{margin-top:6px;max-width:100%;font-family:'Space Grotesk',Inter,sans-serif;font-size:clamp(18px,3.4vw,26px);font-weight:700;line-height:1.15;letter-spacing:-.02em;color:var(--sd-primary-soft);text-shadow:0 0 14px rgba(0,230,118,.6);overflow-wrap:anywhere;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
    .sd-wheel.is-spinning .sd-hub-name{filter:blur(.6px);opacity:.85}
    .sd-hub-count{margin-top:8px;font-size:10px;color:var(--sd-muted)}.sd-hub-count strong{color:var(--sd-primary-soft)}
    .sd-wheel-foot{position:relative;z-index:1;width:100%;display:flex;flex-wrap:wrap;justify-content:space-between;gap:6px;margin-top:6px;padding-top:12px;border-top:1px solid rgba(59,74,61,.35);font-size:11px;color:var(--sd-muted)}
    .sd-wheel-foot span{display:inline-flex;align-items:center;gap:4px}.sd-wheel-foot .material-symbols-outlined{font-size:15px;color:var(--sd-secondary)}
    .sd-wheel-foot .is-accent{color:var(--sd-primary-soft);font-weight:600}
    .sd-controls{display:grid;grid-template-columns:minmax(0,1fr);gap:14px}
    @media (min-width:640px){.sd-controls.has-side{grid-template-columns:minmax(0,2fr) minmax(0,1fr)}}
    .sd-controls form{display:contents}
    .sd-btn{display:inline-flex;align-items:center;justify-content:center;gap:10px;width:100%;border:0;border-radius:12px;padding:16px 22px;cursor:pointer;font-family:'Space Grotesk',Inter,sans-serif;font-size:17px;font-weight:700;color:#00210b;background:linear-gradient(90deg,#10b981,#22c55e,#059669);box-shadow:0 14px 30px -10px rgba(16,185,129,.45);transition:filter .2s,transform .1s}
    .sd-btn:hover:not([disabled]){filter:brightness(1.1)}.sd-btn:active:not([disabled]){transform:scale(.98)}
    .sd-btn .material-symbols-outlined{font-size:24px;transition:transform .5s}.sd-btn:hover:not([disabled]) .material-symbols-outlined{transform:rotate(180deg)}
    .sd-btn[disabled]{opacity:.45;cursor:not-allowed;box-shadow:none}
    .sd-btn.is-ghost{font-size:15px;font-weight:600;color:var(--sd-text);background:#1a2027;border:1px solid rgba(59,74,61,.7);box-shadow:none}
    .sd-btn.is-ghost .material-symbols-outlined{font-size:20px;color:var(--sd-primary-soft)}.sd-btn.is-ghost:hover:not([disabled]){border-color:rgba(117,255,158,.5);background:#252b31}
    .sd-btn.is-ghost:hover:not([disabled]) .material-symbols-outlined{transform:none}
    .sd-btn.is-small{width:auto;padding:6px 12px;font-size:12px;font-family:'JetBrains Mono',monospace}
    .sd-count{font-size:11px;font-weight:700;color:var(--sd-text);background:#2f353c;border:1px solid rgba(59,74,61,.5);border-radius:6px;padding:2px 8px;white-space:nowrap}
    .sd-code{margin-top:4px;font-size:11px;color:var(--sd-muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.sd-code strong{color:var(--sd-primary-soft)}
    .sd-search{position:relative;margin-bottom:12px}
    .sd-search .material-symbols-outlined{position:absolute;left:10px;top:50%;transform:translateY(-50%);font-size:18px;color:var(--sd-muted);pointer-events:none}
    .sd-search input{width:100%;height:40px;padding:0 12px 0 36px;border-radius:8px;background:#090f15;border:1px solid rgba(59,74,61,.6);color:var(--sd-text);font-size:13px;outline:none;box-shadow:none}
    .sd-search input::placeholder{color:#64748b}.sd-search input:focus{border-color:var(--sd-primary);box-shadow:inset 0 0 8px rgba(0,230,118,.2)}
    .sd-people{list-style:none;margin:0 0 14px;padding:0 4px 0 0;display:grid;gap:8px;max-height:380px;overflow-y:auto;scrollbar-width:thin;scrollbar-color:#252b31 transparent}
    .sd-person{display:flex;align-items:center;gap:10px;padding:9px 10px;border-radius:8px;background:#1a2027;border:1px solid rgba(59,74,61,.5);min-width:0;transition:border-color .2s}
    .sd-person:hover{border-color:rgba(117,255,158,.5)}
    .sd-person-no{display:grid;place-items:center;flex-shrink:0;width:26px;height:24px;border-radius:4px;background:#2f353c;color:var(--sd-primary-soft);font-size:10px;font-weight:700}
    .sd-person-name{flex:1;min-width:0;font-size:13px;font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .sd-tag{flex-shrink:0;font-size:10px;border-radius:4px;padding:2px 8px;background:#2f353c;color:var(--sd-muted);white-space:nowrap}
    .sd-person.is-winner{opacity:.55;background:rgba(26,32,39,.4)}
    .sd-person.is-winner .sd-person-no{color:var(--sd-muted)}.sd-person.is-winner .sd-person-name{color:var(--sd-soft);text-decoration:line-through}
    .sd-person.is-picking{background:rgba(16,185,129,.1);border-color:rgba(117,255,158,.4)}
    .sd-person.is-picking .sd-person-no{background:var(--sd-primary);color:#00210b}.sd-person.is-picking .sd-person-name{color:var(--sd-primary-soft);font-weight:700}
    .sd-person.is-picking .sd-tag{background:rgba(117,255,158,.1);border:1px solid rgba(117,255,158,.3);color:var(--sd-primary-soft);font-weight:700}
    .sd-empty{margin:0;padding:8px 2px;font-size:12.5px;color:var(--sd-muted)}
    @keyframes sd-spin{to{transform:rotate(360deg)}}
    @keyframes sd-pulse{0%,100%{transform:scale(.9);opacity:.9}50%{transform:scale(1.4);opacity:.2}}
    @keyframes sd-ping{75%,100%{transform:scale(2);opacity:0}}
    @media (prefers-reduced-motion: reduce){.sd-stage *{animation:none!important;transition:none!important}}
</style>
@endonce
<div class="sd-stage" data-sponsor-draw data-config="{{ json_encode($config, JSON_UNESCAPED_SLASHES) }}"
    data-i18n="{{ json_encode($i18n, JSON_UNESCAPED_UNICODE) }}" data-state="{{ json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}">
    <header class="sd-top">
        <div class="sd-brand">
            <span class="sd-logo" aria-hidden="true"><span class="material-symbols-outlined">track_changes</span></span>
            <div class="min-w-0">
                <p class="sd-sub sd-mono">{{ $stage('eyebrow') }}</p>
                <h2 class="sd-title sd-display" data-sd-campaign>{{ $state['campaign'] }}</h2>
                <p class="sd-sub sd-mono" data-sd-target @if (! $state['target_campaign']) hidden @endif>{{ $stage('target', ['name' => $state['target_campaign']]) }}</p>
            </div>
        </div>
        <div class="sd-top-meta">
            <span class="sd-chip sd-mono">{{ $stage('progress') }} <strong><span data-sd-drawn>{{ $state['drawn_count'] }}</span> / {{ $state['target_winner_count'] }}</strong></span>
            <span class="sd-pill sd-mono" data-sd-status><span class="sd-dot"></span><span></span></span>
            <span class="sd-pill sd-mono" data-sd-connection><span class="sd-dot"></span><span>{{ $i18n['connection_connecting'] }}</span></span>
        </div>
    </header>
    <div class="sd-progress" aria-hidden="true"><span data-sd-progress style="width:0%"></span></div>

    <div class="sd-banner" data-sd-banner hidden role="status"></div>

    <div class="sd-bento">
        {{-- Results: one slot per sponsor to draw, in drawing order. --}}
        <section class="sd-panel">
            <div class="sd-panel-head">
                <h3 class="sd-h sd-display"><span class="material-symbols-outlined" aria-hidden="true">military_tech</span>{{ $stage('results') }}</h3>
            </div>
            <ol class="sd-slots" data-sd-results aria-live="polite"></ol>
            <div class="sd-panel-foot sd-mono">
                <span>{{ $stage('total_slots', ['count' => $state['target_winner_count']]) }}</span>
                <span class="is-accent" data-sd-completion></span>
            </div>
        </section>

        {{-- Wheel: decoration only; the name shown is the result the backend already stored. --}}
        <section class="sd-col-center flex flex-col gap-4 min-w-0">
            <div class="sd-panel sd-wheel-card">
                <div class="sd-wheel-top">
                    <span class="sd-wheel-label sd-mono"><span class="sd-dot"></span>{{ $stage('wheel_title') }}</span>
                    <span class="sd-turn sd-mono" data-sd-turn></span>
                </div>
                <div class="sd-wheel" data-sd-reel aria-live="polite">
                    <div class="sd-ring-a" aria-hidden="true"></div>
                    <div class="sd-ring-b" aria-hidden="true"><i></i><i></i><i></i><i></i></div>
                    <div class="sd-disc" aria-hidden="true">
                        <svg viewBox="0 0 100 100"><circle cx="50" cy="50" r="48" fill="none" stroke="#75ff9e" stroke-width=".5"/><circle cx="50" cy="50" r="36" fill="none" stroke="#4affd8" stroke-dasharray="2 3" stroke-width=".75"/><circle cx="50" cy="50" r="24" fill="none" stroke="#75ff9e" stroke-width=".5"/><line x1="50" x2="50" y1="2" y2="98" stroke="#75ff9e" stroke-width=".75"/><line x1="2" x2="98" y1="50" y2="50" stroke="#75ff9e" stroke-width=".75"/><line x1="16" x2="84" y1="16" y2="84" stroke="#75ff9e" stroke-width=".5"/><line x1="16" x2="84" y1="84" y2="16" stroke="#75ff9e" stroke-width=".5"/></svg>
                        @foreach (range(0, 3) as $position)
                            <span class="sd-disc-name" data-sd-disc-name data-pos="{{ $position }}"></span>
                        @endforeach
                    </div>
                    <div class="sd-pointer" aria-hidden="true"></div>
                    <div class="sd-hub">
                        <span class="sd-hub-label sd-mono"><span class="material-symbols-outlined text-[13px]" aria-hidden="true">stars</span><span data-sd-reel-label>{{ $stage('reel_idle') }}</span></span>
                        <div class="sd-hub-name" data-sd-reel-name>—</div>
                        <div class="sd-hub-count sd-mono">{{ $stage('drawn') }}: <strong data-sd-hub-drawn>{{ $state['drawn_count'] }}</strong> / {{ $state['target_winner_count'] }}</div>
                    </div>
                </div>
                <div class="sd-wheel-foot sd-mono">
                    <span><span class="material-symbols-outlined" aria-hidden="true">verified_user</span>{{ $stage('method_label') }}</span>
                    <span>{{ $stage('link_expires') }}: <span class="is-accent" data-sd-expires>—</span></span>
                </div>
            </div>
            @if ($mode === 'admin')
                <div @class(['sd-controls', 'has-side' => $actions !== null && $actions->isNotEmpty()])>
                    <button type="button" class="sd-btn" data-sd-draw><span class="material-symbols-outlined" aria-hidden="true">track_changes</span><span>{{ $stage('draw_next') }}</span></button>
                    {{ $actions }}
                </div>
            @endif
        </section>

        {{-- Snapshot: the frozen eligible list; winners are excluded from later turns. --}}
        <section class="sd-panel">
            <div class="sd-panel-head" style="flex-direction:column;align-items:stretch;gap:2px">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="sd-h sd-display"><span class="material-symbols-outlined" aria-hidden="true">groups</span>{{ $stage('eligible_list') }}</h3>
                    <span class="sd-count sd-mono" data-sd-eligible-count></span>
                </div>
                <p class="sd-code sd-mono" title="{{ $state['proof']['snapshot_hash'] ?? '' }}">{{ $stage('snapshot_code') }}: <strong>#{{ strtoupper(substr((string) ($state['proof']['snapshot_hash'] ?? ''), 0, 12)) }}</strong></p>
            </div>
            <label class="sd-search">
                <span class="material-symbols-outlined" aria-hidden="true">search</span>
                <input type="search" data-sd-search placeholder="{{ $stage('search_placeholder') }}" aria-label="{{ $stage('search_placeholder') }}" autocomplete="off">
            </label>
            <ol class="sd-people" data-sd-names></ol>
            <p class="sd-empty" data-sd-names-empty hidden>{{ $stage('no_match') }}</p>
            <div class="sd-panel-foot">
                <span data-sd-remaining-people></span>
                <span class="is-accent">{{ $stage('auto_exclude') }}</span>
            </div>
            <span hidden data-sd-eligible>{{ $state['eligible_count'] }}</span><span hidden data-sd-remaining>{{ $state['remaining_count'] }}</span>
        </section>
    </div>
</div>
