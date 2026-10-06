{{-- Expired link or a viewer outside the campaign: no session data is rendered at all. --}}
<x-room.layout :room="$room" :room-user="$roomUser" :user-rooms="$userRooms" :unread-notifications-count="$unreadNotificationsCount" :active-tab="'overview'" :title="__('sponsor_draw.landing.'.$reason.'_title')">
    <div class="max-w-lg mx-auto my-10 rounded-2xl border border-outline-variant bg-surface-container-lowest p-8 text-center" data-sponsor-draw-unavailable="{{ $reason }}">
        <span class="material-symbols-outlined text-[44px] text-outline">{{ $reason === 'expired' ? 'link_off' : 'lock' }}</span>
        <h1 class="mt-3 text-lg font-bold text-on-surface">{{ __('sponsor_draw.landing.'.$reason.'_title') }}</h1>
        <p class="mt-2 text-sm text-on-surface-variant">{{ __('sponsor_draw.landing.'.$reason.'_body') }}</p>
        <a href="{{ route('user.dashboard', $room) }}" class="mt-5 inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-on-primary">{{ __('sponsor_draw.landing.back_to_room') }}</a>
    </div>
</x-room.layout>
