{{-- Empty / loading placeholder: icon on top, then title and description. Mirrors components/admin/empty-state.
     Pass `loading` to spin the icon while data is being fetched, and `:bordered="false"` inside an already-framed list. --}}
@props([
    'icon' => 'inbox',
    'title' => null,
    'description' => null,
    'loading' => false,
    'bordered' => true,
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center py-10 px-4 text-center rounded-xl'.($bordered ? ' border border-dashed border-outline-variant/80 bg-surface-container-lowest' : '')]) }}
    @if($loading) role="status" aria-busy="true" @endif>
    <div class="flex h-12 w-12 items-center justify-center rounded-full bg-surface-container text-outline mb-3">
        <span class="material-symbols-outlined text-[26px] {{ $loading ? 'animate-spin' : '' }}" aria-hidden="true">{{ $loading ? 'progress_activity' : $icon }}</span>
    </div>
    @if($title)
        <h4 class="text-sm font-semibold text-on-surface mb-1" data-empty-title>{{ $title }}</h4>
    @endif
    <p class="text-[13px] text-outline max-w-sm leading-relaxed" data-empty-description>{{ $description ?? $slot }}</p>
</div>
