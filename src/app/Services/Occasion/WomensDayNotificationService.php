<?php

declare(strict_types=1);

namespace App\Services\Occasion;

use App\Enums\NotificationType;
use App\Enums\RoomOccasion;
use App\Enums\RoomStatus;
use App\Models\Room;
use App\Services\Notification\RoomNotificationChannelDispatcher;
use App\Services\Notification\UserNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Vietnamese Women's Day greeting: on 20 October, from SEND_HOUR in each room's timezone, every active room gets an
 * in-app notification for all its members and a message on its notification channels, both linking to the
 * /rooms/{room}/20-10 page. Each room is greeted at most once a year.
 */
class WomensDayNotificationService
{
    /** Local hour (room timezone) from which the greeting is sent. */
    public const SEND_HOUR = 10;

    public function __construct(
        private readonly UserNotificationService $userNotifications,
        private readonly RoomNotificationChannelDispatcher $channels,
    ) {}

    /**
     * Greet every active room (or only the given one) that is due.
     *
     * @param bool $force Skip the date / hour check (manual run); a room is still greeted only once a year.
     * @param string|null $roomSlug Limit the run to this room.
     * @return int Number of rooms greeted by this run.
     */
    public function notifyDueRooms(bool $force = false, ?string $roomSlug = null): int
    {
        $notified = 0;
        Room::query()->where('status', RoomStatus::Active->value)
            ->when($roomSlug !== null, fn ($query) => $query->where('slug', $roomSlug))
            ->orderBy('id')
            ->each(function (Room $room) use ($force, &$notified): void {
                if ($this->notifyRoom($room, $force)) {
                    $notified++;
                }
            });

        return $notified;
    }

    /**
     * Greet a room, at most once per year.
     *
     * @param Room $room Room to greet.
     * @param bool $force Skip the date / hour check.
     * @return bool False when it is not 20 October from SEND_HOUR in the room yet, or the year was already greeted.
     */
    public function notifyRoom(Room $room, bool $force = false): bool
    {
        $occasion = RoomOccasion::WomensDay;
        $now = CarbonImmutable::now((string) ($room->timezone ?: config('app.display_timezone', config('app.timezone'))));
        if (! $force && ($now->month !== $occasion->month() || $now->day !== $occasion->day() || $now->hour < self::SEND_HOUR)) {
            return false;
        }

        // Claim the year first: the unique index (room_id, occasion, year) lets only one run send it.
        $claimed = DB::table('room_occasion_notifications')->insertOrIgnore([
            'room_id' => $room->id,
            'occasion' => $occasion->value,
            'year' => $now->year,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($claimed !== 1) {
            return false;
        }

        $link = route('user.rooms.womens-day', $room);
        $title = __('womens_day.notify_title');
        $body = __('womens_day.notify_body', ['room' => $room->name]);

        $this->userNotifications->toRoom($room, NotificationType::WomensDay->value, $title, $body, [
            'room_id' => $room->id,
            'year' => $now->year,
        ], $link);
        $channelCount = $this->channels->dispatch($room, [
            'event' => NotificationType::WomensDay->value,
            'title' => $title,
            'message' => $body."\n".__('womens_day.notify_link', ['url' => $link]),
            'url' => $link,
        ]);

        DB::table('room_occasion_notifications')
            ->where('room_id', $room->id)->where('occasion', $occasion->value)->where('year', $now->year)
            ->update(['channel_count' => $channelCount, 'sent_at' => now(), 'updated_at' => now()]);

        return true;
    }
}
