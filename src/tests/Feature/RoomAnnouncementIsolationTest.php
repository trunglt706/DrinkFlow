<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\RoomOccasion;
use App\Models\Room;
use App\Services\Birthday\BirthdayNotificationService;
use App\Services\Occasion\OccasionNotificationService;
use App\Services\Reporting\YearRecapNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Scheduled room announcements (birthdays, occasions, year recaps): one room failing must not stop the others.
 */
class RoomAnnouncementIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Room $broken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->broken = Room::create(['name' => 'Broken', 'slug' => 'broken', 'status' => 'active']);
        Room::create(['name' => 'Healthy', 'slug' => 'healthy', 'status' => 'active']);
    }

    public function test_birthday_announcements_continue_after_a_failing_room(): void
    {
        $service = $this->partialMock(BirthdayNotificationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('notifyRoom')->twice()->andReturnUsing(fn (Room $room): bool => $this->failFor($room));
        });

        $this->assertSame(1, $service->notifyDueRooms());
    }

    public function test_occasion_greetings_continue_after_a_failing_room(): void
    {
        $service = $this->partialMock(OccasionNotificationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('notifyRoom')->twice()->andReturnUsing(fn (Room $room): bool => $this->failFor($room));
        });

        $this->assertSame(1, $service->notifyDueRooms(RoomOccasion::cases()[0], true));
    }

    public function test_year_recaps_continue_after_a_failing_room(): void
    {
        $service = $this->partialMock(YearRecapNotificationService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('notifyRoom')->twice()->andReturnUsing(fn (Room $room): bool => $this->failFor($room));
        });

        $this->assertSame(1, $service->notifyDueRooms(2025, true));
    }

    private function failFor(Room $room): bool
    {
        if ($room->is($this->broken)) {
            throw new \RuntimeException('Simulated delivery failure.');
        }

        return true;
    }
}
