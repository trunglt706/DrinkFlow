@props([
    'room' => null,
    'generatePromptUrl' => null,
    'configUrl' => null,
    'platforms' => null,
    'agents' => null,
])

@php
    $gatewayService = app(\App\Services\DataGateway\DataGatewayConverterService::class);
    $platformsData = $platforms ?? array_values($gatewayService->getPlatforms());
    $agentsData = $agents ?? array_values($gatewayService->getAiAgents());

    $resolvedPromptUrl = $generatePromptUrl;
    if (!$resolvedPromptUrl && $room) {
        $resolvedPromptUrl = route('admin.data-gateway.generate-prompt', $room);
    } elseif (!$resolvedPromptUrl && request()->route('room')) {
        $resolvedPromptUrl = route('admin.data-gateway.generate-prompt', request()->route('room'));
    }

    $routeRoom = $room ?? request()->route('room');
    $resolvedAnalyzeUrl = $routeRoom ? route('admin.data-gateway.analyze', $routeRoom) : '';
    $internalI18n = [
        'success' => __('admin.data_gateway_internal_success'),
        'platformSwitched' => __('admin.data_gateway_internal_platform_switched'),
        'failed' => __('admin.data_gateway_internal_failed'),
        'emptyOrigin' => __('admin.data_gateway_err_empty_origin'),
        'invalidJson' => __('admin.data_gateway_err_invalid_json'),
        'counts' => __('admin.data_gateway_internal_summary_counts'),
    ];

    $resolvedConfigUrl = $configUrl;
    if (!$resolvedConfigUrl && $room) {
        $resolvedConfigUrl = route('admin.data-gateway.config', $room);
    } elseif (!$resolvedConfigUrl && request()->route('room')) {
        $resolvedConfigUrl = route('admin.data-gateway.config', request()->route('room'));
    }
@endphp

