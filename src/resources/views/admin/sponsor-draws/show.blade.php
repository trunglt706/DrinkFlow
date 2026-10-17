<x-admin.layout :title="__('sponsor_draw.admin.session_title', ['id' => $session->id])" active="sponsor-draws" :room="$room">
    @php
        $status = $session->status;
        $isDraft = $status === \App\Enums\SponsorDrawStatus::Draft;
        $canReset = in_array($status, [\App\Enums\SponsorDrawStatus::Drawing, \App\Enums\SponsorDrawStatus::Completed], true);
        // The settings dialog opens by itself right after "New session" and when its validation failed.
        $openSettings = $canConfigure && (session('open_settings') || $errors->hasAny(['type', 'target_winner_count', 'target_campaign_id', 'shops', 'shops.*', 'new_shops']));
    @endphp
    <div class="space-y-5" x-data="{ settingsOpen: @js($openSettings), type: @js(old('type', $session->type->value)) }">
        <section class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-outline-variant/40">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.sponsor-draws.index', $room) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-outline-variant text-on-surface-variant hover:bg-surface-container"
                    aria-label="{{ __('sponsor_draw.admin.back') }}" title="{{ __('sponsor_draw.admin.back') }}"><span class="material-symbols-outlined text-[18px]">arrow_back</span></a>
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-on-surface flex items-center gap-2">
                        {{ __('sponsor_draw.admin.session_title', ['id' => $session->id]) }}
                        <span class="rounded-full bg-surface-container px-2 py-0.5 text-[11px] font-semibold text-on-surface-variant">{{ __('sponsor_draw.types.'.$session->type->value) }}</span>
                    </h1>
                    <p class="text-xs text-outline">{{ __('sponsor_draw.admin.created_by', ['name' => $session->createdBy?->name ?? '—', 'time' => $session->localTime($session->created_at, 'd/m/Y H:i')]) }}</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if ($canConfigure)
                    <button type="button" @click="settingsOpen = true" data-sd-settings-open aria-haspopup="dialog"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-primary px-3 py-2 text-xs font-semibold text-on-primary hover:bg-primary-container cursor-pointer">
                        <span class="material-symbols-outlined !text-[16px]" aria-hidden="true">tune</span>{{ __('sponsor_draw.admin.settings') }}
                    </button>
                @endif
                @if ($linkUrl)
                    {{-- Copies the access link; the shared [data-copy] helper shows the toast. --}}
                    <button type="button" data-copy="{{ $linkUrl }}" data-copied-message="{{ __('admin.copied') }}" data-copy-failed-message="{{ __('admin.copy_failed') }}" data-sd-copy-link
                        title="{{ __('sponsor_draw.admin.link_until', ['time' => $session->localTime($session->link_expires_at)]) }}"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-outline-variant px-3 py-2 text-xs font-semibold text-on-surface hover:bg-surface-container cursor-pointer">
                        <span class="material-symbols-outlined !text-[16px]" aria-hidden="true">content_copy</span>{{ __('sponsor_draw.admin.copy_link') }}
                    </button>
                @endif
                @if (! $isDraft && $status !== \App\Enums\SponsorDrawStatus::Cancelled)
                    {{-- A new link replaces the old one (also the way back after the link expired). --}}
                    <form method="POST" action="{{ route('admin.sponsor-draws.link', [$room, $session]) }}" data-confirm-message="{{ __('sponsor_draw.admin.new_link_confirm') }}">
                        @csrf
                        <button type="submit" data-sd-new-link title="{{ $linkUrl ? __('sponsor_draw.admin.new_link') : __('sponsor_draw.admin.link_expired_hint') }}"
                            class="inline-flex items-center gap-1.5 rounded-lg border {{ $linkUrl ? 'border-outline-variant text-on-surface' : 'border-error/40 text-error' }} px-3 py-2 text-xs font-semibold hover:bg-surface-container cursor-pointer">
                            <span class="material-symbols-outlined !text-[16px]" aria-hidden="true">link</span>{{ __('sponsor_draw.admin.new_link') }}
                        </button>
                    </form>
                @endif
                @if ($status->canCancel())
                    <button type="button" data-sd-cancel-open aria-haspopup="dialog"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-error/40 px-3 py-2 text-xs font-semibold text-error hover:bg-error-container/60 cursor-pointer">
                        <span class="material-symbols-outlined text-[16px]">cancel</span>{{ __('sponsor_draw.admin.cancel_button') }}
                    </button>
                @endif
            </div>
        </section>

        @php
            $flashToast = session('status')
                ? ['message' => session('status'), 'type' => 'success']
                : ($errors->any() ? ['message' => $errors->first(), 'type' => 'error'] : null);
        @endphp
        @if ($flashToast)
            {{-- Flash messages are shown as toasts; `load` runs after the admin bundle has defined window.notify. --}}
            <script>window.addEventListener('load', () => window.notify?.(@js($flashToast['message']), @js($flashToast['type'])));</script>
        @endif

        @if ($isDraft)
            {{-- Not set up yet: no list, seed or link exists until the settings are saved. --}}
            <section class="rounded-2xl border border-dashed border-outline-variant bg-surface-container-lowest px-6 py-12 text-center" data-sponsor-draw-draft>
                <span class="mx-auto mb-4 grid h-14 w-14 place-items-center rounded-2xl bg-primary/10 text-primary"><span class="material-symbols-outlined !text-[30px]" aria-hidden="true">casino</span></span>
                <h2 class="text-lg font-bold text-on-surface">{{ __('sponsor_draw.admin.draft_title') }}</h2>
                <p class="mx-auto mt-1 max-w-md text-xs text-outline">{{ __('sponsor_draw.admin.draft_hint') }}</p>
                <button type="button" @click="settingsOpen = true"
                    class="mt-5 inline-flex h-10 items-center gap-1.5 rounded-xl bg-primary px-5 text-sm font-semibold text-on-primary hover:bg-primary-container cursor-pointer">
                    <span class="material-symbols-outlined !text-[18px]" aria-hidden="true">tune</span>{{ __('sponsor_draw.admin.settings') }}
                </button>
            </section>
        @else

        <x-sponsor-draw.stage :state="$state" mode="admin" :timezone="$session->displayTimezone()" :urls="[
            'state' => route('admin.sponsor-draws.state', [$room, $session]),
            'token' => route('admin.sponsor-draws.socket-token', [$room, $session]),
            'draw' => route('admin.sponsor-draws.draw', [$room, $session]),
            'remove_eligible' => route('admin.sponsor-draws.eligible.remove', [$room, $session]),
            'sync_eligible' => route('admin.sponsor-draws.eligible.sync', [$room, $session]),
        ]">
            <x-slot:actions>
                @if (! $status->isConfirmed() && $status !== \App\Enums\SponsorDrawStatus::Cancelled)
                <div @class(['grid gap-3', 'grid-cols-2' => $canReset])>
                {{-- Confirm sits next to the draw button; it only becomes active once every sponsor is drawn. --}}
                @if ($status === \App\Enums\SponsorDrawStatus::Completed)
                    <form method="POST" action="{{ route('admin.sponsor-draws.confirm', [$room, $session]) }}" data-confirm-message="{{ __('sponsor_draw.admin.confirm_confirm') }}">
                        @csrf
                        <button type="submit" class="sd-btn is-ghost" data-sponsor-draw-confirm><span class="material-symbols-outlined" aria-hidden="true">check_circle</span><span>{{ __('sponsor_draw.stage.confirm_short') }}</span></button>
                    </form>
                @elseif (! $status->isConfirmed() && $status !== \App\Enums\SponsorDrawStatus::Cancelled)
                    <button type="button" class="sd-btn is-ghost" disabled title="{{ __($session->type === \App\Enums\SponsorDrawType::Shops ? 'sponsor_draw.admin.confirm_after_draw_shops' : 'sponsor_draw.admin.confirm_after_draw') }}"><span class="material-symbols-outlined" aria-hidden="true">check_circle</span><span>{{ __('sponsor_draw.stage.confirm_short') }}</span></button>
                @endif
                {{-- Reset discards every drawn turn so the drawing starts over; only until the result is confirmed. --}}
                @if ($canReset)
                    <form method="POST" action="{{ route('admin.sponsor-draws.reset', [$room, $session]) }}" data-confirm-tone="danger" data-confirm-message="{{ __('sponsor_draw.admin.reset_confirm') }}">
                        @csrf
                        <button type="submit" class="sd-btn is-reset" data-sponsor-draw-reset><span class="material-symbols-outlined" aria-hidden="true">restart_alt</span><span>{{ __('sponsor_draw.stage.reset_short') }}</span></button>
                    </form>
                @endif
                </div>
                @endif
            </x-slot:actions>
        </x-sponsor-draw.stage>
        @endif


        @if ($canConfigure)
            @include('admin.sponsor-draws._settings-modal')
        @endif

        @if ($status->canCancel())
            {{-- Cancel asks for the reason in a modal (resources/js/sponsor-draw/cancel-modal.js); it reopens with the error when validation fails. --}}
            <div data-sd-cancel-modal @if ($errors->has('reason')) data-open-on-load @endif role="dialog" aria-modal="true" aria-labelledby="sd-cancel-title"
                class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
                <form method="POST" action="{{ route('admin.sponsor-draws.cancel', [$room, $session]) }}"
                    class="w-full max-w-md rounded-2xl border border-outline-variant bg-surface-container-lowest shadow-2xl overflow-hidden">
                    @csrf
                    <div class="flex items-center justify-between gap-3 border-b border-outline-variant/60 bg-surface-container-low p-5">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-error/10 text-error"><span class="material-symbols-outlined text-[22px]">cancel</span></span>
                            <h3 id="sd-cancel-title" class="text-base font-bold text-on-surface">{{ __('sponsor_draw.admin.cancel_title') }}</h3>
                        </div>
                        <button type="button" data-sd-cancel-dismiss class="text-outline hover:text-on-surface cursor-pointer" aria-label="{{ __('admin.close') }}"><span class="material-symbols-outlined text-[20px]">close</span></button>
                    </div>
                    <div class="space-y-2 p-5">
                        <p class="text-xs text-outline">{{ __('sponsor_draw.admin.cancel_hint') }}</p>
                        <label for="sd-cancel-reason" class="block pt-1 text-xs font-semibold text-on-surface">{{ __('sponsor_draw.fields.cancel_reason') }} <span class="text-error">*</span></label>
                        <textarea id="sd-cancel-reason" name="reason" rows="3" required minlength="3" maxlength="500" data-sd-cancel-reason
                            placeholder="{{ __('sponsor_draw.admin.cancel_reason_placeholder') }}"
                            class="w-full rounded-lg border border-outline-variant bg-surface px-3 py-2 text-sm focus:border-primary focus:outline-none">{{ old('reason') }}</textarea>
                        @error('reason')<p class="text-xs text-error" role="alert">{{ $message }}</p>@enderror
                    </div>
                    <div class="flex justify-end gap-2 border-t border-outline-variant/60 bg-surface-container-low px-5 py-4">
                        <button type="button" data-sd-cancel-dismiss class="h-9 rounded-lg border border-outline-variant px-4 text-xs font-semibold text-on-surface hover:bg-surface-container cursor-pointer">{{ __('admin.close') }}</button>
                        <button type="submit" class="h-9 rounded-lg bg-error px-4 text-xs font-semibold text-on-error cursor-pointer">{{ __('sponsor_draw.admin.cancel_button') }}</button>
                    </div>
                </form>
            </div>
        @endif
    </div>
</x-admin.layout>
