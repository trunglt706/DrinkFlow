<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Birthday\BirthdayNotificationService;
use Illuminate\Console\Command;

class NotifyRoomBirthdays extends Command
{
    /** @var string */
    protected $signature = 'drinkflow:notify-birthdays';

    /** @var string */
    protected $description = 'Announce today\'s member birthdays in each room (web + notification channels), from 9:00 room time, once a day.';

    /**
     * Send the birthday announcement of every room that is due.
     *
     * @param BirthdayNotificationService $notifications Birthday announcement service.
     * @return int Process exit code.
     */
    public function handle(BirthdayNotificationService $notifications): int
    {
        $count = $notifications->notifyDueRooms();
        $this->info("Sent birthday notifications to {$count} room(s).");

        return self::SUCCESS;
    }
}
