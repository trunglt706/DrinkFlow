@props(['text', 'align' => 'center', 'resetMs' => 1500])

@php
    // Căn tooltip theo vị trí nút để không tràn ra ngoài màn hình (right: nút sát mép phải, left: sát mép trái).
    $tooltipAlign = match ($align) {
        'right' => 'right-0',
        'left' => 'left-0',
        default => 'left-1/2 -translate-x-1/2',
    };
@endphp

{{-- Nút sao chép nội dung vào clipboard, hiển thị tooltip khi hover; sau khi sao chép icon thành dấu check rồi trở lại sau $resetMs ms. --}}
<span x-data="{
        copied: false,
        timer: null,
        async copy() {
            const value = {{ Js::from((string) $text) }};
            try {
                await navigator.clipboard.writeText(value);
            } catch (error) {
                const helper = document.createElement('textarea');
                helper.value = value;
                helper.style.position = 'fixed';
                helper.style.opacity = '0';
                document.body.appendChild(helper);
                helper.select();
                document.execCommand('copy');
                helper.remove();
            }
            this.copied = true;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.copied = false, {{ (int) $resetMs }});
        }
    }"
      {{ $attributes->merge(['class' => 'group/copy relative inline-flex']) }}>
    <button type="button" @click.stop.prevent="copy()"
            class="inline-flex h-6 w-6 items-center justify-center rounded-md text-outline transition-colors hover:bg-surface-container-high hover:text-primary cursor-pointer"
            aria-label="{{ __('room.orders.copy_code') }}">
        <span class="material-symbols-outlined text-[16px]" x-text="copied ? 'check' : 'content_copy'" aria-hidden="true">content_copy</span>
    </button>
    <span role="tooltip"
          class="pointer-events-none absolute bottom-full {{ $tooltipAlign }} z-20 mb-1.5 hidden whitespace-nowrap rounded-md bg-slate-900 px-2 py-1 text-[11px] font-medium text-white shadow-lg group-hover/copy:block group-focus-within/copy:block"
          x-text="copied ? {{ Js::from(__('room.orders.copied_tooltip')) }} : {{ Js::from(__('room.orders.copy_code')) }}">{{ __('room.orders.copy_code') }}</span>
</span>
