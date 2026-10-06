{{-- Settings of a draw session (sponsors or shops), shown from the draw page while no turn is drawn. Alpine state
     (`settingsOpen`, `type`) lives on the page wrapper of admin/sponsor-draws/show.blade.php. --}}
@php
    $field = 'w-full h-10 rounded-xl border border-outline-variant bg-surface px-3 text-sm text-on-surface focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20';
    $selectedShops = collect(old('shops', $currentShops))->map(static fn ($name): string => (string) $name)->all();
    $selectedKeys = array_map('mb_strtolower', $selectedShops);
    // Shops saved earlier that are not suggestions anymore go back to the "add" box.
    $extraShops = old('new_shops', implode("\n", array_filter($currentShops, static fn (string $name): bool => ! in_array(mb_strtolower($name), array_map('mb_strtolower', $shopSuggestions), true))));
@endphp
<div x-show="settingsOpen" x-cloak x-transition.opacity role="dialog" aria-modal="true" aria-labelledby="sd-settings-title"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs" @keydown.escape.window="settingsOpen = false" data-sd-settings-modal>
    <form method="POST" action="{{ route('admin.sponsor-draws.settings', [$room, $session]) }}" data-loading-form="true" @click.outside="settingsOpen = false"
        class="w-full max-w-xl max-h-[92vh] flex flex-col rounded-2xl border border-outline-variant bg-surface-container-lowest shadow-2xl overflow-hidden">
        @csrf
        @method('PUT')
        <div class="flex items-center justify-between gap-3 border-b border-outline-variant/60 bg-surface-container-low p-5">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary"><span class="material-symbols-outlined !text-[22px]" aria-hidden="true">tune</span></span>
                <div class="min-w-0">
                    <h3 id="sd-settings-title" class="text-base font-bold text-on-surface">{{ __('sponsor_draw.admin.settings_title') }}</h3>
                    <p class="text-[11px] text-outline">{{ __('sponsor_draw.admin.settings_hint') }}</p>
                </div>
            </div>
            <button type="button" @click="settingsOpen = false" class="text-outline hover:text-on-surface cursor-pointer" aria-label="{{ __('admin.close') }}"><span class="material-symbols-outlined !text-[20px]">close</span></button>
        </div>

        <div class="space-y-5 p-5 overflow-y-auto">
            @if ($errors->any())
                <div class="rounded-xl border border-error/30 bg-error-container p-3 text-xs text-on-error-container" role="alert">{{ $errors->first() }}</div>
            @endif

            <fieldset>
                <legend class="mb-2 text-xs font-semibold text-on-surface">{{ __('sponsor_draw.fields.type') }}</legend>
                <div class="grid grid-cols-2 gap-2">
                    @foreach ([['sponsors', 'volunteer_activism'], ['shops', 'storefront']] as [$typeValue, $typeIcon])
                        <label class="flex items-center gap-2.5 rounded-xl border px-3 py-3 text-sm font-semibold cursor-pointer transition-colors"
                            :class="type === '{{ $typeValue }}' ? 'border-primary bg-primary/5 text-primary' : 'border-outline-variant text-on-surface hover:bg-surface-container'">
                            <input type="radio" name="type" value="{{ $typeValue }}" x-model="type" class="sr-only">
                            <span class="material-symbols-outlined !text-[20px]" aria-hidden="true">{{ $typeIcon }}</span>{{ __('sponsor_draw.types.'.$typeValue) }}
                        </label>
                    @endforeach
                </div>
            </fieldset>

            {{-- Sponsors: how many, and the campaign they will sponsor (optional). --}}
            <div x-show="type === 'sponsors'" class="space-y-4" data-sd-settings-sponsors>
                <p class="flex gap-2 rounded-xl bg-emerald-50 p-3 text-[11px] text-on-surface-variant">
                    <span class="material-symbols-outlined !text-[16px] text-primary shrink-0" aria-hidden="true">groups</span>
                    <span>{{ __('sponsor_draw.admin.type_sponsors_hint', ['count' => $eligibleMembers]) }}</span>
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-[1fr_2fr] gap-3">
                    <label class="text-xs font-semibold text-on-surface space-y-1">
                        <span>{{ __('sponsor_draw.fields.target_winner_count') }} <span class="text-error">*</span></span>
                        <input type="number" name="target_winner_count" min="1" max="{{ min($maxWinners, max(1, $eligibleMembers)) }}" :required="type === 'sponsors'" :disabled="type !== 'sponsors'"
                            value="{{ old('target_winner_count', $session->type === \App\Enums\SponsorDrawType::Sponsors ? $session->target_winner_count : 1) }}" class="{{ $field }}">
                    </label>
                    <label class="text-xs font-semibold text-on-surface space-y-1">
                        <span>{{ __('sponsor_draw.fields.target_campaign') }} <span class="font-normal text-outline">({{ __('sponsor_draw.admin.optional') }})</span></span>
                        <select name="target_campaign_id" class="{{ $field }}" :disabled="type !== 'sponsors'">
                            <option value="">{{ __('sponsor_draw.admin.target_none') }}</option>
                            @foreach ($targetCampaigns as $campaign)
                                <option value="{{ $campaign->id }}" @selected((string) old('target_campaign_id', $session->target_campaign_id) === (string) $campaign->id)>{{ $campaign->name }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <p class="text-[11px] text-outline">{{ __('sponsor_draw.admin.target_hint') }}</p>
            </div>

            {{-- Shops: tick suggested shops and/or type new ones; one shop is drawn. --}}
            <div x-show="type === 'shops'" class="space-y-3" data-sd-settings-shops>
                <p class="flex gap-2 rounded-xl bg-emerald-50 p-3 text-[11px] text-on-surface-variant">
                    <span class="material-symbols-outlined !text-[16px] text-primary shrink-0" aria-hidden="true">storefront</span>
                    <span>{{ __('sponsor_draw.admin.type_shops_hint') }}</span>
                </p>
                <div>
                    <p class="mb-2 text-xs font-semibold text-on-surface">{{ __('sponsor_draw.admin.shops_suggested') }}</p>
                    @if ($shopSuggestions === [])
                        <p class="text-[11px] text-outline">{{ __('sponsor_draw.admin.shops_none') }}</p>
                    @else
                        <div class="max-h-48 overflow-y-auto grid grid-cols-1 sm:grid-cols-2 gap-1.5 pr-1">
                            @foreach ($shopSuggestions as $shop)
                                <label class="flex items-center gap-2 rounded-lg border border-outline-variant/70 px-2.5 py-2 text-xs text-on-surface cursor-pointer hover:bg-surface-container has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                                    <input type="checkbox" name="shops[]" value="{{ $shop }}" :disabled="type !== 'shops'" @checked(in_array(mb_strtolower($shop), $selectedKeys, true)) class="rounded border-outline-variant text-primary focus:ring-primary">
                                    <span class="truncate">{{ $shop }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>
                <label class="block text-xs font-semibold text-on-surface space-y-1">
                    <span>{{ __('sponsor_draw.fields.new_shops') }}</span>
                    <textarea name="new_shops" rows="4" maxlength="6000" :disabled="type !== 'shops'"
                        class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">{{ $extraShops }}</textarea>
                    <span class="block font-normal text-[11px] text-outline">{{ __('sponsor_draw.admin.shops_new_hint') }}</span>
                </label>
            </div>

            @if ($session->status === \App\Enums\SponsorDrawStatus::Ready)
                <p class="flex gap-2 text-[11px] text-amber-800"><span class="material-symbols-outlined !text-[16px] shrink-0" aria-hidden="true">info</span>{{ __('sponsor_draw.admin.reconfigure_hint') }}</p>
            @endif
        </div>

        <div class="flex justify-end gap-2 border-t border-outline-variant/60 bg-surface-container-low px-5 py-4">
            <button type="button" @click="settingsOpen = false" class="h-9 rounded-lg border border-outline-variant px-4 text-xs font-semibold text-on-surface hover:bg-surface-container cursor-pointer">{{ __('admin.close') }}</button>
            <button type="submit" class="h-9 inline-flex items-center gap-1.5 rounded-lg bg-primary px-4 text-xs font-semibold text-on-primary hover:bg-primary-container cursor-pointer">
                <span class="material-symbols-outlined !text-[16px]" aria-hidden="true">save</span>{{ __('sponsor_draw.admin.settings_save') }}
            </button>
        </div>
    </form>
</div>
