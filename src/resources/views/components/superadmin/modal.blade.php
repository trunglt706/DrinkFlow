{{-- Superadmin dialog. Opened by [data-modal-open="id"] / window.openSuperadminModal(id) and closed by [data-modal-close],
     the backdrop or Escape (resources/js/superadmin/modal.js).
     `tone` colours the header icon by the kind of dialog: create | edit | info | warning | danger | security | success.
     Put a <x-superadmin.modal-guide> first in the body for instructions, and wrap the buttons in .sa-modal-footer. --}}
@props([
    'id',
    'icon' => 'info',
    'title',
    'description' => null,
    'tone' => 'info',
    'maxWidth' => 'max-w-lg',
])

<div id="{{ $id }}" class="sa-modal fixed inset-0 z-50 hidden items-center justify-center p-4"
     role="dialog" aria-modal="true" aria-labelledby="{{ $id }}-title" @if ($description) aria-describedby="{{ $id }}-description" @endif
     data-modal data-modal-tone="{{ $tone }}">
    <div class="sa-modal-backdrop" data-modal-close></div>
    <div class="sa-modal-panel relative z-10 w-full {{ $maxWidth }}">
        <div class="sa-modal-head">
            <span class="sa-modal-icon is-{{ $tone }}" data-modal-icon aria-hidden="true">
                <span class="material-symbols-outlined">{{ $icon }}</span>
            </span>
            <div class="min-w-0 flex-1">
                <h3 class="sa-modal-title" id="{{ $id }}-title">{{ $title }}</h3>
                @if ($description)
                    <p class="sa-modal-desc" id="{{ $id }}-description">{{ $description }}</p>
                @endif
            </div>
            <button type="button" class="sa-icon-btn sa-modal-close" data-modal-close aria-label="{{ __('superadmin.common.cancel') }}">
                <span class="material-symbols-outlined">close</span>
            </button>
        </div>
        <div class="sa-modal-body">
            <div id="{{ $id }}-error" class="hidden sa-modal-alert is-danger" role="alert"></div>
            {{ $slot }}
        </div>
    </div>
</div>
