<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\RoomStatus;
use App\Models\Room;
use App\Services\Reporting\MonthlySponsorStatisticsService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class CalculateMonthlySponsors extends Command
{
    protected $signature = 'drinkflow:calculate-monthly-sponsors
        {--month= : Calendar month YYYY-MM; defaults to last month}
        {--room= : Optional active room slug; defaults to all active rooms}';

    protected $description = 'Create or update monthly sponsorship totals for sponsoring members of active rooms.';

    public function handle(MonthlySponsorStatisticsService $statistics): int
    {
        $value = $this->option('month');
        if ($value !== null && (! is_string($value) || ! preg_match('/\A[1-9][0-9]{3}-(0[1-9]|1[0-2])\z/', $value))) {
            $this->error('Month must use YYYY-MM (for example 2026-09).');

            return self::FAILURE;
        }
        $month = $value === null
            ? CarbonImmutable::now()->startOfMonth()->subMonth()
            : CarbonImmutable::createFromFormat('!Y-m', $value);
        $rooms = Room::query()->where('status', RoomStatus::Active->value);
        $slug = $this->option('room');
        if ($slug !== null) {
            $rooms->where('slug', $slug);
            if (! $rooms->exists()) {
                $this->error('Active room not found.');

                return self::FAILURE;
            }
        }

        $sponsors = 0;
        $roomCount = 0;
        foreach ($rooms->lazyById(100) as $room) {
            $sponsors += $statistics->rebuild($room, $month);
            $roomCount++;
        }
        $this->info("Updated {$month->format('Y-m')}: {$sponsors} sponsors across {$roomCount} rooms.");

        return self::SUCCESS;
    }
}
