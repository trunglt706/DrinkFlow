<x-admin.layout :title="__('sponsor_draw.admin.title')" active="sponsor-draws" :room="$room">
    <div class="space-y-6">
        <section class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-4 border-b border-outline-variant/40">
            <div>
                <h1 class="text-2xl font-bold tracking-tight text-on-surface">{{ __('sponsor_draw.admin.title') }}</h1>
                <p class="mt-1 max-w-3xl text-xs text-outline">{{ __('sponsor_draw.admin.description') }}</p>
            </div>
            {{-- A new session starts as a draft; its type and list are set on the draw page. --}}
            <form method="POST" action="{{ route('admin.sponsor-draws.store', $room) }}" data-loading-form="true" class="shrink-0">
                @csrf
                <button type="submit" data-sponsor-draw-create
                    class="h-10 inline-flex items-center justify-center gap-1.5 rounded-xl bg-primary px-4 text-sm font-semibold text-on-primary hover:bg-primary-container shadow-sm cursor-pointer">
                    <span class="material-symbols-outlined !text-[18px]" aria-hidden="true">add_circle</span>{{ __('sponsor_draw.admin.new_session') }}
                </button>
            </form>
        </section>

        @if (session('status'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800" role="status">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded-xl border border-error/30 bg-error-container p-3 text-sm text-on-error-container" role="alert">{{ $errors->first() }}</div>
        @endif

        <section class="rounded-xl border border-outline-variant bg-surface-container-lowest overflow-hidden">
            <div class="px-5 py-4 border-b border-outline-variant"><h2 class="text-sm font-bold text-on-surface">{{ __('sponsor_draw.admin.sessions_title') }}</h2></div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-surface-container-low text-[11px] uppercase tracking-wider text-outline">
                        <tr>
                            <th class="px-5 py-3 text-left">#</th>
                            <th class="px-5 py-3 text-left">{{ __('sponsor_draw.admin.col_type') }}</th>
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
                                <td class="px-5 py-3">
                                    <span class="inline-flex items-center gap-1.5 font-semibold">
                                        <span class="material-symbols-outlined !text-[16px] text-primary" aria-hidden="true">{{ $session->type === \App\Enums\SponsorDrawType::Shops ? 'storefront' : 'volunteer_activism' }}</span>{{ __('sponsor_draw.types.'.$session->type->value) }}
                                    </span>
                                    {{-- Sessions created before draft settings came from a campaign. --}}
                                    @if ($session->campaign)<small class="block text-outline">{{ $session->campaign->name }}</small>@endif
                                </td>
                                <td class="px-5 py-3">{{ $session->targetCampaign?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-right font-mono">{{ $session->status === \App\Enums\SponsorDrawStatus::Draft ? '—' : $session->results_count.'/'.$session->target_winner_count }}</td>
                                <td class="px-5 py-3"><span class="rounded-full bg-surface-container px-2 py-0.5 text-[11px] font-semibold">{{ __('sponsor_draw.js.status.'.$session->status->value) }}</span></td>
                                <td class="px-5 py-3 text-xs">{{ $session->status === \App\Enums\SponsorDrawStatus::Draft ? '—' : ($session->linkIsValid() ? __('sponsor_draw.admin.link_until', ['time' => $session->localTime($session->link_expires_at, 'H:i d/m')]) : __('sponsor_draw.admin.link_expired')) }}</td>
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
