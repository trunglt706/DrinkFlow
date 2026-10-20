<x-admin.layout :title="__('occasions.admin.title')" active="events" :room="$room">
    @php
        $timezone = $today->getTimezone();
        $sendHours = ['birthday' => \App\Services\Birthday\BirthdayNotificationService::SEND_HOUR];
        $relative = static fn (int $offset): string => match (true) {
            $offset === 0 => __('occasions.admin.today'),
            $offset > 0 => trans_choice('occasions.admin.in_days', $offset, ['count' => $offset]),
            default => trans_choice('occasions.admin.days_ago', abs($offset), ['count' => abs($offset)]),
        };
    @endphp
    <div class="space-y-6">
        <section class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-outline-variant/40">
            <div>
                @php
                    $showingCount = count(array_filter($events, static fn (array $event): bool => $event['active']));
                @endphp
                <h1 class="flex items-center gap-2 text-2xl font-bold tracking-tight text-on-surface">
                    {{ __('occasions.admin.title') }}
                    <span data-room-events-showing="{{ $showingCount }}" title="{{ trans_choice('occasions.admin.showing_count', $showingCount, ['count' => $showingCount]) }}"
                        class="inline-flex min-w-[24px] items-center justify-center rounded-full px-2 py-0.5 text-xs font-bold {{ $showingCount > 0 ? 'bg-emerald-600 text-white' : 'bg-surface-container text-on-surface-variant' }}">
                        <span class="sr-only">{{ trans_choice('occasions.admin.showing_count', $showingCount, ['count' => $showingCount]) }}</span><span aria-hidden="true">{{ $showingCount }}</span>
                    </span>
                </h1>
                <p class="mt-1 max-w-3xl text-xs text-outline">{{ __('occasions.admin.description') }}</p>
            </div>
        </section>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-error/30 bg-error-container p-3 text-sm text-on-error-container" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="rounded-xl border border-outline-variant bg-surface-container-lowest overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-surface-container-low text-[11px] uppercase tracking-wider text-outline">
                        <tr>
                            <th class="px-5 py-3 text-left">{{ __('occasions.admin.col_event') }}</th>
                            <th class="px-5 py-3 text-left">{{ __('occasions.admin.col_date') }}</th>
                            <th class="px-5 py-3 text-left">{{ __('occasions.admin.col_status') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/60">
                        @forelse ($events as $event)
                            @php
                                $isBirthday = $event['type'] === 'birthday';
                                $sendAt = $event['date']->setTime($sendHours[$event['type']] ?? \App\Services\Occasion\OccasionNotificationService::SEND_HOUR, 0);
                            @endphp
                            {{-- Events shown on the room pages right now are tinted green. --}}
                            <tr data-room-event="{{ $event['type'] }}" @if ($event['active']) data-room-event-active @endif class="{{ $event['active'] ? 'bg-emerald-50' : '' }}">
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center gap-2 font-semibold text-on-surface">
                                        <span class="text-lg" aria-hidden="true">{{ $isBirthday ? '🎂' : $event['occasion']->emoji() }}</span>
                                        {{ $isBirthday ? __('occasions.admin.birthday') : __('occasions.'.$event['type'].'.name') }}
                                    </span>
                                    @if ($isBirthday)
                                        <small class="mt-0.5 block text-xs text-outline">{{ implode(', ', $event['names']) }}</small>
                                    @endif
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap">
                                    <span class="font-mono text-on-surface">{{ $event['date']->format('d/m/Y') }}</span>
                                    <small class="block text-xs text-outline">{{ $relative($event['offset']) }}</small>
                                </td>
                                <td class="px-5 py-3" data-room-event-status="{{ $event['sent_at'] !== null ? 'sent' : 'not-sent' }}">
                                    @if ($event['sent_at'] !== null)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                                            <span class="material-symbols-outlined !text-[14px]" aria-hidden="true">check_circle</span>
                                            {{ __('occasions.admin.status_sent', ['time' => \Illuminate\Support\Carbon::parse($event['sent_at'])->timezone($timezone)->format('H:i d/m')]) }}
                                        </span>
                                        <small class="mt-0.5 block text-xs text-outline">{{ trans_choice('occasions.admin.channels', $event['channel_count'], ['count' => $event['channel_count']]) }}</small>
                                    @elseif ($event['can_send'])
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">
                                            <span class="material-symbols-outlined !text-[14px]" aria-hidden="true">schedule_send</span>{{ __('occasions.admin.status_not_sent') }}
                                        </span>
                                    @elseif ($event['offset'] >= 0)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-surface-container px-2.5 py-0.5 text-xs font-semibold text-on-surface-variant">
                                            <span class="material-symbols-outlined !text-[14px]" aria-hidden="true">event_upcoming</span>{{ __('occasions.admin.status_scheduled', ['time' => $sendAt->format('H:i d/m')]) }}
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-semibold text-red-700">
                                            <span class="material-symbols-outlined !text-[14px]" aria-hidden="true">event_busy</span>{{ __('occasions.admin.status_missed') }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    @if ($event['can_send'])
                                        @php
                                            $resend = $event['sent_at'] !== null;
                                        @endphp
                                        <form method="POST" action="{{ route('admin.events.send', $room) }}" data-confirm-message="{{ __($resend ? 'occasions.admin.resend_confirm' : 'occasions.admin.send_confirm') }}" data-confirm-button="{{ __($resend ? 'occasions.admin.resend' : 'occasions.admin.send') }}" data-loading-form="true">
                                            @csrf
                                            <input type="hidden" name="type" value="{{ $event['type'] }}">
                                            <button type="submit" data-room-event-send="{{ $event['type'] }}"
                                                class="h-9 inline-flex items-center justify-center gap-1.5 rounded-xl px-3 text-xs font-semibold shadow-sm cursor-pointer {{ $resend ? 'border border-outline-variant bg-surface-container-lowest text-on-surface hover:bg-surface-container' : 'bg-primary text-on-primary hover:bg-primary-container' }}">
                                                <span class="material-symbols-outlined !text-[16px]" aria-hidden="true">{{ $resend ? 'forward_to_inbox' : 'send' }}</span>{{ __($resend ? 'occasions.admin.resend' : 'occasions.admin.send') }}
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-4"><x-admin.empty-state icon="celebration" :title="__('occasions.admin.empty')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Background music and sound effects of each event page: saved in place, with a preview of the music. --}}
        <section id="event-settings" class="rounded-xl border border-outline-variant bg-surface-container-lowest overflow-hidden" data-event-settings-section
            data-i18n="{{ json_encode(['preview' => __('occasions.admin.preview'), 'stop' => __('occasions.admin.preview_stop'), 'previewError' => __('occasions.admin.preview_error'), 'needsUrl' => __('occasions.admin.preview_needs_url'), 'saveError' => __('occasions.admin.save_error'), 'pageError' => __('occasions.admin.music_url_page', ['site' => '__SITE__']), 'streamingHosts' => \App\Services\Occasion\RoomEventSettingsService::STREAMING_PAGE_HOSTS], JSON_UNESCAPED_UNICODE) }}">
            <div class="px-5 py-4 border-b border-outline-variant">
                <h2 class="flex items-center gap-2 text-sm font-bold text-on-surface"><span class="material-symbols-outlined !text-[18px] text-primary" aria-hidden="true">music_note</span>{{ __('occasions.admin.settings_title') }}</h2>
                <p class="mt-1 max-w-3xl text-xs text-outline">{{ __('occasions.admin.settings_description') }}</p>
            </div>
            <div class="divide-y divide-outline-variant/60">
                @foreach ($settings as $type => $setting)
                    @php
                        $isCustom = $setting['music_mode'] === \App\Enums\EventMusicMode::Custom;
                        $isOff = $setting['music_mode'] === \App\Enums\EventMusicMode::Off;
                    @endphp
                    <form method="POST" action="{{ route('admin.events.settings', $room) }}" data-event-settings="{{ $type }}"
                        class="grid grid-cols-1 lg:grid-cols-[minmax(0,13rem)_minmax(0,17rem)_minmax(0,1fr)] items-start gap-3 px-5 py-3">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="type" value="{{ $type }}">
                        <span class="inline-flex h-9 items-center gap-2 text-sm font-semibold text-on-surface">
                            <span class="text-lg" aria-hidden="true">{{ $type === 'birthday' ? '🎂' : \App\Enums\RoomOccasion::from($type)->emoji() }}</span>
                            {{ $type === 'birthday' ? __('occasions.admin.birthday') : __('occasions.'.$type.'.name') }}
                        </span>
                        <label class="block">
                            <span class="sr-only">{{ __('occasions.admin.music') }}</span>
                            <select name="music_mode" data-music-mode class="h-9 w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-2 text-xs text-on-surface">
                                @foreach (\App\Enums\EventMusicMode::cases() as $mode)
                                    <option value="{{ $mode->value }}" @selected($setting['music_mode'] === $mode)>
                                        {{ match ($mode) {
                                            \App\Enums\EventMusicMode::Builtin => __('occasions.admin.music_builtin', ['melody' => __('occasions.melodies.'.$type)]),
                                            \App\Enums\EventMusicMode::Custom => __('occasions.admin.music_custom'),
                                            \App\Enums\EventMusicMode::Off => __('occasions.admin.music_off'),
                                        } }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <div class="flex flex-col gap-2">
                            {{-- The link is only asked for (and shown) with "own music link". --}}
                            <label class="block" data-music-url-field @if (! $isCustom) hidden @endif>
                                <span class="sr-only">{{ __('occasions.admin.music_url') }}</span>
                                <input type="url" name="music_url" value="{{ $setting['music_url'] }}" maxlength="2048" inputmode="url" placeholder="{{ __('occasions.admin.music_url_placeholder') }}"
                                    @if ($isCustom) required @endif aria-describedby="music-url-hint-{{ $type }}" class="h-9 w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-2 text-xs text-on-surface">
                                <span id="music-url-hint-{{ $type }}" class="mt-1 block text-[11px] text-outline">{{ __('occasions.admin.music_url_hint') }}</span>
                            </label>
                            <div class="flex flex-wrap items-center gap-3">
                                <button type="button" data-music-preview @if ($isOff) hidden @endif aria-pressed="false"
                                    class="h-9 inline-flex items-center justify-center gap-1.5 rounded-xl border border-outline-variant bg-surface-container-lowest px-3 text-xs font-semibold text-on-surface hover:bg-surface-container cursor-pointer">
                                    <span class="material-symbols-outlined !text-[16px]" aria-hidden="true" data-music-preview-icon>play_arrow</span><span data-music-preview-label>{{ __('occasions.admin.preview') }}</span>
                                </button>
                                <label class="inline-flex items-center gap-2 text-xs font-medium text-on-surface-variant whitespace-nowrap">
                                    <input type="hidden" name="sound_effects" value="0">
                                    <input type="checkbox" name="sound_effects" value="1" @checked($setting['sound_effects']) class="rounded border-outline-variant text-primary">
                                    {{ __('occasions.admin.sound_effects') }}
                                </label>
                                <button type="submit" data-settings-save class="h-9 inline-flex items-center justify-center gap-1.5 rounded-xl bg-primary px-3 text-xs font-semibold text-on-primary hover:bg-primary-container shadow-sm cursor-pointer disabled:opacity-60">
                                    <span class="material-symbols-outlined !text-[16px]" aria-hidden="true">save</span>{{ __('occasions.admin.save') }}
                                </button>
                                <span class="text-xs font-semibold" role="status" aria-live="polite" data-settings-status></span>
                            </div>
                        </div>
                    </form>
                @endforeach
            </div>
        </section>
    </div>

    <x-occasion.audio-engine />
    <script>
        (() => {
            const section = document.querySelector('[data-event-settings-section]');
            if (!section) return;
            let i18n = {};
            try { i18n = JSON.parse(section.dataset.i18n || '{}'); } catch { i18n = {}; }
            const engine = window.DfEventSound;
            let playing = null; // { form, audio, timer }

            const setPreviewButton = (form, on) => {
                const button = form.querySelector('[data-music-preview]');
                button.setAttribute('aria-pressed', on ? 'true' : 'false');
                button.querySelector('[data-music-preview-icon]').textContent = on ? 'stop' : 'play_arrow';
                button.querySelector('[data-music-preview-label]').textContent = on ? i18n.stop : i18n.preview;
            };
            const setStatus = (form, text, ok) => {
                const status = form.querySelector('[data-settings-status]');
                status.textContent = text;
                status.className = 'text-xs font-semibold ' + (ok ? 'text-emerald-700' : 'text-red-600');
            };
            const stopPreview = () => {
                if (!playing) return;
                engine.stopMelody();
                if (playing.audio) playing.audio.pause();
                clearTimeout(playing.timer);
                setPreviewButton(playing.form, false);
                playing = null;
            };

            section.querySelectorAll('[data-event-settings]').forEach((form) => {
                const type = form.dataset.eventSettings;
                const mode = form.querySelector('[data-music-mode]');
                const urlField = form.querySelector('[data-music-url-field]');
                const urlInput = urlField.querySelector('input');
                const preview = form.querySelector('[data-music-preview]');
                const save = form.querySelector('[data-settings-save]');

                // Only "own music link" shows (and requires) the link; "no music" has nothing to preview.
                mode.addEventListener('change', () => {
                    const custom = mode.value === 'custom';
                    urlField.hidden = !custom;
                    urlInput.required = custom;
                    preview.hidden = mode.value === 'off';
                    if (playing && playing.form === form) stopPreview();
                    setStatus(form, '', true);
                });

                preview.addEventListener('click', () => {
                    if (playing && playing.form === form) { stopPreview(); return; }
                    stopPreview();
                    engine.unlock();
                    engine.setVolumes(0.7, 1);
                    let audio = null;
                    if (mode.value === 'custom') {
                        if (!urlInput.value || !urlInput.checkValidity()) { setStatus(form, i18n.needsUrl, false); urlInput.focus(); return; }
                        // A streaming site page (Zing MP3, YouTube...) is a web player, not an audio file.
                        const host = new URL(urlInput.value).hostname.toLowerCase();
                        const site = (i18n.streamingHosts || []).find((item) => host === item || host.endsWith('.' + item));
                        if (site) { setStatus(form, i18n.pageError.replace('__SITE__', site), false); urlInput.focus(); return; }
                        audio = new Audio(urlInput.value);
                        audio.volume = 0.6;
                        audio.addEventListener('error', () => { setStatus(form, i18n.previewError, false); stopPreview(); }, { once: true });
                        audio.play().catch(() => { setStatus(form, i18n.previewError, false); stopPreview(); });
                    } else {
                        engine.startMelody(type);
                    }
                    // A preview stops by itself after 20 seconds.
                    playing = { form, audio, timer: setTimeout(stopPreview, 20000) };
                    setPreviewButton(form, true);
                    setStatus(form, '', true);
                });

                // Saved in place: the page is not reloaded.
                form.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    save.disabled = true;
                    setStatus(form, '', true);
                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            body: new FormData(form),
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        });
                        const data = await response.json().catch(() => ({}));
                        if (response.ok) {
                            setStatus(form, '✓ ' + (data.message || ''), true);
                        } else {
                            const first = data.errors ? (Object.values(data.errors)[0] || [])[0] : null;
                            setStatus(form, first || data.message || i18n.saveError, false);
                        }
                    } catch {
                        setStatus(form, i18n.saveError, false);
                    } finally {
                        save.disabled = false;
                    }
                });
            });
        })();
    </script>
</x-admin.layout>
