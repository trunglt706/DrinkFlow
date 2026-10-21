@props(['rating', 'compact' => false])

{{-- Đánh giá một món đã nhận (resources/js/room/item-rating.js): bấm sao lưu ngay và đổi được cho tới khi gửi nhận xét;
     nhận xét gửi một lần, sau đó đánh giá chỉ còn để xem (sao, nội dung và thời gian gửi).
     $rating: ['can_rate' => bool, 'url' => string, 'stars' => ?int, 'comment' => ?string, 'commented_at' => ?string, 'locked' => bool]. --}}
@php
    $stars = (int) ($rating['stars'] ?? 0);
    $locked = (bool) ($rating['locked'] ?? false);
    $maxStars = \App\Services\Order\OrderItemRatingService::MAX_STARS;
    $maxComment = \App\Services\Order\OrderItemRatingService::MAX_COMMENT;
@endphp
@if ($rating['can_rate'] ?? false)
    <div data-item-rating data-url="{{ $rating['url'] }}" data-stars="{{ $stars }}" @if ($locked) data-locked @endif
        data-i18n="{{ json_encode([
            'saved' => __('room.rating.saved'),
            'error' => __('room.rating.error'),
            'sending' => __('room.rating.sending'),
            'commentRequired' => __('room.rating.comment_required'),
            'starsRequired' => __('room.rating.stars_required'),
            'sentAt' => __('room.rating.sent_at', ['time' => '__TIME__']),
            'yourRating' => __('room.rating.your_rating'),
        ], JSON_UNESCAPED_UNICODE) }}"
        class="{{ $compact ? '' : 'mt-2 border-t border-outline-variant/40 pt-2' }}">
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
            <span class="text-[11px] font-semibold text-on-surface-variant" data-rating-label>{{ $stars > 0 ? __('room.rating.your_rating') : __('room.rating.prompt') }}</span>
            <div class="flex items-center" @unless ($locked) role="radiogroup" @endunless aria-label="{{ __('room.rating.stars') }}" data-rating-stars>
                @for ($i = 1; $i <= $maxStars; $i++)
                    @if ($locked)
                        <span class="h-7 w-6 inline-flex items-center justify-center {{ $i <= $stars ? 'text-amber-500' : 'text-slate-300' }}" aria-hidden="true">
                            <span class="material-symbols-outlined text-[20px]" style="font-variation-settings:'FILL' 1">star</span>
                        </span>
                    @else
                        <button type="button" data-rating-star="{{ $i }}" role="radio" aria-checked="{{ $stars === $i ? 'true' : 'false' }}"
                            aria-label="{{ __('room.rating.star_label', ['count' => $i]) }}" title="{{ __('room.rating.star_label', ['count' => $i]) }}"
                            class="h-7 w-7 inline-flex items-center justify-center rounded-md cursor-pointer hover:bg-amber-50 disabled:cursor-wait {{ $i <= $stars ? 'text-amber-500' : 'text-slate-300' }}">
                            <span class="material-symbols-outlined text-[20px]" style="font-variation-settings:'FILL' 1" aria-hidden="true">star</span>
                        </button>
                    @endif
                @endfor
                @if ($locked)
                    <span class="sr-only">{{ __('room.rating.star_label', ['count' => $stars]) }}</span>
                @endif
            </div>
            <span class="text-[11px] font-semibold" role="status" aria-live="polite" data-rating-status></span>
        </div>

        @if ($locked)
            <div class="mt-1 rounded-lg bg-slate-50 px-2.5 py-1.5" data-rating-comment-text>
                <p class="text-xs text-slate-800 break-words">“{{ $rating['comment'] }}”</p>
                <p class="mt-0.5 flex items-center gap-1 text-[10px] text-slate-500">
                    <span class="material-symbols-outlined text-[12px]" aria-hidden="true">lock</span>{{ __('room.rating.sent_at', ['time' => $rating['commented_at']]) }}
                </p>
            </div>
        @else
            <div class="mt-1 flex items-center gap-1.5" data-rating-comment-row @if ($stars === 0) hidden @endif>
                <label class="flex-1 min-w-0">
                    <span class="sr-only">{{ __('room.rating.comment') }}</span>
                    <input type="text" data-rating-comment maxlength="{{ $maxComment }}" placeholder="{{ __('room.rating.comment_placeholder') }}"
                        class="h-8 w-full rounded-lg border border-slate-200 bg-white px-2.5 text-xs text-slate-900 placeholder:text-slate-400 focus:border-[#006948] focus:ring-1 focus:ring-[#006948] outline-none">
                </label>
                <button type="button" data-rating-save class="h-8 shrink-0 inline-flex items-center gap-1 rounded-lg bg-[#006948] px-3 text-xs font-semibold text-white hover:bg-[#005137] cursor-pointer disabled:opacity-60 disabled:cursor-wait">
                    <span class="material-symbols-outlined text-[14px]" aria-hidden="true" data-rating-save-icon>send</span><span data-rating-save-label>{{ __('room.rating.send_comment') }}</span>
                </button>
            </div>
            <p class="mt-0.5 text-[10px] text-slate-500" data-rating-comment-hint @if ($stars === 0) hidden @endif>{{ __('room.rating.comment_hint') }}</p>
        @endif
    </div>
@endif
