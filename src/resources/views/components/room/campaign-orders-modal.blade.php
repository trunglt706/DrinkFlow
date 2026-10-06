{{-- Modal hiển thị toàn bộ đơn hàng của một chiến dịch trong phòng (dùng chung bởi trang Công nợ, Chiến dịch và Đơn hàng của tôi). --}}
{{-- Yêu cầu component cha có sẵn Alpine state: campaignModalOpen, campaignLoading, campaignData, openCampaignDetail(campaignId). --}}
{{-- Danh sách rút gọn theo hàng ngang (họ tên, email, món & topping) vì số lượng thành viên trong phòng có thể lên tới hàng trăm. --}}
@once
<style>
    .df-sponsor-mark{position:relative;display:inline-flex;align-items:center;justify-content:center;width:18px;height:18px;border-radius:999px;background:#fef3c7;color:#b45309;flex-shrink:0;cursor:default}
    .df-sponsor-mark .material-symbols-outlined{font-size:13px;font-variation-settings:'FILL' 1}
    .df-sponsor-mark .df-sponsor-mark-tip{position:absolute;left:calc(100% + 6px);top:50%;transform:translateY(-50%);white-space:nowrap;background:#0f172a;color:#fff;font-size:11px;font-weight:600;padding:4px 8px;border-radius:6px;opacity:0;visibility:hidden;pointer-events:none;transition:opacity .15s;z-index:30}
    .df-sponsor-mark:hover .df-sponsor-mark-tip,.df-sponsor-mark:focus-visible .df-sponsor-mark-tip{opacity:1;visibility:visible}
    .df-sponsor-mark:focus-visible{outline:2px solid #2563eb;outline-offset:2px}
</style>
@endonce
<template x-teleport="body">
    <div x-show="campaignModalOpen" x-cloak @keydown.escape.window="campaignModalOpen = false"
        class="fixed inset-0 z-[100] flex items-center justify-center p-3 sm:p-4 bg-slate-900/60 backdrop-blur-md transition-opacity duration-200"
        role="dialog" aria-modal="true">
        <div class="bg-surface-container-lowest w-full max-w-3xl max-h-[90vh] rounded-2xl shadow-2xl border border-outline-variant overflow-hidden flex flex-col"
            @click.outside="campaignModalOpen = false">
            <!-- Modal Header -->
            <div class="p-3.5 sm:p-4 border-b border-outline-variant/30 flex items-start justify-between bg-surface-container-low gap-3">
                <div class="flex items-start gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-xl bg-primary-fixed flex items-center justify-center text-on-primary-fixed-variant shrink-0 mt-0.5">
                        <span class="material-symbols-outlined text-[22px]">campaign</span>
                    </div>
                    <div class="min-w-0">
                        <h3 class="text-sm sm:text-base text-on-surface font-bold truncate"
                            x-text="campaignData?.name || '{{ __('room.debts.campaign_details_title') }}'"></h3>
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-on-surface-variant mt-0.5">
                            <template x-if="campaignData?.restaurant">
                                <span class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-[14px] text-primary">storefront</span>
                                    <span class="font-medium text-on-surface" x-text="campaignData.restaurant"></span>
                                </span>
                            </template>
                            <template x-if="campaignData?.code">
                                <span class="flex items-center gap-1 font-mono text-[11px]">
                                    <span class="material-symbols-outlined text-[13px]">tag</span>
                                    <span x-text="campaignData.code"></span>
                                </span>
                            </template>
                        </div>
                    </div>
                </div>
                <button
                    class="w-8 h-8 rounded-lg flex items-center justify-center text-on-surface-variant hover:bg-surface-container-high transition-colors cursor-pointer shrink-0"
                    @click="campaignModalOpen = false">
                    <span class="material-symbols-outlined text-[20px]">close</span>
                </button>
            </div>

            <!-- Modal Body: bảng gọn theo hàng ngang -->
            <div class="overflow-y-auto max-h-[65vh] bg-surface-container-lowest">
                <!-- Loading skeleton -->
                <template x-if="campaignLoading">
                    <div class="p-4 space-y-2 animate-pulse">
                        <div class="h-8 bg-slate-100 rounded"></div>
                        <div class="h-8 bg-slate-100 rounded"></div>
                        <div class="h-8 bg-slate-100 rounded"></div>
                        <div class="h-8 bg-slate-100 rounded"></div>
                    </div>
                </template>

                <!-- Empty orders state -->
                <template x-if="!campaignLoading && campaignData && (!campaignData.orders || campaignData.orders.length === 0)">
                    <div class="py-10 text-center text-on-surface-variant flex flex-col items-center justify-center gap-2">
                        <span class="material-symbols-outlined text-[36px] text-outline-variant">receipt_long</span>
                        <span class="text-xs font-medium">{{ __('room.debts.campaign_empty_orders') }}</span>
                    </div>
                </template>

                <!-- Orders table -->
                <template x-if="!campaignLoading && campaignData && campaignData.orders && campaignData.orders.length > 0">
                    <table class="w-full text-xs border-collapse">
                        <thead class="sticky top-0 bg-surface-container-low text-on-surface-variant text-[10px] uppercase tracking-wider z-10 border-b border-outline-variant/30">
                            <tr>
                                <th class="text-left px-3.5 py-2 font-semibold w-2/5 sm:w-1/3">{{ __('room.debts.column_orderer_name') }}</th>
                                <th class="text-left px-3.5 py-2 font-semibold">{{ __('room.debts.column_items_toppings') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="order in campaignData.orders" :key="order.id">
                                <tr class="border-b border-outline-variant/20 hover:bg-surface-container-low/50">
                                    <td class="px-3.5 py-2.5 align-top">
                                        <div class="font-semibold text-on-surface text-xs leading-snug flex items-center gap-1">
                                            <span x-text="order.orderer_name"></span>
                                            <template x-if="order.is_sponsor">
                                                <span class="df-sponsor-mark" role="img" tabindex="0" aria-label="{{ __('room.debts.campaign_sponsor_tooltip') }}" data-campaign-sponsor-mark>
                                                    <span class="material-symbols-outlined" aria-hidden="true">volunteer_activism</span>
                                                    <span class="df-sponsor-mark-tip" aria-hidden="true">{{ __('room.debts.campaign_sponsor_tooltip') }}</span>
                                                </span>
                                            </template>
                                        </div>
                                        <template x-if="order.orderer_email">
                                            <div class="text-[11px] text-on-surface-variant font-normal leading-tight mt-0.5 break-all sm:break-normal" x-text="order.orderer_email"></div>
                                        </template>
                                    </td>
                                    <td class="px-3.5 py-2.5 align-top">
                                        <div class="flex flex-col divide-y divide-outline-variant/10">
                                            <template x-for="item in order.items" :key="item.id">
                                                <div class="py-1 first:pt-0 last:pb-0 [overflow-wrap:anywhere]">
                                                    <div class="font-medium text-on-surface text-xs flex items-center gap-1.5 leading-snug">
                                                        <span x-text="item.item_name"></span>
                                                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-emerald-50 text-[#006948]" x-text="'x' + (item.quantity || 1)"></span>
                                                    </div>
                                                    <template x-if="item.size_name || (item.toppings && item.toppings.length > 0) || (item.options && item.options.length > 0) || item.sugar_percent || item.ice_percent || item.note">
                                                        <div class="text-[11px] text-on-surface-variant font-normal leading-relaxed mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                                            <template x-if="item.size_name">
                                                                <span class="inline-flex items-center text-slate-600">
                                                                    <span class="text-slate-400 mr-0.5 text-[10px]">Size:</span>
                                                                    <span class="font-medium" x-text="item.size_name"></span>
                                                                </span>
                                                            </template>
                                                            <template x-if="item.toppings && item.toppings.length > 0">
                                                                <span class="inline-flex items-center text-slate-500">
                                                                    <span class="text-slate-400 mr-0.5 text-[10px]">+</span>
                                                                    <span x-text="item.toppings.map(t => t.name).join(', ')"></span>
                                                                </span>
                                                            </template>
                                                            <template x-if="item.options && item.options.length > 0 && (!item.toppings || item.toppings.length === 0)">
                                                                <span class="inline-flex items-center text-slate-500">
                                                                    <span class="text-slate-400 mr-0.5 text-[10px]">+</span>
                                                                    <span x-text="item.options.map(o => o.name || o).join(', ')"></span>
                                                                </span>
                                                            </template>
                                                            <template x-if="item.sugar_percent !== null && item.sugar_percent !== undefined && item.sugar_percent !== ''">
                                                                <span class="text-slate-500" x-text="item.sugar_percent + '% đường'"></span>
                                                            </template>
                                                            <template x-if="item.ice_percent !== null && item.ice_percent !== undefined && item.ice_percent !== ''">
                                                                <span class="text-slate-500" x-text="item.ice_percent + '% đá'"></span>
                                                            </template>
                                                            <template x-if="item.note">
                                                                <span class="italic text-slate-400 text-[10px]" x-text="'(' + item.note + ')'"></span>
                                                            </template>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </template>
            </div>

            <!-- Modal Footer -->
            <div class="p-3 sm:p-3.5 border-t border-outline-variant/30 flex items-center justify-between bg-surface-container-low text-xs">
                <template x-if="campaignData">
                    <span class="flex items-center gap-1 font-medium text-on-surface-variant">
                        <span class="material-symbols-outlined text-[15px] text-primary">shopping_bag</span>
                        <span x-text="campaignData.total_orders + ' {{ __('room.debts.campaign_orders_count') }}' + (campaignData.total_cups ? ' (' + '{{ __('room.debts.items_count', ['count' => ':count']) }}'.replace(':count', campaignData.total_cups) + ')' : '')"></span>
                    </span>
                </template>
                <button type="button" @click="campaignModalOpen = false"
                    class="px-4 py-2 rounded-xl border border-outline-variant bg-surface-container-lowest text-on-surface font-semibold text-xs hover:bg-surface-container-high transition-colors cursor-pointer">
                    {{ __('global.common.close') }}
                </button>
            </div>
        </div>
    </div>
</template>