<div x-data="dataGatewayConverterComponent('{{ $resolvedPromptUrl }}', '{{ $resolvedConfigUrl }}', {{ \Illuminate\Support\Js::from($platformsData) }}, {{ \Illuminate\Support\Js::from($agentsData) }})"
    class="space-y-4 rounded-xl border border-outline-variant bg-surface-container-low/40 p-4 transition-all"
    data-gateway-converter data-analyze-url="{{ $resolvedAnalyzeUrl }}"
    data-internal-i18n="{{ json_encode($internalI18n, JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) }}">

    <!-- Header & Badge -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-outline-variant/60 pb-3">
        <div class="flex items-center gap-2.5">
            <span
                class="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-tr from-primary via-indigo-500 to-secondary text-on-primary shadow-sm">
                <span class="material-symbols-outlined text-[18px]">neurology</span>
            </span>
            <div>
                <h3 class="text-xs font-bold text-on-surface uppercase tracking-wide flex items-center gap-2">
                    {{ __('admin.data_gateway_title') }}
                    <span
                        class="rounded-full bg-gradient-to-r from-primary/15 to-secondary/15 px-2 py-0.5 font-mono text-[9px] font-bold text-primary uppercase border border-primary/25 max-lg:hidden">
                        {{ __('admin.data_gateway_badge') }}
                    </span>
                </h3>
                <p class="text-[11px] text-outline mt-0.5">{{ __('admin.data_gateway_desc') }}</p>
            </div>
        </div>
    </div>

    <!-- Step 1 & 2: Platform & AI Agent Dynamic Selectors -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div>
            <label class="block text-xs font-semibold text-on-surface mb-1 flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[15px] text-primary">storefront</span>
                {{ __('admin.data_gateway_platform_label') }}
            </label>
            <select x-model="platform"
                class="w-full px-3 py-2 bg-surface border border-outline-variant rounded-lg text-xs text-on-surface focus:outline-none focus:border-primary font-medium">
                <template x-for="item in platforms" :key="item.id">
                    <option :value="item.id" x-text="item.name" :selected="item.id === platform"></option>
                </template>
            </select>
        </div>
        <div>
            <label class="block text-xs font-semibold text-on-surface mb-1 flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[15px] text-secondary">smart_toy</span>
                {{ __('admin.data_gateway_ai_label') }}
            </label>
            <select x-model="aiAgent"
                class="w-full px-3 py-2 bg-surface border border-outline-variant rounded-lg text-xs text-on-surface focus:outline-none focus:border-primary font-medium">
                <template x-for="item in agents" :key="item.id">
                    <option :value="item.id" x-text="item.name" :selected="item.id === aiAgent"></option>
                </template>
            </select>
        </div>
    </div>

    <!-- Highlighted Dynamic Instruction Card (Loaded from Service/API) -->
    <div
        class="relative overflow-hidden rounded-xl border-2 border-primary/30 bg-gradient-to-br from-primary/10 via-surface-container-low to-secondary/10 p-4 shadow-sm">
        <div class="flex items-center justify-between pb-2 border-b border-primary/20">
            <div class="flex items-center gap-2 font-bold text-xs text-primary uppercase tracking-wide">
                <span class="material-symbols-outlined text-[18px] animate-pulse">tips_and_updates</span>
                <span>{{ __('admin.data_gateway_instruction_title') }}</span>
            </div>
            <span class="rounded bg-primary/20 px-2 py-0.5 text-[10px] font-mono font-bold text-primary uppercase"
                x-text="selectedPlatform?.id || platform"></span>
        </div>

        <div class="mt-3 space-y-2.5 text-xs">
            <template x-if="selectedPlatform">
                <div class="space-y-2">
                    <p class="text-xs font-semibold text-on-surface leading-snug" x-text="selectedPlatform.instruction">
                    </p>
                    <template x-if="selectedPlatform.api_prefix">
                        <div
                            class="flex items-center gap-2 bg-surface/90 border border-outline-variant rounded-lg px-2.5 py-1.5 font-mono text-[11px] text-primary select-all break-all shadow-2xs">
                            <code class="truncate" x-text="selectedPlatform.api_prefix"></code>
                        </div>
                    </template>
                </div>
            </template>
        </div>
    </div>

    <!-- Step 3: Origin JSON Textarea & Action Buttons -->
    <div class="space-y-2">
        <div class="flex items-center justify-between">
            <label class="text-xs font-semibold text-on-surface flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[15px] text-outline">code</span>
                {{ __('admin.data_gateway_origin_json_label') }}
            </label>
            <span class="text-[10px] font-mono text-outline" x-text="originJsonCharCount"></span>
        </div>
        <textarea x-model="originJson" rows="5" placeholder="{{ __('admin.data_gateway_origin_json_placeholder') }}"
            class="w-full font-mono text-[11px] p-3 bg-surface border border-outline-variant rounded-lg text-on-surface focus:outline-none focus:border-primary leading-relaxed shadow-inner"></textarea>

        <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
            <div class="flex flex-wrap items-center gap-2">
                <!-- Button 0: Internal analysis (no AI agent) -->
                <button type="button" @click="analyzeInternally()" :disabled="loading || analyzing || !originJson.trim()"
                    title="{{ __('admin.data_gateway_internal_hint') }}" data-internal-analyze-btn
                    class="px-3.5 py-2 bg-secondary text-white rounded-lg text-xs font-semibold hover:opacity-95 shadow-xs transition-all flex items-center gap-1.5 active:scale-98 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span class="material-symbols-outlined text-[16px]" :class="analyzing ? 'animate-spin' : ''"
                        x-text="analyzing ? 'sync' : 'analytics'"></span>
                    <span>{{ __('admin.data_gateway_btn_internal_analyze') }}</span>
                </button>

                <!-- Button 1: Copy Prompt -->
                <button type="button" @click="copyPrompt()" :disabled="loading || !originJson.trim()"
                    class="px-3 py-2 bg-surface border border-outline-variant rounded-lg text-xs font-semibold text-on-surface hover:bg-surface-container-low hover:border-primary shadow-xs transition-all flex items-center gap-1.5 active:scale-98 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span class="material-symbols-outlined text-[16px] text-primary"
                        :class="loading ? 'animate-spin' : ''" x-text="loading ? 'sync' : 'content_copy'"></span>
                    <span>{{ __('admin.data_gateway_btn_copy_prompt') }}</span>
                </button>

                <!-- Button 2: Copy Prompt & Open Agent Tab -->
                <button type="button" @click="copyPromptAndOpenAgent()" :disabled="loading || !originJson.trim()"
                    class="px-3.5 py-2 bg-gradient-to-r from-primary to-primary-container text-on-primary rounded-lg text-xs font-semibold hover:opacity-95 shadow-xs transition-all flex items-center gap-1.5 active:scale-98 disabled:opacity-50 disabled:cursor-not-allowed">
                    <span class="material-symbols-outlined text-[16px]" :class="loading ? 'animate-spin' : ''"
                        x-text="loading ? 'sync' : 'open_in_new'"></span>
                    <span
                        x-text="`{{ __('admin.data_gateway_btn_copy_and_open_agent', ['agent' => ':agent']) }}`.replace(':agent', selectedAgentDisplayName)"></span>
                </button>

                <!-- Button 3: Format JSON -->
                <button type="button" @click="formatJson()" x-show="originJson.trim().length > 0"
                    class="px-3 py-2 bg-surface border border-outline-variant rounded-lg text-xs font-medium text-outline hover:text-on-surface hover:bg-surface-container-low transition-colors flex items-center gap-1">
                    <span class="material-symbols-outlined text-[15px]">code_blocks</span>
                    <span>{{ __('admin.data_gateway_btn_format_json') }}</span>
                </button>
            </div>

            <button type="button" @click="clearOriginJson()" x-show="originJson.trim().length > 0"
                class="text-[11px] text-error hover:underline flex items-center gap-0.5">
                <span class="material-symbols-outlined text-[14px]">delete</span>
                <span>{{ __('admin.data_gateway_btn_clear') }}</span>
            </button>
        </div>
    </div>

    <p class="text-[11px] text-outline flex items-start gap-1.5 -mt-2">
        <span class="material-symbols-outlined text-[14px] text-secondary shrink-0">info</span>
        <span>{{ __('admin.data_gateway_internal_hint') }}</span>
    </p>

    <!-- Internal Analysis Summary -->
    <div x-show="analysis" x-cloak data-internal-analysis-summary
        class="rounded-lg border border-secondary/30 bg-secondary-container/40 p-3 text-xs text-on-surface space-y-1.5">
        <div class="flex items-center gap-1.5 font-semibold">
            <span class="material-symbols-outlined text-[16px] text-secondary">analytics</span>
            <span>{{ __('admin.data_gateway_internal_summary_title') }}</span>
            <span class="rounded bg-secondary/15 px-1.5 py-0.5 font-mono text-[10px] uppercase" x-text="analysis?.platform"></span>
        </div>
        <p x-text="analysisCountsText"></p>
        <template x-if="analysis?.summary?.ignored_groups?.length">
            <div class="text-[11px] text-outline">
                <span>{{ __('admin.data_gateway_internal_ignored_groups') }}</span>
                <span class="font-medium text-on-surface" x-text="analysis.summary.ignored_groups.join(', ')"></span>
            </div>
        </template>
    </div>

    <!-- Toast / Notification Alert Box -->
    <div x-show="toast.show" x-cloak x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-1"
        :class="toast.type === 'error' ? 'bg-error-container text-on-error-container border-error/30' :
            'bg-secondary-container text-on-secondary-container border-secondary/30'"
        class="flex items-center justify-between gap-2 rounded-lg border p-3 text-xs shadow-xs">
        <div class="flex items-center gap-2">
            <span class="material-symbols-outlined text-[18px]"
                x-text="toast.type === 'error' ? 'error' : 'check_circle'"></span>
            <span class="font-medium" x-text="toast.message"></span>
        </div>
        <button type="button" @click="toast.show = false" class="opacity-70 hover:opacity-100">
            <span class="material-symbols-outlined text-[16px]">close</span>
        </button>
    </div>

    <!-- Step 4: AI Result JSON Input & Apply to Menu -->
    <div class="space-y-2 pt-2 border-t border-outline-variant/60">
        {{-- Mobile: the step title sits above its hint instead of beside it. --}}
        <div class="flex items-center justify-between max-lg:flex-col max-lg:items-start max-lg:gap-0.5">
            <label class="text-xs font-semibold text-on-surface flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[15px] text-secondary">data_object</span>
                {{ __('admin.data_gateway_ai_result_label') }}
            </label>
            <span class="text-[10px] text-outline italic">{{ __('admin.data_gateway_paste_tip') }}</span>
        </div>
        <textarea x-model="aiResultJson" rows="4" placeholder="{{ __('admin.data_gateway_ai_result_placeholder') }}"
            class="w-full font-mono text-[11px] p-3 bg-surface border border-outline-variant rounded-lg text-on-surface focus:outline-none focus:border-secondary leading-relaxed shadow-inner"></textarea>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <!-- Hidden file input for .md/.json/.txt files -->
            <input type="file" accept=".md,.json,.txt" x-ref="resultFileInput" @change="importResultFile($event)"
                class="sr-only">

            {{-- Mobile: "apply to menu" (short label) and "import result file" share a full-width row. --}}
            <div class="flex flex-wrap items-center gap-2 max-lg:w-full max-lg:flex-nowrap">
                <!-- Apply to Menu Button -->
                <button type="button" @click="applyAiResultToMenu()" :disabled="!aiResultJson.trim()"
                    class="px-4 py-2 bg-primary text-on-primary rounded-lg text-xs font-semibold hover:bg-primary-container shadow-xs transition-colors flex items-center justify-center gap-1.5 disabled:opacity-50 disabled:cursor-not-allowed max-lg:min-w-0 max-lg:flex-1 max-lg:px-3">
                    <span class="material-symbols-outlined text-[16px]">playlist_add_check</span>
                    <span class="max-lg:hidden">{{ __('admin.data_gateway_btn_apply_to_menu') }}</span>
                    <span class="lg:hidden">{{ __('admin.data_gateway_btn_apply_to_menu_short') }}</span>
                </button>

                <!-- Import Result File Button -->
                <button type="button" @click="$refs.resultFileInput.click()"
                    class="px-3.5 py-2 bg-surface border border-outline-variant rounded-lg text-xs font-semibold text-on-surface hover:bg-surface-container-low hover:border-secondary shadow-xs transition-all flex items-center justify-center gap-1.5 active:scale-98 max-lg:min-w-0 max-lg:flex-1 max-lg:px-3">
                    <span class="material-symbols-outlined text-[16px] text-secondary">upload_file</span>
                    <span>{{ __('admin.data_gateway_btn_import_file') }}</span>
                </button>
            </div>

            <button type="button" @click="aiResultJson = ''" x-show="aiResultJson.trim().length > 0"
                class="text-[11px] text-outline hover:text-on-surface">
                {{ __('admin.data_gateway_btn_clear') }}
            </button>
        </div>

        <!-- Menu Expiry Notice -->
        <div
            class="rounded-lg border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-900 dark:text-amber-200 flex items-start gap-2.5 shadow-2xs mt-2">
            <span
                class="material-symbols-outlined text-[18px] text-amber-600 dark:text-amber-400 shrink-0 mt-0.5">info</span>
            <div class="leading-relaxed">
                <strong class="font-semibold">{{ __('admin.data_gateway_menu_expiry_title') }}:</strong>
                <span>{{ __('admin.data_gateway_menu_expiry_desc') }}</span>
            </div>
        </div>
    </div>
