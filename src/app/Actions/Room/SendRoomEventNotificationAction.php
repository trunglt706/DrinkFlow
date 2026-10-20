<?php

declare(strict_types=1);

namespace App\Actions\Room;

use App\Enums\RoomOccasion;
use App\Models\Room;
use App\Services\Birthday\BirthdayNotificationService;
use App\Services\Occasion\OccasionNotificationService;

/**
 * Send a room event notification on demand (admin events page): today's birthdays, or an occasion greeting while
 * the occasion is shown. The admin may send it again; only the automatic send is limited to once.
 */
class SendRoomEventNotificationAction
{
    /** Event type of the members' birthdays (the other types are RoomOccasion values). */
    public const TYPE_BIRTHDAY = 'birthday';

    public function __construct(
        private readonly BirthdayNotificationService $birthdays,
        private readonly OccasionNotificationService $occasions,
    ) {}

    /**
     * Event types an admin may send.
     *
     * @return list<string>
     */
    public static function types(): array
    {
        return [self::TYPE_BIRTHDAY, ...array_map(static fn (RoomOccasion $occasion): string => $occasion->value, RoomOccasion::cases())];
    }

    /**
     * @param Room $room Room to notify.
     * @param string $type `birthday` or a RoomOccasion value.
     * @return bool False when the event is not due today.
     */
    public function execute(Room $room, string $type): bool
    {
        if ($type === self::TYPE_BIRTHDAY) {
            return $this->birthdays->sendFromAdmin($room);
        }

        return $this->occasions->sendFromAdmin($room, RoomOccasion::from($type));
    }
}
