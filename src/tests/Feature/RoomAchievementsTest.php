<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\GlobalUser;
use App\Models\MonthlySponsorStatistic;
use App\Models\Room;
use App\Models\RoomUser;
use App\Services\Reporting\MonthlySponsorStatisticsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Room "Achievements" page: the viewer's TOP 1-3 sponsor medals of each month of the current year, the medal totals
 * and the encouragement message; cached for a day and refreshed when the statistics are rebuilt.
 */
class RoomAchievementsTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-20 10:00:00');
        $this->room = Room::create(['name' => 'Medal Room', 'slug' => 'medal-room', 'status' => 'active', 'timezone' => 'UTC']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function member(string $name, ?Room $room = null): RoomUser
    {
        $user = GlobalUser::create(['name' => $name, 'email' => strtolower(str_replace(' ', '', $name)).uniqid().'@example.test', 'status' => 'active']);

        return RoomUser::create(['room_id' => ($room ?? $this->room)->id, 'global_user_id' => $user->id, 'display_name' => $name, 'status' => 'active']);
    }

    /**
     * @param array<int, int> $totals Sponsored amount keyed by room_user_id.
     */
    private function month(string $month, array $totals): void
    {
        foreach ($totals as $roomUserId => $amount) {
            MonthlySponsorStatistic::create(['room_id' => $this->room->id, 'room_user_id' => $roomUserId, 'month' => $month, 'total_sponsored' => $amount, 'sponsored_campaigns' => 1]);
        }
    }

    public function test_viewer_sees_the_medals_of_each_month_of_the_year(): void
    {
        $viewer = $this->member('Viewer Vy');
        $others = [$this->member('Other A'), $this->member('Other B'), $this->member('Other C')];
        [$a, $b, $c] = array_map(static fn (RoomUser $member): int => $member->id, $others);

        $this->month('2025-12-01', [$viewer->id => 900000]); // Another year.
        $this->month('2026-01-01', [$viewer->id => 300000, $a => 100000]); // Gold.
        $this->month('2026-02-01', [$a => 200000, $viewer->id => 150000, $b => 50000]); // Silver.
        $this->month('2026-03-01', [$a => 90000, $b => 80000, $c => 70000, $viewer->id => 10000]); // 4th: no medal.
        $this->month('2026-09-01', [$a => 60000, $b => 55000, $c => 50000, $viewer->id => 50000]); // Tie for 3rd: the lower membership ID (viewer) gets bronze.
        $this->month('2026-10-01', [$viewer->id => 999999]); // Current month: not ranked yet.

        $html = $this->actingAs($viewer->globalUser, 'web')->get(route('user.rooms.achievements', $this->room->slug))
            ->assertOk()
            ->assertSee(__('achievements.title', ['year' => 2026]))
            ->assertSee('data-achievements-total="3"', false)
            ->assertSee(trans_choice('achievements.cheer', 3, ['count' => 3]))
            ->getContent();
        $this->assertMatchesRegularExpression('/aria-current="page"\s+data-room-tab-achievements/', $html);

        $expected = [1 => 'medal', 2 => 'medal', 3 => 'none', 4 => 'none', 9 => 'medal', 10 => 'current', 11 => 'upcoming', 12 => 'upcoming'];
        foreach ($expected as $month => $status) {
            $this->assertStringContainsString('data-achievement-month="'.$month.'" data-status="'.$status.'"', $html, "month {$month}");
        }
        foreach (['gold', 'silver', 'bronze'] as $metal) {
            $this->assertMatchesRegularExpression('/data-achievements-count="'.$metal.'">.*?<span class="text-sm font-bold text-slate-900">1<\/span>/s', $html);
        }
        $this->assertStringContainsString(e(__('achievements.position', ['position' => 4])), $html);

        $service = app(MonthlySponsorStatisticsService::class)->memberYearAchievements($this->room, $viewer, 2026);
        $this->assertSame([1, 2, null, 3], [$service['months'][0]['rank'], $service['months'][1]['rank'], $service['months'][2]['rank'], $service['months'][8]['rank']]);
    }

    public function test_a_member_without_medal_is_encouraged_and_the_tab_is_in_the_room_menu(): void
    {
        $viewer = $this->member('Viewer Vy');

        $this->actingAs($viewer->globalUser, 'web')->get(route('user.dashboard', $this->room->slug))
            ->assertOk()->assertSee('data-room-tab-achievements', false)->assertSee(route('user.rooms.achievements', $this->room->slug), false);
        $this->actingAs($viewer->globalUser, 'web')->get(route('user.rooms.achievements', $this->room->slug))
            ->assertOk()
            ->assertSee('data-achievements-total="0"', false)
            ->assertSee(trans_choice('achievements.cheer', 0));
    }

    public function test_page_is_only_for_members_of_the_room(): void
    {
        $outsider = $this->member('Outsider', Room::create(['name' => 'Other', 'slug' => 'other-medal-room', 'status' => 'active']));

        $this->get(route('user.rooms.achievements', $this->room->slug))->assertRedirect();
        $this->actingAs($outsider->globalUser, 'web')->get(route('user.rooms.achievements', $this->room->slug))
            ->assertRedirect()->assertDontSee('data-achievements', false);
    }

    public function test_achievements_are_cached_for_a_day_and_refreshed_when_statistics_are_rebuilt(): void
    {
        $viewer = $this->member('Viewer Vy');
        $this->month('2026-01-01', [$viewer->id => 100000]);
        $url = route('user.rooms.achievements', $this->room->slug);
        $this->actingAs($viewer->globalUser, 'web')->get($url)->assertSee('data-achievements-total="1"', false);

        // New statistics are not read again while the cache lives...
        $this->month('2026-08-01', [$viewer->id => 100000]);
        $this->actingAs($viewer->globalUser, 'web')->get($url)->assertSee('data-achievements-total="1"', false);

        // ...until a month is rebuilt (here a month without any order), or the day is over.
        app(MonthlySponsorStatisticsService::class)->rebuild($this->room, CarbonImmutable::parse('2026-05-01'));
        $this->actingAs($viewer->globalUser, 'web')->get($url)->assertSee('data-achievements-total="2"', false);

        $this->month('2026-07-01', [$viewer->id => 100000]);
        Carbon::setTestNow('2026-10-21 10:00:01');
        $this->actingAs($viewer->globalUser, 'web')->get($url)->assertSee('data-achievements-total="3"', false);
    }
}
