{{-- Sound engine shared by the room event pages and the admin events page (preview): synthesised sound effects and
     built-in melodies (public domain tunes, Tết: an original pentatonic tune), all generated with Web Audio, no file.
     window.DfEventSound must be used from a click handler first (browser autoplay rules). --}}
@once
<script>
  window.DfEventSound = window.DfEventSound || (() => {
    // "note:beats", R = rest.
    const MELODIES = {
      birthday: { bpm: 100, voice: 'box', notes: 'G4:.75 G4:.25 A4:1 G4:1 C5:1 B4:2 G4:.75 G4:.25 A4:1 G4:1 D5:1 C5:2 G4:.75 G4:.25 G5:1 E5:1 C5:1 B4:1 A4:2 F5:.75 F5:.25 E5:1 C5:1 D5:1 C5:3 R:2' },
      new_year: { bpm: 84, voice: 'box', notes: 'G4:1 C5:1.5 C5:.5 C5:1 E5:1 D5:1.5 C5:.5 D5:1 E5:1 C5:1.5 C5:.5 E5:1 G5:1 A5:3 A5:1 G5:1.5 E5:.5 E5:1 C5:1 D5:1.5 C5:.5 D5:1 E5:1 C5:1.5 A4:.5 A4:1 G4:1 C5:3 R:2' },
      christmas: { bpm: 168, voice: 'bell', notes: 'E5:1 E5:1 E5:2 E5:1 E5:1 E5:2 E5:1 G5:1 C5:1.5 D5:.5 E5:4 F5:1 F5:1 F5:1.5 F5:.5 F5:1 E5:1 E5:1 E5:.5 E5:.5 E5:1 D5:1 D5:1 E5:1 D5:2 G5:2 E5:1 E5:1 E5:2 E5:1 E5:1 E5:2 E5:1 G5:1 C5:1.5 D5:.5 E5:4 F5:1 F5:1 F5:1.5 F5:.5 F5:1 E5:1 E5:1 E5:.5 E5:.5 G5:1 G5:1 F5:1 D5:1 C5:4 R:2' },
      lunar_new_year: { bpm: 132, voice: 'pluck', notes: 'C5:1 D5:1 E5:1 G5:1 A5:1 G5:1 E5:2 D5:1 E5:1 G5:1 A5:1 C6:2 A5:2 G5:1 A5:1 G5:1 E5:1 D5:1 C5:1 D5:2 E5:1 G5:1 E5:1 D5:1 C5:1 A4:1 C5:2 D5:1 E5:1 D5:1 C5:1 A4:1 G4:1 C5:2 R:2' },
      womens_day: { bpm: 132, voice: 'box', notes: 'E5:.5 D#5:.5 E5:.5 D#5:.5 E5:.5 B4:.5 D5:.5 C5:.5 A4:1 R:.5 C4:.5 E4:.5 A4:.5 B4:1 R:.5 E4:.5 G#4:.5 B4:.5 C5:1 R:.5 E4:.5 E5:.5 D#5:.5 E5:.5 D#5:.5 E5:.5 B4:.5 D5:.5 C5:.5 A4:1 R:.5 C4:.5 E4:.5 A4:.5 B4:1 R:.5 E4:.5 C5:.5 B4:.5 A4:2 R:2' },
      intl_womens_day: { bpm: 76, voice: 'box', notes: 'F#5:2 E5:2 D5:2 C#5:2 B4:2 A4:2 B4:2 C#5:2 D5:1 C#5:1 B4:1 A4:1 G4:1 F#4:1 G4:1 E4:1 D4:1 F#4:1 A4:1 G4:1 F#4:1 D4:1 F#4:1 E4:1 D4:2 R:2' },
      mens_day: { bpm: 120, voice: 'brass', notes: 'E4:1 E4:1 F4:1 G4:1 G4:1 F4:1 E4:1 D4:1 C4:1 C4:1 D4:1 E4:1 E4:1.5 D4:.5 D4:2 E4:1 E4:1 F4:1 G4:1 G4:1 F4:1 E4:1 D4:1 C4:1 C4:1 D4:1 E4:1 D4:1.5 C4:.5 C4:2 R:2' },
    };
    const SEMITONES = { C: 0, 'C#': 1, D: 2, 'D#': 3, E: 4, F: 5, 'F#': 6, G: 7, 'G#': 8, A: 9, 'A#': 10, B: 11 };
    const freq = (note) => {
      const match = /^([A-G]#?)(\d)$/.exec(note);
      return match ? 440 * 2 ** (((Number(match[2]) + 1) * 12 + SEMITONES[match[1]] - 69) / 12) : 0;
    };

    let ctx = null;
    let fxGain = null;
    let musicGain = null;
    let noise = null;
    let melodyTimer = null;
    const context = () => {
      if (ctx) {
        if (ctx.state === 'suspended') ctx.resume();
        return ctx;
      }
      const AudioContextClass = window.AudioContext || window.webkitAudioContext;
      if (!AudioContextClass) return null;
      ctx = new AudioContextClass();
      fxGain = ctx.createGain();
      fxGain.gain.value = 0.7;
      fxGain.connect(ctx.destination);
      musicGain = ctx.createGain();
      musicGain.gain.value = 1;
      musicGain.connect(ctx.destination);
      noise = ctx.createBuffer(1, ctx.sampleRate, ctx.sampleRate);
      const data = noise.getChannelData(0);
      for (let i = 0; i < data.length; i++) data[i] = Math.random() * 2 - 1;
      return ctx;
    };

    // Building blocks: a tone with an attack / decay envelope, and a filtered noise burst.
    const tone = (frequency, at, duration, { type = 'sine', volume = 0.2, to = null, out = fxGain, attack = 0.005 } = {}) => {
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();
      osc.type = type;
      osc.frequency.setValueAtTime(frequency, at);
      if (to) osc.frequency.exponentialRampToValueAtTime(to, at + duration);
      gain.gain.setValueAtTime(0.0001, at);
      gain.gain.exponentialRampToValueAtTime(volume, at + attack);
      gain.gain.exponentialRampToValueAtTime(0.0001, at + duration);
      osc.connect(gain).connect(out);
      osc.start(at);
      osc.stop(at + duration + 0.05);
    };
    const hiss = (at, duration, { type = 'bandpass', frequency = 1000, to = null, q = 1, volume = 0.3 } = {}) => {
      const source = ctx.createBufferSource();
      const filter = ctx.createBiquadFilter();
      const gain = ctx.createGain();
      source.buffer = noise;
      source.loop = true;
      filter.type = type;
      filter.frequency.setValueAtTime(frequency, at);
      if (to) filter.frequency.exponentialRampToValueAtTime(to, at + duration);
      filter.Q.value = q;
      gain.gain.setValueAtTime(0.0001, at);
      gain.gain.exponentialRampToValueAtTime(volume, at + 0.02);
      gain.gain.exponentialRampToValueAtTime(0.0001, at + duration);
      source.connect(filter).connect(gain).connect(fxGain);
      source.start(at);
      source.stop(at + duration + 0.05);
    };
    const arpeggio = (notes, at, step, options) => notes.forEach((note, i) => tone(freq(note), at + i * step, options.duration || 0.9, options));

    const EFFECTS = {
      pop: (t) => { hiss(t, 0.08, { type: 'highpass', frequency: 900, volume: 0.6 }); tone(320, t, 0.14, { to: 60, volume: 0.6 }); },
      fizz: (t) => hiss(t, 1.6, { frequency: 5200, q: 0.7, volume: 0.22 }),
      whoosh: (t) => hiss(t, 0.6, { frequency: 300, to: 2600, q: 1.2, volume: 0.35 }),
      paper: (t) => { for (let i = 0; i < 7; i++) hiss(t + Math.random() * 0.45, 0.04, { frequency: 2200 + Math.random() * 2000, q: 2, volume: 0.25 }); },
      chime: (t) => arpeggio(['C6', 'E6', 'G6', 'C7'], t, 0.08, { volume: 0.12, duration: 1 }),
      bloom: (t) => arpeggio(['C5', 'E5', 'G5', 'B5', 'D6', 'G6'], t, 0.07, { type: 'triangle', volume: 0.12, duration: 1.3 }),
      bells: (t) => { const scale = ['C6', 'D6', 'E6', 'G6', 'A6', 'C7']; for (let i = 0; i < 10; i++) tone(freq(scale[Math.floor(Math.random() * scale.length)]), t + i * 0.09, 0.7, { volume: 0.08 }); },
      fanfare: (t) => { arpeggio(['G4', 'C5', 'E5'], t, 0.14, { type: 'sawtooth', volume: 0.06, duration: 0.25 }); tone(freq('G5'), t + 0.42, 1.1, { type: 'sawtooth', volume: 0.07 }); tone(freq('C5'), t + 0.42, 1.1, { type: 'triangle', volume: 0.08 }); },
      coin: (t) => { [0, 0.18].forEach((d) => { tone(1400, t + d, 0.06, { type: 'square', volume: 0.05 }); tone(1900, t + d + 0.06, 0.2, { type: 'square', volume: 0.05 }); }); },
      boom: (t) => { hiss(t, 0.9, { type: 'lowpass', frequency: 500, q: 0.5, volume: 0.28 }); tone(90, t, 0.5, { to: 35, volume: 0.25 }); },
      blow: (t) => hiss(t, 0.6, { type: 'lowpass', frequency: 1200, to: 400, q: 0.6, volume: 0.3 }),
    };
    const VOICES = {
      box: (f, t, d) => { tone(f, t, Math.max(0.6, d * 1.6), { volume: 0.16, out: musicGain }); tone(f * 2, t, 0.5, { volume: 0.04, out: musicGain }); },
      bell: (f, t, d) => { tone(f, t, Math.max(0.5, d * 1.4), { volume: 0.13, out: musicGain }); tone(f * 3, t, 0.3, { volume: 0.03, out: musicGain }); },
      pluck: (f, t) => { tone(f, t, 0.55, { type: 'triangle', volume: 0.18, out: musicGain }); tone(f * 2, t, 0.25, { type: 'sine', volume: 0.05, out: musicGain }); },
      brass: (f, t, d) => tone(f * 2, t, Math.max(0.3, d * 0.95), { type: 'triangle', volume: 0.14, out: musicGain, attack: 0.04 }),
    };

    return {
      /** Create / resume the audio context (inside a click handler). */
      unlock() {
        return context() !== null;
      },
      /** Play a sound effect now or after `delay` milliseconds. */
      effect(name, delay = 0) {
        if (!context() || !EFFECTS[name]) return;
        EFFECTS[name](ctx.currentTime + delay / 1000);
      },
      /** Loop a built-in melody (notes scheduled a little ahead); replaces the melody playing. */
      startMelody(name) {
        const melody = MELODIES[name];
        if (!context() || !melody) return false;
        this.stopMelody();
        const beat = 60 / melody.bpm;
        const notes = melody.notes.split(' ').map((item) => { const [note, beats] = item.split(':'); return { note, beats: Number(beats) }; });
        const play = VOICES[melody.voice] || VOICES.box;
        let index = 0;
        let next = ctx.currentTime + 0.1;
        melodyTimer = setInterval(() => {
          while (next < ctx.currentTime + 0.6) {
            const { note, beats } = notes[index];
            if (note !== 'R') play(freq(note), next, beats * beat);
            next += beats * beat;
            index = (index + 1) % notes.length;
          }
        }, 150);
        return true;
      },
      stopMelody() {
        clearInterval(melodyTimer);
        melodyTimer = null;
      },
      /** Volumes (0 mutes): sound effects, built-in melody. */
      setVolumes(effects, music) {
        if (!ctx) return;
        fxGain.gain.setTargetAtTime(effects, ctx.currentTime, 0.05);
        musicGain.gain.setTargetAtTime(music, ctx.currentTime, 0.3);
      },
    };
  })();
</script>
@endonce
