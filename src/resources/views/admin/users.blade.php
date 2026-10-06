<x-admin.layout :title="__('admin.users_directory')" active="users" :room="$room">
    <!-- Header & Action Ribbon -->
    <div class="flex flex-wrap items-center justify-between gap-4 pb-4 border-b border-outline-variant/40">
        <div>
            <h1 class="text-2xl font-bold text-on-surface tracking-tight">{{ __('admin.users_directory') }}</h1>
        </div>
        <div class="flex items-center gap-2.5">
            <button type="button" id="btn-open-create-user-modal"
                class="px-3.5 py-2 bg-primary hover:bg-primary-container text-on-primary rounded-lg text-xs font-semibold flex items-center gap-1.5 shadow-xs transition-colors cursor-pointer">
                <span class="material-symbols-outlined text-[16px]">person_add</span>
                <span>{{ __('admin.btn_add_user') }}</span>
            </button>
        </div>
    </div>

    <!-- Notice Notification Banner -->
    <div id="notice" class="hidden mb-4 rounded-xl px-4 py-3 text-xs font-medium"></div>

    <!-- 4 Member KPI Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 shadow-xs">
            <div class="flex items-center justify-between text-outline">
                <span
                    class="text-[11px] font-mono uppercase tracking-wider font-semibold">{{ __('admin.total_members') }}</span>
                <span class="material-symbols-outlined text-[20px] text-primary">group</span>
            </div>
            <div class="text-2xl font-bold font-mono text-on-surface mt-2">{{ $totalMembers ?? 0 }}</div>
            <div class="text-[11px] text-outline mt-1">{{ __('admin.kpi_total_members_desc') }}</div>
        </div>

        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 shadow-xs">
            <div class="flex items-center justify-between text-outline">
                <span
                    class="text-[11px] font-mono uppercase tracking-wider font-semibold">{{ __('admin.active_members') }}</span>
                <span class="material-symbols-outlined text-[20px] text-emerald-600">verified_user</span>
            </div>
            <div class="text-2xl font-bold font-mono text-emerald-600 mt-2">{{ $activeMembers ?? 0 }}</div>
            <div class="text-[11px] text-emerald-700 mt-1">{{ __('admin.kpi_active_members_desc') }}</div>
        </div>

        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 shadow-xs">
            <div class="flex items-center justify-between text-outline">
                <span
                    class="text-[11px] font-mono uppercase tracking-wider font-semibold">{{ __('admin.blocked_members') }}</span>
                <span class="material-symbols-outlined text-[20px] text-rose-600">block</span>
            </div>
            <div class="text-2xl font-bold font-mono text-rose-600 mt-2">{{ $blockedMembers ?? 0 }}</div>
            <div class="text-[11px] text-rose-700 mt-1">{{ __('admin.kpi_blocked_members_desc') }}</div>
        </div>

        <div class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 shadow-xs">
            <div class="flex items-center justify-between text-outline">
                <span
                    class="text-[11px] font-mono uppercase tracking-wider font-semibold">{{ __('admin.debt_members') }}</span>
                <span class="material-symbols-outlined text-[20px] text-amber-600">pending_actions</span>
            </div>
            <div class="text-2xl font-bold font-mono text-amber-600 mt-2">{{ $debtMembers ?? 0 }}</div>
            <div class="text-[11px] text-amber-700 mt-1">{{ __('admin.kpi_debt_members_desc') }}</div>
        </div>
    </div>

    <!-- Search & Filter Toolbar -->
    <form id="users-filter-form" data-skeleton-on-submit method="GET"
        action="{{ route('admin.room-users.page', $room) }}"
        class="bg-surface-container-lowest border border-outline-variant rounded-xl p-4 flex flex-wrap items-center justify-between gap-3 shadow-xs">
        {{-- Mobile (below lg): search row, then status + filter / clear / reload sharing one full-width row. --}}
        <div class="flex items-center gap-3 flex-1 min-w-[280px] max-lg:basis-full">
            <x-admin.search-input id="user-search" name="q" :value="$filters['q'] ?? ''"
                placeholder="{{ __('admin.search_users_placeholder') }}" containerClass="relative w-full max-w-md" />
        </div>
        <div class="flex items-center gap-2 max-lg:w-full lg:flex-wrap">
            <select id="user-status-filter" name="status"
                class="h-9 px-3 bg-surface border border-outline-variant rounded text-xs text-on-surface max-lg:min-w-0 max-lg:flex-1">
                <option value="all" {{ ($filters['status'] ?? 'all') === 'all' ? 'selected' : '' }}>
                    {{ __('admin.filter_all') }}
                </option>
                @foreach ($statusFilters as $statusFilter)
                    <option value="{{ $statusFilter['value'] }}"
                        {{ ($filters['status'] ?? '') === $statusFilter['value'] ? 'selected' : '' }}>
                        {{ $statusFilter['label'] }}</option>
                @endforeach
            </select>
            <button type="submit" data-icon-only data-tooltip="{{ __('admin.filter_apply') }}"
                aria-label="{{ __('admin.filter_apply') }}"
                class="text-xs font-semibold h-9 w-9 shrink-0 inline-flex items-center justify-center rounded-lg bg-primary text-on-primary hover:bg-primary-container transition-colors">
                <span class="material-symbols-outlined text-[18px]" aria-hidden="true">filter_alt</span>
            </button>
            @if (trim((string) ($filters['q'] ?? '')) !== '' || ($filters['status'] ?? 'all') !== 'all')
                <a id="users-clear-filters" href="{{ route('admin.room-users.page', $room) }}"
                    class="h-9 shrink-0 inline-flex items-center gap-1.5 px-3 rounded-lg border border-outline-variant bg-surface text-on-surface text-xs font-semibold hover:bg-surface-container transition-colors no-underline">
                    <span class="material-symbols-outlined text-[16px]">filter_alt_off</span>
                    {{ __('admin.filter_clear') }}
                </a>
            @endif
            <x-admin.reload-button />
        </div>
    </form>

    <div id="users-bulk-toolbar"
        class="hidden items-center justify-between gap-3 rounded-xl border border-primary/30 bg-primary/5 p-3 shadow-xs max-lg:flex-col max-lg:items-stretch max-lg:gap-2"
        data-confirm-message="{{ __('admin.confirm_bulk_user_action') }}">
        <span class="text-xs font-semibold text-on-surface"><span id="users-selected-count">0</span>
            {{ __('admin.users_selected') }}</span>
        <div class="flex flex-wrap items-center gap-2 max-lg:w-full max-lg:flex-nowrap">
            <button type="button" data-bulk-user-action="active"
                class="inline-flex items-center justify-center gap-1.5 rounded-lg max-lg:min-w-0 max-lg:flex-1 bg-emerald-600 px-3 py-2 text-xs font-semibold text-white hover:bg-emerald-700">
                <span
                    class="material-symbols-outlined text-[16px]">lock_open</span>{{ __('admin.bulk_approve_users') }}
            </button>
            <button type="button" data-bulk-user-action="blocked"
                class="inline-flex items-center justify-center gap-1.5 rounded-lg max-lg:min-w-0 max-lg:flex-1 bg-amber-600 px-3 py-2 text-xs font-semibold text-white hover:bg-amber-700">
                <span class="material-symbols-outlined text-[16px]">lock</span>{{ __('admin.bulk_block_users') }}
            </button>
            <button type="button" data-bulk-user-action="removed"
                class="inline-flex items-center justify-center gap-1.5 rounded-lg max-lg:min-w-0 max-lg:flex-1 bg-rose-600 px-3 py-2 text-xs font-semibold text-white hover:bg-rose-700">
                <span
                    class="material-symbols-outlined text-[16px]">person_remove</span>{{ __('admin.bulk_remove_users') }}
            </button>
        </div>
    </div>

    <!-- Users Table Ledger -->
    <div class="bg-surface-container-lowest border border-outline-variant rounded-xl overflow-hidden shadow-xs">
        <div class="overflow-x-auto">
            {{-- Fixed-width side columns; the member column takes the remaining space. --}}
            <table data-skeleton="table"
                class="table-colgroup w-full min-w-[72rem] table-fixed text-left text-xs border-collapse">
                <colgroup>
                    <col class="w-12">
                    <col>
                    <col class="w-32">
                    <col class="w-36">
                    <col class="w-32">
                    <col class="w-40">
                    <col class="w-32">
                    <col class="w-32">
                </colgroup>
                <thead>
                    <tr
                        class="bg-surface-container-low text-outline font-mono uppercase text-[11px] border-b border-outline-variant">
                        <th class="w-10 py-3 px-4 text-left"><input type="checkbox" id="users-select-all"
                                class="h-4 w-4 cursor-pointer rounded border-outline-variant text-primary focus:ring-primary"
                                aria-label="{{ __('admin.select_all_users') }}"></th>
                        <th class="py-3 px-4 text-left">{{ __('admin.th_user_member') }}</th>
                        <th class="py-3 px-4 text-center">{{ __('admin.th_user_role') }}</th>
                        <th class="py-3 px-4 text-center">{{ __('admin.th_trusted_devices') }}</th>
                        <th class="py-3 px-4 text-right">{{ __('admin.th_total_orders') }}</th>
                        <th class="py-3 px-4 text-center">{{ __('admin.th_joined_at') }}</th>
                        <th class="py-3 px-4 text-center">{{ __('admin.th_status') }}</th>
                        <th class="py-3 px-4 text-center">{{ __('admin.th_actions') }}</th>
                    </tr>
                </thead>
                <tbody id="users-tbody" class="divide-y divide-outline-variant/50">
                    @forelse($roomUsers as $ru)
                        @php
                            $name = $ru->globalUser?->name ?? ($ru->display_name ?? 'Member #' . $ru->id);
                            $email = $ru->globalUser?->email ?? __('global.common.not_available');
                            $statusVal =
                                $ru->status instanceof \BackedEnum
                                    ? $ru->status->value
                                    : (string) ($ru->status ?? 'active');
                            $statusIcon = match ($statusVal) {
                                'active' => 'check_circle',
                                'blocked' => 'block',
                                'removed' => 'person_remove',
                                default => 'help',
                            };
                            $joinedAt = $ru->joined_at ?? $ru->created_at;
                            $roleVal =
                                $ru->role instanceof \BackedEnum ? $ru->role->value : (string) ($ru->role ?? 'member');
                            $roleClass = match ($roleVal) {
                                'owner' => 'bg-amber-50 text-amber-800 border-amber-300 font-bold',
                                'admin' => 'bg-purple-50 text-purple-700 border-purple-200 font-semibold',
                                default => 'bg-surface-container text-secondary border-outline-variant',
                            };
                        @endphp
                        <tr class="hover:bg-surface-container-low/50 transition-colors" data-user-row
                            data-status="{{ $statusVal }}">
                            <td class="py-3.5 px-4 text-left w-10">
                                @if ($statusVal !== 'removed')
                                    <input type="checkbox" data-user-select data-room-user-id="{{ $ru->id }}"
                                        class="h-4 w-4 cursor-pointer rounded border-outline-variant text-primary focus:ring-primary"
                                        aria-label="{{ $name }}">
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-left">
                                <div class="group/user">
                                    <div class="flex items-center gap-1">
                                        <button type="button" data-open-user-detail
                                            data-room-user-id="{{ $ru->id }}"
                                            class="flex items-center gap-1.5 text-left cursor-pointer focus:outline-none bg-transparent border-0 p-0 font-bold text-on-surface text-sm group-hover/user:text-primary transition-colors">
                                            <span>{{ $name }}</span>
                                            <span
                                                class="material-symbols-outlined text-[14px] text-outline opacity-0 group-hover/user:opacity-100 transition-opacity">visibility</span>
                                        </button>
                                        <x-sponsor-badge :rank="$sponsorRanks['ranks'][$ru->id] ?? null" :month="$sponsorRanks['month']" />
                                    </div>
                                    <div class="flex items-center gap-1 text-[11px] text-outline">
                                        <span>{{ $email }}</span>
                                        @if ($ru->globalUser?->email)
                                            <button type="button" data-copy="{{ $ru->globalUser->email }}"
                                                data-copied-message="{{ __('admin.copied') }}"
                                                data-copy-failed-message="{{ __('admin.copy_failed') }}"
                                                data-tooltip="{{ __('admin.copy_email') }}"
                                                aria-label="{{ __('admin.copy_email') }}"
                                                class="inline-flex items-center text-outline hover:text-primary transition-colors cursor-pointer">
                                                <span class="material-symbols-outlined text-[12px]">content_copy</span>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded text-[11px] border {{ $roleClass }}">
                                    {{ __('admin.role_' . $roleVal) }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-center font-mono">
                                <button type="button" data-open-devices data-room-user-id="{{ $ru->id }}"
                                    data-member-name="{{ $name }}"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-surface-container hover:bg-surface-container-high text-on-surface font-semibold text-[11px] border border-outline-variant transition-colors"
                                    title="{{ __('admin.manage_devices') }}">
                                    <span class="material-symbols-outlined text-[14px] text-primary">devices</span>
                                    <span>{{ __('admin.devices_unit', ['count' => $ru->devices_count ?? 0]) }}</span>
                                </button>
                            </td>
                            <td class="py-3.5 px-4 text-right font-mono font-bold text-on-surface">
                                {{ __('admin.orders_unit', ['count' => $ru->orders_count ?? 0]) }}
                            </td>
                            <td class="py-3.5 px-4 text-center font-mono text-secondary" data-member-joined-at>
                                @if ($joinedAt)
                                    <span class="text-on-surface">{{ $joinedAt->format('d/m/Y') }}</span>
                                @else
                                    <span class="text-outline">—</span>
                                @endif
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <span data-member-status-badge
                                    class="inline-flex items-center gap-1 pl-1.5 pr-2.5 py-0.5 rounded-full whitespace-nowrap text-[11px] font-semibold border {{ $statusVal === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-rose-50 text-rose-700 border-rose-200' }}">
                                    <span class="material-symbols-outlined text-[14px]"
                                        aria-hidden="true">{{ $statusIcon }}</span>
                                    {{ __('admin.status_' . $statusVal) }}
                                </span>
                            </td>
                            <td class="py-3.5 px-4 text-center">
                                <div class="flex items-center justify-center gap-1.5">
                                    @if ($statusVal !== 'removed')
                                        <button type="button" data-toggle-user-status
                                            data-room-user-id="{{ $ru->id }}"
                                            data-new-status="{{ $statusVal === 'active' ? 'blocked' : 'active' }}"
                                            class="group relative p-1 rounded hover:bg-surface-container transition-colors {{ $statusVal === 'active' ? 'text-secondary hover:text-rose-600' : 'text-emerald-600' }}"
                                            title="{{ $statusVal === 'active' ? __('admin.btn_block_user') : __('admin.approve_user') }}"
                                            aria-label="{{ $statusVal === 'active' ? __('admin.btn_block_user') : __('admin.approve_user') }}">
                                            <span
                                                class="material-symbols-outlined text-[16px]">{{ $statusVal === 'active' ? 'lock' : 'lock_open' }}</span>
                                            <span role="tooltip"
                                                class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-2 -translate-x-1/2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-[10px] font-medium text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100">{{ $statusVal === 'active' ? __('admin.btn_block_user') : __('admin.approve_user') }}</span>
                                        </button>
                                    @endif
                                    @if ($statusVal === 'removed')
                                        <button type="button" data-restore-room-user
                                            data-room-user-id="{{ $ru->id }}"
                                            class="group relative p-1 rounded text-emerald-600 hover:text-emerald-700 hover:bg-surface-container transition-colors"
                                            title="{{ __('admin.restore_user_to_room') }}"
                                            aria-label="{{ __('admin.restore_user_to_room') }}">
                                            <span
                                                class="material-symbols-outlined text-[16px]">restore_from_trash</span>
                                            <span role="tooltip"
                                                class="pointer-events-none absolute bottom-full right-0 z-20 mb-2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-[10px] font-medium text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100">{{ __('admin.restore_user_to_room') }}</span>
                                        </button>
                                    @endif
                                    @if ($statusVal !== 'removed')
                                        <button type="button" data-remove-room-user
                                            data-room-user-id="{{ $ru->id }}"
                                            class="group relative p-1 rounded text-secondary hover:text-rose-600 hover:bg-surface-container transition-colors"
                                            title="{{ __('admin.remove_user_from_room') }}"
                                            aria-label="{{ __('admin.remove_user_from_room') }}">
                                            <span class="material-symbols-outlined text-[16px]">person_remove</span>
                                            <span role="tooltip"
                                                class="pointer-events-none absolute bottom-full right-0 z-20 mb-2 whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-[10px] font-medium text-white opacity-0 shadow-lg transition-opacity group-hover:opacity-100 group-focus-visible:opacity-100">{{ __('admin.remove_user_from_room') }}</span>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-outline">
                                <div class="flex flex-col items-center gap-2">
                                    <span class="material-symbols-outlined text-4xl text-outline-variant">group</span>
                                    <p class="font-medium text-sm">{{ __('admin.no_users_found') }}</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($roomUsers->hasPages())
            <div class="p-4 border-t border-outline-variant">
                {{ $roomUsers->links() }}
            </div>
        @endif
    </div>

    <!-- Device Trust Management Modal -->
    <div id="device-modal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4 backdrop-blur-xs"
        data-revoke-label="{{ __('admin.revoke_device') }}" data-revoked-label="{{ __('admin.revoked') }}"
        data-no-device-label="{{ __('admin.no_device_registered') }}"
        data-device-fallback-label="{{ __('admin.device_uuid') }}"
        data-load-error-label="{{ __('admin.error_generic') }}">
        <div id="device-backdrop" class="absolute inset-0"></div>
        <div
            class="relative z-10 w-full max-w-xl bg-surface-container-lowest border border-outline-variant rounded-xl p-6 shadow-2xl max-h-[85vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 border-b border-outline-variant mb-4">
                <div class="flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded bg-primary text-on-primary flex items-center justify-center">
                        <span class="material-symbols-outlined text-[18px]">devices</span>
                    </div>
                    <div>
                        <h3 class="font-bold text-base text-on-surface" id="device-modal-title">
                            {{ __('admin.manage_devices') }}
                        </h3>
                        <p class="text-xs text-outline">{{ __('admin.device_fingerprint') }}</p>
                    </div>
                </div>
                <button type="button" data-close-device-modal class="text-outline hover:text-on-surface">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>
            <div id="device-modal-body" class="space-y-3">
                <!-- Dynamically loaded -->
            </div>
        </div>
    </div>

    <!-- User action confirmation modal -->
    <div id="user-action-modal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4 backdrop-blur-xs" role="dialog"
        aria-modal="true" data-status-title="{{ __('admin.toggle_user_status') }}"
        data-remove-title="{{ __('admin.remove_user_from_room') }}"
        data-restore-title="{{ __('admin.restore_user_to_room') }}"
        data-restore-message="{{ __('admin.confirm_restore_user') }}"
        data-block-message="{{ __('admin.confirm_block_user') }}"
        data-unblock-message="{{ __('admin.confirm_unblock_user') }}"
        data-remove-message="{{ __('admin.confirm_remove_user') }}"
        data-bulk-title="{{ __('admin.bulk_user_action_title') }}"
        data-processing-label="{{ __('admin.processing') }}" data-confirm-label="{{ __('admin.confirm_action') }}">
        <div
            class="relative z-10 w-full max-w-sm bg-surface-container-lowest border border-outline-variant rounded-xl p-6 shadow-2xl">
            <div class="flex items-start gap-3">
                <span id="user-action-icon-wrap"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
                    <span id="user-action-icon" class="material-symbols-outlined text-[22px]">manage_accounts</span>
                </span>
                <div class="min-w-0">
                    <h3 id="user-action-title" class="font-bold text-base text-on-surface">
                        {{ __('admin.toggle_user_status') }}
                    </h3>
                    <p id="user-action-message" class="mt-1.5 text-xs text-outline leading-relaxed"></p>
                </div>
            </div>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" id="user-action-cancel"
                    class="px-4 py-2 rounded-lg bg-surface-container text-on-surface text-xs font-semibold">{{ __('admin.cancel') }}</button>
                <button type="button" id="user-action-confirm"
                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-lg bg-primary text-on-primary text-xs font-semibold transition-colors">
                    <span id="user-action-confirm-icon" class="material-symbols-outlined text-[16px]">check</span>
                    <span id="user-action-confirm-label">{{ __('admin.confirm_action') }}</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Add / Create Member Modal -->
    <div id="create-user-modal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4 backdrop-blur-xs" role="dialog"
        aria-modal="true" aria-labelledby="create-user-modal-title">
        <div id="create-user-backdrop" class="absolute inset-0"></div>
        <div
            class="relative z-10 w-full max-w-lg bg-surface-container-lowest border border-outline-variant rounded-2xl p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
            <div class="flex items-start justify-between pb-3 border-b border-outline-variant mb-4">
                <div class="flex items-center gap-3">
                    <div
                        class="w-10 h-10 rounded-xl bg-primary/10 text-primary border border-primary/20 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[22px]">person_add</span>
                    </div>
                    <div>
                        <h3 class="font-bold text-base text-on-surface" id="create-user-modal-title">
                            {{ __('admin.add_user_modal_title') }}
                        </h3>
                        <p class="text-xs text-outline mt-0.5">{{ __('admin.add_user_modal_desc') }}</p>
                    </div>
                </div>
                <button type="button" id="create-user-close"
                    class="p-1 rounded-lg text-outline hover:text-on-surface hover:bg-surface-container transition-colors">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            <!-- Error Banner -->
            <div id="create-user-error"
                class="hidden mb-4 rounded-xl p-3 bg-error-container/60 border border-error/30 text-error text-xs leading-relaxed">
            </div>

            <form id="create-user-form" class="space-y-4">
                <div>
                    <label for="create-user-email" class="block text-xs font-semibold text-on-surface mb-1">
                        {{ __('admin.user_email_label') }} <span class="text-error">*</span>
                    </label>
                    <input type="email" id="create-user-email" name="email" required
                        placeholder="example@thk-hd.vn"
                        class="w-full px-3 py-2 bg-surface border border-outline-variant rounded-lg text-xs text-on-surface focus:outline-none focus:border-primary">
                    <p class="mt-1 text-[11px] text-outline leading-relaxed">{{ __('admin.user_email_hint') }}</p>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="create-user-name" class="block text-xs font-semibold text-on-surface mb-1">
                            {{ __('admin.user_name_label') }} <span class="text-error">*</span>
                        </label>
                        <input type="text" id="create-user-name" name="name" required
                            placeholder="Nguyen Van A"
                            class="w-full px-3 py-2 bg-surface border border-outline-variant rounded-lg text-xs text-on-surface focus:outline-none focus:border-primary">
                    </div>
                    <div>
                        <label for="create-user-phone" class="block text-xs font-semibold text-on-surface mb-1">
                            {{ __('admin.user_phone_label') }}
                        </label>
                        <input type="tel" id="create-user-phone" name="phone" placeholder="0912345678"
                            class="w-full px-3 py-2 bg-surface border border-outline-variant rounded-lg text-xs text-on-surface focus:outline-none focus:border-primary">
                    </div>
                </div>

                <div>
                    <label for="create-user-desk" class="block text-xs font-semibold text-on-surface mb-1">
                        {{ __('admin.user_desk_label') }}
                    </label>
                    <input type="text" id="create-user-desk" name="desk_location"
                        placeholder="{{ __('admin.user_desk_placeholder') }}"
                        class="w-full px-3 py-2 bg-surface border border-outline-variant rounded-lg text-xs text-on-surface focus:outline-none focus:border-primary">
                </div>

                <div class="pt-2 flex items-center justify-end gap-2.5 border-t border-outline-variant">
                    <button type="button" id="create-user-cancel"
                        class="px-4 py-2 rounded-lg bg-surface-container hover:bg-surface-container-high text-on-surface text-xs font-semibold transition-colors">
                        {{ __('admin.cancel') }}
                    </button>
                    <button type="submit" id="create-user-submit"
                        class="px-4 py-2 rounded-lg bg-primary hover:bg-primary-container text-on-primary text-xs font-semibold transition-colors flex items-center gap-1.5 shadow-xs cursor-pointer">
                        <span class="material-symbols-outlined text-[16px]">person_add</span>
                        <span>{{ __('admin.btn_submit_add_user') }}</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- User Detail Modal -->
    <div id="user-detail-modal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/60 p-4 backdrop-blur-xs" role="dialog"
        aria-modal="true" aria-labelledby="user-detail-name" data-status-active="{{ __('admin.status_active') }}"
        data-status-blocked="{{ __('admin.status_blocked') }}"
        data-status-pending="{{ __('admin.status_pending') }}"
        data-status-removed="{{ __('admin.status_removed') }}" data-role-owner="{{ __('admin.role_owner') }}"
        data-role-admin="{{ __('admin.role_admin') }}" data-role-member="{{ __('admin.role_member') }}">
        <div id="user-detail-backdrop" class="absolute inset-0"></div>
        <div
            class="relative z-10 w-full max-w-lg bg-surface-container-lowest border border-outline-variant rounded-2xl p-6 shadow-2xl max-h-[90vh] overflow-y-auto">
            <!-- Modal Header -->
            <div class="flex items-start justify-between pb-4 border-b border-outline-variant/60 mb-5">
                <div class="flex items-center gap-3.5">
                    <div id="user-detail-avatar-container"
                        class="w-12 h-12 rounded-full bg-primary/10 text-primary border border-primary/20 flex items-center justify-center text-base font-bold overflow-hidden shrink-0 shadow-xs">
                        <span id="user-detail-avatar-initial">?</span>
                        <img id="user-detail-avatar-img" src="" alt=""
                            class="w-full h-full object-cover rounded-full hidden" loading="lazy">
                    </div>
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 id="user-detail-name" class="font-bold text-base text-on-surface tracking-tight">-
                            </h3>
                            <span id="user-detail-role-badge"
                                class="text-[11px] font-semibold px-2 py-0.5 rounded border">-</span>
                            <span id="user-detail-status-badge"
                                class="text-[11px] font-semibold px-2 py-0.5 rounded border">-</span>
                        </div>
                        <div class="flex items-center gap-1 mt-0.5">
                            <p id="user-detail-email" class="text-xs text-outline">-</p>
                            <button type="button" id="user-detail-email-copy" data-copy=""
                                data-copied-message="{{ __('admin.copied') }}"
                                data-copy-failed-message="{{ __('admin.copy_failed') }}"
                                data-tooltip="{{ __('admin.copy_email') }}"
                                aria-label="{{ __('admin.copy_email') }}"
                                class="hidden inline-flex items-center text-outline hover:text-primary transition-colors cursor-pointer">
                                <span class="material-symbols-outlined text-[13px]">content_copy</span>
                            </button>
                        </div>
                        <p class="mt-0.5 text-[11px] text-outline">
                            {{ __('admin.member_code') }}: <span id="user-detail-code"
                                class="font-mono font-semibold text-primary">-</span>
                        </p>
                    </div>
                </div>
                <button type="button" id="user-detail-close"
                    class="p-1.5 rounded-lg text-outline hover:text-on-surface hover:bg-surface-container transition-colors"
                    aria-label="{{ __('admin.cancel') }}">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            <!-- Loading State -->
            <div id="user-detail-loading" class="py-8 text-center text-outline">
                <span
                    class="material-symbols-outlined animate-spin text-3xl text-primary mb-2">progress_activity</span>
                <p class="text-xs font-medium">{{ __('admin.loading_user_detail') }}</p>
            </div>

            <!-- Error State -->
            <div id="user-detail-error" class="hidden py-6 text-center text-error text-xs">
                <span class="material-symbols-outlined text-3xl mb-1">error</span>
                <p>{{ __('admin.load_user_failed') }}</p>
            </div>

            <!-- Main Detail Content -->
            <div id="user-detail-content" class="hidden space-y-4">
                <!-- Contact & Workplace Info -->
                <div
                    class="bg-surface-container-low/70 border border-outline-variant/60 rounded-xl p-3.5 space-y-2.5 text-xs">
                    <div class="flex items-center justify-between py-1 border-b border-outline-variant/40">
                        <span class="text-outline flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[15px] text-secondary">call</span>
                            {{ __('admin.user_phone') }}
                        </span>
                        <span class="flex items-center gap-1">
                            <span id="user-detail-phone" class="font-semibold text-on-surface font-mono">-</span>
                            <button type="button" id="user-detail-phone-copy" data-copy=""
                                data-copied-message="{{ __('admin.copied') }}"
                                data-copy-failed-message="{{ __('admin.copy_failed') }}"
                                data-tooltip="{{ __('admin.copy_phone') }}"
                                aria-label="{{ __('admin.copy_phone') }}"
                                class="hidden inline-flex items-center text-outline hover:text-primary transition-colors cursor-pointer">
                                <span class="material-symbols-outlined text-[13px]">content_copy</span>
                            </button>
                        </span>
                    </div>
                    <div class="flex items-center justify-between py-1 border-b border-outline-variant/40">
                        <span class="text-outline flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[15px] text-secondary">desk</span>
                            {{ __('admin.user_desk') }}
                        </span>
                        <span id="user-detail-desk" class="font-medium text-on-surface text-right">-</span>
                    </div>
                    <div class="flex items-center justify-between py-1">
                        <span class="text-outline flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[15px] text-secondary">location_on</span>
                            {{ __('admin.user_delivery_location') }}
                        </span>
                        <span id="user-detail-delivery" class="font-medium text-on-surface text-right">-</span>
                    </div>
                </div>

                <!-- Membership & Activity Stats -->
                <div class="grid grid-cols-2 gap-2.5">
                    <div class="bg-surface-container-low/70 border border-outline-variant/60 rounded-xl p-3">
                        <span
                            class="text-outline text-[10px] uppercase font-mono block">{{ __('admin.user_joined_at') }}</span>
                        <div class="text-on-surface mt-1 font-semibold text-xs" id="user-detail-joined">-</div>
                    </div>
                    <div class="bg-surface-container-low/70 border border-outline-variant/60 rounded-xl p-3">
                        <span
                            class="text-outline text-[10px] uppercase font-mono block">{{ __('admin.user_last_active') }}</span>
                        <div class="text-on-surface mt-1 font-semibold text-xs" id="user-detail-last-active">-</div>
                    </div>
                    <div class="bg-surface-container-low/70 border border-outline-variant/60 rounded-xl p-3">
                        <span
                            class="text-outline text-[10px] uppercase font-mono block">{{ __('admin.user_total_orders') }}</span>
                        <div class="text-primary font-bold text-base font-mono mt-0.5" id="user-detail-total-orders">0
                        </div>
                    </div>
                    <div class="bg-surface-container-low/70 border border-outline-variant/60 rounded-xl p-3">
                        <span
                            class="text-outline text-[10px] uppercase font-mono block">{{ __('admin.user_total_debt') }}</span>
                        <div class="font-bold text-sm font-mono mt-0.5" id="user-detail-total-debt">-</div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-admin.layout>
