@props([
    'id' => 'admin-date-range-filter',
    'dateFrom' => request('date_from', ''),
    'dateTo' => request('date_to', ''),
    'formId' => null,
    'fullWidth' => false,
])

<div id="{{ $id }}" class="relative {{ $fullWidth ? 'block w-full' : 'inline-block' }} text-left" data-date-range-picker="true" data-label-today="{{ __('admin.preset_today') }}" data-label-all="{{ __('admin.preset_all_time') }}" data-label-custom="{{ __('admin.preset_custom') }}" @if($formId) data-form-id="{{ $formId }}" @endif>
    <input type="hidden" name="date_from" value="{{ $dateFrom }}" class="date-from-hidden" data-date-from />
    <input type="hidden" name="date_to" value="{{ $dateTo }}" class="date-to-hidden" data-date-to />

    <button
        type="button"
        class="date-range-toggle inline-flex {{ $fullWidth ? 'w-full justify-between' : '' }} items-center gap-2 px-3 py-2 text-xs font-semibold bg-surface-container-lowest border border-outline-variant/70 rounded-xl text-on-surface hover:bg-surface-container-low transition-colors shadow-2xs cursor-pointer"
        data-date-range-trigger
        aria-haspopup="true"
        aria-expanded="false"
    >
        <span class="material-symbols-outlined text-[18px] text-primary">calendar_today</span>
        <span class="date-range-label font-medium" data-date-range-label>
            @if(empty($dateFrom) && empty($dateTo))
                {{ __('admin.preset_all_time') }}
            @elseif(!empty($dateFrom) && !empty($dateTo) && $dateFrom === $dateTo && $dateFrom === date('Y-m-d'))
                {{ __('admin.preset_today') }}
            @elseif(!empty($dateFrom) && !empty($dateTo) && [$dateFrom, $dateTo] === \App\Support\Helpers\DateRangeHelper::lastDays())
                {{ __('admin.preset_last_7_days') }}: {{ \Illuminate\Support\Carbon::parse($dateFrom)->format('d/m') }} ~ {{ \Illuminate\Support\Carbon::parse($dateTo)->format('d/m') }}
            @elseif(!empty($dateFrom) && !empty($dateTo))
                {{ $dateFrom }} ~ {{ $dateTo }}
            @elseif(!empty($dateFrom))
                >= {{ $dateFrom }}
            @elseif(!empty($dateTo))
                <= {{ $dateTo }}
            @else
                {{ __('admin.date_range') }}
            @endif
        </span>
        <span class="material-symbols-outlined text-[16px] text-outline">arrow_drop_down</span>
    </button>

    <div class="date-range-dropdown hidden absolute right-0 z-30 mt-1.5 w-72 md:w-80 origin-top-right rounded-xl bg-surface-container-lowest shadow-xl border border-outline-variant p-3.5 focus:outline-none" data-date-range-dropdown>
        <div class="text-[10px] font-mono font-bold uppercase tracking-wider text-outline mb-2 px-1">
            {{ __('admin.date_range') }}
        </div>

        <div class="grid grid-cols-2 gap-1.5 mb-3">
            <button type="button" class="preset-btn px-2.5 py-1.5 text-xs text-left rounded-lg hover:bg-surface-container-low text-on-surface font-medium transition-colors cursor-pointer" data-preset="all">
                {{ __('admin.preset_all_time') }}
            </button>
            <button type="button" class="preset-btn px-2.5 py-1.5 text-xs text-left rounded-lg hover:bg-surface-container-low text-on-surface font-medium transition-colors cursor-pointer" data-preset="today">
                {{ __('admin.preset_today') }}
            </button>
            <button type="button" class="preset-btn px-2.5 py-1.5 text-xs text-left rounded-lg hover:bg-surface-container-low text-on-surface font-medium transition-colors cursor-pointer" data-preset="yesterday">
                {{ __('admin.preset_yesterday') }}
            </button>
            <button type="button" class="preset-btn px-2.5 py-1.5 text-xs text-left rounded-lg hover:bg-surface-container-low text-on-surface font-medium transition-colors cursor-pointer" data-preset="last_7_days">
                {{ __('admin.preset_last_7_days') }}
            </button>
            <button type="button" class="preset-btn px-2.5 py-1.5 text-xs text-left rounded-lg hover:bg-surface-container-low text-on-surface font-medium transition-colors cursor-pointer" data-preset="last_30_days">
                {{ __('admin.preset_last_30_days') }}
            </button>
            <button type="button" class="preset-btn px-2.5 py-1.5 text-xs text-left rounded-lg hover:bg-surface-container-low text-on-surface font-medium transition-colors cursor-pointer" data-preset="this_month">
                {{ __('admin.preset_this_month') }}
            </button>
            <button type="button" class="preset-btn px-2.5 py-1.5 text-xs text-left rounded-lg hover:bg-surface-container-low text-on-surface font-medium transition-colors cursor-pointer" data-preset="last_month">
                {{ __('admin.preset_last_month') }}
            </button>
        </div>

        <div class="border-t border-outline-variant/60 pt-3 space-y-2" data-custom-date-box>
            <div class="text-[11px] font-semibold text-outline px-1">
                {{ __('admin.preset_custom') }}
            </div>
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-[10px] font-mono text-outline mb-0.5">{{ __('admin.start_date') }}</label>
                    {{-- Shared date input: the hidden yyyy-mm-dd value keeps the class read by ui-enhancements.js. --}}
                    <x-date-input :value="$dateFrom" value-class="date-from-input" :label="__('admin.start_date')"
                        input-class="w-full px-2 py-1.5 text-xs bg-surface border border-outline-variant rounded-lg text-on-surface focus:ring-1 focus:ring-primary focus:border-primary" />
                </div>
                <div>
                    <label class="block text-[10px] font-mono text-outline mb-0.5">{{ __('admin.end_date') }}</label>
                    <x-date-input :value="$dateTo" value-class="date-to-input" :label="__('admin.end_date')"
                        input-class="w-full px-2 py-1.5 text-xs bg-surface border border-outline-variant rounded-lg text-on-surface focus:ring-1 focus:ring-primary focus:border-primary" />
                </div>
            </div>
            <div class="pt-1 flex items-center justify-end gap-2">
                <button type="button" class="apply-btn w-full py-1.5 px-3 bg-primary hover:bg-primary-container text-on-primary rounded-lg text-xs font-semibold shadow-xs transition-colors cursor-pointer" data-apply-date-range>
                    {{ __('admin.apply_date_range') }}
                </button>
            </div>
        </div>
    </div>
</div>
