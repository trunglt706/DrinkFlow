<?php

declare(strict_types=1);

namespace App\Services\Occasion;

use App\Actions\Room\SendRoomEventNotificationAction;
use App\Enums\EventMusicMode;
use App\Models\Room;
use App\Models\RoomEventSetting;

/**
 * Background music and sound effects of the room event pages (birthday and occasions), set by the room admin.
 */
class RoomEventSettingsService
{
    /**
     * Music streaming sites whose links open a web player page, never an audio file the browser can play (their
     * streams are signed, short-lived and reserved to their own player).
     */
    public const STREAMING_PAGE_HOSTS = ['zingmp3.vn', 'mp3.zing.vn', 'nhaccuatui.com', 'youtube.com', 'youtu.be', 'spotify.com', 'soundcloud.com', 'music.apple.com'];

    /**
     * The streaming site of a link that is a web player page, or null for any other link.
     *
     * @param string $url Link entered by the admin.
     */
    public static function streamingPageHost(string $url): ?string
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        foreach (self::STREAMING_PAGE_HOSTS as $site) {
            if ($host === $site || str_ends_with($host, '.'.$site)) {
                return $site;
            }
        }

        return null;
    }

    /**
     * Settings of one event page, defaults when the admin never changed them.
     *
     * @param Room $room Room.
     * @param string $type `birthday` or a RoomOccasion value.
     * @return array{music_mode: EventMusicMode, music_url: string|null, sound_effects: bool}
     */
    public function forEvent(Room $room, string $type): array
    {
        return $this->all($room)[$type] ?? self::defaults();
    }

    /**
     * Settings of every event page of a room, keyed by event type.
     *
     * @return array<string, array{music_mode: EventMusicMode, music_url: string|null, sound_effects: bool}>
     */
    public function all(Room $room): array
    {
        $saved = RoomEventSetting::query()->where('room_id', $room->id)->get()->keyBy('event_type');
        $settings = [];
        foreach (SendRoomEventNotificationAction::types() as $type) {
            $row = $saved->get($type);
            $settings[$type] = $row === null ? self::defaults() : [
                'music_mode' => $row->music_mode,
                'music_url' => $row->music_mode === EventMusicMode::Custom ? $row->music_url : null,
                'sound_effects' => $row->sound_effects,
            ];
        }

        return $settings;
    }

    /**
     * Save the settings of one event page.
     *
     * @param Room $room Room.
     * @param string $type `birthday` or a RoomOccasion value.
     * @param EventMusicMode $mode Background music mode.
     * @param string|null $url Audio file link (custom mode only).
     * @param bool $soundEffects Whether the page plays its sound effects.
     */
    public function update(Room $room, string $type, EventMusicMode $mode, ?string $url, bool $soundEffects): void
    {
        RoomEventSetting::query()->updateOrCreate(
            ['room_id' => $room->id, 'event_type' => $type],
            ['music_mode' => $mode, 'music_url' => $mode === EventMusicMode::Custom ? $url : null, 'sound_effects' => $soundEffects],
        );
    }

    /**
     * Built-in melody and sound effects.
     *
     * @return array{music_mode: EventMusicMode, music_url: null, sound_effects: bool}
     */
    public static function defaults(): array
    {
        return ['music_mode' => EventMusicMode::Builtin, 'music_url' => null, 'sound_effects' => true];
    }
}
