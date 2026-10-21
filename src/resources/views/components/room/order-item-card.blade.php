@props(['item', 'rating' => null])

{{-- Thẻ hiển thị một món trong đơn (tên, số lượng × đơn giá, thành tiền, size/đường/đá/topping, ghi chú). --}}
<div class="bg-surface-container-low rounded-lg p-2.5 flex items-start gap-2.5">
    <div
        class="relative w-9 h-9 rounded-lg bg-surface-container-high flex items-center justify-center text-primary shrink-0 overflow-hidden">
        <span class="material-symbols-outlined text-[18px]">emoji_food_beverage</span>
        @if($imageUrl = $item->campaignItem?->image_url)
            <img src="{{ $imageUrl }}" alt="{{ $item->item_name }}" loading="lazy"
                class="absolute inset-0 h-full w-full object-cover" onerror="this.remove()">
        @endif
    </div>
    <div class="flex-1 min-w-0">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <h5 class="text-sm leading-5 text-on-surface font-bold truncate">
                    {{ $item->item_name }}
                </h5>
                <p class="text-[11px] leading-4 text-on-surface-variant">
                    {{ __('room.orders.qty_prefix') }}: {{ $item->quantity }} ×
                    {{ \App\Support\Helpers\FormatHelper::formatCurrency($item->unit_price) }}
                </p>
            </div>
            <span
                class="font-tabular-nums text-tabular-nums text-sm leading-5 font-bold text-on-surface shrink-0">
                {{ \App\Support\Helpers\FormatHelper::formatCurrency($item->line_subtotal) }}
            </span>
        </div>

        <!-- Customizations tags -->
        <div class="flex flex-wrap gap-1 mt-1.5">
            @if($item->size_name)
                <span
                    class="px-1.5 py-0.5 rounded bg-surface-container-highest text-on-surface text-[10px] leading-4 flex items-center gap-0.5">
                    <span class="material-symbols-outlined text-[12px]">format_size</span>
                    Size {{ $item->size_name }}
                </span>
            @endif
            @if($item->sugar_percent !== null)
                <span
                    class="px-1.5 py-0.5 rounded bg-surface-container-highest text-on-surface text-[10px] leading-4 flex items-center gap-0.5">
                    <span class="material-symbols-outlined text-[12px]">water_drop</span>
                    {{ $item->sugar_percent }}% {{ __('room.orders.sugar') }}
                </span>
            @endif
            @if($item->ice_percent !== null)
                <span
                    class="px-1.5 py-0.5 rounded bg-surface-container-highest text-on-surface text-[10px] leading-4 flex items-center gap-0.5">
                    <span class="material-symbols-outlined text-[12px]">ac_unit</span>
                    {{ $item->ice_percent }}% {{ __('room.orders.ice') }}
                </span>
            @endif
            @foreach($item->toppings as $top)
                <span
                    class="px-1.5 py-0.5 rounded bg-primary-fixed text-on-primary-fixed-variant text-[10px] leading-4 flex items-center gap-0.5 font-medium">
                    <span class="material-symbols-outlined text-[12px]">add_circle</span>
                    {{ $top->topping_name }} (+{{ \App\Support\Helpers\FormatHelper::formatCurrency($top->unit_price) }})
                </span>
            @endforeach
            @if($item->is_self_paid)
                <span
                    class="px-1.5 py-0.5 rounded bg-amber-100 text-amber-800 border border-amber-300 text-[10px] leading-4 flex items-center gap-0.5 font-semibold">
                    <span class="material-symbols-outlined text-[12px]">payments</span>
                    {{ __('room.campaign.self_paid_badge') }}
                </span>
            @endif
        </div>

        @if($item->note)
            <div class="mt-1.5 flex items-start gap-1.5 text-on-surface-variant">
                <span
                    class="material-symbols-outlined text-secondary text-[14px] flex-shrink-0">edit_note</span>
                <p class="text-[11px] leading-4 italic">
                    “{{ $item->note }}”
                </p>
            </div>
        @endif

        @if(! $slot->isEmpty())
            {{-- Thông tin bổ sung dưới cùng thẻ (vd: người được đặt hộ và mã đơn). --}}
            <div class="mt-1.5 flex flex-wrap items-center gap-x-1 text-[10px] leading-4 text-on-surface-variant">
                {{ $slot }}
            </div>
        @endif

        @if($rating !== null)
            <x-room.item-rating :rating="$rating" />
        @endif
    </div>
</div>
