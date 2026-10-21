<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Reporting\YearRecapNotificationService;
use Illuminate\Console\Command;

class SendYearRecaps extends Command
{
    /** @var string */
    protected $signature = 'drinkflow:send-year-recaps {--year= : Year to announce (default: the year just ended, in January)} {--room= : Only this room (slug)} {--force : Skip the date / hour check (each room is still announced once per year)}';

    /** @var string */
    protected $description = 'Announce the members\' year recaps (web notification + socket + room channels) once the new year has started, from 9:00 room time on 1 January.';

    /**
     * Announce the recap of the year just ended in the rooms where it is due.
     *
     * @param YearRecapNotificationService $notifications Year recap announcements.
     * @return int Process exit code.
     */
    public function handle(YearRecapNotificationService $notifications): int
    {
        $year = $this->option('year') !== null ? (int) $this->option('year') : null;
        $room = $this->option('room') !== null ? (string) $this->option('room') : null;
        $count = $notifications->notifyDueRooms($year, (bool) $this->option('force'), $room);
        $this->info("Announced the year recap in {$count} room(s).");

        return self::SUCCESS;
    }
}
