<x-admin.layout :title="__('admin.webhook_channels_title')" active="settings" :room="$room">
    <!-- Header & Action Ribbon -->
    <div class="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-outline-variant/40">
        <div>
            <h1 class="text-2xl font-bold text-on-surface tracking-tight">{{ __('admin.webhook_channels_title') }}</h1>
        </div>
        <div>
            <a href="{{ route('admin.settings.page', $room) }}" class="px-3.5 py-2 bg-surface-container hover:bg-surface-container-high border border-outline-variant text-on-surface rounded text-xs font-semibold flex items-center gap-1.5 shadow-xs transition-colors no-underline">
                <span class="material-symbols-outlined text-[16px]">arrow_back</span>
                <span>{{ __('admin.general_settings_btn') }}</span>
            </a>
        </div>
    </div>

    <!-- Notice Notification Banner -->
    <div id="notice" class="hidden mb-4 rounded-xl px-4 py-3 text-xs font-medium"
         data-notification-saved="{{ __('admin.notification_channel_saved') }}"
         data-notification-save-failed="{{ __('admin.notification_channel_save_failed') }}"
         data-notification-test-sent="{{ __('admin.notification_channel_test_sent') }}"
         data-notification-test-failed="{{ __('admin.notification_channel_test_failed') }}"
         data-notification-server-error="{{ __('admin.notification_channel_server_error') }}"
         data-notification-delete-confirm="{{ __('admin.notification_channel_delete_confirm') }}"
         data-notification-delete-failed="{{ __('admin.notification_channel_delete_failed') }}"
         data-notification-edit="{{ __('admin.edit') }}"
         data-notification-connect-new="{{ __('admin.connect_new_bot') }}"
         data-notification-save-webhook="{{ __('admin.save_webhook') }}"
         data-processing="{{ __('admin.processing') }}"></div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left: Configured Channels List -->
        <div class="lg:col-span-2 space-y-4">
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-xs">
                <div class="p-4 border-b border-outline-variant flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[20px] text-primary">sensors</span>
                        <h2 class="font-bold text-sm text-on-surface">{{ __('admin.active_webhooks') }}</h2>
                    </div>
                    <span class="text-xs text-outline font-mono">{{ __('admin.channels_count_unit', ['count' => $channels->count()]) }}</span>
                </div>

                <div class="divide-y divide-outline-variant/50">
                    @forelse($channels as $ch)
                        @php
                            $chStatusVal = is_array($ch) ? ($ch['status'] ?? 'enabled') : ($ch->status instanceof \BackedEnum ? $ch->status->value : (string) ($ch->status ?? 'enabled'));
                            $chTypeVal = is_array($ch) ? ($ch['type'] ?? 'webhook') : (string) ($ch->type ?? 'webhook');
                            $chName = is_array($ch) ? ($ch['name'] ?? ucfirst($chTypeVal)) : ($ch->name ?? ucfirst($chTypeVal));
                            $chId = is_array($ch) ? $ch['id'] : $ch->id;
                            $chConfigured = is_array($ch) ? ($ch['configured'] ?? false) : true;
                        @endphp
                        <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-surface-container-low/40 transition-colors" data-notification-channel>
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 rounded bg-primary/10 text-primary flex items-center justify-center font-bold text-sm shrink-0">
                                    <span class="material-symbols-outlined text-[20px]">
                                        {{ match($chTypeVal) { 'telegram' => 'send', 'slack' => 'tag', 'chatwork' => 'chat', default => 'webhook' } }}
                                    </span>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-sm text-on-surface">{{ $chName }}</span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold uppercase bg-surface-container text-on-surface border border-outline-variant">
                                            {{ $chTypeVal }}
                                        </span>
                                        <span class="px-2 py-0.5 rounded text-[10px] font-semibold border {{ $chStatusVal === 'enabled' || $chStatusVal === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-gray-100 text-gray-500' }}">
                                            {{ __('admin.status_' . $chStatusVal) }}
                                        </span>
                                    </div>
                                    <div class="text-xs text-outline mt-0.5">
                                        @if($chConfigured)
                                            <span class="text-emerald-700 font-medium">✓ {{ __('admin.configured_status') }}</span>
                                        @else
                                            <span class="text-rose-700 font-medium">⚠ {{ __('admin.not_configured_status') }}</span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <button type="button" data-channel-edit="{{ $chId }}" onclick="editChannel({{ $chId }}, @js(['id' => $chId, 'name' => $chName, 'type' => $chTypeVal]))" class="relative group p-1.5 text-secondary hover:text-primary rounded hover:bg-surface-container transition-colors cursor-pointer" title="{{ __('admin.edit_channel') }}" aria-label="{{ __('admin.edit_channel') }}">
                                    <span class="material-symbols-outlined text-[16px]">edit</span>
                                    <span role="tooltip" class="pointer-events-none absolute bottom-full left-1/2 z-30 mb-2 -translate-x-1/2 whitespace-nowrap rounded-md bg-[#0b1c30] px-2 py-1 text-[10px] font-medium text-white opacity-0 shadow-lg transition-all duration-150 group-hover:opacity-100 group-hover:-translate-y-0.5">{{ __('admin.edit_channel') }}</span>
                                </button>
                                <button type="button" data-channel-test="{{ $chId }}" onclick="openTestModal({{ $chId }}, @js(['id' => $chId, 'name' => $chName, 'type' => $chTypeVal]))" class="relative group px-3 py-1.5 bg-primary/10 hover:bg-primary/20 text-primary rounded text-xs font-semibold flex items-center gap-1.5 transition-colors cursor-pointer" title="{{ __('admin.test_notification_modal_title') }}" aria-label="{{ __('admin.test_notification_modal_title') }}">
                                    <span class="material-symbols-outlined text-[15px]">science</span>
                                    <span>{{ __('admin.test_ping') }}</span>
                                    <span role="tooltip" class="pointer-events-none absolute bottom-full left-1/2 z-30 mb-2 -translate-x-1/2 whitespace-nowrap rounded-md bg-[#0b1c30] px-2 py-1 text-[10px] font-medium text-white opacity-0 shadow-lg transition-all duration-150 group-hover:opacity-100 group-hover:-translate-y-0.5">{{ __('admin.test_notification_modal_title') }}</span>
                                </button>
                                <button type="button" data-channel-delete="{{ $chId }}" onclick="deleteChannel({{ $chId }})" class="relative group p-1.5 text-secondary hover:text-rose-600 rounded hover:bg-surface-container transition-colors cursor-pointer" title="{{ __('admin.btn_delete_channel') }}" aria-label="{{ __('admin.btn_delete_channel') }}">
                                    <span class="material-symbols-outlined text-[16px]">delete</span>
                                    <span role="tooltip" class="pointer-events-none absolute bottom-full left-1/2 z-30 mb-2 -translate-x-1/2 whitespace-nowrap rounded-md bg-[#0b1c30] px-2 py-1 text-[10px] font-medium text-white opacity-0 shadow-lg transition-all duration-150 group-hover:opacity-100 group-hover:-translate-y-0.5">{{ __('admin.btn_delete_channel') }}</span>
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="py-12 text-center text-outline">
                            <span class="material-symbols-outlined text-4xl text-outline-variant">notifications_off</span>
                            <p class="text-xs mt-1">{{ __('admin.no_webhooks_found') }}</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Right: Add Webhook Channel Form -->
        <div class="space-y-4">
            <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-5 shadow-xs">
                <div class="flex items-center justify-between gap-2 pb-3 border-b border-outline-variant mb-4">
                    <div class="flex items-center gap-2">
                        <span id="channel-form-icon" class="material-symbols-outlined text-[20px] text-primary">add_link</span>
                        <h2 id="channel-form-title" class="font-bold text-sm text-on-surface">{{ __('admin.connect_new_bot') }}</h2>
                    </div>
                    <button id="channel-edit-cancel" type="button" class="hidden text-xs font-semibold text-outline hover:text-on-surface">{{ __('admin.notification_channel_cancel') }}</button>
                </div>

                <form id="add-channel-form" data-no-loading class="space-y-3 text-xs">
                    <div>
                        <label class="block font-semibold text-on-surface mb-1">{{ __('admin.channel_name_label') }}</label>
                        <input type="text" id="ch-name" placeholder="{{ __('admin.channel_name_placeholder') }}" class="w-full h-9 px-3 bg-surface border border-outline-variant rounded font-medium text-xs text-on-surface" required>
                    </div>

                    <div>
                        <label class="block font-semibold text-on-surface mb-1">{{ __('admin.webhook_form_platform') }}</label>
                        <select id="ch-type" class="w-full h-9 px-3 bg-surface border border-outline-variant rounded text-on-surface font-semibold" required>
                            <option value="telegram">{{ __('admin.platform_telegram') }}</option>
                            <option value="slack">{{ __('admin.platform_slack') }}</option>
                            <option value="chatwork">{{ __('admin.platform_chatwork') }}</option>
                            <option value="webhook">{{ __('admin.platform_webhook') }}</option>
                        </select>
                    </div>

                    <!-- Platform Fields: Telegram -->
                    <div id="platform-telegram" class="platform-config-fields space-y-3">
                        <div>
                            <label class="block font-semibold text-on-surface mb-1">{{ __('admin.telegram_bot_token') }}</label>
                            <div class="relative">
                                <input type="password" id="ch-tg-token" autocomplete="new-password" placeholder="123456789:ABCdefGhIJKlmNoPQRsTUVwxyZ" class="w-full h-9 pl-3 pr-9 bg-surface border border-outline-variant rounded font-mono text-xs text-on-surface">
                                <button type="button" data-secret-toggle="ch-tg-token" aria-pressed="false" title="{{ __('admin.toggle_secret_visibility') }}" aria-label="{{ __('admin.toggle_secret_visibility') }}" class="absolute right-2 top-1/2 -translate-y-1/2 inline-flex items-center text-outline hover:text-primary transition-colors cursor-pointer">
                                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">visibility</span>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block font-semibold text-on-surface mb-1">{{ __('admin.telegram_chat_id') }}</label>
                            <input type="text" id="ch-tg-chat-id" placeholder="-1001234567890" class="w-full h-9 px-3 bg-surface border border-outline-variant rounded font-mono text-xs text-on-surface">
                        </div>
                    </div>

                    <!-- Platform Fields: Slack -->
                    <div id="platform-slack" class="platform-config-fields space-y-3 hidden">
                        <div>
                            <label class="block font-semibold text-on-surface mb-1">{{ __('admin.slack_webhook_url') }}</label>
                            <input type="url" id="ch-slack-url" placeholder="https://hooks.slack.com/services/..." class="w-full h-9 px-3 bg-surface border border-outline-variant rounded font-mono text-xs text-on-surface">
                        </div>
                    </div>

                    <!-- Platform Fields: Chatwork -->
                    <div id="platform-chatwork" class="platform-config-fields space-y-3 hidden">
                        <div>
                            <label class="block font-semibold text-on-surface mb-1">{{ __('admin.chatwork_api_token') }}</label>
                            <div class="relative">
                                <input type="password" id="ch-cw-token" autocomplete="new-password" placeholder="abcdef0123456789abcdef0123456789" class="w-full h-9 pl-3 pr-9 bg-surface border border-outline-variant rounded font-mono text-xs text-on-surface">
                                <button type="button" data-secret-toggle="ch-cw-token" aria-pressed="false" title="{{ __('admin.toggle_secret_visibility') }}" aria-label="{{ __('admin.toggle_secret_visibility') }}" class="absolute right-2 top-1/2 -translate-y-1/2 inline-flex items-center text-outline hover:text-primary transition-colors cursor-pointer">
                                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">visibility</span>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block font-semibold text-on-surface mb-1">{{ __('admin.chatwork_room_id') }}</label>
                            <input type="text" id="ch-cw-room-id" placeholder="12345678" class="w-full h-9 px-3 bg-surface border border-outline-variant rounded font-mono text-xs text-on-surface">
                        </div>
                    </div>

                    <!-- Platform Fields: Webhook -->
                    <div id="platform-webhook" class="platform-config-fields space-y-3 hidden">
                        <div>
                            <label class="block font-semibold text-on-surface mb-1">{{ __('admin.webhook_endpoint_url') }}</label>
                            <input type="url" id="ch-wh-url" placeholder="https://api.yourdomain.com/webhook" class="w-full h-9 px-3 bg-surface border border-outline-variant rounded font-mono text-xs text-on-surface">
                        </div>
                        <div>
                            <label class="block font-semibold text-on-surface mb-1">{{ __('admin.webhook_secret_token') }} ({{ __('admin.optional') }})</label>
                            <div class="relative">
                                <input type="password" id="ch-wh-secret" autocomplete="new-password" placeholder="secret-token-key" class="w-full h-9 pl-3 pr-9 bg-surface border border-outline-variant rounded font-mono text-xs text-on-surface">
                                <button type="button" data-secret-toggle="ch-wh-secret" aria-pressed="false" title="{{ __('admin.toggle_secret_visibility') }}" aria-label="{{ __('admin.toggle_secret_visibility') }}" class="absolute right-2 top-1/2 -translate-y-1/2 inline-flex items-center text-outline hover:text-primary transition-colors cursor-pointer">
                                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">visibility</span>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="pt-3">
                        <button id="channel-form-submit" type="submit" class="w-full h-9 bg-primary hover:bg-primary/90 text-on-primary rounded font-bold transition-colors cursor-pointer inline-flex items-center justify-center gap-1.5 shadow-xs">
                            <span class="material-symbols-outlined text-[16px]">save</span>
                            <span>{{ __('admin.save_webhook') }}</span>
                        </button>
                    </div>
                </form>
            </div>

            <aside id="platform-integration-guide" class="bg-primary/5 border border-primary/20 rounded-xl p-5 shadow-xs" aria-live="polite">
                <div class="flex items-center gap-2 pb-3 border-b border-primary/15 mb-3">
                    <span class="material-symbols-outlined text-[20px] text-primary">help</span>
                    <h2 class="font-bold text-sm text-on-surface">{{ __('admin.integration_guide_title') }}</h2>
                </div>

                @foreach(['telegram', 'slack', 'chatwork', 'webhook'] as $platform)
                    <div data-platform-guide="{{ $platform }}" class="platform-integration-guide {{ $platform === 'telegram' ? '' : 'hidden' }}">
                        <div class="mb-2 text-xs font-bold text-primary">{{ __('admin.platform_' . $platform) }}</div>
                        <ol class="space-y-2 list-decimal pl-4 text-xs leading-5 text-on-surface-variant">
                            @for($step = 1; $step <= 3; $step++)
                                <li>{{ __('admin.integration_guide_' . $platform . '_step_' . $step) }}</li>
                            @endfor
                        </ol>
                    </div>
                @endforeach
            </aside>
        </div>
    </div>

    <!-- Test Template Modal -->
    <div id="channel-test-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs" role="dialog" aria-modal="true" aria-labelledby="channel-test-modal-title">
        <div class="w-full max-w-lg rounded-2xl bg-surface-container-lowest border border-outline-variant shadow-xl overflow-hidden">
            <div class="p-6 border-b border-outline-variant/50">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-[22px]">science</span>
                        </div>
                        <div>
                            <h2 id="channel-test-modal-title" class="text-base font-bold text-on-surface">{{ __('admin.test_notification_modal_title') }}</h2>
                            <p class="text-xs text-on-surface-variant">{{ __('admin.test_notification_modal_desc') }}</p>
                        </div>
                    </div>
                    <button id="channel-test-close" type="button" class="p-1 text-secondary hover:text-on-surface rounded-lg hover:bg-surface-container transition-colors cursor-pointer">
                        <span class="material-symbols-outlined text-[20px]">close</span>
                    </button>
                </div>
            </div>

            <div class="p-6 space-y-4 text-xs">
                <!-- Channel Info Tag -->
                <div class="flex items-center justify-between p-3 rounded-lg bg-surface-container-low border border-outline-variant/60">
                    <div class="flex items-center gap-2">
                        <span class="font-semibold text-on-surface">{{ __('admin.channel_name_label') }}:</span>
                        <span id="test-target-channel-name" class="font-bold text-primary">...</span>
                    </div>
                    <span id="test-target-channel-type" class="px-2 py-0.5 rounded text-[10px] font-semibold uppercase bg-surface-container-high text-on-surface border border-outline-variant">
                        ...
                    </span>
                </div>

                <!-- Template Selector -->
                <div>
                    <label class="block font-semibold text-on-surface mb-1.5">{{ __('admin.test_notification_select_template') }}</label>
                    <select id="test-template-select" class="w-full h-10 px-3 bg-surface border border-outline-variant rounded-lg text-on-surface font-semibold text-xs focus:border-primary focus:ring-1 focus:ring-primary outline-hidden">
                        <option value="test_ping">{{ __('admin.template_test_ping') }}</option>
                        <option value="campaign.created">{{ __('admin.template_campaign_created') }}</option>
                        <option value="campaign.closed">{{ __('admin.template_campaign_closed') }}</option>
                        <option value="campaign.cancelled">{{ __('admin.template_campaign_cancelled') }}</option>
                        <option value="debt.reminder">{{ __('admin.template_debt_reminder') }}</option>
                    </select>
                </div>

                <!-- Live Preview Box -->
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="font-semibold text-on-surface">{{ __('admin.test_notification_preview') }}</label>
                        <span class="text-[10px] font-mono text-outline uppercase" id="test-preview-format-label">HTML / MRKDWN</span>
                    </div>
                    <div id="test-preview-box" class="p-4 rounded-xl bg-slate-900 text-slate-100 font-mono text-[11px] leading-relaxed border border-slate-700 whitespace-pre-wrap max-h-56 overflow-y-auto select-text shadow-inner"
                         data-previews="{{ json_encode($testPreviews, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}">
                        ...
                    </div>
                </div>

                <!-- Rate limit note -->
                <div class="flex items-center gap-1.5 text-[11px] text-outline">
                    <span class="material-symbols-outlined text-[14px]">info</span>
                    <span>{{ __('admin.test_notification_rate_limit_note') }}</span>
                </div>
            </div>

            <div class="px-6 py-4 bg-surface-container-low flex justify-end gap-3 border-t border-outline-variant">
                <button id="channel-test-cancel" type="button" class="px-4 py-2 text-xs font-semibold text-on-surface hover:bg-surface-container-high rounded-lg cursor-pointer">{{ __('admin.notification_channel_cancel') }}</button>
                <button id="channel-test-submit" type="button" class="px-4 py-2 text-xs font-semibold text-white bg-primary hover:bg-primary/90 rounded-lg inline-flex items-center gap-1.5 shadow-xs transition-colors cursor-pointer">
                    <span class="material-symbols-outlined text-[16px]">send</span>
                    <span>{{ __('admin.test_notification_send_btn') }}</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Channel Delete Modal -->
    <div id="channel-delete-modal" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs" role="dialog" aria-modal="true" aria-labelledby="channel-delete-modal-title">
        <div class="w-full max-w-md rounded-2xl bg-surface-container-lowest border border-outline-variant shadow-xl overflow-hidden">
            <div class="p-6">
                <div class="w-11 h-11 rounded-xl bg-rose-50 text-rose-600 flex items-center justify-center mb-4">
                    <span class="material-symbols-outlined">delete_forever</span>
                </div>
                <h2 id="channel-delete-modal-title" class="text-base font-bold text-on-surface">{{ __('admin.notification_channel_delete_title') }}</h2>
                <p class="mt-2 text-sm text-on-surface-variant">{{ __('admin.notification_channel_delete_confirm') }}</p>
            </div>
            <div class="px-6 py-4 bg-surface-container-low flex justify-end gap-3 border-t border-outline-variant">
                <button id="channel-delete-cancel" type="button" class="px-4 py-2 text-xs font-semibold text-on-surface hover:bg-surface-container-high rounded-lg cursor-pointer">{{ __('admin.notification_channel_cancel') }}</button>
                <button id="channel-delete-confirm" type="button" class="px-4 py-2 text-xs font-semibold text-white bg-rose-600 hover:bg-rose-700 rounded-lg inline-flex items-center gap-1.5 cursor-pointer">
                    <span class="material-symbols-outlined text-[16px]">delete</span>
                    <span>{{ __('admin.delete_confirm_btn') }}</span>
                </button>
            </div>
        </div>
    </div>
</x-admin.layout>
