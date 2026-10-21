<?php

declare(strict_types=1);

namespace App\Services\Occasion;

use App\Actions\Room\SendRoomEventNotificationAction;
use App\Enums\EventMusicMode;
use App\Models\Room;
use App\Models\RoomEventSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

    /** Audio files an admin may upload (extensions), the largest file (kilobytes) and their folder on the public disk. */
    public const MUSIC_FILE_TYPES = ['mp3', 'm4a', 'ogg', 'wav'];

    public const MUSIC_FILE_MAX_KB = 8192;

    /**
     * Largest audio file accepted (kilobytes): MUSIC_FILE_MAX_KB, lowered to what this server's PHP accepts
     * (upload_max_filesize / post_max_size), since a larger file never reaches the application.
     */
    public static function musicFileMaxKb(): int
    {
        $serverKb = intdiv((int) min(PHP_INT_MAX, UploadedFile::getMaxFilesize()), 1024);

        return max(1, min(self::MUSIC_FILE_MAX_KB, $serverKb));
    }

    /**
     * musicFileMaxKb() in megabytes, for messages (one decimal under 10 MB).
     */
    public static function musicFileMaxMb(): string
    {
        $mb = self::musicFileMaxKb() / 1024.0;

        return $mb >= 10 || floor($mb) === $mb ? (string) (int) floor($mb) : number_format($mb, 1, '.', '');
    }

    public const MUSIC_FOLDER = 'event-music';

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
     * @return array{music_mode: EventMusicMode, music_url: string|null, music_file_url: string|null, music_name: string|null, sound_effects: bool, wishes: list<string>|null}
     */
    public function forEvent(Room $room, string $type): array
    {
        return $this->all($room)[$type] ?? self::defaults();
    }

    /**
     * Settings of every event page of a room, keyed by event type.
     *
     * @return array<string, array{music_mode: EventMusicMode, music_url: string|null, music_file_url: string|null, music_name: string|null, sound_effects: bool, wishes: list<string>|null}>
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
                'music_file_url' => $row->music_mode === EventMusicMode::Upload && $row->music_path !== null ? Storage::disk('public')->url($row->music_path) : null,
                'music_name' => $row->music_mode === EventMusicMode::Upload ? $row->music_name : null,
                'sound_effects' => $row->sound_effects,
                'wishes' => $row->wishes === null ? null : array_values($row->wishes),
            ];
        }

        return $settings;
    }

    /**
     * Save the settings of one event page. A new uploaded file replaces the previous one, and leaving the upload mode
     * deletes the file, so the disk only keeps the music in use.
     *
     * @param Room $room Room.
     * @param string $type `birthday` or a RoomOccasion value.
     * @param EventMusicMode $mode Background music mode.
     * @param string|null $url Audio file link (custom mode only).
     * @param bool $soundEffects Whether the page plays its sound effects.
     * @param UploadedFile|null $file New audio file (upload mode only); null keeps the current one.
     *
     * @throws \RuntimeException When the uploaded file cannot be written to the public disk.
     */
    public function update(Room $room, string $type, EventMusicMode $mode, ?string $url, bool $soundEffects, ?UploadedFile $file = null): void
    {
        $setting = RoomEventSetting::query()->firstOrNew(['room_id' => $room->id, 'event_type' => $type]);
        $oldPath = $setting->music_path;
        $setting->fill(['music_mode' => $mode, 'music_url' => $mode === EventMusicMode::Custom ? $url : null, 'sound_effects' => $soundEffects]);

        if ($mode !== EventMusicMode::Upload) {
            $setting->fill(['music_path' => null, 'music_name' => null]);
        } elseif ($file !== null) {
            // Random name: the original name is only kept for display.
            $name = bin2hex(random_bytes(16)).'.'.strtolower($file->getClientOriginalExtension());
            $path = $file->storeAs(self::MUSIC_FOLDER.'/'.$room->id, $name, 'public');
            if ($path === false) {
                throw new \RuntimeException('The event music file could not be stored.');
            }
            $setting->fill(['music_path' => $path, 'music_name' => mb_substr($file->getClientOriginalName(), 0, 191)]);
        }

        $setting->save();
        if ($oldPath !== null && $oldPath !== $setting->music_path) {
            Storage::disk('public')->delete($oldPath);
        }
    }

    /**
     * Whether the event page already has an uploaded audio file (the upload mode then needs no new file).
     *
     * @param Room $room Room.
     * @param string $type `birthday` or a RoomOccasion value.
     */
    public function hasMusicFile(Room $room, string $type): bool
    {
        return RoomEventSetting::query()->where('room_id', $room->id)->where('event_type', $type)->whereNotNull('music_path')->exists();
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
     * @return array{music_mode: EventMusicMode, music_url: null, music_file_url: null, music_name: null, sound_effects: bool, wishes: null}
     */
    public static function defaults(): array
    {
        return ['music_mode' => EventMusicMode::Builtin, 'music_url' => null, 'music_file_url' => null, 'music_name' => null, 'sound_effects' => true, 'wishes' => null];
    }
}
