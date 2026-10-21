<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Room;
use App\Services\Reporting\YearRecapService;
use Illuminate\Console\Command;

class BuildYearRecaps extends Command
{
    /** @var string */
    protected $signature = 'drinkflow:build-year-recaps {--year= : Year to rebuild in every active room (default: the season year of each room)} {--room= : Only this room (slug)}';

    /** @var string */
    protected $description = 'Compute and store the members\' year recaps (every day of the recap season, 15 December to 31 January).';

    /**
     * Rebuild the recaps of the season year, or of the given year / room.
     *
     * @param YearRecapService $recaps Year recap service.
     * @return int Process exit code.
     */
    public function handle(YearRecapService $recaps): int
    {
        $year = $this->option('year') !== null ? (int) $this->option('year') : null;
        $slug = $this->option('room');

        if ($year === null && $slug === null) {
            $count = $recaps->rebuildSeason();
        } else {
            $count = 0;
            $rooms = Room::query()->when($slug !== null, static fn ($query) => $query->where('slug', (string) $slug))->get();
            foreach ($rooms as $room) {
                $roomYear = $year ?? $recaps->seasonYear($room);
                if ($roomYear !== null) {
                    $count += $recaps->rebuildRoom($room, $roomYear);
                }
            }
        }

        $this->info("Stored {$count} year recap(s).");

        return self::SUCCESS;
    }
}
