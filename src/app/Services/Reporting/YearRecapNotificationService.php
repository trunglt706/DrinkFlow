<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\NotificationType;
use App\Enums\RoomStatus;
use App\Enums\RoomUserStatus;
use App\Models\Room;
use App\Models\RoomUser;
use App\Services\Notification\RoomNotificationChannelDispatcher;
use App\Services\Notification\UserNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Year recap announcement: once the old year is over (from SEND_HOUR on 1 January, room timezone; any later day of
 * January catches up a missed run), every active room gets its recaps computed, then each member who ordered during
 * the year receives an in-app notification (pushed live over the socket) linking to their recap, and the room's
 * notification channels (Slack, Telegram, webhooks...) get the room's figures of the year. Once per room and year.
 */
class YearRecapNotificationService
{
    /** Local hour (room timezone) from which the recap is announced on 1 January. */
    public const SEND_HOUR = 9;

    public function __construct(
        private readonly YearRecapService $recaps,
        private readonly UserNotificationService $userNotifications,
        private readonly RoomNotificationChannelDispatcher $channels,
    ) {}

    /**
     * Announce the recap of the year just ended in every active room (or only one) where it is due.
     *
     * @param int|null $year Year to announce (manual run); default: the previous year, only in January.
     * @param bool $force Skip the date / hour check; a room is still announced only once a year.
     * @param string|null $roomSlug Limit the run to this room.
     * @return int Rooms announced by this run.
     */
    public function notifyDueRooms(?int $year = null, bool $force = false, ?string $roomSlug = null): int
    {
        // Cheap early exit on the other days of the year: January has started in no timezone.
        $utc = CarbonImmutable::now('UTC');
        if (! $force && $year === null && ! ($utc->month === 1 || ($utc->month === 12 && $utc->day === 31))) {
            return 0;
        }

        $sent = 0;
        Room::query()->where('status', RoomStatus::Active->value)
            ->when($roomSlug !== null, static fn ($query) => $query->where('slug', $roomSlug))
            ->orderBy('id')
            ->each(function (Room $room) use ($year, $force, &$sent): void {
                if ($this->notifyRoom($room, $year, $force)) {
                    $sent++;
                }
            });

        return $sent;
    }

    /**
     * Announce a room's recap of a year, at most once.
     *
     * @param Room $room Room.
     * @param int|null $year Year; default: the year before the room's current year.
     * @param bool $force Skip the date / hour check.
     * @return bool False when not due yet, nobody ordered that year, or it was already announced.
     */
    public function notifyRoom(Room $room, ?int $year = null, bool $force = false): bool
    {
        $timezone = (string) ($room->timezone ?: config('app.display_timezone', config('app.timezone')));
        $now = CarbonImmutable::now($timezone);
        $year ??= $now->year - 1;
        if (! $force && ($now->month !== 1 || $now->year !== $year + 1 || ($now->day === 1 && $now->hour < self::SEND_HOUR))) {
            return false;
        }

        $cups = $this->recaps->roomCups($room, $year)->filter(static fn (int $value): bool => $value > 0);
        if ($cups->isEmpty()) {
            return false;
        }

        // The unique index (room_id, year) lets only one run announce it.
        $claimed = DB::table('room_year_recap_notifications')->insertOrIgnore([
            'room_id' => $room->id,
            'year' => $year,
            'created_at' => now(),
            'updated_at' => now(),
        ]) === 1;
        if (! $claimed) {
            return false;
        }

        $this->recaps->rebuildRoom($room, $year);
        $link = route('user.rooms.recap', [$room, $year]);
        $type = NotificationType::YearRecap->value;
        $members = RoomUser::query()->where('room_id', $room->id)->where('status', RoomUserStatus::Active->value)
            ->whereIn('id', $cups->keys())->get();
        foreach ($members as $member) {
            $this->userNotifications->toRoomUser(
                $member,
                $type,
                __('recap.notify_title', ['year' => $year]),
                __('recap.notify_body', ['year' => $year, 'room' => $room->name, 'cups' => $this->number($cups[$member->id] ?? 0)]),
                ['room_id' => $room->id, 'year' => $year],
                $link,
            );
        }

        $channelCount = $this->channels->dispatch($room, [
            'event' => $type,
            'title' => __('recap.notify_title', ['year' => $year]),
            'message' => __('recap.notify_channel', [
                'year' => $year,
                'room' => $room->name,
                'cups' => $this->number((int) $cups->sum()),
                'members' => $cups->count(),
            ])."\n".__('recap.notify_link', ['url' => $link]),
            'url' => $link,
        ]);

        DB::table('room_year_recap_notifications')->where('room_id', $room->id)->where('year', $year)
            ->update(['member_count' => $members->count(), 'channel_count' => $channelCount, 'sent_at' => now(), 'updated_at' => now()]);

        return true;
    }

    private function number(int $value): string
    {
        return number_format($value, 0, ',', '.');
    }
}