</div>

<script>
    function dataGatewayConverterComponent(apiGeneratePromptUrl, apiConfigUrl, initialPlatforms = [],
        initialAgents = []) {
        return {
            apiUrl: apiGeneratePromptUrl || '',
            configUrl: apiConfigUrl || '',
            platforms: Array.isArray(initialPlatforms) && initialPlatforms.length ? initialPlatforms : [],
            agents: Array.isArray(initialAgents) && initialAgents.length ? initialAgents : [],
            platform: '',
            aiAgent: '',
            originJson: '',
            aiResultJson: '',
            loading: false,
            analyzing: false,
            analysis: null,
            toast: {
                show: false,
                type: 'success',
                message: '',
                timer: null
            },

            init() {
                if (this.platforms.length > 0) {
                    this.platform = this.platforms[0].id;
                }
                if (this.agents.length > 0) {
                    this.aiAgent = this.agents[0].id;
                }

                if (!this.platforms.length && this.configUrl) {
                    this.fetchConfigFromServer();
                }
            },

            get selectedPlatform() {
                return this.platforms.find(p => p.id === this.platform) || this.platforms[0] || null;
            },

            get selectedAgent() {
                return this.agents.find(a => a.id === this.aiAgent) || this.agents[0] || null;
            },

            get selectedAgentDisplayName() {
                return this.selectedAgent?.display_name || this.selectedAgent?.name || (this.aiAgent === 'chatgpt' ?
                    'ChatGPT' : 'Gemini');
            },

            get selectedAgentUrl() {
                return this.selectedAgent?.url || (this.aiAgent === 'chatgpt' ? 'https://chatgpt.com/' :
                    'https://gemini.google.com/');
            },

            get internalI18n() {
                try {
                    return JSON.parse(this.$root.dataset.internalI18n || '{}');
                } catch (e) {
                    return {};
                }
            },

            get analysisCountsText() {
                const summary = this.analysis?.summary;
                if (!summary) return '';
                return (this.internalI18n.counts || '')
                    .replace(':items', summary.item_count)
                    .replace(':categories', summary.category_count)
                    .replace(':duplicates', summary.skipped_duplicates)
                    .replace(':unavailable', summary.skipped_unavailable);
            },

            /**
             * Convert the origin JSON on the server (no AI agent) and put the menu into the step 4 result box,
             * so the admin reviews it and loads it with the existing "apply to menu" button.
             */
            async analyzeInternally() {
                const i18n = this.internalI18n;
                const raw = this.originJson.trim();
                if (!raw) {
                    this.showToastMessage(i18n.emptyOrigin, 'error');
                    return;
                }
                try {
                    JSON.parse(raw);
                } catch (e) {
                    this.showToastMessage(i18n.invalidJson, 'error');
                    return;
                }

                this.analyzing = true;
                try {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                    const response = await fetch(this.$root.dataset.analyzeUrl, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrfToken,
                        },
                        body: JSON.stringify({ platform: this.platform, origin_json: raw }),
                    });
                    const result = await response.json().catch(() => ({}));
                    if (!response.ok || !result.data) {
                        throw new Error(result.message || i18n.failed);
                    }

                    const data = result.data;
                    this.analysis = data;
                    this.aiResultJson = JSON.stringify(data.items, null, 2);

                    let message = (i18n.success || '')
                        .replace(':items', data.summary.item_count)
                        .replace(':categories', data.summary.category_count);
                    if (data.platform_mismatch) {
                        const platformName = this.platforms.find(p => p.id === data.platform)?.name || data.platform;
                        message = (i18n.platformSwitched || '').replaceAll(':platform', platformName) + ' ' + message;
                    }
                    this.platform = data.platform;
                    if (data.restaurant) {
                        window.dispatchEvent(new CustomEvent('drinkflow:suggest-restaurant', { detail: { name: data.restaurant } }));
                    }
                    this.showToastMessage(message, 'success', 8000);
                } catch (err) {
                    this.analysis = null;
                    this.showToastMessage(err.message || i18n.failed, 'error');
                } finally {
                    this.analyzing = false;
                }
            },

            get originJsonCharCount() {
                const count = this.originJson.length;
                return `{{ __('admin.data_gateway_chars_count', ['count' => ':count']) }}`.replace(':count', count
                    .toLocaleString());
            },

            async fetchConfigFromServer() {
                try {
                    const response = await fetch(this.configUrl, {
                        headers: {
                            'Accept': 'application/json'
                        }
                    });
                    if (!response.ok) return;
                    const data = await response.json();
                    if (Array.isArray(data.platforms) && data.platforms.length) {
                        this.platforms = data.platforms;
                        if (!this.platform) this.platform = this.platforms[0].id;
                    }
                    if (Array.isArray(data.ai_agents) && data.ai_agents.length) {
                        this.agents = data.ai_agents;
                        if (!this.aiAgent) this.aiAgent = this.agents[0].id;
                    }
                } catch (e) {
                    console.warn('Cannot fetch data gateway config:', e);
                }
            },

            importResultFile(event) {
                const file = event.target.files?.[0];
                if (!file) return;

                const reader = new FileReader();
                reader.onload = (e) => {
                    const content = e.target?.result || '';
                    this.aiResultJson = '';
                    this.$nextTick(() => {
                        this.aiResultJson = typeof content === 'string' ? content : String(content);
                        const msg =
                            `{{ __('admin.data_gateway_success_import_file', ['file' => ':file']) }}`
                            .replace(':file', file.name);
                        this.showToastMessage(msg, 'success');
                    });
                };
                reader.onerror = () => {
                    this.showToastMessage('{{ addslashes(__('admin.data_gateway_file_read_error')) }}'.replace(':message', reader.error?.message || '{{ addslashes(__('admin.data_gateway_file_read_failed')) }}'),
                        'error');
                };
                reader.readAsText(file, 'UTF-8');
                event.target.value = '';
            },

            showToastMessage(message, type = 'success', duration = 5000) {
                this.toast.type = type;
                this.toast.message = message;
                this.toast.show = true;
                if (this.toast.timer) clearTimeout(this.toast.timer);
                this.toast.timer = setTimeout(() => {
                    this.toast.show = false;
                }, duration);
            },

            clearOriginJson() {
                this.originJson = '';
            },

            formatJson() {
                try {
                    if (!this.originJson.trim()) return;
                    const parsed = JSON.parse(this.originJson);
                    this.originJson = JSON.stringify(parsed, null, 2);
                } catch (e) {
                    this.showToastMessage('{{ __('admin.data_gateway_err_invalid_json') }}', 'error');
                }
            },

            async fetchPromptFromServer(originRaw) {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const response = await fetch(this.apiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                    },
                    body: JSON.stringify({
                        platform: this.platform,
                        ai_agent: this.aiAgent,
                        origin_json: originRaw,
                    })
                });

                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || '{{ addslashes(__('admin.data_gateway_prompt_server_error')) }}');
                }

                return data;
            },

            async copyPrompt(silent = false) {
                const raw = this.originJson.trim();
                if (!raw) {
                    this.showToastMessage('{{ __('admin.data_gateway_err_empty_origin') }}', 'error');
                    return null;
                }

                // Client-side quick validation before API dispatch
                try {
                    JSON.parse(raw);
                } catch (e) {
                    this.showToastMessage('{{ __('admin.data_gateway_err_invalid_json') }}', 'error');
                    return null;
                }

                this.loading = true;
                try {
                    let promptText = '';
                    let agentUrl = this.selectedAgentUrl;

                    if (this.apiUrl) {
                        const serverResult = await this.fetchPromptFromServer(raw);
                        promptText = serverResult.prompt;
                        agentUrl = serverResult.agent_url || agentUrl;
                    } else {
                        promptText = raw;
                    }

                    if (navigator.clipboard && window.isSecureContext) {
                        await navigator.clipboard.writeText(promptText);
                    } else {
                        const textArea = document.createElement('textarea');
                        textArea.value = promptText;
                        textArea.style.position = 'fixed';
                        textArea.style.left = '-999999px';
                        textArea.style.top = '-999999px';
                        document.body.appendChild(textArea);
                        textArea.focus();
                        textArea.select();
                        document.execCommand('copy');
                        textArea.remove();
                    }

                    if (!silent) {
                        const successMsg = `{{ __('admin.data_gateway_success_copy_prompt', ['ai' => ':ai']) }}`
                            .replace(':ai', this.selectedAgentDisplayName);
                        this.showToastMessage(successMsg, 'success');
                    }

                    return {
                        promptText,
                        agentUrl
                    };
                } catch (err) {
                    this.showToastMessage(err.message || '{{ addslashes(__('admin.data_gateway_copy_prompt_failed')) }}', 'error');
                    return null;
                } finally {
                    this.loading = false;
                }
            },

            async copyPromptAndOpenAgent() {
                const result = await this.copyPrompt(true);
                if (!result) return;

                const targetUrl = result.agentUrl || this.selectedAgentUrl;

                // Open in new tab
                window.open(targetUrl, '_blank', 'noopener,noreferrer');

                const successMsg = `{{ __('admin.data_gateway_success_copy_prompt', ['ai' => ':ai']) }}`.replace(
                    ':ai', this.selectedAgentDisplayName);
                this.showToastMessage(successMsg, 'success');
            },

            applyAiResultToMenu() {
                let raw = this.aiResultJson.trim();
                if (!raw) {
                    this.showToastMessage('{{ __('admin.data_gateway_err_empty_ai_result') }}', 'error');
                    return;
                }

                // A .md file (or an AI answer) may wrap the JSON in a ```json ... ``` block with text around it.
                const fenced = raw.match(/```(?:json)?\s*([\s\S]*?)```/i);
                raw = (fenced ? fenced[1] : raw).trim();

                let parsed;
                try {
                    parsed = JSON.parse(raw);
                } catch (e) {
                    this.showToastMessage('{{ __('admin.data_gateway_err_invalid_ai_result') }}', 'error');
                    return;
                }

                let itemsList = [];
                if (Array.isArray(parsed)) {
                    itemsList = parsed;
                } else if (parsed && typeof parsed === 'object') {
                    if (Array.isArray(parsed.items)) {
                        itemsList = parsed.items;
                    } else if (Array.isArray(parsed.dishes)) {
                        itemsList = parsed.dishes;
                    } else if (Array.isArray(parsed.menu)) {
                        itemsList = parsed.menu;
                    } else if (Array.isArray(parsed.data)) {
                        itemsList = parsed.data;
                    } else if (Array.isArray(parsed.products)) {
                        itemsList = parsed.products;
                    } else if (Array.isArray(parsed.categories)) {
                        parsed.categories.forEach(cat => {
                            const catName = cat.name || cat.category || '{{ addslashes(__('admin.default_menu_category')) }}';
                            const catItems = cat.items || cat.dishes || [];
                            catItems.forEach(item => {
                                itemsList.push({
                                    ...item,
                                    category: item.category || catName
                                });
                            });
                        });
                    }
                }

                if (!itemsList.length) {
                    this.showToastMessage('{{ __('admin.data_gateway_err_invalid_ai_result') }}', 'error');
                    return;
                }

                // Only named items count; a category left without any of them is dropped with them.
                itemsList = itemsList.filter(item => item && typeof item === 'object' && String(item.name ?? '').trim() !== '');
                if (!itemsList.length) {
                    this.showToastMessage('{{ __('admin.data_gateway_err_invalid_ai_result') }}', 'error');
                    return;
                }

                // Sync with parent Alpine component (campaignCreateComponent)
                if (typeof this.applyMenuItems === 'function') {
                    this.applyMenuItems(itemsList);
                } else if (this.$root && typeof this.$root.applyMenuItems === 'function') {
                    this.$root.applyMenuItems(itemsList);
                } else {
                    window.dispatchEvent(new CustomEvent('drinkflow:apply-menu-items', {
                        detail: {
                            items: itemsList
                        }
                    }));
                }

                const successMsg = `{{ __('admin.data_gateway_success_apply_menu', ['count' => ':count']) }}`.replace(
                    ':count', itemsList.length);
                this.showToastMessage(successMsg, 'success');
            }
        };
    }
</script>
