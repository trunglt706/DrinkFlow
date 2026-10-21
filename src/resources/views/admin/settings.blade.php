<x-admin.layout :title="__('admin.room_settings_title')" active="settings" :room="$room">
    {{-- ── Header ─────────────────────────────────────────────────────────── --}}
    <div class="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-outline-variant/40">
        <div>
            <h1 class="text-2xl font-bold text-on-surface tracking-tight">{{ __('admin.room_settings_title') }}</h1>
            <p class="mt-1 text-xs text-outline">{{ __('admin.room_settings_subtitle') }}</p>
        </div>
        <div class="flex items-center gap-2.5">
            <a href="{{ route('admin.notification-channels.page', $room) }}"
                class="px-3.5 py-2 bg-surface-container hover:bg-surface-container-high border border-outline-variant text-on-surface rounded-lg text-xs font-semibold flex items-center gap-1.5 shadow-xs transition-colors no-underline">
                <span class="material-symbols-outlined text-[16px] text-primary">notifications</span>
                <span>{{ __('admin.webhook_channel_btn') }}</span>
            </a>
        </div>
    </div>

    {{-- ── Notice Notification Banner ─────────────────────────────────────── --}}
    <div id="notice" class="hidden mt-4 rounded-xl px-4 py-3 text-xs font-medium"></div>

    <div class="mt-6 grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
        {{-- ══════════════════════════════════════════════════════════════════
        CỘT TRÁI: CẤU HÌNH PHÒNG (một form, chia theo nhóm)
        ══════════════════════════════════════════════════════════════════ --}}
        <form id="room-settings-form" data-loading-form="true"
            data-msg-success="{{ __('admin.settings_saved_ok') }}"
            data-msg-error="{{ __('admin.error_generic') }}"
            data-loading-text="{{ __('admin.loading') }}"
            class="lg:col-span-7 space-y-5">

            {{-- 1. Truy cập phòng --}}
            <section class="bg-surface-container-lowest border border-outline-variant rounded-2xl shadow-xs overflow-hidden">
                <header class="flex items-center gap-3 px-5 py-4 border-b border-outline-variant/60 bg-surface-container-low">
                    <div class="w-9 h-9 rounded-lg bg-primary/10 text-primary flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[20px]">lock_open</span>
                    </div>
                    <div class="min-w-0">
                        <h2 class="font-bold text-sm text-on-surface">{{ __('admin.room_access_title') }}</h2>
                        <p class="text-[11px] text-outline">{{ __('admin.room_access_desc') }}</p>
                    </div>
                </header>
                <div class="p-5 space-y-5">
                    <div class="flex items-center gap-4">
                        <div class="min-w-0 flex-1">
                            <label for="set-room-public" class="block cursor-pointer text-xs font-bold text-on-surface">{{ __('admin.room_public_mode') }}</label>
                            <p class="mt-0.5 max-w-md text-[11px] text-outline leading-relaxed" id="room-public-hint"
                                data-hint-public="{{ __('admin.room_public_mode_hint') }}"
                                data-hint-private="{{ __('admin.room_private_mode_hint') }}">
                                {{ ($settings['is_public'] ?? true) ? __('admin.room_public_mode_hint') : __('admin.room_private_mode_hint') }}
                            </p>
                        </div>
                        <label class="relative inline-flex shrink-0 cursor-pointer">
                            <input type="checkbox" id="set-room-public" {{ ($settings['is_public'] ?? true) ? 'checked' : '' }} class="peer sr-only">
                            <span class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full bg-outline-variant transition-colors peer-checked:bg-primary peer-focus-visible:ring-2 peer-focus-visible:ring-primary/40 after:content-[''] after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition-transform peer-checked:after:translate-x-5"></span>
                        </label>
                    </div>

                    <div class="pt-5 border-t border-outline-variant/50">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="material-symbols-outlined text-[18px] text-primary">link</span>
                            <h3 class="text-xs font-bold text-on-surface">{{ __('admin.room_join_link') }}</h3>
                        </div>
                        <div class="flex items-center gap-2">
                            <code id="room-join-link"
                                class="min-w-0 flex-1 truncate rounded-lg border border-outline-variant/60 bg-surface px-3 py-2.5 text-xs text-on-surface-variant">{{ route('user.rooms.join.show', $room->slug) }}</code>
                            <button type="button" id="copy-room-join-link"
                                data-msg-copied="{{ __('admin.room_join_link_copied') }}"
                                data-msg-failed="{{ __('admin.copy_room_join_url_failed') }}"
                                data-tooltip="{{ __('admin.copy_room_join_link') }}"
                                aria-label="{{ __('admin.copy_room_join_link') }}"
                                class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary text-on-primary hover:bg-primary/90 transition-colors cursor-pointer">
                                <span class="material-symbols-outlined text-[18px]">content_copy</span>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            {{-- 1b. Quy tắc truy cập: domain email được tham gia, IP được phép / bị chặn (App\Services\Room\RoomAccessPolicy) --}}
            <section class="bg-surface-container-lowest border border-outline-variant rounded-2xl shadow-xs overflow-hidden" data-room-access-rules>
                <header class="flex items-center gap-3 px-5 py-4 border-b border-outline-variant/60 bg-surface-container-low">
                    <div class="w-9 h-9 rounded-lg bg-sky-50 text-sky-700 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[20px]">shield_lock</span>
                    </div>
                    <div class="min-w-0">
                        <h2 class="font-bold text-sm text-on-surface">{{ __('admin.room_access_rules_title') }}</h2>
                        <p class="text-[11px] text-outline">{{ __('admin.room_access_rules_desc') }}</p>
                    </div>
                </header>
                <div class="p-5 space-y-5">
                    <x-admin.tags-input id="set-allowed-email-domains" name="allowed_email_domains" validate="domain" icon="alternate_email"
                        :values="$settings['allowed_email_domains'] ?? []"
                        :label="__('admin.room_allowed_email_domains')"
                        :hint="__('admin.room_allowed_email_domains_hint')"
                        :placeholder="__('admin.room_allowed_email_domains_placeholder')" />

                    <div class="pt-5 border-t border-outline-variant/50">
                        <x-admin.tags-input id="set-allowed-ips" name="allowed_ips" validate="ip" icon="verified_user"
                            :values="$settings['allowed_ips'] ?? []"
                            :label="__('admin.room_allowed_ips')"
                            :hint="__('admin.room_allowed_ips_hint')"
                            :placeholder="__('admin.room_allowed_ips_placeholder')" />
                    </div>

                    <div class="pt-5 border-t border-outline-variant/50">
                        <x-admin.tags-input id="set-blocked-ips" name="blocked_ips" validate="ip" variant="block" icon="block"
                            :values="$settings['blocked_ips'] ?? []"
                            :label="__('admin.room_blocked_ips')"
                            :hint="__('admin.room_blocked_ips_hint')"
                            :placeholder="__('admin.room_blocked_ips_placeholder')" />
                    </div>

                    <p class="flex items-center gap-1.5 rounded-lg bg-surface-container-low px-3 py-2 text-[11px] text-on-surface-variant">
                        <span class="material-symbols-outlined text-[16px] text-outline">lan</span>
                        <span>{{ __('admin.room_access_current_ip', ['ip' => request()->ip()]) }}</span>
                    </p>
                </div>
            </section>

            {{-- 2. Mặc định chiến dịch --}}
            <section class="bg-surface-container-lowest border border-outline-variant rounded-2xl shadow-xs overflow-hidden">
                <header class="flex items-center justify-between gap-3 px-5 py-4 border-b border-outline-variant/60 bg-surface-container-low">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-[20px]">edit_note</span>
                        </div>
                        <div class="min-w-0">
                            <h2 class="font-bold text-sm text-on-surface">{{ __('admin.default_campaign_title') }}</h2>
                            <p class="text-[11px] text-outline">{{ __('admin.campaign_defaults_desc') }}</p>
                        </div>
                    </div>
                    <span class="px-2 py-0.5 bg-blue-50 border border-blue-200 text-blue-700 text-[10px] font-bold rounded shrink-0">{{ __('admin.dynamic_tags') }}</span>
                </header>
                <div class="p-5 space-y-5">
                    <div class="space-y-2">
                        <label for="set-template" class="block font-semibold text-xs text-on-surface">{{ __('admin.campaign_syntax_template') }}</label>
                        <input type="text" id="set-template"
                            value="{{ $settings['campaign_title_template'] ?? '[' . $room->name . '] Trà chiều & Cafe {date}' }}"
                            class="w-full h-10 px-3 bg-surface border border-outline-variant rounded-lg text-xs text-on-surface focus:border-primary focus:ring-1 focus:ring-primary outline-hidden transition-colors font-bold">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="text-[11px] text-outline">{{ __('admin.supported_variables') }}</span>
                            @foreach (['{date}', '{time}', '{day_of_week}', '{creator_name}'] as $tag)
                                <button type="button" onclick="insertTag('{{ $tag }}')"
                                    class="px-2 py-0.5 bg-surface-container hover:bg-surface-container-high rounded border border-outline-variant font-mono text-[11px] text-on-surface font-semibold transition-colors">{{ $tag }}</button>
                            @endforeach
                        </div>
                    </div>

                    <div class="space-y-2 pt-5 border-t border-outline-variant/50">
                        <label for="set-max-budget" class="flex items-center gap-2 font-semibold text-xs text-on-surface">
                            <span class="material-symbols-outlined text-[18px] text-amber-600">payments</span>
                            {{ __('admin.max_product_budget_ceiling') }}
                        </label>
                        <div class="relative">
                            <input type="text" inputmode="numeric" id="set-max-budget" data-format-currency="true"
                                value="{{ \App\Support\Helpers\FormatHelper::formatCurrency((int) ($settings['max_campaign_budget'] ?? 70000)) }}"
                                class="w-full h-10 px-3 bg-surface border border-outline-variant rounded-lg text-xs text-on-surface focus:border-primary focus:ring-1 focus:ring-primary outline-hidden transition-colors pr-16 font-mono font-bold text-primary">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[11px] font-bold text-outline">{{ __('admin.vnd_unit') }}</span>
                        </div>
                        <p class="text-[11px] text-outline">{{ __('admin.product_budget_limit_hint') }} <strong class="font-semibold text-on-surface">{{ \App\Support\Helpers\FormatHelper::formatCurrency((int) ($settings['max_campaign_budget'] ?? 70000)) }}</strong></p>
                    </div>
                </div>
            </section>

            {{-- 3. Chính sách công nợ --}}
            <section class="bg-surface-container-lowest border border-outline-variant rounded-2xl shadow-xs overflow-hidden">
                <header class="flex items-center gap-3 px-5 py-4 border-b border-outline-variant/60 bg-surface-container-low">
                    <div class="w-9 h-9 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[20px]">gavel</span>
                    </div>
                    <div class="min-w-0">
                        <h2 class="font-bold text-sm text-on-surface">{{ __('admin.spending_debt_policy_title') }}</h2>
                        <p class="text-[11px] text-outline">{{ __('admin.debt_policy_desc') }}</p>
                    </div>
                </header>
                <div class="p-5 space-y-5">
                    <div class="space-y-2">
                        <label for="set-debt-ceiling" class="block font-semibold text-xs text-on-surface">{{ __('admin.personal_debt_ceiling') }}</label>
                        <div class="relative">
                            <input type="text" inputmode="numeric" id="set-debt-ceiling" data-format-currency="true"
                                value="{{ \App\Support\Helpers\FormatHelper::formatCurrency((int) ($settings['personal_debt_ceiling'] ?? 150000)) }}"
                                class="w-full h-10 px-3 bg-surface border border-outline-variant rounded-lg text-xs text-on-surface focus:border-primary focus:ring-1 focus:ring-primary outline-hidden transition-colors pr-16 font-mono font-bold">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-[11px] font-bold text-outline">{{ __('admin.vnd_unit') }}</span>
                        </div>
                    </div>

                    <div class="flex items-center gap-4 pt-5 border-t border-outline-variant/50">
                        <label for="set-autolock-debt" class="min-w-0 flex-1 cursor-pointer select-none text-xs font-semibold text-on-surface">{{ __('admin.auto_lock_on_debt_limit') }}</label>
                        <label class="relative inline-flex shrink-0 cursor-pointer">
                            <input type="checkbox" id="set-autolock-debt" {{ ($settings['auto_lock_on_debt_limit'] ?? true) ? 'checked' : '' }} class="peer sr-only">
                            <span class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full bg-outline-variant transition-colors peer-checked:bg-primary peer-focus-visible:ring-2 peer-focus-visible:ring-primary/40 after:content-[''] after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition-transform peer-checked:after:translate-x-5"></span>
                        </label>
                    </div>
                    <div class="flex items-center gap-4 pt-5 border-t border-outline-variant/50">
                        <div class="min-w-0 flex-1">
                            <label for="set-auto-debt-reminders" class="block cursor-pointer select-none text-xs font-semibold text-on-surface">{{ __('debt_reminder.setting_label') }}</label>
                            <p class="mt-0.5 text-[11px] text-outline">{{ __('debt_reminder.setting_hint') }}</p>
                        </div>
                        <label class="relative inline-flex shrink-0 cursor-pointer">
                            <input type="checkbox" id="set-auto-debt-reminders" {{ ($settings[\App\Services\Debt\DebtAutoReminderService::SETTING_KEY] ?? true) ? 'checked' : '' }} class="peer sr-only">
                            <span class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer items-center rounded-full bg-outline-variant transition-colors peer-checked:bg-primary peer-focus-visible:ring-2 peer-focus-visible:ring-primary/40 after:content-[''] after:absolute after:left-0.5 after:top-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition-transform peer-checked:after:translate-x-5"></span>
                        </label>
                    </div>
                </div>
            </section>

            {{-- Save bar --}}
            <div class="flex justify-end">
                <button type="submit" id="save-campaign-settings-btn"
                    class="px-5 py-2.5 bg-primary hover:bg-primary/90 text-on-primary rounded-xl text-xs font-bold flex items-center gap-2 shadow-xs transition-all disabled:opacity-60 disabled:cursor-not-allowed">
                    <span class="material-symbols-outlined text-[18px]">save</span>
                    <span>{{ __('admin.save_changes_btn') }}</span>
                </button>
            </div>
        </form>

        {{-- ══════════════════════════════════════════════════════════════════
        CỘT PHẢI: TÀI KHOẢN THANH TOÁN
        ══════════════════════════════════════════════════════════════════ --}}
        <div class="lg:col-span-5 lg:sticky lg:top-6 bg-surface-container-lowest border border-outline-variant rounded-2xl shadow-xs overflow-hidden">
            <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-outline-variant/60 bg-surface-container-low">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[20px]">account_balance</span>
                    </div>
                    <div class="min-w-0">
                        <h2 class="font-bold text-sm text-on-surface">{{ __('admin.payment_accounts_title') }}</h2>
                        <p class="text-[11px] text-outline">{{ __('admin.payment_accounts_desc') }}</p>
                    </div>
                </div>
                <button type="button" onclick="openCreateAccountModal()"
                    class="px-3 py-1.5 bg-primary hover:bg-primary/90 text-on-primary rounded-lg text-xs font-bold flex items-center gap-1 shadow-xs transition-colors shrink-0">
                    <span class="material-symbols-outlined text-[16px]">add</span>
                    <span>{{ __('admin.add_new_btn') }}</span>
                </button>
            </div>

            <div class="p-5">
                {{-- Danh sách tài khoản --}}
            <div class="grid grid-cols-1 gap-3.5" id="accounts-container">
                @forelse($accounts as $acc)
                    @php
                        $accStatusVal = $acc->status instanceof \BackedEnum ? $acc->status->value : (string) ($acc->status ?? 'active');
                        $rawAccNumber = (string) $acc->getRawOriginal('account_number');
                        $bankDisplay = trim($acc->bank_code . ' - ' . ($acc->bank_name ?: $acc->bank_code));
                    @endphp
                    <div
                        class="p-4 bg-surface-container-low/40 hover:bg-surface-container-low/80 border border-outline-variant/70 hover:border-primary/40 rounded-xl transition-all flex flex-col justify-between gap-3 group">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3 min-w-0">
                                <div
                                    class="w-10 h-10 rounded-lg bg-primary/10 text-primary flex items-center justify-center font-bold font-mono text-xs shrink-0 border border-primary/20">
                                    {{ $acc->bank_code }}
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5 flex-wrap">
                                        <span
                                            class="font-bold text-sm text-on-surface font-mono tracking-wide">{{ $rawAccNumber }}</span>
                                        @if($acc->is_default)
                                            <span
                                                class="px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 text-[10px] font-bold border border-emerald-200">{{ __('admin.default_badge') }}</span>
                                        @endif
                                        <span
                                            class="px-1.5 py-0.5 rounded text-[10px] font-semibold border {{ $accStatusVal === 'active' ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-gray-100 text-gray-500 border-gray-200' }}">
                                            {{ __('admin.status_' . $accStatusVal) }}
                                        </span>
                                    </div>
                                    <div class="text-xs font-bold text-secondary uppercase mt-0.5 truncate">
                                        {{ $acc->account_name }}</div>
                                    {{-- Hiển thị: Mã bank - Tên bank --}}
                                    <div class="text-[11px] text-outline truncate font-medium mt-0.5">{{ $bankDisplay }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Action Buttons Toolbar --}}
                        <div class="pt-2 border-t border-outline-variant/40 flex items-center justify-between gap-2">
                            {{-- View VietQR Code Button --}}
                            <button type="button" data-qr-btn data-account-id="{{ $acc->id }}"
                                data-qr-endpoint="{{ route('admin.payment-accounts.qr', [$room, $acc]) }}"
                                class="px-2.5 py-1 bg-surface-container hover:bg-primary hover:text-on-primary text-on-surface rounded-md text-xs font-semibold flex items-center gap-1 border border-outline-variant transition-colors">
                                <span
                                    class="material-symbols-outlined text-[15px] text-primary group-hover:text-inherit">qr_code_2</span>
                                <span>{{ __('admin.view_qr') }}</span>
                            </button>

                            <div class="flex items-center gap-1">
                                {{-- Edit Button --}}
                                <button type="button"
                                    onclick="openEditAccountModal({{ $acc->id }}, '{{ $acc->bank_code }}', @js($acc->bank_name), '{{ $rawAccNumber }}', @js($acc->account_name), {{ $acc->is_default ? 'true' : 'false' }})"
                                    class="group/edit relative p-1.5 text-secondary hover:text-primary rounded-md hover:bg-surface-container transition-colors"
                                    title="{{ __('admin.edit') }}" aria-label="{{ __('admin.edit') }}">
                                    <span class="material-symbols-outlined text-[17px]">edit</span>
                                    <span role="tooltip"
                                        class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-[10px] font-medium text-white opacity-0 shadow-lg transition-opacity group-hover/edit:opacity-100 group-focus-visible/edit:opacity-100">{{ __('admin.edit') }}</span>
                                </button>
                                {{-- Delete Button --}}
                                <button type="button" onclick="openDeleteAccountModal({{ $acc->id }})"
                                    class="group/del relative p-1.5 text-secondary hover:text-rose-600 rounded-md hover:bg-surface-container transition-colors"
                                    title="{{ __('admin.delete') }}" aria-label="{{ __('admin.delete') }}">
                                    <span class="material-symbols-outlined text-[17px]">delete</span>
                                    <span role="tooltip"
                                        class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-[10px] font-medium text-white opacity-0 shadow-lg transition-opacity group-hover/del:opacity-100 group-focus-visible/del:opacity-100">{{ __('admin.delete') }}</span>
                                </button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="py-10 text-center text-outline border border-dashed border-outline-variant rounded-xl">
                        <span class="material-symbols-outlined text-4xl text-outline-variant">account_balance_wallet</span>
                        <p class="text-xs mt-1.5 font-medium">{{ __('admin.no_payment_accounts') }}</p>
                    </div>
                @endforelse
            </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════════════
    MODAL: Thêm / Chỉnh Sửa Tài Khoản Thanh Toán
    ══════════════════════════════════════════════════════════════════════════════ --}}
    <div id="payment-account-modal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
        <div class="w-full max-w-md rounded-2xl bg-surface-container-lowest shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-outline-variant">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[20px] text-primary">add_card</span>
                    <h3 id="payment-modal-title"
                        data-title-create="{{ __('admin.add_new_account') }}"
                        data-title-edit="{{ __('admin.edit_account') }}"
                        class="font-bold text-sm text-on-surface">
                        {{ __('admin.add_new_account') }}</h3>
                </div>
                <button type="button" onclick="closeAccountModal()"
                    class="p-1 rounded-md hover:bg-surface-container text-outline transition-colors">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>

            <form id="payment-account-form"
                data-msg-created="{{ __('admin.account_created_ok') }}"
                data-msg-updated="{{ __('admin.account_updated_ok') }}"
                data-msg-error="{{ __('admin.error_generic') }}"
                data-loading-text="{{ __('admin.loading') }}"
                class="p-5 space-y-4 text-xs">
                <input id="payment-account-id" type="hidden">

                {{-- Mã ngân hàng có chức năng search theo mã hoặc tên ngân hàng --}}
                <div>
                    <label class="block font-semibold text-on-surface mb-1">{{ __('admin.bank_code') }}:</label>
                    <select id="acc-bank-code" data-searchable="true"
                        data-placeholder="{{ __('admin.search_bank_placeholder') }}"
                        data-empty-text="{{ __('admin.no_options_found') }}"
                        class="w-full h-10 px-3 bg-surface border border-outline-variant rounded-lg text-on-surface font-semibold focus:border-primary focus:ring-1 focus:ring-primary"
                        required>
                        @foreach($banks ?? app(\App\Services\Common\BankService::class)->getAllBanks() as $bank)
                            <option value="{{ $bank['code'] }}" data-bank-name="{{ $bank['name'] }}">{{ $bank['code'] }} –
                                {{ $bank['short_name'] }} ({{ $bank['name'] }})</option>
                        @endforeach
                    </select>
                </div>

                {{-- Số tài khoản chỉ cho phép nhập số 0-9 --}}
                <div>
                    <label
                        class="block font-semibold text-on-surface mb-1">{{ __('admin.account_number_label') }}</label>
                    <input type="text" id="acc-number" inputmode="numeric" pattern="[0-9]*" maxlength="30"
                        placeholder="0011004382918"
                        class="w-full h-10 px-3 bg-surface border border-outline-variant rounded-lg font-mono font-bold text-on-surface focus:border-primary focus:ring-1 focus:ring-primary"
                        required>
                </div>

                <div>
                    <label
                        class="block font-semibold text-on-surface mb-1">{{ __('admin.account_holder_label') }}</label>
                    <input type="text" id="acc-name" placeholder="NGUYEN VAN A"
                        class="w-full h-10 px-3 bg-surface border border-outline-variant rounded-lg uppercase font-bold text-on-surface focus:border-primary focus:ring-1 focus:ring-primary"
                        required>
                </div>

                <div class="pt-1 flex items-center gap-2">
                    <input type="checkbox" id="acc-default" checked
                        class="rounded border-outline-variant text-primary focus:ring-primary h-4 w-4 cursor-pointer">
                    <label for="acc-default"
                        class="text-on-surface font-semibold cursor-pointer select-none">{{ __('admin.set_as_default') }}</label>
                </div>

                <div class="pt-3 flex justify-end gap-2 border-t border-outline-variant/60">
                    <button type="button" onclick="closeAccountModal()"
                        class="px-4 py-2 border border-outline-variant rounded-lg font-semibold text-on-surface hover:bg-surface-container transition-colors">
                        {{ __('admin.cancel') }}
                    </button>
                    <button id="payment-submit-btn" type="submit"
                        class="px-5 py-2 bg-primary hover:bg-primary/90 text-on-primary rounded-lg font-bold shadow-sm transition-colors flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[16px]">save</span>
                        <span>{{ __('admin.save_changes_btn') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════════════
    MODAL: Xem Mã VietQR Code (Hiệu ứng Snake Border 4.8s)
    ══════════════════════════════════════════════════════════════════════════════ --}}
    <div id="payment-qr-modal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
        <div class="relative w-full max-w-sm rounded-2xl bg-surface-container-lowest shadow-2xl overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-outline-variant">
                <div class="flex items-center gap-2">
                    <span class="material-symbols-outlined text-[18px] text-primary">qr_code_2</span>
                    <h3 class="font-bold text-sm text-on-surface">{{ __('admin.view_qr') }}</h3>
                </div>
                <button type="button" data-close-qr
                    class="p-1 rounded-md hover:bg-surface-container text-outline transition-colors">
                    <span class="material-symbols-outlined text-[18px]">close</span>
                </button>
            </div>

            <div id="qr-modal-body" class="flex flex-col items-center justify-center px-5 py-6 gap-4">
                {{-- Loading state --}}
                <div id="qr-loading" class="flex flex-col items-center gap-3 py-8">
                    <div class="w-10 h-10 border-4 border-primary/20 border-t-primary rounded-full animate-spin"></div>
                    <p class="text-xs text-outline">{{ __('admin.loading') }}</p>
                </div>

                {{-- QR Content --}}
                <div id="qr-canvas-wrap" class="hidden flex-col items-center gap-4 w-full">
                    <div id="qr-bank-badge"
                        class="px-3 py-1 rounded-full bg-primary/10 text-primary text-xs font-bold tracking-wider uppercase">
                    </div>

                    {{-- QR Card + Snake Border SVG overlay --}}
                    <div id="qr-card-container" class="relative p-4">
                        <svg id="qr-snake-svg" class="absolute inset-0 w-full h-full pointer-events-none z-10"
                            viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                            <rect class="qr-snake-track" x="1" y="1" width="98" height="98" rx="6" ry="6"
                                pathLength="100"
                                style="fill:none;stroke:#e2e8f0;stroke-width:2.5;stroke-linecap:round;" />
                            <rect id="qr-snake-rect" class="qr-snake-anim" x="1" y="1" width="98" height="98" rx="6"
                                ry="6" pathLength="100"
                                style="fill:none;stroke:#16a34a;stroke-width:3;stroke-linecap:round;stroke-dasharray:5 95;stroke-dashoffset:0;" />
                        </svg>
                        <canvas id="qr-canvas" class="hidden rounded-lg relative z-0 mx-auto"></canvas>
                    </div>

                    <div id="qr-amount-label" class="hidden text-2xl font-bold text-primary tracking-tight"></div>

                    <div id="qr-info" class="text-center space-y-0.5">
                        <p id="qr-acc-name" class="text-sm font-bold text-on-surface uppercase tracking-wide"></p>
                        <p id="qr-acc-number" class="text-xs text-outline font-mono tracking-widest"></p>
                        <div id="qr-desc-wrap" class="hidden mt-2">
                            <p class="text-[10px] text-outline uppercase tracking-wider mb-1">
                                {{ __('admin.transfer_content') }}</p>
                            <span id="qr-desc-value"
                                class="text-xs font-bold font-mono bg-surface border border-outline-variant rounded px-2 py-1"></span>
                        </div>
                    </div>

                    <p class="text-[10px] text-outline text-center">{{ __('admin.qr_scan_note') }}</p>
                </div>

                {{-- Error state --}}
                <div id="qr-error" class="hidden flex-col items-center gap-3 py-8 text-center">
                    <span class="material-symbols-outlined text-4xl text-outline-variant">error</span>
                    <p class="text-xs text-outline">{{ __('admin.qr_error') }}</p>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════════════
    MODAL: Xác Nhận Xóa Tài Khoản
    ══════════════════════════════════════════════════════════════════════════════ --}}
    <div id="payment-delete-modal"
        data-msg-deleted="{{ __('admin.account_deleted_ok') }}"
        data-msg-error="{{ __('admin.error_generic') }}"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
        <div class="w-full max-w-sm rounded-2xl bg-surface-container-lowest shadow-2xl overflow-hidden">
            <div class="px-5 py-4 border-b border-outline-variant flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px] text-error">delete_forever</span>
                <h3 class="font-bold text-sm text-on-surface">{{ __('admin.delete_account_title') }}</h3>
            </div>
            <div class="px-5 py-4">
                <p class="text-xs text-outline leading-relaxed">{{ __('admin.delete_account_confirm') }}</p>
                <p class="mt-2 text-xs leading-relaxed text-warning">{{ __('admin.delete_account_live_campaign_hint') }}
                </p>
            </div>
            <div class="flex justify-end gap-2 px-5 py-4 border-t border-outline-variant">
                <button type="button" data-close-delete
                    class="px-4 py-2 border border-outline-variant rounded-lg text-xs font-semibold text-on-surface hover:bg-surface-container transition-colors">
                    {{ __('admin.cancel') }}
                </button>
                <button type="button" id="confirm-payment-delete"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-error text-on-error text-xs font-bold hover:bg-error/90 transition-colors">
                    <span class="material-symbols-outlined text-[16px]">delete</span>
                    <span>{{ __('admin.delete_confirm_btn') }}</span>
                </button>
            </div>
        </div>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/qrcode@1.4.4/build/qrcode.min.js"></script>
    @endpush
</x-admin.layout>