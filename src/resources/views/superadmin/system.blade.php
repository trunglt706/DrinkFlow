@extends('superadmin.layout', ['title' => __('superadmin.system.title'), 'active' => 'system'])
@section('content')
    <x-superadmin.page-header :eyebrow="__('superadmin.common.infra_security')" :title="__('superadmin.system.title')" :description="__('superadmin.system.description')" />
    <div id="notice" class="sa-notice"></div>
    <section class="sa-card sa-section">
        <div class="sa-section-header">
            <div>
                <h2>{{ __('superadmin.dashboard.system_health') }}</h2>
                <p>{{ __('superadmin.dashboard.health_description') }}</p>
            </div>
            <button class="sa-button secondary" type="button" data-modal-open="mail-test-modal"><span class="material-symbols-outlined text-[16px]">outgoing_mail</span>{{ __('superadmin.system.send_test_mail') }}</button>
        </div>
        <div id="system-health" class="sa-health-list">
            <x-superadmin.empty-state loading :title="__('superadmin.common.loading_title')" :description="__('superadmin.common.loading_description')" />
        </div>
    </section>
    @php
        $fieldLabel = 'flex flex-col gap-1.5 text-[13px] font-semibold text-on-surface';
        $fieldInput = 'sa-input !min-w-0 w-full';
        $fieldHint = 'text-xs font-normal text-outline';
    @endphp
    {{-- Mail & storage overrides: a filled field wins, an empty field keeps the .env value. The page
         script fills values, placeholders and the per-field source hint ([data-config-hint]). --}}
    <section class="sa-card sa-section">
        <div class="sa-section-header">
            <div>
                <h2>{{ __('superadmin.system.service_config_title') }}</h2>
                <p>{{ __('superadmin.system.service_config_description') }}</p>
            </div>
        </div>
        {{-- Tabs: the active one is kept in the URL hash (#mail / #storage) so links can open a tab directly. --}}
        <div class="sa-tabs" role="tablist" aria-label="{{ __('superadmin.system.service_config_title') }}" data-config-tabs>
            <button type="button" class="sa-tab" role="tab" id="config-tab-mail" aria-controls="config-panel-mail" aria-selected="true" data-tab="mail">
                <span class="material-symbols-outlined">mail</span>{{ __('superadmin.system.mail_config_title') }}
            </button>
            <button type="button" class="sa-tab" role="tab" id="config-tab-storage" aria-controls="config-panel-storage" aria-selected="false" tabindex="-1" data-tab="storage">
                <span class="material-symbols-outlined">hard_drive</span>{{ __('superadmin.system.storage_config_title') }}
            </button>
        </div>
        <div class="sa-tab-panel" role="tabpanel" id="config-panel-mail" aria-labelledby="config-tab-mail">
        <p class="sa-tab-description">{{ __('superadmin.system.mail_config_description') }}</p>
        <form id="mail-config-form" class="space-y-4" data-config-form="{{ route('superadmin.system.mail') }}">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <label class="{{ $fieldLabel }}">{{ __('superadmin.system.mail_config_mailer') }}
                    <select name="mailer" class="{{ $fieldInput }}">
                        <option value="" data-env-option>{{ __('superadmin.system.config_use_env') }}</option>
                        @foreach (\App\Services\System\SystemConfigService::MAILERS as $mailer)
                            <option value="{{ $mailer }}">{{ __('superadmin.system.mail_config_mailer_'.$mailer) }}</option>
                        @endforeach
                    </select>
                    <span class="{{ $fieldHint }}" data-config-hint="mailer"></span>
                </label>
                <label class="{{ $fieldLabel }}">{{ __('superadmin.system.mail_config_host') }}
                    <input name="host" type="text" maxlength="255" autocomplete="off" class="{{ $fieldInput }}">
                    <span class="{{ $fieldHint }}" data-config-hint="host"></span>
                </label>
                <label class="{{ $fieldLabel }}">{{ __('superadmin.system.mail_config_port') }}
                    <input name="port" type="number" min="1" max="65535" class="{{ $fieldInput }}">
                    <span class="{{ $fieldHint }}" data-config-hint="port"></span>
                </label>
                <label class="{{ $fieldLabel }}">{{ __('superadmin.system.mail_config_scheme') }}
                    <select name="scheme" class="{{ $fieldInput }}">
                        <option value="" data-env-option>{{ __('superadmin.system.config_use_env') }}</option>
                        @foreach (\App\Services\System\SystemConfigService::SMTP_SCHEMES as $scheme)
                            <option value="{{ $scheme }}">{{ __('superadmin.system.mail_config_scheme_'.$scheme) }}</option>
                        @endforeach
                    </select>
                    <span class="{{ $fieldHint }}" data-config-hint="scheme"></span>
                </label>
                <label class="{{ $fieldLabel }}">{{ __('superadmin.system.mail_config_username') }}
                    <input name="username" type="text" maxlength="255" autocomplete="off" class="{{ $fieldInput }}">
                    <span class="{{ $fieldHint }}" data-config-hint="username"></span>
                </label>
                <div class="{{ $fieldLabel }}">
                    <label for="mail-config-password">{{ __('superadmin.system.mail_config_password') }}</label>
                    <x-superadmin.password-input id="mail-config-password" name="password" autocomplete="new-password" maxlength="255"
                        placeholder="{{ __('superadmin.system.mail_config_password_keep') }}" />
                    <span class="{{ $fieldHint }}" data-config-hint="password"></span>
                    <label class="hidden items-center gap-1.5 font-normal text-xs text-on-surface-variant" data-clear-secret="password">
                        <input type="checkbox" name="clear_password" value="1">{{ __('superadmin.system.mail_config_password_clear') }}
                    </label>
                </div>
                <label class="{{ $fieldLabel }}">{{ __('superadmin.system.mail_config_from_address') }}
                    <input name="from_address" type="email" maxlength="255" autocomplete="off" class="{{ $fieldInput }}">
                    <span class="{{ $fieldHint }}" data-config-hint="from_address"></span>
                </label>
                <label class="{{ $fieldLabel }}">{{ __('superadmin.system.mail_config_from_name') }}
                    <input name="from_name" type="text" maxlength="255" autocomplete="off" class="{{ $fieldInput }}">
                    <span class="{{ $fieldHint }}" data-config-hint="from_name"></span>
                </label>
            </div>
            <p class="hidden text-xs font-semibold text-error" data-config-error></p>
            <div class="sa-form-actions">
                <button class="sa-button" type="submit"><span class="material-symbols-outlined text-[16px]">save</span>{{ __('superadmin.system.save_mail_config') }}</button>
                <button class="sa-button secondary" type="button" data-modal-open="mail-test-modal"><span class="material-symbols-outlined text-[16px]">outgoing_mail</span>{{ __('superadmin.system.send_test_mail') }}</button>
            </div>
        </form>
        </div>
        <div class="sa-tab-panel" role="tabpanel" id="config-panel-storage" aria-labelledby="config-tab-storage" hidden>
        <p class="sa-tab-description">{{ __('superadmin.system.storage_config_description') }}</p>
        <form id="storage-config-form" class="space-y-4" data-config-form="{{ route('superadmin.system.storage') }}">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:max-w-2xl">
                <label class="{{ $fieldLabel }}">{{ __('superadmin.system.storage_config_disk') }}
                    <select name="disk" class="{{ $fieldInput }}">
                        <option value="" data-env-option>{{ __('superadmin.system.config_use_env') }}</option>
                        @foreach ($storageDisks as $disk)
                            <option value="{{ $disk }}">{{ $disk }}</option>
                        @endforeach
                    </select>
                    <span class="{{ $fieldHint }}" data-config-hint="disk"></span>
                </label>
                <label class="{{ $fieldLabel }}">{{ __('superadmin.system.storage_config_quota') }}
                    <input name="quota_mb" type="number" min="1" max="{{ \App\Http\Requests\UpdateStorageSettingsRequest::QUOTA_MAX_MB }}" class="{{ $fieldInput }}">
                    <span class="{{ $fieldHint }}" data-config-hint="quota_mb"></span>
                    <span class="{{ $fieldHint }}">{{ __('superadmin.system.storage_config_quota_hint') }}</span>
                </label>
            </div>
            <p class="hidden text-xs font-semibold text-error" data-config-error></p>
            <div class="sa-form-actions">
                <button class="sa-button" type="submit"><span class="material-symbols-outlined text-[16px]">save</span>{{ __('superadmin.system.save_storage_config') }}</button>
            </div>
        </form>
        </div>
    </section>
    <section class="sa-card sa-section">
        <div class="sa-section-header">
            <div>
                <h2>{{ __('superadmin.system.maintenance_mode') }}</h2>
                <p>{{ __('superadmin.system.maintenance_description') }}</p>
            </div>
        </div>
        {{-- data-no-loading: the spinner belongs to the confirm modal, not to this form's first submit. --}}
        <form id="maintenance-form" class="sa-health-list" data-no-loading>
            <label class="sa-health-row">
                <span><strong>{{ __('superadmin.system.enable_maintenance') }}</strong><small>{{ __('superadmin.system.superadmin_access') }}</small></span>
                <input id="maintenance-enabled" type="checkbox">
            </label>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 sm:max-w-xl">
                <label for="maintenance-starts" class="flex flex-col gap-1 text-xs font-semibold">{{ __('superadmin.system.starts_at') }}
                    <input id="maintenance-starts" class="sa-input !min-w-0 w-full !py-2" type="datetime-local">
                </label>
                <label for="maintenance-ends" class="flex flex-col gap-1 text-xs font-semibold">{{ __('superadmin.system.ends_at') }}
                    <input id="maintenance-ends" class="sa-input !min-w-0 w-full !py-2" type="datetime-local">
                </label>
            </div>
            <div class="sa-form-actions">
                <button class="sa-button" type="submit"><span class="material-symbols-outlined text-[16px]">construction</span>{{ __('superadmin.system.save_maintenance') }}</button>
            </div>
        </form>
    </section>
    <section class="sa-card sa-section">
        <div class="sa-section-header">
            <div>
                <h2>{{ __('superadmin.system.danger_zone') }}</h2>
                <p>{{ __('superadmin.system.reset_description') }}</p>
            </div>
            <button class="sa-button danger" type="button" data-modal-open="reset-system-modal"><span class="material-symbols-outlined text-[16px]">restart_alt</span>{{ __('superadmin.system.reset') }}</button>
        </div>
    </section>

    <x-superadmin.modal id="mail-test-modal" icon="outgoing_mail" tone="info" :title="__('superadmin.system.send_test_mail')" :description="__('superadmin.system.mail_test_modal_description', ['driver' => config('mail.default')])" max-width="max-w-md">
        <form id="mail-test-form" class="space-y-4">
            @csrf
            <x-superadmin.modal-guide :title="__('superadmin.modals.mail_test.guide_title')">
                <li>{{ __('superadmin.modals.mail_test.guide_send') }}</li>
                <li>{{ __('superadmin.modals.mail_test.guide_check') }}</li>
            </x-superadmin.modal-guide>
            <div>
                <label for="mail-test-email" class="sa-label">{{ __('superadmin.system.mail_test_email') }} <span class="text-error">*</span></label>
                <input type="email" id="mail-test-email" name="email" required maxlength="255" autocomplete="email" value="{{ request()->user('superadmin')->email }}"
                    class="sa-field">
                <p class="sa-hint">{{ __('superadmin.modals.mail_test.email_hint') }}</p>
            </div>
            <div>
                <label for="mail-test-message" class="sa-label">{{ __('superadmin.system.mail_test_message') }}</label>
                <textarea id="mail-test-message" name="message" rows="5" maxlength="{{ \App\Http\Requests\SendTestMailRequest::MESSAGE_MAX_LENGTH }}"
                    placeholder="{{ __('superadmin.system.mail_test_body', ['app' => config('app.name')]) }}"
                    class="sa-field"></textarea>
                <p class="sa-hint">{{ __('superadmin.system.mail_test_message_hint') }}</p>
            </div>
            <div class="sa-modal-footer">
                <button type="button" class="sa-button secondary" data-modal-close>{{ __('superadmin.common.cancel') }}</button>
                <button type="submit" class="sa-button">
                    <span class="material-symbols-outlined text-[16px]">send</span>{{ __('superadmin.system.mail_test_send') }}
                </button>
            </div>
        </form>
    </x-superadmin.modal>

    <x-superadmin.modal id="reset-system-modal" icon="restart_alt" tone="danger" :title="__('superadmin.system.reset')" :description="__('superadmin.system.reset_modal_description')" max-width="max-w-md">
        <form id="reset-system-form" class="space-y-4">
            @csrf
            <div class="sa-modal-alert is-danger">
                <span class="material-symbols-outlined" aria-hidden="true">report</span>
                <span>{{ __('superadmin.system.reset_warning') }}</span>
            </div>
            <x-superadmin.modal-guide :title="__('superadmin.modals.reset_system.guide_title')" tone="warning">
                <li>{{ __('superadmin.modals.reset_system.guide_backup') }}</li>
                <li>{{ __('superadmin.modals.reset_system.guide_unlock') }}</li>
            </x-superadmin.modal-guide>
            <div>
                <label for="reset-system-password" class="sa-label">{{ __('superadmin.system.reset_password_label') }} <span class="text-error">*</span></label>
                <x-superadmin.password-input id="reset-system-password" name="password" autocomplete="current-password" required />
            </div>
            <div>
                <label for="reset-system-phrase" class="sa-label">{{ __('superadmin.system.reset_phrase_label', ['phrase' => $resetPhrase]) }} <span class="text-error">*</span></label>
                <input type="text" id="reset-system-phrase" name="phrase" required autocomplete="off" spellcheck="false" placeholder="{{ $resetPhrase }}"
                    class="sa-field font-mono">
            </div>
            <div class="sa-modal-footer">
                <button type="button" class="sa-button secondary" data-modal-close>{{ __('superadmin.common.cancel') }}</button>
                <button type="submit" disabled class="sa-button danger disabled:opacity-50 disabled:cursor-not-allowed">
                    <span class="material-symbols-outlined text-[16px]">restart_alt</span>{{ __('superadmin.system.reset') }}
                </button>
            </div>
        </form>
    </x-superadmin.modal>

    {{-- Empty states cloned by the page script, so every string stays translated through __(). --}}
    <template id="tpl-load-failed"><x-superadmin.empty-state icon="error" :title="__('superadmin.common.load_failed_title')" description="" /></template>
@endsection
@push('scripts')
    <x-superadmin.page-script name="system" :i18n="[
        'config' => [
            'system' => __('superadmin.system.config_source_system'), 'env' => __('superadmin.system.config_source_env'),
            'envValue' => __('superadmin.system.config_env_value'), 'envEmpty' => __('superadmin.system.config_env_empty'),
            'envSecretSet' => __('superadmin.system.config_env_secret_set'), 'envSecretEmpty' => __('superadmin.system.config_env_secret_empty'),
            'useEnv' => __('superadmin.system.config_use_env'),
        ],
        'health' => [
            'database' => __('superadmin.dashboard.database'), 'queue' => __('superadmin.dashboard.queue'), 'socket' => 'Socket.IO',
            'mail' => __('superadmin.dashboard.mail'), 'storage' => __('superadmin.dashboard.storage'), 'supervisor' => __('superadmin.dashboard.supervisor'),
        ],
        'confirm_maintenance_enable' => __('superadmin.system.confirm_maintenance_enable'),
        'confirm_maintenance_disable' => __('superadmin.system.confirm_maintenance_disable'),
        'maintenance_enable_description' => __('superadmin.system.maintenance_enable_description'),
        'maintenance_disable_description' => __('superadmin.system.maintenance_disable_description'),
        'save_maintenance' => __('superadmin.system.save_maintenance'), 'maintenance_saved' => __('superadmin.system.maintenance_saved'),
        'reset_complete' => __('superadmin.system.reset_complete'),
    ]" :urls="[
        'index' => route('superadmin.system.index'), 'maintenance' => route('superadmin.system.maintenance.update'),
        'mail_test' => route('superadmin.system.mail-test'), 'reset' => route('superadmin.system.reset'), 'reset_phrase' => $resetPhrase,
    ]" />
@endpush
