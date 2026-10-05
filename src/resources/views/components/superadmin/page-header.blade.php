{{-- Page title block of every superadmin page: optional back link, eyebrow, title, description and actions (slot).
     Keeps the .superadmin-heading markup the console CSS and tests rely on. `title-id` / `description-id` let page
     scripts fill a profile loaded by AJAX; the `meta` slot renders extra markup (e.g. a status pill) under the title. --}}
@props([
    'title',
    'eyebrow' => null,
    'description' => null,
    'back' => null,
    'backLabel' => null,
    'titleId' => null,
    'descriptionId' => null,
])

<div {{ $attributes->merge(['class' => 'superadmin-heading']) }}>
    <div class="min-w-0">
        @if ($back)
            <a class="sa-back-link" href="{{ $back }}"><span class="material-symbols-outlined text-[18px]" aria-hidden="true">arrow_back</span>{{ $backLabel ?? __('superadmin.common.back') }}</a>
        @endif
        @if ($eyebrow)
            <p class="superadmin-eyebrow">{{ $eyebrow }}</p>
        @endif
        <h1 @if ($titleId) id="{{ $titleId }}" @endif>{{ $title }}</h1>
        @if ($description !== null)
            <p @if ($descriptionId) id="{{ $descriptionId }}" @endif>{{ $description }}</p>
        @endif
        {{ $meta ?? '' }}
    </div>
    @if (trim((string) $slot) !== '')
        <div class="superadmin-actions">{{ $slot }}</div>
    @endif
</div>
