<x-admin.layout :title="__('occasions.admin.title')" active="events" :room="$room">
    @php
        $timezone = $today->getTimezone();
        $sendHours = ['birthday' => \App\Services\Birthday\BirthdayNotificationService::SEND_HOUR];
        $relative = static fn (int $offset): string => match (true) {
            $offset === 0 => __('occasions.admin.today'),
            $offset > 0 => trans_choice('occasions.admin.in_days', $offset, ['count' => $offset]),
            default => trans_choice('occasions.admin.days_ago', abs($offset), ['count' => abs($offset)]),
        };
        $eventName = static fn (string $type): string => $type === 'birthday' ? __('occasions.admin.birthday') : __('occasions.'.$type.'.name');
        $eventEmoji = static fn (string $type): string => $type === 'birthday' ? '🎂' : \App\Enums\RoomOccasion::from($type)->emoji();
        $showingCount = count(array_filter($events, static fn (array $event): bool => $event['active']));
        $maxWishLength = \App\Services\Occasion\RoomEventSettingsService::MAX_WISH_LENGTH;
    @endphp
    <div class="space-y-6">
        <section class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-outline-variant/40">
            <div>
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
                                $type = $event['type'];
                                $isBirthday = $type === 'birthday';
                                $sendAt = $event['date']->setTime($sendHours[$type] ?? \App\Services\Occasion\OccasionNotificationService::SEND_HOUR, 0);
                                $setting = $settings[$type];
                                $musicCustomized = $setting['music_mode'] !== \App\Enums\EventMusicMode::Builtin || ! $setting['sound_effects'];
                                $wishesCustomized = $setting['wishes'] !== null;
                            @endphp
                            {{-- Events shown on the room pages right now are tinted green. --}}
                            <tr data-room-event="{{ $type }}" @if ($event['active']) data-room-event-active @endif class="{{ $event['active'] ? 'bg-emerald-50' : '' }}">
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center gap-2 font-semibold text-on-surface">
                                        <span class="text-lg" aria-hidden="true">{{ $eventEmoji($type) }}</span>{{ $eventName($type) }}
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
                                <td class="px-5 py-3">
                                    <div class="flex items-center justify-end gap-2">
                                        {{-- Settings of the event page (modals below); a dot marks a page the admin customized. --}}
                                        @foreach ([['music', 'music_note', 'configure_music', $musicCustomized], ['wishes', 'format_quote', 'configure_wishes', $wishesCustomized]] as [$kind, $icon, $label, $customized])
                                            @php
                                                $tooltip = __('occasions.admin.'.$kind.'_tooltip', ['event' => $eventName($type)]);
                                                $tooltip = $customized ? __('occasions.admin.customized_suffix', ['label' => $tooltip]) : $tooltip;
                                            @endphp
                                            <button type="button" data-event-modal-open="{{ $kind }}-{{ $type }}" aria-haspopup="dialog" data-tooltip="{{ $tooltip }}" aria-label="{{ $tooltip }}"
                                                class="relative h-9 inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-xl border border-outline-variant bg-surface-container-lowest px-2.5 text-xs font-semibold text-on-surface hover:bg-surface-container cursor-pointer">
                                                <span class="material-symbols-outlined !text-[16px]" aria-hidden="true">{{ $icon }}</span>
                                                <span class="hidden xl:inline">{{ __('occasions.admin.'.$label) }}</span>
                                                @if ($customized)
                                                    <span class="absolute -right-1 -top-1 h-2.5 w-2.5 rounded-full bg-primary ring-2 ring-surface-container-lowest" aria-hidden="true" data-event-customized="{{ $kind }}"></span>
                                                @endif
                                            </button>
                                        @endforeach
                                        @if ($event['can_send'])
                                            @php
                                                $resend = $event['sent_at'] !== null;
                                            @endphp
                                            <form method="POST" action="{{ route('admin.events.send', $room) }}" data-confirm-message="{{ __($resend ? 'occasions.admin.resend_confirm' : 'occasions.admin.send_confirm') }}" data-confirm-button="{{ __($resend ? 'occasions.admin.resend' : 'occasions.admin.send') }}" data-loading-form="true">
                                                @csrf
                                                <input type="hidden" name="type" value="{{ $type }}">
                                                <button type="submit" data-room-event-send="{{ $type }}"
                                                    class="h-9 inline-flex items-center justify-center gap-1.5 rounded-xl px-3 text-xs font-semibold shadow-sm cursor-pointer whitespace-nowrap {{ $resend ? 'border border-outline-variant bg-surface-container-lowest text-on-surface hover:bg-surface-container' : 'bg-primary text-on-primary hover:bg-primary-container' }}">
                                                    <span class="material-symbols-outlined !text-[16px]" aria-hidden="true">{{ $resend ? 'forward_to_inbox' : 'send' }}</span>{{ __($resend ? 'occasions.admin.resend' : 'occasions.admin.send') }}
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="p-4"><x-admin.empty-state icon="celebration" :title="__('occasions.admin.empty')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    {{-- Sound modals: background music and sound effects of each event page, saved in place, with a preview. --}}
    <div data-event-settings-section
        data-i18n="{{ json_encode(['preview' => __('occasions.admin.preview'), 'stop' => __('occasions.admin.preview_stop'), 'previewError' => __('occasions.admin.preview_error'), 'needsUrl' => __('occasions.admin.preview_needs_url'), 'saveError' => __('occasions.admin.save_error'), 'saving' => __('occasions.admin.saving'), 'pageError' => __('occasions.admin.music_url_page', ['site' => '__SITE__']), 'streamingHosts' => \App\Services\Occasion\RoomEventSettingsService::STREAMING_PAGE_HOSTS], JSON_UNESCAPED_UNICODE) }}">
        @foreach ($settings as $type => $setting)
            @php
                $isCustom = $setting['music_mode'] === \App\Enums\EventMusicMode::Custom;
                $isOff = $setting['music_mode'] === \App\Enums\EventMusicMode::Off;
            @endphp
            <div class="fixed inset-0 z-[60] hidden items-center justify-center bg-slate-900/50 p-4 backdrop-blur-xs" data-event-modal="music-{{ $type }}"
                role="dialog" aria-modal="true" aria-labelledby="music-modal-{{ $type }}-title">
                <div class="w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-2xl bg-surface-container-lowest shadow-2xl">
                    <div class="flex items-start justify-between gap-3 border-b border-outline-variant px-5 py-4">
                        <div>
                            <h2 id="music-modal-{{ $type }}-title" class="flex items-center gap-2 text-base font-bold text-on-surface">
                                <span class="material-symbols-outlined !text-[20px] text-primary" aria-hidden="true">music_note</span>{{ __('occasions.admin.settings_title') }}
                            </h2>
                            <p class="mt-0.5 text-sm text-on-surface-variant">{{ $eventEmoji($type) }} {{ $eventName($type) }}</p>
                        </div>
                        <button type="button" data-event-modal-close aria-label="{{ __('occasions.admin.close') }}" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container cursor-pointer">
                            <span class="material-symbols-outlined !text-[20px]" aria-hidden="true">close</span>
                        </button>
                    </div>
                    <form method="POST" action="{{ route('admin.events.settings', $room) }}" data-event-settings="{{ $type }}" class="space-y-4 px-5 py-4">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="type" value="{{ $type }}">
                        <p class="text-xs text-outline">{{ __('occasions.admin.settings_description') }}</p>
                        <label class="block space-y-1.5">
                            <span class="text-xs font-semibold text-on-surface">{{ __('occasions.admin.music') }}</span>
                            <select name="music_mode" data-music-mode class="h-10 w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-2 text-sm text-on-surface">
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
                        {{-- The link is only asked for (and shown) with "own music link". --}}
                        <label class="block space-y-1.5" data-music-url-field @if (! $isCustom) hidden @endif>
                            <span class="text-xs font-semibold text-on-surface">{{ __('occasions.admin.music_url') }}</span>
                            <input type="url" name="music_url" value="{{ $setting['music_url'] }}" maxlength="2048" inputmode="url" placeholder="{{ __('occasions.admin.music_url_placeholder') }}"
                                @if ($isCustom) required @endif aria-describedby="music-url-hint-{{ $type }}" class="h-10 w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3 text-sm text-on-surface">
                            <span id="music-url-hint-{{ $type }}" class="block text-[11px] text-outline">{{ __('occasions.admin.music_url_hint') }}</span>
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm font-medium text-on-surface">
                            <input type="hidden" name="sound_effects" value="0">
                            <input type="checkbox" name="sound_effects" value="1" @checked($setting['sound_effects']) class="rounded border-outline-variant text-primary">
                            {{ __('occasions.admin.sound_effects') }}
                        </label>
                        <div class="flex flex-wrap items-center justify-end gap-3 border-t border-outline-variant pt-4">
                            <span class="mr-auto text-xs font-semibold" role="status" aria-live="polite" data-settings-status></span>
                            <button type="button" data-music-preview @if ($isOff) hidden @endif aria-pressed="false"
                                class="h-9 inline-flex items-center justify-center gap-1.5 rounded-xl border border-outline-variant bg-surface-container-lowest px-3 text-xs font-semibold text-on-surface hover:bg-surface-container cursor-pointer">
                                <span class="material-symbols-outlined !text-[16px]" aria-hidden="true" data-music-preview-icon>play_arrow</span><span data-music-preview-label>{{ __('occasions.admin.preview') }}</span>
                            </button>
                            <button type="submit" data-settings-save class="h-9 inline-flex items-center justify-center gap-1.5 rounded-xl bg-primary px-4 text-xs font-semibold text-on-primary hover:bg-primary-container shadow-sm cursor-pointer disabled:opacity-60">
                                <span class="material-symbols-outlined !text-[16px]" aria-hidden="true">save</span>{{ __('occasions.admin.save') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Wishes modals: the default sample wishes until the admin saves the room's own list. --}}
    <div data-event-wishes-section
        data-i18n="{{ json_encode(['custom' => __('occasions.admin.wishes_using_custom', ['count' => '__COUNT__']), 'number' => __('occasions.admin.wish_number', ['number' => '__NUMBER__']), 'saveError' => __('occasions.admin.save_error'), 'saving' => __('occasions.admin.saving')], JSON_UNESCAPED_UNICODE) }}">
        @foreach ($settings as $type => $setting)
            @php
                $custom = $setting['wishes'] !== null;
                $list = $setting['wishes'] ?? \App\Services\Occasion\RoomEventSettingsService::defaultWishes($type);
            @endphp
            <div class="fixed inset-0 z-[60] hidden items-center justify-center bg-slate-900/50 p-4 backdrop-blur-xs" data-event-modal="wishes-{{ $type }}"
                role="dialog" aria-modal="true" aria-labelledby="wishes-modal-{{ $type }}-title">
                <div class="flex w-full max-w-2xl max-h-[90vh] flex-col overflow-hidden rounded-2xl bg-surface-container-lowest shadow-2xl" data-wishes-panel="{{ $type }}">
                    <div class="flex items-start justify-between gap-3 border-b border-outline-variant px-5 py-4">
                        <div>
                            <h2 id="wishes-modal-{{ $type }}-title" class="flex items-center gap-2 text-base font-bold text-on-surface">
                                <span class="material-symbols-outlined !text-[20px] text-primary" aria-hidden="true">format_quote</span>{{ __('occasions.admin.wishes_title') }}
                            </h2>
                            <p class="mt-0.5 text-sm text-on-surface-variant">{{ $eventEmoji($type) }} {{ $eventName($type) }}</p>
                        </div>
                        <button type="button" data-event-modal-close aria-label="{{ __('occasions.admin.close') }}" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-on-surface-variant hover:bg-surface-container cursor-pointer">
                            <span class="material-symbols-outlined !text-[20px]" aria-hidden="true">close</span>
                        </button>
                    </div>
                    <div class="space-y-3 border-b border-outline-variant px-5 py-3">
                        <p class="text-xs text-outline">{{ __('occasions.admin.wishes_description', ['max' => \App\Services\Occasion\RoomEventSettingsService::MAX_WISHES, 'length' => $maxWishLength]) }}</p>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span data-wishes-mode class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $custom ? 'bg-emerald-100 text-emerald-800' : 'bg-surface-container text-on-surface-variant' }}">
                                {{ $custom ? __('occasions.admin.wishes_using_custom', ['count' => count($list)]) : __('occasions.admin.wishes_using_default') }}
                            </span>
                            {{-- Back to the sample wishes (confirmed in the admin modal, then the page reloads). --}}
                            <form method="POST" action="{{ route('admin.events.wishes', $room) }}" data-wishes-reset @unless ($custom) hidden @endunless
                                data-confirm-message="{{ __('occasions.admin.wishes_reset_confirm') }}" data-confirm-button="{{ __('occasions.admin.wishes_reset_button') }}">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="type" value="{{ $type }}">
                                <input type="hidden" name="use_default" value="1">
                                <button type="submit" class="inline-flex items-center gap-1 text-xs font-semibold text-on-surface-variant hover:text-primary cursor-pointer">
                                    <span class="material-symbols-outlined !text-[16px]" aria-hidden="true">restart_alt</span>{{ __('occasions.admin.wishes_reset_button') }}
                                </button>
                            </form>
                        </div>
                        @if ($type === 'birthday')
                            <p class="text-[11px] text-outline">{{ __('occasions.admin.wish_name_hint') }}</p>
                        @endif
                    </div>
                    <form method="POST" action="{{ route('admin.events.wishes', $room) }}" data-wishes-form="{{ $type }}" class="flex min-h-0 flex-1 flex-col">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="type" value="{{ $type }}">
                        <ol class="min-h-0 flex-1 space-y-2 overflow-y-auto px-5 py-4" data-wishes-list>
                            @foreach ($list as $wish)
                                <li class="flex items-start gap-2" data-wish-row>
                                    <span class="mt-2 w-6 shrink-0 text-right text-xs font-bold text-outline" data-wish-index>{{ $loop->iteration }}</span>
                                    <label class="block flex-1">
                                        <span class="sr-only" data-wish-label>{{ __('occasions.admin.wish_number', ['number' => $loop->iteration]) }}</span>
                                        <textarea name="wishes[]" rows="3" maxlength="{{ $maxWishLength }}" placeholder="{{ __('occasions.admin.wish_placeholder') }}"
                                            class="w-full resize-y rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 text-sm text-on-surface">{{ $wish }}</textarea>
                                    </label>
                                    <div class="flex shrink-0 flex-col gap-1 sm:flex-row">
                                        <button type="button" data-wish-up title="{{ __('occasions.admin.wish_up') }}" aria-label="{{ __('occasions.admin.wish_up') }}" class="flex h-8 w-8 items-center justify-center rounded-lg border border-outline-variant text-on-surface-variant hover:bg-surface-container cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"><span class="material-symbols-outlined !text-[18px]" aria-hidden="true">arrow_upward</span></button>
                                        <button type="button" data-wish-down title="{{ __('occasions.admin.wish_down') }}" aria-label="{{ __('occasions.admin.wish_down') }}" class="flex h-8 w-8 items-center justify-center rounded-lg border border-outline-variant text-on-surface-variant hover:bg-surface-container cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"><span class="material-symbols-outlined !text-[18px]" aria-hidden="true">arrow_downward</span></button>
                                        <button type="button" data-wish-remove title="{{ __('occasions.admin.wish_remove') }}" aria-label="{{ __('occasions.admin.wish_remove') }}" class="flex h-8 w-8 items-center justify-center rounded-lg border border-outline-variant text-red-600 hover:bg-red-50 cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"><span class="material-symbols-outlined !text-[18px]" aria-hidden="true">delete</span></button>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                        <div class="flex flex-wrap items-center justify-end gap-3 border-t border-outline-variant px-5 py-4">
                            <span class="mr-auto text-xs font-semibold" role="status" aria-live="polite" data-wishes-status></span>
                            <button type="button" data-wish-add class="h-9 inline-flex items-center gap-1.5 rounded-xl border border-dashed border-outline-variant px-3 text-xs font-semibold text-on-surface hover:bg-surface-container cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed">
                                <span class="material-symbols-outlined !text-[16px]" aria-hidden="true">add</span>{{ __('occasions.admin.wish_add') }}
                            </button>
                            <button type="submit" data-wishes-save class="h-9 inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 text-xs font-semibold text-on-primary hover:bg-primary-container shadow-sm cursor-pointer disabled:opacity-60">
                                <span class="material-symbols-outlined !text-[16px]" aria-hidden="true">save</span>{{ __('occasions.admin.wishes_save') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endforeach
    </div>

    <x-occasion.audio-engine />
    <script>
        // Submit loading of the save buttons: spinner + "Saving…", restored once the request is done.
        window.dfEventBusy = (button, busy, label) => {
            if (busy) {
                button.dataset.idleHtml = button.innerHTML;
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                button.innerHTML = '<span class="material-symbols-outlined !text-[16px] animate-spin" aria-hidden="true">progress_activity</span>';
                button.append(document.createTextNode(label || ''));
            } else {
                button.innerHTML = button.dataset.idleHtml || button.innerHTML;
                button.disabled = false;
                button.removeAttribute('aria-busy');
            }
        };

        (() => {
            // Modals: open from the buttons of the events list; close with ✕, Escape or a click outside.
            let openModal = null;
            let opener = null;
            const close = () => {
                if (!openModal) return;
                openModal.classList.add('hidden');
                openModal.classList.remove('flex');
                openModal.dispatchEvent(new CustomEvent('event-modal:close'));
                document.body.style.overflow = '';
                openModal = null;
                if (opener) opener.focus();
            };
            document.querySelectorAll('[data-event-modal-open]').forEach((button) => button.addEventListener('click', () => {
                const modal = document.querySelector(`[data-event-modal="${button.dataset.eventModalOpen}"]`);
                if (!modal) return;
                close();
                opener = button;
                openModal = modal;
                modal.classList.remove('hidden');
                modal.classList.add('flex');
                document.body.style.overflow = 'hidden';
                (modal.querySelector('select, textarea, input:not([type=hidden])') || modal.querySelector('[data-event-modal-close]')).focus();
            }));
            document.querySelectorAll('[data-event-modal]').forEach((modal) => {
                modal.addEventListener('click', (event) => { if (event.target === modal || event.target.closest('[data-event-modal-close]')) close(); });
            });
            document.addEventListener('keydown', (event) => {
                // The admin confirm dialog (above the modal) handles its own Escape.
                const confirmOpen = document.getElementById('admin-confirm-modal')?.classList.contains('flex');
                if (event.key === 'Escape' && openModal && !confirmOpen) close();
            });
        })();

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
                status.className = 'mr-auto text-xs font-semibold ' + (ok ? 'text-emerald-700' : 'text-red-600');
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

                // Closing the modal stops its preview.
                form.closest('[data-event-modal]').addEventListener('event-modal:close', () => { if (playing && playing.form === form) stopPreview(); });

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
                    window.dfEventBusy(save, true, i18n.saving);
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
                        window.dfEventBusy(save, false);
                    }
                });
            });
        })();

        (() => {
            const section = document.querySelector('[data-event-wishes-section]');
            if (!section) return;
            let i18n = {};
            try { i18n = JSON.parse(section.dataset.i18n || '{}'); } catch { i18n = {}; }
            const MAX = {{ \App\Services\Occasion\RoomEventSettingsService::MAX_WISHES }};

            section.querySelectorAll('[data-wishes-panel]').forEach((panel) => {
                const form = panel.querySelector('[data-wishes-form]');
                const list = form.querySelector('[data-wishes-list]');
                const add = form.querySelector('[data-wish-add]');
                const save = form.querySelector('[data-wishes-save]');
                const status = form.querySelector('[data-wishes-status]');
                const mode = panel.querySelector('[data-wishes-mode]');
                const reset = panel.querySelector('[data-wishes-reset]');
                const template = list.querySelector('[data-wish-row]').cloneNode(true);

                const renumber = () => {
                    const rows = [...list.querySelectorAll('[data-wish-row]')];
                    rows.forEach((row, i) => {
                        row.querySelector('[data-wish-index]').textContent = i + 1;
                        row.querySelector('[data-wish-label]').textContent = i18n.number.replace('__NUMBER__', i + 1);
                        row.querySelector('[data-wish-up]').disabled = i === 0;
                        row.querySelector('[data-wish-down]').disabled = i === rows.length - 1;
                        row.querySelector('[data-wish-remove]').disabled = rows.length === 1;
                    });
                    add.disabled = rows.length >= MAX;
                };
                const setStatus = (text, ok) => {
                    status.textContent = text;
                    status.className = 'mr-auto text-xs font-semibold ' + (ok ? 'text-emerald-700' : 'text-red-600');
                };

                list.addEventListener('click', (event) => {
                    const row = event.target.closest('[data-wish-row]');
                    if (!row) return;
                    if (event.target.closest('[data-wish-remove]')) row.remove();
                    else if (event.target.closest('[data-wish-up]') && row.previousElementSibling) row.previousElementSibling.before(row);
                    else if (event.target.closest('[data-wish-down]') && row.nextElementSibling) row.nextElementSibling.after(row);
                    else return;
                    renumber();
                    setStatus('', true);
                });
                add.addEventListener('click', () => {
                    const row = template.cloneNode(true);
                    row.querySelector('textarea').value = '';
                    list.append(row);
                    renumber();
                    row.querySelector('textarea').focus();
                });

                // Saved in place: the page is not reloaded.
                form.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    window.dfEventBusy(save, true, i18n.saving);
                    setStatus('', true);
                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            body: new FormData(form),
                            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            credentials: 'same-origin',
                        });
                        const data = await response.json().catch(() => ({}));
                        if (response.ok) {
                            setStatus('✓ ' + (data.message || ''), true);
                            mode.textContent = i18n.custom.replace('__COUNT__', (data.wishes || []).length);
                            mode.className = 'inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold bg-emerald-100 text-emerald-800';
                            reset.hidden = false;
                            // Blank wishes were dropped by the server.
                            list.querySelectorAll('textarea').forEach((area) => { if (area.value.trim() === '' && list.children.length > 1) area.closest('[data-wish-row]').remove(); });
                            renumber();
                        } else {
                            const first = data.errors ? (Object.values(data.errors)[0] || [])[0] : null;
                            setStatus(first || data.message || i18n.saveError, false);
                        }
                    } catch {
                        setStatus(i18n.saveError, false);
                    } finally {
                        window.dfEventBusy(save, false);
                    }
                });
                renumber();
            });
        })();
    </script>
</x-admin.layout>
