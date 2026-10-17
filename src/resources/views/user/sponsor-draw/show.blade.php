<x-room.layout :room="$room" :room-user="$roomUser" :user-rooms="$userRooms" :unread-notifications-count="$unreadNotificationsCount" :active-tab="'overview'" :title="__('sponsor_draw.landing.page_title', ['campaign' => $state['campaign']])">
    <div class="space-y-4">
        <header>
            <h1 class="text-xl font-bold text-on-surface">{{ __('sponsor_draw.landing.heading') }}</h1>
            <p class="mt-1 text-xs text-on-surface-variant">{{ __('sponsor_draw.landing.intro') }}</p>
        </header>
        <x-sponsor-draw.stage :state="$state" mode="viewer" :timezone="$timezone" :urls="[
            'state' => route('user.sponsor-draws.state', [$room, $token]),
            'token' => route('user.sponsor-draws.socket-token', [$room, $token]),
        ]" />
    </div>
</x-room.layout>
