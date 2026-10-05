{{-- Logout confirmation for the superadmin console, opened by the account menu via [data-modal-open="superadmin-logout-modal"]. --}}
<x-superadmin.modal id="superadmin-logout-modal" icon="logout" tone="info" :title="__('admin.logout_modal_title')" :description="__('admin.logout_modal_desc')" max-width="max-w-md">
    <x-superadmin.modal-guide :title="__('superadmin.modals.logout.guide_title')">
        <li>{{ __('superadmin.modals.logout.guide_session') }}</li>
        <li>{{ __('superadmin.modals.logout.guide_unsaved') }}</li>
    </x-superadmin.modal-guide>
    <form method="POST" action="{{ route('superadmin.logout') }}" class="sa-modal-footer">
        @csrf
        <button type="button" class="sa-button secondary" data-modal-close>
            {{ __('admin.logout_modal_cancel') }}
        </button>
        <button type="submit" class="sa-button danger">
            <span class="material-symbols-outlined text-[16px]">logout</span>{{ __('admin.logout_modal_confirm') }}
        </button>
    </form>
</x-superadmin.modal>
