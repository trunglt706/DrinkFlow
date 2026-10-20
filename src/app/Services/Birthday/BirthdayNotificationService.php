<?php

declare(strict_types=1);

namespace App\Services\Birthday;

use App\Enums\NotificationType;
use App\Enums\RoomStatus;
use App\Models\Room;
use App\Services\Notification\RoomNotificationChannelDispatcher;
use App\Services\Notification\UserNotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Morning birthday announcement: once a day, at SEND_HOUR in each room's timezone, every active room with members
 * born on that day gets an in-app notification for all its members and a message on its notification channels.
 */
class BirthdayNotificationService
{
    /** Local hour (room timezone) from which the day's announcement is sent. */
    public const SEND_HOUR = 9;

    public function __construct(
        private readonly RoomBirthdayService $birthdays,
        private readonly UserNotificationService $userNotifications,
        private readonly RoomNotificationChannelDispatcher $channels,
    ) {}

    /**
     * Announce today's birthdays in every active room whose local time has reached SEND_HOUR.
     *
     * @return int Number of rooms notified by this run.
     */
    public function notifyDueRooms(): int
    {
        $notified = 0;
        Room::query()->where('status', RoomStatus::Active->value)->orderBy('id')
            ->each(function (Room $room) use (&$notified): void {
                if ($this->notifyRoom($room)) {
                    $notified++;
                }
            });

        return $notified;
    }

    /**
     * Announce a room's birthdays of today, at most once per room and local day.
     *
     * @param Room $room Room to announce in.
     * @return bool False when it is too early, nobody is born today, or the day was already announced.
     */
    public function notifyRoom(Room $room): bool
    {
        $today = $this->birthdays->today($room);
        if (Carbon::now($today->getTimezone())->hour < self::SEND_HOUR) {
            return false;
        }

        return $this->send($room, $today, false);
    }

    /**
     * Send today's announcement from the admin events page, whatever the hour and as many times as the admin wants
     * (only the automatic send is limited to once a day). The scheduler will not send it again afterwards.
     *
     * @param Room $room Room to announce in.
     * @return bool False when nobody is born today.
     */
    public function sendFromAdmin(Room $room): bool
    {
        return $this->send($room, $this->birthdays->today($room), true);
    }

    /**
     * Claim the (room, day) row, then announce the day's birthdays and record the (latest) delivery.
     *
     * @param bool $fromAdmin Send even when the day is already claimed (admin resend).
     */
    private function send(Room $room, CarbonImmutable $today, bool $fromAdmin): bool
    {
        $celebrants = array_values(array_filter($this->birthdays->celebrants($room), static fn (array $celebrant): bool => $celebrant['offset'] === 0));
        if ($celebrants === []) {
            return false;
        }

        // Claim the day first: the unique index (room_id, celebrated_on) lets only one run send it.
        $claimed = DB::table('room_birthday_notifications')->insertOrIgnore([
            'room_id' => $room->id,
            'celebrated_on' => $today->toDateString(),
            'celebrant_count' => count($celebrants),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($claimed !== 1 && ! $fromAdmin) {
            return false;
        }

        $names = $this->joinNames(array_column($celebrants, 'name'));
        $link = route('user.rooms.birthday', $room);
        $title = trans_choice('birthday.notify_title', count($celebrants));
        $body = trans_choice('birthday.notify_body', count($celebrants), ['names' => $names]);

        $this->userNotifications->toRoom($room, NotificationType::BirthdayToday->value, $title, $body, [
            'room_id' => $room->id,
            'date' => $today->toDateString(),
            'names' => array_column($celebrants, 'name'),
        ], $link);
        $channelCount = $this->channels->dispatch($room, [
            'event' => NotificationType::BirthdayToday->value,
            'title' => $title,
            'message' => $body."\n".__('birthday.notify_link', ['url' => $link]),
            'url' => $link,
        ]);

        DB::table('room_birthday_notifications')
            ->where('room_id', $room->id)->where('celebrated_on', $today->toDateString())
            ->update(['channel_count' => $channelCount, 'sent_at' => now(), 'updated_at' => now()]);

        return true;
    }

    /**
     * "A", "A & B", "A, B & C".
     *
     * @param list<string> $names Names in display order.
     */
    private function joinNames(array $names): string
    {
        if (count($names) <= 1) {
            return (string) ($names[0] ?? '');
        }
        $last = array_pop($names);

        return implode(', ', $names).' & '.$last;
    }
}
