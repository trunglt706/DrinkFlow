{{-- Sound of a room event page: synthesised sound effects (Web Audio, no files) and background music, either the
     built-in melody of the event (public domain tunes, synthesised) or an https audio link set by the room admin.
     Nothing plays before a click (browser autoplay rules): the page calls window.EventAudio.start() from its click
     handler, then EventAudio.play('<effect>'). A floating button mutes / unmutes; the choice is kept per viewer. --}}
@props(['audio', 'melody'])
@php
    /** @var array{music_mode: \App\Enums\EventMusicMode, music_url: string|null, sound_effects: bool} $audio */
    $mode = $audio['music_mode']->value;
    $config = [
        'music' => $mode,
        'url' => $mode === \App\Enums\EventMusicMode::Custom->value ? $audio['music_url'] : null,
        'effects' => (bool) $audio['sound_effects'],
        'melody' => $melody,
        'labels' => ['on' => __('occasions.ui.sound_on'), 'off' => __('occasions.ui.sound_off')],
    ];
    $silent = $mode === \App\Enums\EventMusicMode::Off->value && ! $audio['sound_effects'];
@endphp
@unless ($silent)
<style>
  .ev-sound{position:fixed;top:16px;right:16px;z-index:25;width:42px;height:42px;display:grid;place-items:center;border-radius:50%;border:1px solid rgba(255,255,255,.28);
    background:rgba(0,0,0,.3);color:#fff;font-size:18px;cursor:pointer;transition:transform .2s,background .2s}
  .ev-sound:hover{transform:scale(1.08);background:rgba(255,255,255,.18)}
  .ev-sound:focus-visible{outline:2px solid #fff;outline-offset:3px}
  .ev-sound.is-playing::after{content:'';position:absolute;inset:-4px;border-radius:50%;border:2px solid rgba(255,255,255,.45);animation:ev-sound-ring 1.6s ease-out infinite}
  @keyframes ev-sound-ring{from{transform:scale(.9);opacity:1}to{transform:scale(1.35);opacity:0}}
  @media (prefers-reduced-motion:reduce){.ev-sound.is-playing::after{animation:none}}
</style>
<button type="button" class="ev-sound" data-event-sound data-config="{{ json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}"
  aria-pressed="false" aria-label="{{ __('occasions.ui.sound_off') }}"><span aria-hidden="true">🔊</span></button>
<x-occasion.audio-engine />
<script>
  (() => {
    const button = document.querySelector('[data-event-sound]');
    const engine = window.DfEventSound;
    let config = {};
    try { config = JSON.parse(button.dataset.config || '{}'); } catch { config = {}; }
    const STORAGE_KEY = 'df-event-muted';
    let muted = false;
    try { muted = localStorage.getItem(STORAGE_KEY) === '1'; } catch { muted = false; }
    let started = false;
    let player = null;

    const applyVolume = () => {
      engine.setVolumes(muted ? 0 : 0.7, muted ? 0 : 1);
      if (player) player.muted = muted;
    };
    const startMusic = () => {
      if (config.music === 'custom' && config.url) {
        player = new Audio(config.url);
        player.loop = true;
        player.volume = 0.55;
        player.muted = muted;
        // A link that cannot play falls back to the built-in melody.
        const fallback = () => { if (player) { player = null; engine.startMelody(config.melody); } };
        player.addEventListener('error', fallback, { once: true });
        player.play().catch(fallback);
      } else if (config.music === 'builtin') {
        engine.startMelody(config.melody);
      }
    };
    const render = () => {
      button.setAttribute('aria-pressed', muted ? 'true' : 'false');
      button.setAttribute('aria-label', muted ? config.labels.on : config.labels.off);
      button.firstElementChild.textContent = muted ? '🔇' : '🔊';
      button.classList.toggle('is-playing', started && !muted && config.music !== 'off');
    };
    button.addEventListener('click', () => {
      muted = !muted;
      try { localStorage.setItem(STORAGE_KEY, muted ? '1' : '0'); } catch { /* per-viewer convenience only */ }
      if (!muted && !started) window.EventAudio.start();
      applyVolume();
      render();
    });
    render();

    window.EventAudio = {
      /** Start the audio (call from a click handler); background music begins once. */
      start() {
        if (started) return;
        started = true;
        engine.unlock();
        applyVolume();
        startMusic();
        render();
      },
      /** Play a sound effect now or after `delay` milliseconds. */
      play(name, delay = 0) {
        if (config.effects && started && !muted) engine.effect(name, delay);
      },
    };
  })();
</script>
@endunless
