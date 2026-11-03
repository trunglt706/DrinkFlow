<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\RoomStatus;
use App\Models\Room;
use App\Services\Reporting\MonthlySponsorHonorNotificationService;
use App\Services\Reporting\MonthlySponsorStatisticsService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class CalculateMonthlySponsors extends Command
{
    protected $signature = 'drinkflow:calculate-monthly-sponsors
        {--month= : Calendar month YYYY-MM; defaults to last month}
        {--room= : Optional active room slug; defaults to all active rooms}
        {--no-notify : Only rebuild the statistics, do not announce the top 3 sponsors}';

    protected $description = 'Create or update monthly sponsorship totals for sponsoring members of active rooms, then announce each room\'s top 3 sponsors (web notification + socket + channel gateways, once per room and month).';

    public function handle(MonthlySponsorStatisticsService $statistics, MonthlySponsorHonorNotificationService $honors): int
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
        $announced = 0;
        $notify = ! $this->option('no-notify');
        foreach ($rooms->lazyById(100) as $room) {
            $sponsors += $statistics->rebuild($room, $month);
            $roomCount++;
            // One room's announcement failing must not stop the statistics or announcements of the other rooms.
            try {
                if ($notify && $honors->notifyRoom($room, $month)) {
                    $announced++;
                }
            } catch (\Throwable $exception) {
                report($exception);
                $this->warn("Could not announce the top sponsors of room {$room->slug}.");
            }
        }
        $this->info("Updated {$month->format('Y-m')}: {$sponsors} sponsors across {$roomCount} rooms.");
        if ($notify) {
            $this->info("Announced the top 3 sponsors in {$announced} room(s).");
        }

        return self::SUCCESS;
    }
}
