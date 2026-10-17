{{-- Confirm dialog for "Khóa chiến dịch" / "Mở khóa đặt món"; driven by resources/js/admin/campaign-ordering-lock.js --}}
<div id="campaign-ordering-lock-modal"
     data-i18n="{{ json_encode([
         'lock' => [
             'title' => __('admin.campaign_lock_confirm_title'),
             'desc' => __('admin.campaign_lock_confirm_desc'),
             'confirm' => __('admin.campaign_lock_action'),
         ],
         'unlock' => [
             'title' => __('admin.campaign_unlock_confirm_title'),
             'desc' => __('admin.campaign_unlock_confirm_desc'),
             'confirm' => __('admin.campaign_unlock_action'),
         ],
         'processing' => __('admin.campaign_lock_processing'),
         'failed' => __('admin.campaign_lock_failed'),
     ], JSON_UNESCAPED_UNICODE) }}"
     class="fixed inset-0 z-50 !m-0 hidden items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
     role="dialog" aria-modal="true" aria-labelledby="campaign-ordering-lock-title">
    <div data-lock-modal-backdrop class="absolute inset-0"></div>
    <div class="relative z-10 w-full max-w-md bg-surface-container-lowest border border-outline-variant rounded-2xl p-6 shadow-2xl">
        <div class="flex items-start gap-3.5 mb-4">
            <div data-lock-modal-icon-wrap class="w-10 h-10 rounded-xl border flex items-center justify-center shrink-0">
                <span data-lock-modal-icon class="material-symbols-outlined text-[22px]">lock</span>
            </div>
            <div class="flex-1 min-w-0">
                <h3 id="campaign-ordering-lock-title" data-lock-modal-title class="font-bold text-base text-on-surface"></h3>
                <p data-lock-modal-desc class="text-xs text-outline mt-1"></p>
            </div>
            <button type="button" data-lock-modal-close class="text-outline hover:text-on-surface p-1 rounded-lg hover:bg-surface-container transition-colors" aria-label="{{ __('admin.close_modal_btn') }}">
                <span class="material-symbols-outlined text-[20px]">close</span>
            </button>
        </div>

        <label class="flex items-start gap-2.5 rounded-xl border border-outline-variant/60 bg-surface-container-low p-3.5 text-xs text-on-surface cursor-pointer">
            <input type="checkbox" data-lock-modal-notify checked class="mt-0.5 rounded border-outline-variant">
            <span>
                <span class="font-semibold block">{{ __('admin.campaign_lock_notify_label') }}</span>
                <span class="text-outline">{{ __('admin.campaign_lock_notify_hint') }}</span>
            </span>
        </label>

        <p data-lock-modal-error class="hidden mt-3 rounded-lg border border-rose-200 bg-rose-50 px-3 py-2 text-xs text-rose-700 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-300" role="alert"></p>

        <div class="flex items-center justify-end gap-2.5 mt-5">
            <button type="button" data-lock-modal-close class="px-4 py-2 text-xs font-semibold rounded-xl border border-outline-variant text-on-surface hover:bg-surface-container transition-colors">
                {{ __('admin.cancel') }}
            </button>
            <button type="button" data-lock-modal-confirm class="px-4 py-2 text-xs font-semibold rounded-xl text-white shadow-xs inline-flex items-center gap-1.5 transition-all disabled:opacity-60 disabled:cursor-wait">
                <span data-lock-modal-confirm-icon class="material-symbols-outlined text-[16px]">lock</span>
                <span data-lock-modal-confirm-label></span>
            </button>
        </div>
    </div>
</div>
