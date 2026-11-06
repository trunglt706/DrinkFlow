<x-global.layout 
    :title="'DrinkFlow - ' . __('global.devices.page_title')" 
    :user="$user"
    activeTab="devices" 
    :breadcrumbs="$breadcrumbs"
    :unreadNotificationsCount="$unreadNotificationsCount ?? 0"
    :notifications="$notifications ?? collect()"
>
<div 
    x-data="{
        showSingleLogoutModal: false,
        showLogoutAllModal: false,
        showDeleteAccountModal: false,
        showHelpModal: false,
        targetSessionId: '',
        targetDeviceName: '',
        openSingleLogout(sessionId, deviceName) {
            this.targetSessionId = sessionId;
            this.targetDeviceName = deviceName;
            this.showSingleLogoutModal = true;
        }
    }" 
    class="space-y-6"
>
    <!-- Status & Alert Messages -->
    @if(session('status'))
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-sm flex items-center justify-between shadow-2xs">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-emerald-600 text-[20px]">check_circle</span>
                <span>{{ session('status') }}</span>
            </div>
            <button @click="$el.parentElement.remove()" class="text-emerald-500 hover:text-emerald-700">
                <span class="material-symbols-outlined text-[18px]">close</span>
            </button>
        </div>
    @endif

    @if($errors->any())
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 text-sm shadow-2xs">
            <div class="flex items-center gap-2 font-semibold mb-1">
                <span class="material-symbols-outlined text-rose-600 text-[20px]">error</span>
                <span>{{ $errors->first() }}</span>
            </div>
        </div>
    @endif

    <!-- Page Header Area -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-slate-200 pb-5">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 tracking-tight">{{ __('global.devices.title') }}</h1>
            <p class="text-sm text-slate-600 mt-1">{{ __('global.devices.subtitle') }}</p>
        </div>
    </div>

    {{-- QR sign-in on a phone: the code is fetched (and replaced every few seconds) only while the panel is open. --}}
    <section class="bg-white border border-slate-200 rounded-xl p-5 shadow-2xs" data-qr-login
        data-endpoint="{{ route('user.me.devices.qr-login') }}"
        data-i18n="{{ json_encode([
            'show' => __('global.qr_login.show'),
            'hide' => __('global.qr_login.hide'),
            'countdown' => __('global.qr_login.countdown', ['seconds' => '__SECONDS__']),
            'loading' => __('global.qr_login.loading'),
            'stopped' => __('global.qr_login.stopped'),
            'error' => __('global.qr_login.error'),
        ], JSON_UNESCAPED_UNICODE) }}">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="flex items-start gap-3 min-w-0">
                <div class="w-10 h-10 rounded-lg bg-emerald-50 text-[#006948] flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined" aria-hidden="true">qr_code_2</span>
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm font-semibold text-slate-900">{{ __('global.qr_login.title') }}</h2>
                    <p class="mt-1 text-xs text-slate-500 leading-relaxed">{{ __('global.qr_login.description', ['seconds' => \App\Services\Auth\QrLoginService::ROTATE_SECONDS]) }}</p>
                </div>
            </div>
            <button type="button" data-qr-login-toggle aria-expanded="false" aria-controls="qr-login-panel"
                class="shrink-0 px-3 py-2 rounded-lg bg-[#006948] hover:bg-[#005239] text-white text-xs font-semibold inline-flex items-center justify-center gap-1.5 cursor-pointer">
                <span class="material-symbols-outlined text-[16px]" aria-hidden="true">qr_code_scanner</span>
                <span data-qr-login-toggle-label>{{ __('global.qr_login.show') }}</span>
            </button>
        </div>

        <div id="qr-login-panel" class="hidden mt-4 pt-4 border-t border-slate-100 flex-col items-center gap-3 text-center" data-qr-login-panel>
            <canvas class="rounded-lg border border-slate-200 bg-white" width="216" height="216" role="img" aria-label="{{ __('global.qr_login.qr_label') }}" data-qr-login-canvas></canvas>
            <p class="text-xs font-medium text-slate-600" aria-live="polite" data-qr-login-status>{{ __('global.qr_login.loading') }}</p>
            <button type="button" class="hidden px-3 py-1.5 rounded-lg border border-slate-200 text-slate-700 hover:bg-slate-50 text-xs font-semibold cursor-pointer" data-qr-login-restart>
                {{ __('global.qr_login.restart') }}
            </button>
            <p class="max-w-md text-[11px] text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">{{ __('global.qr_login.warning') }}</p>
        </div>
    </section>

    <!-- Current device and other sessions -->
    <section class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div class="bg-white border border-emerald-200 rounded-xl p-5 shadow-2xs flex flex-col justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-lg bg-emerald-50 text-[#006948] flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined">{{ $currentDeviceInfo['icon'] }}</span>
                </div>
                <div class="min-w-0">
                    <h2 class="text-sm font-semibold text-slate-900">{{ $currentDeviceInfo['device_name'] }}</h2>
                    <p class="mt-1 text-xs text-slate-500 break-all">{{ $user->email }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ __('global.devices.connected_network', ['ip' => $currentIp]) }}</p>
                </div>
            </div>
            <div class="flex items-center justify-between gap-3 pt-3 border-t border-slate-100">
                <span class="px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700">{{ __('global.devices.current_device_title') }}</span>
                <button type="button" onclick="openLogoutModal()" class="px-3 py-2 rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50 text-xs font-semibold cursor-pointer">
                    {{ __('global.devices.logout_session') }}
                </button>
            </div>
        </div>
            @foreach($otherSessions as $sess)
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-2xs flex flex-col justify-between gap-4 hover:border-slate-300 transition-colors">
                    <div class="flex items-start gap-3.5">
                        <div class="w-10 h-10 rounded-xl bg-slate-100 border border-slate-200 flex items-center justify-center shrink-0 text-slate-600 mt-0.5">
                            <span class="material-symbols-outlined text-xl">{{ $sess['icon'] ?? 'devices' }}</span>
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="text-sm font-semibold text-slate-800">{{ $sess['device_name'] }}</h3>
                            </div>
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
                                <span class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[15px] text-slate-400">schedule</span>
                                    {{ __('global.devices.last_active_label') }} <span class="text-slate-700 font-medium">{{ $sess['last_active'] }}</span>
                                </span>
                                <span class="text-slate-300">•</span>
                                <span class="font-mono text-[11px] text-slate-400">IP: {{ $sess['ip'] }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-100">
                        <button 
                            type="button" 
                            @click="openSingleLogout('{{ $sess['id'] }}', '{{ addslashes($sess['device_name']) }}')"
                            class="px-3 py-1.5 rounded-xl border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 hover:border-slate-300 text-xs font-medium transition-colors flex items-center gap-1.5 focus:outline-none focus:ring-1 focus:ring-[#006948] cursor-pointer shadow-2xs"
                        >
                            <span class="material-symbols-outlined text-[16px] text-slate-400">logout</span>
                            {{ __('global.devices.logout_session') }}
                        </button>
                    </div>
                </div>
            @endforeach
    </section>

    {{-- Temporarily hidden: emergency security actions. Restore this block to enable the view.
    <!-- Section 4: Thao tác bảo mật khẩn cấp (Global Security Actions) -->
    <section class="bg-white border border-slate-200 rounded-2xl p-6 shadow-2xs">
        <div class="flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined text-rose-600 text-xl">warning</span>
            <h2 class="text-base font-semibold text-slate-900">{{ __('global.devices.emergency_actions_title') }}</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 items-start">
            <!-- Emergency Sign-out Card -->
            <div class="p-4 rounded-xl border border-rose-200 bg-rose-50/30 flex flex-col justify-between space-y-4">
                <div>
                    <h3 class="text-sm font-semibold text-slate-800 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-rose-600 text-lg">gpp_bad</span>
                        {{ __('global.devices.logout_remote_title') }}
                    </h3>
                    <p class="text-xs text-slate-600 mt-1.5 leading-relaxed">
                        {{ __('global.devices.logout_remote_desc') }}
                    </p>
                </div>
                <div class="pt-2 border-t border-rose-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <span class="text-[11px] text-slate-400">{{ __('global.devices.sso_code_confirm') }}</span>
                    <button 
                        type="button" 
                        @click="showLogoutAllModal = true"
                        class="px-4 py-2 rounded-xl bg-white border border-rose-200 text-rose-600 hover:bg-rose-50 text-xs font-semibold transition-colors flex items-center justify-center gap-2 focus:outline-none focus:ring-2 focus:ring-rose-500 cursor-pointer shadow-2xs"
                    >
                        <span class="material-symbols-outlined text-[17px]">no_accounts</span>
                        {{ __('global.devices.logout_all_btn') }}
                    </button>
                </div>
            </div>

            <!-- Danger Zone / Delete Account Card -->
            <div class="p-4 rounded-xl border border-rose-200 bg-rose-50/40 flex flex-col justify-between space-y-4">
                <div class="space-y-1.5">
                    <div>
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-rose-100 text-rose-700 border border-rose-200 mb-1">
                            <span class="material-symbols-outlined text-[12px]">dangerous</span> {{ __('global.devices.danger_zone') }}
                        </span>
                        <h3 class="text-sm font-semibold text-rose-700 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-rose-600 text-lg">person_off</span> {{ __('global.devices.delete_account_title') }}
                        </h3>
                    </div>
                    <p class="text-xs text-slate-600 leading-relaxed">
                        {{ __('global.devices.delete_account_desc') }}
                    </p>
                </div>
                <div class="pt-3 border-t border-rose-200/60 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <span class="text-[11px] text-rose-600 font-medium flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">error</span> {{ __('global.devices.irreversible') }}
                    </span>
                    <button 
                        type="button" 
                        @click="showDeleteAccountModal = true"
                        class="px-4 py-2 rounded-xl bg-rose-600 text-white hover:bg-rose-700 text-xs font-semibold transition-colors flex items-center justify-center gap-1.5 focus:outline-none focus:ring-2 focus:ring-rose-500 shadow-2xs cursor-pointer"
                    >
                        <span class="material-symbols-outlined text-[17px]">delete_forever</span> {{ __('global.devices.request_delete_btn') }}
                    </button>
                </div>
            </div>
        </div>
    </section>

    --}}

    <!-- Modal 1: Xác nhận đăng xuất thiết bị đơn lẻ -->
    <div 
        x-show="showSingleLogoutModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
        @click.self="showSingleLogoutModal = false"
        @keydown.escape.window="showSingleLogoutModal = false"
    >
        <div class="bg-white border border-slate-200 rounded-2xl max-w-md w-full p-6 shadow-xl relative animate-fadeIn">
            <button @click="showSingleLogoutModal = false" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 p-1 rounded-lg hover:bg-slate-100 transition-colors cursor-pointer" aria-label="{{ __('global.common.close') }}">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center shrink-0 border border-rose-100">
                    <span class="material-symbols-outlined text-xl">logout</span>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-900">{{ __('global.devices.modal_single_logout_title') }}</h3>
                    <p class="text-xs text-slate-500">{{ __('global.devices.modal_single_logout_subtitle') }}</p>
                </div>
            </div>
            <p class="text-sm text-slate-600 mb-6 leading-relaxed">
                {{ __('global.devices.modal_single_logout_desc', ['device' => '']) }}<strong class="text-slate-800" x-text="targetDeviceName"></strong>?
            </p>
            <form :action="'{{ url('/me/devices/logout') }}/' + targetSessionId" method="POST" class="flex justify-end gap-3">
                @csrf
                <button type="button" @click="showSingleLogoutModal = false" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 text-xs font-medium transition-colors cursor-pointer">
                    {{ __('global.common.cancel') }}
                </button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-rose-600 text-white hover:bg-rose-700 text-xs font-semibold transition-colors flex items-center gap-1.5 shadow-xs cursor-pointer">
                    <span class="material-symbols-outlined text-[16px]">logout</span> {{ __('global.devices.logout_session') }}
                </button>
            </form>
        </div>
    </div>

    {{-- Temporarily hidden: emergency action dialogs.
    <!-- Modal 2: Xác nhận đăng xuất tất cả thiết bị khác -->
    <div 
        x-show="showLogoutAllModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
        @click.self="showLogoutAllModal = false"
        @keydown.escape.window="showLogoutAllModal = false"
    >
        <div class="bg-white border border-slate-200 rounded-2xl max-w-md w-full p-6 shadow-xl relative animate-fadeIn">
            <button @click="showLogoutAllModal = false" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 p-1 rounded-lg hover:bg-slate-100 transition-colors cursor-pointer" aria-label="{{ __('global.common.close') }}">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center shrink-0 border border-rose-100">
                    <span class="material-symbols-outlined text-xl">no_accounts</span>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-900">{{ __('global.devices.modal_logout_all_title') }}</h3>
                    <p class="text-xs text-slate-500">{{ __('global.devices.modal_logout_all_subtitle') }}</p>
                </div>
            </div>
            <p class="text-sm text-slate-600 mb-6 leading-relaxed">
                {{ __('global.devices.modal_logout_all_desc') }}
            </p>
            <form action="{{ route('user.me.devices.logout-all') }}" method="POST" class="flex justify-end gap-3">
                @csrf
                <button type="button" @click="showLogoutAllModal = false" class="px-4 py-2 rounded-xl border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 text-xs font-medium transition-colors cursor-pointer">
                    {{ __('global.common.cancel') }}
                </button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-rose-600 text-white hover:bg-rose-700 text-xs font-semibold transition-colors flex items-center gap-1.5 shadow-xs cursor-pointer">
                    <span class="material-symbols-outlined text-[16px]">lock_reset</span> {{ __('global.devices.confirm_logout_all') }}
                </button>
            </form>
        </div>
    </div>

    <!-- Modal 3: Hủy bỏ tài khoản vĩnh viễn (Danger Zone Modal) -->
    <div 
        x-show="showDeleteAccountModal"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 backdrop-blur-sm p-4"
        @click.self="showDeleteAccountModal = false"
        @keydown.escape.window="showDeleteAccountModal = false"
    >
        <div class="bg-white border border-rose-200 rounded-2xl max-w-lg w-full p-6 shadow-xl relative animate-fadeIn">
            <button @click="showDeleteAccountModal = false" class="absolute top-4 right-4 text-slate-400 hover:text-slate-700 p-1 rounded-lg hover:bg-slate-100 transition-colors cursor-pointer" aria-label="{{ __('global.common.close') }}">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-rose-50 text-rose-600 flex items-center justify-center shrink-0 border border-rose-100">
                    <span class="material-symbols-outlined text-2xl">warning</span>
                </div>
                <div>
                    <h3 class="text-base font-bold text-rose-600">{{ __('global.devices.modal_delete_title') }}</h3>
                    <p class="text-xs text-slate-500">{{ __('global.devices.modal_delete_subtitle') }}</p>
                </div>
            </div>

            <div class="p-3.5 rounded-xl bg-rose-50 border border-rose-200 mb-4 flex items-center gap-2.5">
                <span class="material-symbols-outlined text-rose-600 text-[20px] shrink-0">dangerous</span>
                <span class="text-xs font-bold text-rose-700">{{ __('global.devices.modal_delete_warning') }}</span>
            </div>

            <div class="space-y-2 mb-4 text-xs text-slate-600">
                <p>{{ __('global.devices.modal_delete_scope') }}</p>
                <ul class="space-y-1 list-disc pl-5 text-slate-500">
                    <li>{{ __('global.devices.delete_scope_1') }}</li>
                    <li>{{ __('global.devices.delete_scope_2') }}</li>
                    <li>{{ __('global.devices.delete_scope_3') }}</li>
                    <li>{{ __('global.devices.delete_scope_4') }}</li>
                </ul>
            </div>

            <form action="{{ route('user.me.account.delete') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-slate-700 mb-1.5" for="confirm_delete">
                        {{ __('global.devices.confirm_delete_prompt', ['email' => $user->email]) }}
                    </label>
                    <input 
                        id="confirm_delete" 
                        name="confirm_delete" 
                        required
                        class="w-full px-3.5 py-2.5 rounded-xl border border-slate-300 bg-white text-slate-900 text-sm focus:outline-none focus:ring-2 focus:ring-rose-500 focus:border-rose-500" 
                        placeholder="{{ __('global.devices.confirm_delete_placeholder', ['email' => $user->email]) }}" 
                        type="text"
                    >
                </div>

                <div class="flex flex-col sm:flex-row justify-end gap-3 pt-2">
                    <button type="button" @click="showDeleteAccountModal = false" class="px-4 py-2.5 rounded-xl border border-slate-200 bg-white text-slate-700 hover:bg-slate-50 text-xs font-medium transition-colors cursor-pointer">
                        {{ __('global.devices.keep_account') }}
                    </button>
                    <button type="submit" class="px-4 py-2.5 rounded-xl bg-rose-600 text-white hover:bg-rose-700 text-xs font-semibold transition-colors flex items-center justify-center gap-1.5 shadow-sm cursor-pointer">
                        <span class="material-symbols-outlined text-[17px]">delete_forever</span> {{ __('global.devices.confirm_delete_btn') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
    --}}
</div>
</x-global.layout>
