<x-room.layout :room="$room" :room-user="$roomUser" :user-rooms="$userRooms" :unread-notifications-count="$unreadNotificationsCount" :active-tab="'profile'" :title="__('room.profile.page_title')">
    @php
        $memberStatus = $roomUser->status instanceof \App\Enums\RoomUserStatus
            ? $roomUser->status->value
            : (string) $roomUser->status;
        $memberStatusLabel = __('room.profile.status_' . $memberStatus);
    @endphp
    <div class="flex flex-col w-full gap-4">
        <!-- Profile Header Card -->
        <section class="w-full bg-surface-container-lowest rounded-xl shadow-2xs border border-outline-variant/30 overflow-hidden p-3.5 sm:p-4 relative">
            <div class="absolute top-0 right-0 w-80 h-80 bg-primary/5 rounded-full blur-3xl pointer-events-none -mr-20 -mt-20"></div>
            <div class="flex flex-col lg:flex-row gap-4 items-start justify-between relative z-10">
                <!-- User Info & Avatar -->
                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-3 w-full lg:w-auto">
                    <div class="relative shrink-0">
                        <x-avatar :user="$user" size="lg" class="rounded-xl border-2 border-[#006948]/30 ring-2 ring-emerald-500/10 shadow-2xs" :alt="$user->name" />
                    </div>
                    <div class="flex flex-col gap-1">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <h1 class="text-sm sm:text-base font-bold text-on-surface">{{ $user->name }}</h1>
                            <x-sponsor-badge :rank="$sponsorBadge['rank']" :month="$sponsorBadge['month']" />
                            <span class="px-2 py-0.5 rounded bg-primary-fixed text-on-primary-fixed text-[10px] sm:text-[11px] flex items-center gap-1 font-semibold">
                                <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse"></span> {{ $memberStatusLabel }}
                            </span>
                        </div>
                        <p class="text-xs text-on-surface-variant flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px] text-primary">terminal</span>
                            <span>{{ $roomUser->role === 'admin' ? __('room.profile.role_admin') : __('room.profile.role_member') }} • {{ $room->name }}</span>
                        </p>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-0.5 text-on-surface-variant text-[11px]">
                            <span class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-[13px]">mail</span> {{ $user->email }}
                            </span>
                            <span class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-[13px]">badge</span> {{ __('room.profile.member_code_prefix') }}: {{ $roomUser->user_code }}
                            </span>
                            <span class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-[13px]">calendar_today</span> {{ __('room.profile.joined_date_prefix') }}: {{ $roomUser->created_at?->format('d/m/Y') }}
                            </span>
                        </div>
                    </div>
                </div>

                <a
                    href="{{ route('user.me.profile') }}"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-outline-variant/40 bg-surface-container-low hover:bg-surface-container text-on-surface text-xs font-semibold transition-colors shrink-0"
                >
                    <span class="material-symbols-outlined text-[16px]">edit</span>
                    <span>{{ __('room.profile.edit_profile') }}</span>
                </a>
            </div>

            <!-- Quick Stats Metric Band -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3 mt-3 pt-3 border-t border-outline-variant/20">
                <!-- Metric 1 -->
                <div class="bg-surface-container-low rounded-lg p-2.5 sm:p-3 flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-surface-container flex items-center justify-center text-primary shrink-0">
                        <span class="material-symbols-outlined text-[18px]">receipt_long</span>
                    </div>
                    <div class="flex flex-col min-w-0">
                        <span class="text-[10px] sm:text-[11px] text-on-surface-variant uppercase tracking-wider font-semibold">{{ __('room.profile.total_orders') }}</span>
                        <div class="flex items-baseline gap-1">
                            <span class="text-sm sm:text-base font-tabular-nums text-on-surface font-bold font-mono">{{ $totalOrders }}</span>
                            <span class="text-[11px] text-on-surface-variant">{{ __('room.profile.orders_unit') }}</span>
                        </div>
                    </div>
                </div>

                <!-- Metric 2 -->
                <div class="bg-surface-container-low rounded-lg p-2.5 sm:p-3 flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-primary-fixed text-on-primary-fixed-variant flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-[18px]">account_balance_wallet</span>
                    </div>
                    <div class="flex flex-col min-w-0">
                        <span class="text-[10px] sm:text-[11px] text-on-surface-variant uppercase tracking-wider font-semibold">{{ __('room.profile.total_debt') }}</span>
                        <div class="flex items-baseline gap-1">
                            <span class="text-sm sm:text-base font-tabular-nums text-rose-600 dark:text-rose-400 font-bold font-mono">{{ \App\Support\Helpers\FormatHelper::formatCurrency($totalDebt) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Metric 3 -->
                <div class="bg-surface-container-low rounded-lg p-2.5 sm:p-3 flex items-center gap-2.5">
                    <div class="w-8 h-8 rounded-lg bg-surface-container flex items-center justify-center text-primary shrink-0">
                        <span class="material-symbols-outlined text-[18px]">verified_user</span>
                    </div>
                    <div class="flex flex-col min-w-0">
                        <span class="text-[10px] sm:text-[11px] text-on-surface-variant uppercase tracking-wider font-semibold">{{ __('room.profile.total_spent') }}</span>
                        <div class="flex items-baseline gap-1">
                            <span class="text-sm sm:text-base font-tabular-nums text-emerald-600 dark:text-emerald-400 font-bold font-mono">{{ \App\Support\Helpers\FormatHelper::formatCurrency($totalSpent) }}</span>
                            <span class="text-[10px] text-secondary font-semibold ml-1">{{ __('room.profile.accumulated') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <x-sponsor-badge-history :badges="$recentSponsorBadges" />

        <!-- Profile Details Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 items-stretch">
            <!-- Left 7 cols: Favorite items & Room specs -->
            <div class="lg:col-span-7 flex flex-col gap-3">
                <div class="bg-surface-container-lowest rounded-xl p-3.5 sm:p-4 shadow-2xs border border-outline-variant/30 flex flex-col flex-1 gap-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-[18px]">stars</span>
                            <h3 class="text-xs sm:text-sm text-on-surface font-bold">{{ __('room.profile.favorite_items') }}</h3>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 content-start flex-1 gap-2">
                        @forelse($favoriteItems as $fav)
                            <div class="bg-surface-container-low rounded-lg p-2 flex items-center justify-between gap-2 min-w-0">
                                <div class="flex items-center gap-2 min-w-0">
                                    <div class="w-7 h-7 rounded-md bg-surface-container-high flex items-center justify-center text-primary shrink-0">
                                        <span class="material-symbols-outlined text-[15px]">local_cafe</span>
                                    </div>
                                    <span class="text-xs font-semibold text-on-surface truncate">{{ $fav['name'] }}</span>
                                </div>
                                <span class="px-1.5 py-0.5 rounded-full bg-primary-fixed text-on-primary-fixed text-[10px] font-bold whitespace-nowrap shrink-0">
                                    {{ __('room.profile.ordered_times', ['count' => $fav['count']]) }}
                                </span>
                            </div>
                        @empty
                            <div class="sm:col-span-2 p-6 text-center text-on-surface-variant text-xs flex flex-col flex-1 items-center justify-center gap-2">
                                <span class="w-10 h-10 rounded-xl bg-surface-container-low text-primary/60 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-[24px]" aria-hidden="true">local_cafe</span>
                                </span>
                                <p>{{ __('room.profile.no_favorite_drinks') }}</p>
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Right 5 cols: Department info -->
            <div class="lg:col-span-5 flex flex-col gap-3">
                <div class="bg-surface-container-lowest rounded-xl p-3.5 sm:p-4 shadow-2xs border border-outline-variant/30 flex flex-col flex-1 gap-3">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[18px]">domain</span>
                        <h3 class="text-xs sm:text-sm text-on-surface font-bold">{{ __('room.profile.contact_info') }}</h3>
                    </div>

                    <div class="flex flex-col gap-2 text-xs">
                        <div class="flex justify-between items-center py-1.5 border-b border-outline-variant/20">
                            <span class="text-on-surface-variant">{{ __('room.profile.department_label') }}</span>
                            <span class="font-bold text-on-surface">{{ $room->name }}</span>
                        </div>
                        <div class="flex justify-between items-center py-1.5 border-b border-outline-variant/20">
                            <span class="text-on-surface-variant">{{ __('room.profile.room_code_label') }}</span>
                            <span class="font-bold text-primary">{{ $room->code ?? $room->slug }}</span>
                        </div>
                        <div class="flex justify-between items-center py-1.5 border-b border-outline-variant/20">
                            <span class="text-on-surface-variant">{{ __('room.profile.last_activity_label') }}</span>
                            <span class="font-bold text-on-surface">{{ $roomUser->last_active_at?->format('d/m/Y H:i') ?? __('room.profile.no_activity') }}</span>
                        </div>
                        <div class="flex justify-between items-center py-1.5 border-b border-outline-variant/20">
                            <span class="text-on-surface-variant">{{ __('room.profile.member_status_label') }}</span>
                            <span class="font-bold text-primary">{{ $memberStatusLabel }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-room.layout>
