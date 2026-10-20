<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Occasion\WomensDayNotificationService;
use Illuminate\Console\Command;

class NotifyWomensDay extends Command
{
    /** @var string */
    protected $signature = 'drinkflow:notify-womens-day
        {--room= : Only greet the room with this slug}
        {--force : Send now even if it is not 20 October from 10:00 room time (still once a year per room)}';

    /** @var string */
    protected $description = 'Send the Vietnamese Women\'s Day (20/10) greeting with a link to the room\'s 20/10 page, from 10:00 room time, once a year per room.';

    /**
     * Greet every room that is due.
     *
     * @param WomensDayNotificationService $notifications Women's Day greeting service.
     * @return int Process exit code.
     */
    public function handle(WomensDayNotificationService $notifications): int
    {
        $room = $this->option('room');
        $count = $notifications->notifyDueRooms((bool) $this->option('force'), is_string($room) && $room !== '' ? $room : null);
        $this->info("Sent Women's Day greetings to {$count} room(s).");

        return self::SUCCESS;
    }
}
