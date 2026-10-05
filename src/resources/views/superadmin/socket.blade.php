@extends('superadmin.layout', ['title' => __('superadmin.socket.title'), 'active' => 'socket'])
@section('content')
    <x-superadmin.page-header :eyebrow="__('superadmin.common.infra_security')" :title="__('superadmin.socket.title')" :description="__('superadmin.socket.description')">
    <a class="sa-button secondary" href="{{ route('superadmin.queue.page') }}">{{ __('superadmin.socket.open_failed_jobs') }}</a>
    </x-superadmin.page-header>
    <section class="sa-grid kpis kpis-4">
        <article class="sa-card sa-kpi"><span class="label">{{ __('superadmin.socket.gateway') }}</span><strong id="gateway-status"
                class="value">—</strong><span class="hint" id="gateway-reason">{{ __('superadmin.socket.gateway_health') }}</span></article>
        <article class="sa-card sa-kpi"><span class="label">{{ __('superadmin.socket.users_online') }}</span><strong id="connected-users"
                class="value">—</strong><span class="hint">{{ __('superadmin.socket.live_connections') }}</span></article>
        <article class="sa-card sa-kpi"><span class="label">{{ __('superadmin.socket.admins_online') }}</span><strong id="connected-admins"
                class="value">—</strong><span class="hint">{{ __('superadmin.socket.admin_superadmin') }}</span></article>
        <article class="sa-card sa-kpi"><span class="label">{{ __('superadmin.socket.auth_failures') }}</span><strong id="auth-failures"
                class="value">—</strong><span class="hint">{{ __('superadmin.socket.rejected_tokens') }}</span></article>
    </section>
    <section class="sa-card sa-section">
        <div class="sa-section-header">
            <div>
                <h2>{{ __('superadmin.socket.recent_disconnects') }}</h2>
                <p>{{ __('superadmin.socket.telemetry_only') }}</p>
            </div>
        </div>
        <div id="disconnects" class="sa-health-list">
            <x-superadmin.empty-state loading :bordered="false" :title="__('superadmin.common.loading_title')" :description="__('superadmin.common.loading_description')" />
        </div>
    </section>

    {{-- Empty states cloned by the page script, so every string stays translated through __(). --}}
    <template id="tpl-no-disconnects"><x-superadmin.empty-state icon="wifi_off" :bordered="false" :title="__('superadmin.socket.no_disconnects_title')" :description="__('superadmin.socket.no_disconnects_description')" /></template>
    <template id="tpl-load-failed"><x-superadmin.empty-state icon="error" :bordered="false" :title="__('superadmin.common.load_failed_title')" description="" /></template>
@endsection
@push('scripts')
    <x-superadmin.page-script name="socket" :i18n="['gateway_health' => __('superadmin.socket.gateway_health')]" :urls="['index' => route('superadmin.socket.index')]" />
@endpush
