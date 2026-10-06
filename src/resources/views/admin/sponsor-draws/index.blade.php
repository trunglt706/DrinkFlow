<x-admin.layout :title="__('sponsor_draw.admin.title')" active="sponsor-draws" :room="$room">
    @php
        $field = 'w-full h-10 rounded-xl border border-outline-variant bg-surface px-3 text-sm text-on-surface focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20';
    @endphp
    <div class="space-y-6">
        <section class="pb-4 border-b border-outline-variant/40">
            <h1 class="text-2xl font-bold tracking-tight text-on-surface">{{ __('sponsor_draw.admin.title') }}</h1>
            <p class="mt-1 max-w-3xl text-xs text-outline">{{ __('sponsor_draw.admin.description') }}</p>
        </section>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-error/30 bg-error-container p-3 text-sm text-on-error-container" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5" data-sponsor-draw-create>
            <h2 class="text-sm font-bold text-on-surface">{{ __('sponsor_draw.admin.create_title') }}</h2>
            <p class="mt-1 text-xs text-outline">{{ __('sponsor_draw.admin.create_hint') }}</p>
            <form method="POST" action="{{ route('admin.sponsor-draws.store', $room) }}" class="mt-4 grid grid-cols-1 md:grid-cols-[2fr_1fr_2fr_auto] gap-3 items-end">
                @csrf
                <label class="text-xs font-semibold text-on-surface space-y-1">
                    <span>{{ __('sponsor_draw.fields.campaign') }} <span class="text-error">*</span></span>
                    <select name="campaign_id" required class="{{ $field }}">
                        @foreach ($campaigns as $campaign)
                            <option value="{{ $campaign->id }}" @selected((string) old('campaign_id') === (string) $campaign->id)>{{ $campaign->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-semibold text-on-surface space-y-1">
                    <span>{{ __('sponsor_draw.fields.target_winner_count') }} <span class="text-error">*</span></span>
                    <input type="number" name="target_winner_count" min="1" max="{{ $maxWinners }}" required value="{{ old('target_winner_count', 1) }}" class="{{ $field }}">
                </label>
                <label class="text-xs font-semibold text-on-surface space-y-1">
                    <span>{{ __('sponsor_draw.fields.target_campaign') }} <span class="font-normal text-outline">({{ __('sponsor_draw.admin.optional') }})</span></span>
                    <select name="target_campaign_id" class="{{ $field }}">
                        <option value="">{{ __('sponsor_draw.admin.target_none') }}</option>
                        @foreach ($targetCampaigns as $campaign)
                            <option value="{{ $campaign->id }}" @selected((string) old('target_campaign_id') === (string) $campaign->id)>{{ $campaign->name }}</option>
                        @endforeach
                    </select>
                </label>
                <button type="submit" class="h-10 inline-flex items-center justify-center gap-1.5 rounded-xl bg-primary px-4 text-sm font-semibold text-on-primary hover:bg-primary-container" @disabled($campaigns->isEmpty())>
                    <span class="material-symbols-outlined text-[18px]">casino</span>{{ __('sponsor_draw.admin.create_submit') }}
                </button>
            </form>
            <p class="mt-3 text-[11px] text-outline">{{ __('sponsor_draw.admin.target_hint') }}</p>
        </section>

        <section class="rounded-xl border border-outline-variant bg-surface-container-lowest overflow-hidden">
            <div class="px-5 py-4 border-b border-outline-variant"><h2 class="text-sm font-bold text-on-surface">{{ __('sponsor_draw.admin.sessions_title') }}</h2></div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-surface-container-low text-[11px] uppercase tracking-wider text-outline">
                        <tr>
                            <th class="px-5 py-3 text-left">#</th>
                            <th class="px-5 py-3 text-left">{{ __('sponsor_draw.fields.campaign') }}</th>
                            <th class="px-5 py-3 text-left">{{ __('sponsor_draw.fields.target_campaign') }}</th>
                            <th class="px-5 py-3 text-right">{{ __('sponsor_draw.admin.col_progress') }}</th>
                            <th class="px-5 py-3 text-left">{{ __('validation.attributes.status') }}</th>
                            <th class="px-5 py-3 text-left">{{ __('sponsor_draw.admin.col_link') }}</th>
                            <th class="px-5 py-3 text-left">{{ __('sponsor_draw.admin.col_created') }}</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-outline-variant/60">
                        @forelse ($sessions as $session)
                            <tr>
                                <td class="px-5 py-3 font-mono text-outline">{{ $session->id }}</td>
                                <td class="px-5 py-3 font-semibold">{{ $session->campaign?->name }}</td>
                                <td class="px-5 py-3">{{ $session->targetCampaign?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-right font-mono">{{ $session->results_count }}/{{ $session->target_winner_count }}</td>
                                <td class="px-5 py-3"><span class="rounded-full bg-surface-container px-2 py-0.5 text-[11px] font-semibold">{{ __('sponsor_draw.js.status.'.$session->status->value) }}</span></td>
                                <td class="px-5 py-3 text-xs">{{ $session->linkIsValid() ? __('sponsor_draw.admin.link_until', ['time' => $session->localTime($session->link_expires_at, 'H:i d/m')]) : __('sponsor_draw.admin.link_expired') }}</td>
                                <td class="px-5 py-3 text-xs text-outline">{{ $session->localTime($session->created_at, 'd/m/Y H:i') }} · {{ $session->createdBy?->name }}</td>
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
    </div>
</x-admin.layout>
