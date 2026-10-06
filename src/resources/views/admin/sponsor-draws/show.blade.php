<x-admin.layout :title="__('sponsor_draw.admin.session_title', ['id' => $session->id])" active="sponsor-draws" :room="$room">
    @php
        $status = $session->status;
        $notifications = $state['notifications'] ?? [];
        $assignment = $state['sponsor_assignment'] ?? null;
    @endphp
    <div class="space-y-5">
        <section class="flex flex-wrap items-center justify-between gap-3 pb-4 border-b border-outline-variant/40">
            <div class="flex items-center gap-3">
                <a href="{{ route('admin.sponsor-draws.index', $room) }}" class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-outline-variant text-on-surface-variant hover:bg-surface-container"
                    aria-label="{{ __('sponsor_draw.admin.back') }}" title="{{ __('sponsor_draw.admin.back') }}"><span class="material-symbols-outlined text-[18px]">arrow_back</span></a>
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-on-surface">{{ __('sponsor_draw.admin.session_title', ['id' => $session->id]) }}</h1>
                    <p class="text-xs text-outline">{{ __('sponsor_draw.admin.created_by', ['name' => $session->createdBy?->name ?? '—', 'time' => $session->localTime($session->created_at, 'd/m/Y H:i')]) }}</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.sponsor-draws.proof', [$room, $session]) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-outline-variant px-3 py-2 text-xs font-semibold text-on-surface hover:bg-surface-container" download>
                    <span class="material-symbols-outlined text-[16px]">verified</span>{{ __('sponsor_draw.admin.download_proof') }}
                </a>
                @if ($status->canCancel())
                    <button type="button" data-sd-cancel-open aria-haspopup="dialog"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-error/40 px-3 py-2 text-xs font-semibold text-error hover:bg-error-container/60 cursor-pointer">
                        <span class="material-symbols-outlined text-[16px]">cancel</span>{{ __('sponsor_draw.admin.cancel_button') }}
                    </button>
                @endif
            </div>
        </section>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-error/30 bg-error-container p-3 text-sm text-on-error-container" role="alert">{{ $errors->first() }}</div>
        @endif

        {{-- Access link: shareable while valid; a new link replaces the old one. --}}
        <section class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4 space-y-2" data-sponsor-draw-link>
            <h2 class="text-sm font-bold text-on-surface">{{ __('sponsor_draw.admin.link_title') }}</h2>
            @if ($linkUrl)
                <div class="flex flex-wrap items-center gap-2">
                    <input type="text" readonly value="{{ $linkUrl }}" class="min-w-0 flex-1 h-9 rounded-lg border border-outline-variant bg-surface px-3 font-mono text-xs" aria-label="{{ __('sponsor_draw.admin.link_title') }}" onclick="this.select()">
                    <button type="button" data-copy="{{ $linkUrl }}" data-copied-message="{{ __('admin.copied') }}" data-copy-failed-message="{{ __('admin.copy_failed') }}"
                        class="h-9 inline-flex items-center gap-1 rounded-lg border border-outline-variant px-3 text-xs font-semibold hover:bg-surface-container"><span class="material-symbols-outlined text-[16px]">content_copy</span>{{ __('sponsor_draw.admin.copy_link') }}</button>
                </div>
                <p class="text-xs text-outline">{{ __('sponsor_draw.admin.link_until', ['time' => $session->localTime($session->link_expires_at)]) }}</p>
            @else
                <p class="text-xs text-error">{{ __('sponsor_draw.admin.link_expired_hint') }}</p>
            @endif
            @if ($status !== \App\Enums\SponsorDrawStatus::Cancelled)
                <form method="POST" action="{{ route('admin.sponsor-draws.link', [$room, $session]) }}"
                    data-confirm-message="{{ __('sponsor_draw.admin.new_link_confirm') }}">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1 text-xs font-semibold text-primary hover:underline"><span class="material-symbols-outlined text-[16px]">link</span>{{ __('sponsor_draw.admin.new_link') }}</button>
                </form>
            @endif
        </section>

        <x-sponsor-draw.stage :state="$state" mode="admin" :timezone="$session->displayTimezone()" :urls="[
            'state' => route('admin.sponsor-draws.state', [$room, $session]),
            'token' => route('admin.sponsor-draws.socket-token', [$room, $session]),
            'draw' => route('admin.sponsor-draws.draw', [$room, $session]),
        ]">
            <x-slot:actions>
                {{-- Confirm sits next to the draw button; it only becomes active once every sponsor is drawn. --}}
                @if ($status === \App\Enums\SponsorDrawStatus::Completed)
                    <form method="POST" action="{{ route('admin.sponsor-draws.confirm', [$room, $session]) }}" data-confirm-message="{{ __('sponsor_draw.admin.confirm_confirm') }}">
                        @csrf
                        <button type="submit" class="sd-btn is-ghost" data-sponsor-draw-confirm><span class="material-symbols-outlined" aria-hidden="true">check_circle</span><span>{{ __('sponsor_draw.stage.confirm_short') }}</span></button>
                    </form>
                @elseif (! $status->isConfirmed() && $status !== \App\Enums\SponsorDrawStatus::Cancelled)
                    <button type="button" class="sd-btn is-ghost" disabled title="{{ __('sponsor_draw.admin.confirm_after_draw') }}"><span class="material-symbols-outlined" aria-hidden="true">check_circle</span><span>{{ __('sponsor_draw.stage.confirm_short') }}</span></button>
                @endif
            </x-slot:actions>
        </x-sponsor-draw.stage>

        <section class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4 space-y-3">
            <h2 class="text-sm font-bold text-on-surface">{{ __('sponsor_draw.admin.confirm_title') }}</h2>
            @if ($status === \App\Enums\SponsorDrawStatus::Completed)
                <p class="text-xs text-outline">{{ __('sponsor_draw.admin.confirm_hint') }}</p>
            @elseif ($status->isConfirmed())
                <p class="text-xs text-on-surface">{{ __('sponsor_draw.admin.confirmed_by', ['name' => $session->confirmedBy?->name ?? '—', 'time' => $session->localTime($session->confirmed_at, 'd/m/Y H:i')]) }}</p>
                @if (is_array($assignment) && ($assignment['status'] ?? 'none') !== 'none')
                    <p class="text-xs {{ $assignment['status'] === 'assigned' ? 'text-emerald-700' : 'text-amber-700' }}" data-sponsor-assignment="{{ $assignment['status'] }}">
                        {{ $assignment['status'] === 'assigned' ? __('sponsor_draw.admin.assignment_done', ['name' => $session->targetCampaign?->name]) : __('sponsor_draw.admin.assignment_skipped', ['reason' => $assignment['reason'] ?? '']) }}
                    </p>
                @endif
                <div class="overflow-x-auto">
                    <table class="w-full text-xs" data-sponsor-draw-notifications>
                        <thead class="text-[11px] uppercase tracking-wider text-outline"><tr><th class="py-2 text-left">{{ __('sponsor_draw.admin.channel') }}</th><th class="py-2 text-left">{{ __('validation.attributes.status') }}</th><th class="py-2 text-right">{{ __('sponsor_draw.admin.attempts') }}</th><th class="py-2 text-left">{{ __('sponsor_draw.admin.detail') }}</th></tr></thead>
                        <tbody class="divide-y divide-outline-variant/60">
                            @forelse ($notifications as $row)
                                <tr>
                                    <td class="py-2">{{ $row['name'] ?: ucfirst($row['type']) }} <span class="text-outline">({{ $row['type'] }})</span></td>
                                    <td class="py-2 font-semibold {{ $row['status'] === 'sent' ? 'text-emerald-700' : ($row['status'] === 'failed' ? 'text-error' : 'text-amber-700') }}">{{ __('sponsor_draw.admin.delivery.'.$row['status']) }}</td>
                                    <td class="py-2 text-right font-mono">{{ $row['attempts'] }}</td>
                                    <td class="py-2 text-outline">{{ $row['status'] === 'sent' ? ($row['sent_at'] ? $session->localTime($row['sent_at'], 'H:i d/m') : '') : ($row['last_error'] ?? '') }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="4" class="py-3 text-outline">{{ __('sponsor_draw.admin.no_channels') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if (collect($notifications)->contains('status', 'failed'))
                    <form method="POST" action="{{ route('admin.sponsor-draws.resend', [$room, $session]) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1.5 rounded-lg border border-outline-variant px-3 py-2 text-xs font-semibold hover:bg-surface-container"><span class="material-symbols-outlined text-[16px]">replay</span>{{ __('sponsor_draw.admin.resend_button') }}</button>
                    </form>
                @endif
            @else
                <p class="text-xs text-outline">{{ $status === \App\Enums\SponsorDrawStatus::Cancelled ? __('sponsor_draw.admin.cancelled_reason', ['reason' => $session->cancel_reason]) : __('sponsor_draw.admin.confirm_after_draw') }}</p>
            @endif
        </section>

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
