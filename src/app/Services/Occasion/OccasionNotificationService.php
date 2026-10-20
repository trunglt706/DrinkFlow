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
 * Occasion greetings (20/10, 8/3, New Year, Tết, Christmas, Men's Day): on the occasion day, from SEND_HOUR in each
 * room's timezone, every active room gets an in-app notification for all its members and a message on its
 * notification channels, both linking to the occasion page. Each room is greeted at most once per occasion and year.
 */
class OccasionNotificationService
{
    /** Local hour (room timezone) from which the greeting is sent. */
    public const SEND_HOUR = 9;

    public function __construct(
        private readonly RoomEventService $events,
        private readonly UserNotificationService $userNotifications,
        private readonly RoomNotificationChannelDispatcher $channels,
    ) {}

    /**
     * Greet every active room (or only the given one) for every occasion (or only the given one) that is due.
     *
     * @param RoomOccasion|null $only Limit the run to this occasion.
     * @param bool $force Skip the date / hour check (manual run); a room is still greeted only once a year.
     * @param string|null $roomSlug Limit the run to this room.
     * @return int Number of greetings sent by this run.
     */
    public function notifyDueRooms(?RoomOccasion $only = null, bool $force = false, ?string $roomSlug = null): int
    {
        $occasions = $only !== null ? [$only] : RoomOccasion::cases();
        // Cheap early exit on the other days of the year: no occasion within a day of today (any timezone).
        if (! $force && ! $this->anyOccasionNearToday($occasions)) {
            return 0;
        }

        $sent = 0;
        Room::query()->where('status', RoomStatus::Active->value)
            ->when($roomSlug !== null, fn ($query) => $query->where('slug', $roomSlug))
            ->orderBy('id')
            ->each(function (Room $room) use ($occasions, $force, &$sent): void {
                foreach ($occasions as $occasion) {
                    if ($this->notifyRoom($room, $occasion, $force)) {
                        $sent++;
                    }
                }
            });

        return $sent;
    }

    /**
     * Greet a room for an occasion, at most once per year.
     *
     * @param Room $room Room to greet.
     * @param RoomOccasion $occasion Occasion.
     * @param bool $force Skip the date / hour check.
     * @return bool False when it is not the occasion day from SEND_HOUR in the room yet, or it was already sent.
     */
    public function notifyRoom(Room $room, RoomOccasion $occasion, bool $force = false): bool
    {
        $now = CarbonImmutable::now($this->events->today($room)->getTimezone());
        $occurrence = $this->events->occurrence($occasion, $now->startOfDay());
        if (! $force && ($occurrence['offset'] !== 0 || $now->hour < self::SEND_HOUR)) {
            return false;
        }

        return $this->send($room, $occasion, $occurrence['date']->year, false);
    }

    /**
     * Send the greeting from the admin events page while the occasion is shown, as many times as the admin wants
     * (only the automatic send is limited to once a year). The scheduler will not send it again afterwards.
     *
     * @param Room $room Room to greet.
     * @param RoomOccasion $occasion Occasion.
     * @return bool False when the occasion is not shown today.
     */
    public function sendFromAdmin(Room $room, RoomOccasion $occasion): bool
    {
        $occurrence = $this->events->occurrence($occasion, $this->events->today($room));
        if (! $occurrence['active']) {
            return false;
        }

        return $this->send($room, $occasion, $occurrence['date']->year, true);
    }

    /**
     * Claim the (room, occasion, year) row, then send the greeting and record its (latest) delivery.
     *
     * @param bool $fromAdmin Send even when the row is already claimed (admin resend).
     */
    private function send(Room $room, RoomOccasion $occasion, int $year, bool $fromAdmin): bool
    {
        // The unique index (room_id, occasion, year) lets only one automatic run send it.
        $claimed = DB::table('room_occasion_notifications')->insertOrIgnore([
            'room_id' => $room->id,
            'occasion' => $occasion->value,
            'year' => $year,
            'created_at' => now(),
            'updated_at' => now(),
        ]) === 1;
        if (! $claimed && ! $fromAdmin) {
            return false;
        }

        $link = route('user.rooms.occasion', [$room, $occasion->slug()]);
        $key = 'occasions.'.$occasion->value;
        $title = __($key.'.notify_title');
        $body = __($key.'.notify_body', ['room' => $room->name]);
        $type = NotificationType::fromOccasion($occasion)->value;

        $this->userNotifications->toRoom($room, $type, $title, $body, [
            'room_id' => $room->id,
            'occasion' => $occasion->value,
            'year' => $year,
        ], $link);
        $channelCount = $this->channels->dispatch($room, [
            'event' => $type,
            'title' => $title,
            'message' => $body."\n".__('occasions.notify_link', ['url' => $link]),
            'url' => $link,
        ]);

        DB::table('room_occasion_notifications')
            ->where('room_id', $room->id)->where('occasion', $occasion->value)->where('year', $year)
            ->update(['channel_count' => $channelCount, 'sent_at' => now(), 'updated_at' => now()]);

        return true;
    }

    /**
     * Whether one of the occasions falls within a day of today (UTC), so that some room timezone may be on it.
     *
     * @param list<RoomOccasion> $occasions Occasions checked.
     */
    private function anyOccasionNearToday(array $occasions): bool
    {
        $today = CarbonImmutable::now('UTC')->startOfDay();
        foreach ($occasions as $occasion) {
            foreach ([$today->year - 1, $today->year, $today->year + 1] as $year) {
                if (abs(RoomEventService::daysBetween($today, $occasion->dateIn($year))) <= 1) {
                    return true;
                }
            }
        }

        return false;
    }
}
