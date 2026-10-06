@extends('superadmin.layout', ['title' => __('superadmin.notifications.title'), 'active' => 'notifications'])
@section('content')
    <x-superadmin.page-header :eyebrow="__('superadmin.common.system_messaging')" :title="__('superadmin.notifications.title')">
            <button class="sa-button" type="button" data-modal-open="create-channel-modal">
                <span class="material-symbols-outlined text-[16px]">add_alert</span>{{ __('superadmin.notifications.add_channel') }}
            </button>
    </x-superadmin.page-header>
    <div id="notice" class="sa-notice"></div>
    {{-- Inbox: only notifications addressed to the signed-in admin account (PageController::notifications). --}}
    <section id="inbox" class="sa-card sa-section" data-sa-inbox>
        <div class="sa-section-header">
            <div>
                <h2>{{ __('superadmin.inbox.title') }}</h2>
                <p>{{ __('superadmin.inbox.description', ['count' => $inboxUnreadCount]) }}</p>
            </div>
            <div class="superadmin-actions">
                <form method="GET" action="{{ route('superadmin.notifications.page') }}#inbox" class="superadmin-actions">
                    @if(($filters['search'] ?? '') !== '')<input type="hidden" name="q" value="{{ $filters['search'] }}">@endif
                    @if($filters['channelType'] !== '')<input type="hidden" name="channel_type" value="{{ $filters['channelType'] }}">@endif
                    <select name="inbox_status" class="sa-input" aria-label="{{ __('superadmin.common.status') }}">
                        <option value="">{{ __('superadmin.inbox.status_all') }}</option>
                        <option value="{{ \App\Services\Notification\SuperadminNotificationService::FILTER_UNREAD }}" @selected($filters['inboxStatus'] === \App\Services\Notification\SuperadminNotificationService::FILTER_UNREAD)>{{ __('superadmin.inbox.status_unread') }}</option>
                        <option value="{{ \App\Services\Notification\SuperadminNotificationService::FILTER_READ }}" @selected($filters['inboxStatus'] === \App\Services\Notification\SuperadminNotificationService::FILTER_READ)>{{ __('superadmin.inbox.status_read') }}</option>
                    </select>
                    <x-superadmin.search-input name="inbox_q" :value="$filters['inboxSearch']" placeholder="{{ __('superadmin.inbox.search') }}" />
                    {{-- Received date, same picker as the audit log. --}}
                    <x-admin.date-range-filter id="inbox-date-range" :dateFrom="$filters['inboxFrom']" :dateTo="$filters['inboxTo']" />
                    <button class="sa-button secondary" type="submit"><span class="material-symbols-outlined text-[16px]">filter_alt</span>{{ __('superadmin.common.filter') }}</button>
                </form>
                @if($inboxUnreadCount > 0)
                    <button class="sa-button secondary" type="button" data-sa-inbox-mark-all><span class="material-symbols-outlined text-[16px]">done_all</span>{{ __('superadmin.inbox.mark_all_read') }}</button>
                @endif
            </div>
        </div>
        <div class="sa-inbox-list">
            @forelse($inbox as $notification)
                @php($presentation = $inboxPresentations[$notification->id] ?? ['title' => $notification->title, 'body' => $notification->body, 'icon' => 'notifications'])
                <div class="sa-inbox-row" data-sa-inbox-row data-id="{{ $notification->id }}" @if(!$notification->read_at) data-unread @endif>
                    <span class="sa-bell-icon"><span class="material-symbols-outlined">{{ $presentation['icon'] }}</span></span>
                    <div class="sa-inbox-text">
                        <strong>@if(!empty($presentation['link']))<a href="{{ $presentation['link'] }}">{{ $presentation['title'] }}</a>@else{{ $presentation['title'] }}@endif</strong>
                        @if($presentation['body'])<p>{{ $presentation['body'] }}</p>@endif
                        <small>
                            @if($notification->room)
                                <a href="{{ route('superadmin.rooms.detail.page', $notification->room) }}">{{ $notification->room->name }}</a> ·
                            @endif
                            <time datetime="{{ $notification->created_at?->toIso8601String() }}" title="{{ $notification->created_at?->format('d/m/Y H:i') }}">{{ $notification->created_at?->diffForHumans() }}</time>
                        </small>
                    </div>
                    @if(!$notification->read_at)
                        <button class="sa-button secondary" type="button" data-sa-inbox-read><span class="material-symbols-outlined text-[16px]">done</span>{{ __('superadmin.inbox.mark_read') }}</button>
                    @endif
                </div>
            @empty
                @if($filters['inboxStatus'] !== '' || $filters['inboxSearch'] !== '')
                    <x-superadmin.empty-state icon="search_off" :title="__('superadmin.inbox.no_results_title')" :description="__('superadmin.inbox.no_results_description')" />
                @else
                    <x-superadmin.empty-state icon="notifications_off" :title="__('superadmin.inbox.empty_title')" :description="__('superadmin.inbox.empty_description')" />
                @endif
            @endforelse
        </div>
        {{ $inbox->fragment('inbox')->links('components.superadmin.pagination') }}
    </section>
    <section class="sa-card sa-section">
        <div class="sa-section-header">
            <div>
                <h2>{{ __('superadmin.notifications.system_channels') }}</h2>
                <p>{{ __('superadmin.common.accounts_count', ['count' => $channels->total()]) }}</p>
            </div>
            <form method="GET" class="superadmin-actions">
                @if($filters['inboxStatus'] !== '')<input type="hidden" name="inbox_status" value="{{ $filters['inboxStatus'] }}">@endif
                @if($filters['inboxSearch'] !== '')<input type="hidden" name="inbox_q" value="{{ $filters['inboxSearch'] }}">@endif
                @if($filters['inboxFrom'] !== '')<input type="hidden" name="date_from" value="{{ $filters['inboxFrom'] }}">@endif
                @if($filters['inboxTo'] !== '')<input type="hidden" name="date_to" value="{{ $filters['inboxTo'] }}">@endif
                <x-superadmin.search-input :value="$filters['search'] ?? ''" placeholder="{{ __('superadmin.notifications.search') }}" />
                <select name="channel_type" class="sa-input" aria-label="{{ __('superadmin.common.type') }}">
                    <option value="">{{ __('superadmin.notifications.all_channel_types') }}</option>
                    @foreach (\App\Models\SystemNotificationChannel::TYPES as $typeValue => $typeLabel)
                        <option value="{{ $typeValue }}" @selected($filters['channelType'] === $typeValue)>{{ $typeLabel }}</option>
                    @endforeach
                </select>
                <button class="sa-button secondary" type="submit"><span class="material-symbols-outlined text-[16px]">filter_alt</span>{{ __('superadmin.common.filter') }}</button>
            </form>
        </div>
        <div class="sa-table-wrap">
            <table class="sa-table">
                <thead>
                    <tr>
                        <th class="col-primary">{{ __('superadmin.notifications.channel') }}</th>
                        <th>{{ __('superadmin.common.type') }}</th>
                        <th class="col-status">{{ __('superadmin.common.status') }}</th>
                        <th class="col-mono">{{ __('superadmin.notifications.credential') }}</th>
                        <th class="text-right col-actions">{{ __('superadmin.common.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($channels as $channel)
                        <tr>
                            <td class="col-primary"><strong>{{ $channel->name }}</strong></td>
                            <td>{{ $channel->type }}</td>
                            <td class="col-status"><x-superadmin.status-pill :status="$channel->status" /></td>
                            <td class="col-mono">••••••••</td>
                            <td class="col-actions">
                                <x-superadmin.row-actions>
                                    @can('settings.manage')
                                        <button class="sa-button secondary" type="button" data-action="test-channel" data-channel-id="{{ $channel->id }}"><span class="material-symbols-outlined text-[16px]">network_ping</span>{{ __('superadmin.notifications.test_ping') }}</button>
                                    @endcan
                                    <x-slot:menu>
                                        <button class="sa-menu-item is-danger" type="button" data-action="delete-channel" data-channel-id="{{ $channel->id }}" role="menuitem"><span class="material-symbols-outlined">delete</span>{{ __('superadmin.common.delete') }}</button>
                                    </x-slot:menu>
                                </x-superadmin.row-actions>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5">
                            @if(($filters['search'] ?? '') !== '')
                                <x-superadmin.empty-state icon="search_off" :bordered="false" :title="__('superadmin.notifications.no_results_title')" :description="__('superadmin.notifications.no_results_description')" />
                            @else
                                <x-superadmin.empty-state icon="notifications_off" :bordered="false" :title="__('superadmin.notifications.no_channels_title')" :description="__('superadmin.notifications.no_channels_description')" />
                            @endif
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $channels->links('components.superadmin.pagination') }}
    </section>

    <x-superadmin.modal id="create-channel-modal" icon="add_alert" tone="create" :title="__('superadmin.notifications.add_channel')" :description="__('superadmin.modals.channel.description')">
        <form id="create-channel-form" class="space-y-4">
            @csrf
            <x-superadmin.modal-guide :title="__('superadmin.modals.channel.guide_title')">
                <li>{{ __('superadmin.modals.channel.guide_type') }}</li>
                <li>{{ __('superadmin.modals.channel.guide_secret') }}</li>
                <li>{{ __('superadmin.modals.channel.guide_test') }}</li>
            </x-superadmin.modal-guide>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="create-channel-name" class="sa-label">{{ __('superadmin.notifications.prompt_name') }} <span class="text-error">*</span></label>
                    <input type="text" id="create-channel-name" name="name" required maxlength="255" class="sa-field">
                    <p class="sa-hint">{{ __('superadmin.modals.channel.name_hint') }}</p>
                </div>
                <div>
                    <label for="create-channel-type" class="sa-label">{{ __('superadmin.common.type') }} <span class="text-error">*</span></label>
                    <select id="create-channel-type" name="type" required class="sa-field" data-channel-type>
                        @foreach (\App\Models\SystemNotificationChannel::TYPES as $typeValue => $typeLabel)
                            <option value="{{ $typeValue }}">{{ $typeLabel }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            {{-- Credential fields per channel type; resources/js/superadmin/pages/notifications.js shows the matching group. --}}
            <div class="space-y-4" data-channel-fields="slack">
                <div>
                    <label for="channel-slack-webhook-url" class="sa-label">{{ __('superadmin.modals.channel.webhook_url') }} <span class="text-error">*</span></label>
                    <input type="url" id="channel-slack-webhook-url" name="config[webhook_url]" autocomplete="off" class="sa-field font-mono" data-channel-required="1">
                    <p class="sa-hint">{{ __('superadmin.modals.channel.slack_webhook_hint') }}</p>
                </div>
            </div>
            <div class="space-y-4" data-channel-fields="telegram" hidden>
                <div>
                    <label for="channel-bot-token" class="sa-label">{{ __('superadmin.modals.channel.bot_token') }} <span class="text-error">*</span></label>
                    <x-superadmin.password-input id="channel-bot-token" name="config[bot_token]" autocomplete="off" data-channel-required="1" />
                    <p class="sa-hint">{{ __('superadmin.modals.channel.bot_token_hint') }}</p>
                </div>
                <div>
                    <label for="channel-chat-id" class="sa-label">{{ __('superadmin.modals.channel.chat_id') }} <span class="text-error">*</span></label>
                    <input type="text" id="channel-chat-id" name="config[chat_id]" autocomplete="off" class="sa-field font-mono" data-channel-required="1">
                    <p class="sa-hint">{{ __('superadmin.modals.channel.chat_id_hint') }}</p>
                </div>
            </div>
            <div class="space-y-4" data-channel-fields="chatwork" hidden>
                <div>
                    <label for="channel-api-token" class="sa-label">{{ __('superadmin.modals.channel.api_token') }} <span class="text-error">*</span></label>
                    <x-superadmin.password-input id="channel-api-token" name="config[api_token]" autocomplete="off" data-channel-required="1" />
                    <p class="sa-hint">{{ __('superadmin.modals.channel.api_token_hint') }}</p>
                </div>
                <div>
                    <label for="channel-room-id" class="sa-label">{{ __('superadmin.modals.channel.room_id') }} <span class="text-error">*</span></label>
                    <input type="text" id="channel-room-id" name="config[room_id]" autocomplete="off" class="sa-field font-mono" data-channel-required="1">
                    <p class="sa-hint">{{ __('superadmin.modals.channel.room_id_hint') }}</p>
                </div>
            </div>
            <div class="space-y-4" data-channel-fields="webhook" hidden>
                <div>
                    <label for="channel-webhook-url" class="sa-label">{{ __('superadmin.modals.channel.webhook_url') }} <span class="text-error">*</span></label>
                    <input type="url" id="channel-webhook-url" name="config[webhook_url]" autocomplete="off" class="sa-field font-mono" data-channel-required="1">
                    <p class="sa-hint">{{ __('superadmin.modals.channel.endpoint_hint') }}</p>
                </div>
                <div>
                    <label for="channel-secret-token" class="sa-label">{{ __('superadmin.modals.channel.secret_token') }}</label>
                    <x-superadmin.password-input id="channel-secret-token" name="config[secret_token]" autocomplete="off" data-channel-required="0" />
                    <p class="sa-hint">{{ __('superadmin.modals.channel.secret_token_hint') }}</p>
                </div>
            </div>
            <div class="sa-modal-footer">
                <button type="button" class="sa-button secondary" data-modal-close>{{ __('superadmin.common.cancel') }}</button>
                <button type="submit" class="sa-button">
                    <span class="material-symbols-outlined text-[16px]">add_alert</span>{{ __('superadmin.notifications.add_channel') }}
                </button>
            </div>
        </form>
    </x-superadmin.modal>
@endsection
@push('scripts')
    <x-superadmin.page-script name="notifications"
        :i18n="['confirm_delete' => __('superadmin.notifications.confirm_delete'), 'deleted' => __('superadmin.common.delete'), 'delete_description' => __('superadmin.modals.confirm.channel_delete'), 'test_sent' => __('superadmin.notifications.test_sent')]"
        :urls="['store' => route('superadmin.notifications.store'), 'test' => route('superadmin.notifications.test', ['channel' => '__ID__'])]" />
@endpush
