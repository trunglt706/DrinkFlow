{{-- Table row actions: the slot holds the primary action(s) (e.g. a details link); `menu` holds secondary and
     destructive actions shown in a ⋯ dropdown (resources/js/superadmin/shell.js, [data-sa-dropdown]). --}}
@props([])

<div {{ $attributes->merge(['class' => 'sa-row-actions']) }}>
    {{ $slot }}
    @if (isset($menu) && trim((string) $menu) !== '')
        <div class="sa-dropdown" data-sa-dropdown>
            <button type="button" class="sa-icon-btn" data-sa-dropdown-toggle aria-haspopup="menu" aria-expanded="false"
                title="{{ __('superadmin.layout.more_actions') }}" aria-label="{{ __('superadmin.layout.more_actions') }}">
                <span class="material-symbols-outlined" aria-hidden="true">more_horiz</span>
            </button>
            <div class="sa-menu" role="menu" data-sa-dropdown-menu hidden>{{ $menu }}</div>
        </div>
    @endif
</div>
