<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\RoomOccasion;
use App\Services\Occasion\OccasionNotificationService;
use Illuminate\Console\Command;

class NotifyRoomOccasions extends Command
{
    /** @var string */
    protected $signature = 'drinkflow:notify-occasions
        {--occasion= : Only this occasion (new_year, lunar_new_year, intl_womens_day, womens_day, mens_day, christmas)}
        {--room= : Only greet the room with this slug}
        {--force : Send now even if it is not the occasion day from 9:00 room time (still once a year per room)}';

    /** @var string */
    protected $description = 'Send the occasion greetings (New Year, Tết, 8/3, 20/10, 19/11, Christmas) with a link to the occasion page, from 9:00 room time on the day, once a year per room.';

    /**
     * Greet every room that is due.
     *
     * @param OccasionNotificationService $notifications Occasion greeting service.
     * @return int Process exit code.
     */
    public function handle(OccasionNotificationService $notifications): int
    {
        $occasion = null;
        $value = $this->option('occasion');
        if (is_string($value) && $value !== '') {
            $occasion = RoomOccasion::tryFrom($value);
            if ($occasion === null) {
                $this->error("Unknown occasion \"{$value}\".");

                return self::INVALID;
            }
        }
        $room = $this->option('room');
        $count = $notifications->notifyDueRooms($occasion, (bool) $this->option('force'), is_string($room) && $room !== '' ? $room : null);
        $this->info("Sent {$count} occasion greeting(s).");

        return self::SUCCESS;
    }
}
