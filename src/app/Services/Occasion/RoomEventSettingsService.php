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
    /** Most wishes an event page can have, and the longest wish (characters). */
    public const MAX_WISHES = 20;

    public const MAX_WISH_LENGTH = 300;

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
     * @return array{music_mode: EventMusicMode, music_url: string|null, sound_effects: bool, wishes: list<string>|null}
     */
    public function forEvent(Room $room, string $type): array
    {
        return $this->all($room)[$type] ?? self::defaults();
    }

    /**
     * Settings of every event page of a room, keyed by event type.
     *
     * @return array<string, array{music_mode: EventMusicMode, music_url: string|null, sound_effects: bool, wishes: list<string>|null}>
     *         `wishes`: the room's own wishes, null when the default sample wishes are used.
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
                'wishes' => $row->wishes === null ? null : array_values($row->wishes),
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
     * Wishes shown on an event page: the room's own list, otherwise the default sample wishes (viewer's language).
     * Birthday wishes may contain `:name`, replaced by the celebrants' names on the page.
     *
     * @param Room $room Room.
     * @param string $type `birthday` or a RoomOccasion value.
     * @return list<string>
     */
    public function wishes(Room $room, string $type): array
    {
        return $this->forEvent($room, $type)['wishes'] ?? self::defaultWishes($type);
    }

    /**
     * Save the room's own wishes of an event page, or go back to the default sample wishes (null).
     *
     * @param Room $room Room.
     * @param string $type `birthday` or a RoomOccasion value.
     * @param list<string>|null $wishes Wishes in display order, null for the defaults.
     */
    public function updateWishes(Room $room, string $type, ?array $wishes): void
    {
        RoomEventSetting::query()->updateOrCreate(
            ['room_id' => $room->id, 'event_type' => $type],
            ['wishes' => $wishes === null ? null : array_values($wishes)],
        );
    }

    /**
     * Default sample wishes of an event, in the current language.
     *
     * @param string $type `birthday` or a RoomOccasion value.
     * @return list<string>
     */
    public static function defaultWishes(string $type): array
    {
        return array_values((array) __($type === SendRoomEventNotificationAction::TYPE_BIRTHDAY ? 'birthday.wishes' : 'occasions.'.$type.'.wishes'));
    }

    /**
     * Built-in melody, sound effects and the default sample wishes.
     *
     * @return array{music_mode: EventMusicMode, music_url: null, sound_effects: bool, wishes: null}
     */
    public static function defaults(): array
    {
        return ['music_mode' => EventMusicMode::Builtin, 'music_url' => null, 'sound_effects' => true, 'wishes' => null];
    }
}
