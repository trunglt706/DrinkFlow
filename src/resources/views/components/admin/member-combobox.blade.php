@props(['name', 'members', 'selected' => null, 'placeholder' => null, 'emptyLabel' => null, 'id' => null, 'disabled' => null])

{{-- Ô chọn thành viên có tìm kiếm (Alpine): gõ để lọc theo tên / mã, chọn bằng chuột hoặc phím ↑ ↓ Enter, Esc để đóng.
     Giá trị gửi đi nằm trong input ẩn `name` (id thành viên, rỗng = không chọn). `disabled`: biểu thức Alpine. --}}
@php
    $options = collect($members)->map(static fn ($member): array => [
        'id' => (string) $member->id,
        'label' => (string) ($member->display_name ?: $member->user_code),
        'hint' => (string) $member->user_code,
        'search' => \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($member->display_name.' '.$member->user_code)),
    ])->values();
    $inputId = $id ?? 'member-combobox-'.\Illuminate\Support\Str::random(6);
@endphp
<div x-data="{
        open: false,
        query: '',
        active: 0,
        options: {{ Js::from($options) }},
        value: {{ Js::from((string) ($selected ?? '')) }},
        emptyLabel: {{ Js::from($emptyLabel ?? '') }},
        normalize(text) { return text.normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/đ/g, 'd').replace(/Đ/g, 'D').toLowerCase(); },
        get filtered() {
            const term = this.normalize(this.query.trim());
            const list = term === '' ? this.options : this.options.filter((option) => option.search.includes(term));
            return list.slice(0, 50);
        },
        get selectedLabel() { return this.options.find((option) => option.id === this.value)?.label ?? ''; },
        show() { this.open = true; this.query = ''; this.active = 0; },
        choose(option) { this.value = option ? option.id : ''; this.open = false; this.query = ''; this.$refs.hidden.dispatchEvent(new Event('change', { bubbles: true })); },
        move(step) { const count = this.filtered.length + (this.emptyLabel ? 1 : 0); if (count) this.active = (this.active + step + count) % count; },
        pick() { if (this.emptyLabel && this.active === 0) { this.choose(null); return; } const option = this.filtered[this.active - (this.emptyLabel ? 1 : 0)]; if (option) this.choose(option); },
    }"
    @click.outside="open = false" @member-combobox-reset.window="if ($event.detail === '{{ $name }}') value = ''"
    {{ $attributes->merge(['class' => 'relative']) }} data-member-combobox="{{ $name }}">
    <input type="hidden" name="{{ $name }}" x-ref="hidden" :value="value" @if ($disabled) :disabled="{{ $disabled }}" @endif>
    <input type="text" id="{{ $inputId }}" autocomplete="off" role="combobox" aria-autocomplete="list" :aria-expanded="open ? 'true' : 'false'" aria-controls="{{ $inputId }}-list"
        @if ($disabled) :disabled="{{ $disabled }}" @endif
        :value="open ? query : (selectedLabel || '')" @input="query = $event.target.value; open = true; active = 0"
        @focus="show()" @click="show()" @keydown.down.prevent="open ? move(1) : show()" @keydown.up.prevent="move(-1)"
        @keydown.enter.prevent="open && pick()" @keydown.escape="open = false" @keydown.tab="open = false"
        placeholder="{{ $placeholder ?? __('room_fund.member_search') }}"
        class="h-10 w-full rounded-lg border border-outline-variant bg-surface pl-3 pr-8 text-xs disabled:opacity-60">
    <span class="material-symbols-outlined pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-[18px] text-outline" aria-hidden="true">expand_more</span>
    <ul id="{{ $inputId }}-list" role="listbox" x-show="open" x-cloak
        class="absolute z-30 mt-1 max-h-60 w-full overflow-y-auto rounded-lg border border-outline-variant bg-surface-container-lowest py-1 text-xs shadow-lg">
        @if ($emptyLabel)
            <li role="option" :aria-selected="value === '' ? 'true' : 'false'" @mousedown.prevent="choose(null)" @mouseenter="active = 0"
                :class="active === 0 ? 'bg-surface-container' : ''" class="cursor-pointer px-3 py-2 text-on-surface-variant">{{ $emptyLabel }}</li>
        @endif
        <template x-for="(option, index) in filtered" :key="option.id">
            <li role="option" :aria-selected="value === option.id ? 'true' : 'false'" @mousedown.prevent="choose(option)" @mouseenter="active = index + (emptyLabel ? 1 : 0)"
                :class="active === index + (emptyLabel ? 1 : 0) ? 'bg-surface-container' : ''" class="flex cursor-pointer items-center justify-between gap-2 px-3 py-2 text-on-surface">
                <span class="truncate font-semibold" x-text="option.label"></span>
                <span class="shrink-0 font-mono text-[10px] text-outline" x-text="option.hint"></span>
            </li>
        </template>
        <li x-show="filtered.length === 0" class="px-3 py-2 text-outline">{{ __('room_fund.member_no_match') }}</li>
    </ul>
</div>
