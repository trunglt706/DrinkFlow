{{-- Single shared confirmation dialog. Pages call window.openSuperadminConfirm({ message, description, tone, title, icon,
     confirmLabel, confirmIcon, irreversible, onConfirm }) (resources/js/superadmin/modal.js) instead of confirm();
     server-rendered forms use data-confirm (+ data-confirm-tone / -title / -description / -label / -icon / -irreversible).
     `tone` picks the header icon, default title, description style and button colour:
     danger (delete / destroy) | warning (block / suspend / force) | info (retry / apply / approve) | success (restore / unblock). --}}
@php
    $confirmTones = [
        'danger' => ['icon' => 'delete_forever', 'title' => __('superadmin.modals.confirm.title_danger'), 'label' => __('superadmin.common.delete'), 'button_icon' => 'delete'],
        'warning' => ['icon' => 'warning', 'title' => __('superadmin.modals.confirm.title_warning'), 'label' => __('superadmin.modals.confirm.confirm'), 'button_icon' => 'check'],
        'info' => ['icon' => 'help', 'title' => __('superadmin.modals.confirm.title_info'), 'label' => __('superadmin.modals.confirm.confirm'), 'button_icon' => 'check'],
        'success' => ['icon' => 'check_circle', 'title' => __('superadmin.modals.confirm.title_success'), 'label' => __('superadmin.modals.confirm.confirm'), 'button_icon' => 'check'],
    ];
@endphp
<x-superadmin.modal id="confirm-modal" icon="delete_forever" tone="danger" :title="$confirmTones['danger']['title']" max-width="max-w-md">
    <div data-confirm-tones="{{ json_encode($confirmTones, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) }}" hidden></div>
    <p id="confirm-modal-message" class="sa-confirm-message"></p>
    <div id="confirm-modal-description" class="hidden">
        <div class="sa-modal-alert is-warning" data-confirm-description-box>
            <span class="material-symbols-outlined" aria-hidden="true" data-confirm-description-icon>info</span>
            <span id="confirm-modal-description-text"></span>
        </div>
    </div>
    <p id="confirm-modal-irreversible" class="sa-confirm-irreversible hidden">
        <span class="material-symbols-outlined" aria-hidden="true">history_toggle_off</span>{{ __('superadmin.modals.confirm.irreversible') }}
    </p>
    <div id="confirm-modal-error" class="hidden sa-modal-alert is-danger" role="alert"></div>
    <div class="sa-modal-footer">
        <button type="button" class="sa-button secondary" data-modal-close>
            {{ __('superadmin.common.cancel') }}
        </button>
        <button type="button" id="confirm-modal-submit" class="sa-button danger">
            <span class="material-symbols-outlined text-[16px]" id="confirm-modal-submit-icon" data-default="delete">delete</span>
            <span id="confirm-modal-submit-label" data-default="{{ __('superadmin.common.delete') }}">{{ __('superadmin.common.delete') }}</span>
        </button>
    </div>
</x-superadmin.modal>
