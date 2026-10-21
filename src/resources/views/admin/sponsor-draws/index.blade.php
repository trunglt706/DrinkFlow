<x-admin.layout :title="__('sponsor_draw.admin.title')" active="sponsor-draws" :room="$room">
    @php
        // The "New session" dialog reopens with its errors when the settings were refused.
        $openSettings = $errors->hasAny(['type', 'target_winner_count', 'target_campaign_id', 'shops', 'shops.*', 'new_shops']);
    @endphp
    <div class="space-y-6" x-data="{ settingsOpen: @js($openSettings), type: @js(old('type', \App\Enums\SponsorDrawType::Sponsors->value)) }">
        <section class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-outline-variant/40">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-on-surface">{{ __('sponsor_draw.admin.title') }}</h1>
                <p class="mt-1 max-w-3xl text-xs text-outline">{{ __('sponsor_draw.admin.description') }}</p>
            </div>
            {{-- "New session" asks for the settings first; the session is only created when they are saved. --}}
            <button type="button" @click="settingsOpen = true" data-sponsor-draw-create aria-haspopup="dialog"
                class="shrink-0 h-10 inline-flex items-center justify-center gap-1.5 rounded-xl bg-primary px-4 text-sm font-semibold text-on-primary hover:bg-primary-container shadow-sm cursor-pointer">
                <span class="material-symbols-outlined !text-[18px]" aria-hidden="true">add_circle</span>{{ __('sponsor_draw.admin.new_session') }}
            </button>
        </section>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any() && ! $openSettings)
            <div class="rounded-xl border border-error/30 bg-error-container p-3 text-sm text-on-error-container" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="rounded-xl border border-outline-variant bg-surface-container-lowest overflow-hidden">
            <div class="px-5 py-4 border-b border-outline-variant"><h2 class="text-sm font-bold text-on-surface">{{ __('sponsor_draw.admin.sessions_title') }}</h2></div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-surface-container-low text-[11px] uppercase tracking-wider text-outline">
                        <tr>
                            <th class="w-px whitespace-nowrap px-4 py-3 text-left">#</th>
                            <th class="px-5 py-3 text-left">{{ __('sponsor_draw.admin.col_type') }}</th>
                            <th class="px-5 py-3 text-left">{{ __('sponsor_draw.fields.target_campaign') }}</th>
                            <th class="px-5 py-3 text-right">{{ __('sponsor_draw.admin.col_progress') }}</th>
                            <th class="px-5 py-3 text-left">{{ __('validation.attributes.status') }}</th>
                            <th class="px-5 py-3 text-left">{{ __('sponsor_draw.admin.col_link') }}</th>
                            <th class="min-w-[13rem] whitespace-nowrap px-5 py-3 text-left">{{ __('sponsor_draw.admin.col_created') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/60">
                        @forelse ($sessions as $session)
                            <tr>
                                <td class="w-px whitespace-nowrap px-4 py-3 font-mono text-outline">{{ $session->id }}</td>
                                <td class="px-5 py-3">
                                    {{-- Type as a badge: shops in sky blue, sponsors in emerald. --}}
                                    <span data-draw-type="{{ $session->type->value }}" class="inline-flex whitespace-nowrap rounded-full border px-2.5 py-0.5 text-[11px] font-semibold {{ $session->type === \App\Enums\SponsorDrawType::Shops ? 'border-sky-200 bg-sky-50 text-sky-800' : 'border-emerald-200 bg-emerald-50 text-emerald-800' }}">{{ __('sponsor_draw.types.'.$session->type->value) }}</span>
                                    {{-- Sessions created before draft settings came from a campaign. --}}
                                    @if ($session->campaign)<small class="block text-outline">{{ $session->campaign->name }}</small>@endif
                                </td>
                                <td class="px-5 py-3">{{ $session->targetCampaign?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-right font-mono">{{ $session->status === \App\Enums\SponsorDrawStatus::Draft ? '—' : $session->results_count.'/'.$session->target_winner_count }}</td>
                                <td class="px-5 py-3"><span class="rounded-full bg-surface-container px-2 py-0.5 text-[11px] font-semibold">{{ __('sponsor_draw.js.status.'.$session->status->value) }}</span></td>
                                <td class="px-5 py-3 text-xs">{{ $session->status === \App\Enums\SponsorDrawStatus::Draft ? '—' : ($session->linkIsValid() ? __('sponsor_draw.admin.link_until', ['time' => $session->localTime($session->link_expires_at, 'H:i d/m')]) : __('sponsor_draw.admin.link_expired')) }}</td>
                                <td class="whitespace-nowrap px-5 py-3 text-xs text-outline">{{ $session->localTime($session->created_at, 'd/m/Y H:i') }}<small class="block">{{ $session->createdBy?->name }}</small></td>
                                <td class="px-5 py-3 text-right"><a href="{{ route('admin.sponsor-draws.show', [$room, $session]) }}" class="text-xs font-semibold text-primary hover:underline">{{ __('sponsor_draw.admin.open') }}</a></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="p-4"><x-admin.empty-state icon="casino" :title="__('sponsor_draw.admin.sessions_empty')" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($sessions->hasPages())
                <div class="px-5 py-3">{{ $sessions->links() }}</div>
            @endif
        </section>

        {{-- `session` passed explicitly: the table loop above leaves its last row in $session. --}}
        @include('admin.sponsor-draws._settings-modal', ['session' => null, 'currentShops' => []])
    </div>
</x-admin.layout>
