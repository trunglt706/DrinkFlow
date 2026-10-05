<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\OrderStatus;
use App\Enums\RoomStatus;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\GlobalUser;
use App\Models\MonthlySponsorStatistic;
use App\Models\Order;
use App\Models\Room;
use App\Models\RoomUser;
use App\Services\Reporting\MonthlySponsorStatisticsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlySponsorStatisticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_saves_every_sponsor_and_updates_existing_rows_without_duplicates(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 5)->startOfDay());
        $room = Room::create(['name' => 'Coffee', 'slug' => 'mens-est', 'status' => 'active']);
        $orders = [];
        foreach ([10000, 20000, 30000, 40000] as $index => $amount) {
            $member = $this->member($room, 'Sponsor '.$index);
            $orders[] = $this->sponsorship($room, $member, $amount, '2026-09-15');
        }
        $this->artisan('drinkflow:calculate-monthly-sponsors')->assertSuccessful();
        $this->assertDatabaseCount('monthly_sponsor_statistics', 4);
        $first = MonthlySponsorStatistic::where('room_user_id', $orders[0]->room_user_id)->firstOrFail();

        $orders[0]->update(['subtotal' => 90000]);
        $orders[1]->update(['status' => OrderStatus::Cancelled]);
        $this->artisan('drinkflow:calculate-monthly-sponsors')->assertSuccessful();
        $this->assertDatabaseCount('monthly_sponsor_statistics', 3);
        $this->assertSame(90000, $first->fresh()->total_sponsored);
        $this->assertDatabaseMissing('monthly_sponsor_statistics', ['room_user_id' => $orders[1]->room_user_id]);
        $this->assertSame([90000, 40000, 30000], array_column(
            app(MonthlySponsorStatisticsService::class)->previousMonthHonors($room)['sponsors'], 'amount'
        ));
    }

    public function test_previous_month_handles_year_boundary_and_excludes_unfinished_or_cancelled_campaigns(): void
    {
        $this->travelTo(now()->setDate(2027, 1, 31)->startOfDay());
        $room = Room::create(['name' => 'New Year', 'slug' => 'new-year']);
        $member = $this->member($room, 'December Sponsor');
        $this->sponsorship($room, $member, 20000, '2026-12-01 00:00:00');
        $this->sponsorship($room, $member, 30000, '2026-12-31 23:59:59');
        $this->sponsorship($room, $member, 90000, '2026-11-30 23:59:59');
        $this->sponsorship($room, $member, 90000, '2027-01-01 00:00:00');
        $this->sponsorship($room, $member, 90000, '2026-12-10')->update(['status' => OrderStatus::Delivering]);
        $cancelled = $this->sponsorship($room, $member, 90000, '2026-12-10');
        $cancelled->campaign->update(['status' => CampaignStatus::Cancelled]);
        $this->artisan('drinkflow:calculate-monthly-sponsors')->assertSuccessful();
        $this->assertDatabaseHas('monthly_sponsor_statistics', [
            'room_user_id' => $member->id, 'month' => '2026-12-01', 'total_sponsored' => 50000, 'sponsored_campaigns' => 2,
        ]);
        $this->assertDatabaseCount('monthly_sponsor_statistics', 1);
    }

    public function test_full_sponsorship_splits_fees_and_excludes_self_paid_items(): void
    {
        $room = Room::create(['name' => 'Shared', 'slug' => 'shared']);
        $a = $this->member($room, 'A');
        $b = $this->member($room, 'B');
        $order = $this->sponsorship($room, $a, 80000, '2026-09-10');
        $order->items()->create(['item_name' => 'Cake', 'unit_price' => 50000, 'quantity' => 1, 'line_subtotal' => 50000, 'is_self_paid' => true]);
        $order->campaign->update([
            'delivery_fee' => 15001,
            'sponsor_allocations' => [
                ['room_user_id' => $a->id, 'percentage' => 50],
                ['room_user_id' => $b->id, 'percentage' => 50],
            ],
        ]);
        $this->artisan('drinkflow:calculate-monthly-sponsors', ['--month' => '2026-09'])->assertSuccessful();
        $this->assertDatabaseHas('monthly_sponsor_statistics', ['room_user_id' => $a->id, 'total_sponsored' => 22500]);
        $this->assertDatabaseHas('monthly_sponsor_statistics', ['room_user_id' => $b->id, 'total_sponsored' => 22501]);
    }

    public function test_room_option_limits_updates_and_all_active_rooms_are_processed_by_default(): void
    {
        $a = Room::create(['name' => 'A', 'slug' => 'room-a']);
        $b = Room::create(['name' => 'B', 'slug' => 'room-b']);
        $this->sponsorship($a, $this->member($a, 'A sponsor'), 10000, '2026-09-10');
        $this->sponsorship($b, $this->member($b, 'B sponsor'), 20000, '2026-09-10');
        $this->artisan('drinkflow:calculate-monthly-sponsors', ['--month' => '2026-09', '--room' => 'room-a'])->assertSuccessful();
        $this->assertDatabaseCount('monthly_sponsor_statistics', 1);
        $this->artisan('drinkflow:calculate-monthly-sponsors', ['--month' => '2026-09'])->assertSuccessful();
        $this->assertDatabaseCount('monthly_sponsor_statistics', 2);
        $this->artisan('drinkflow:calculate-monthly-sponsors', ['--month' => '2026-13'])->assertFailed();
        $this->artisan('drinkflow:calculate-monthly-sponsors', ['--month' => '2026-9'])->assertFailed();
        $this->artisan('drinkflow:calculate-monthly-sponsors', ['--room' => 'missing'])->assertFailed();
        $this->assertDatabaseCount('monthly_sponsor_statistics', 2);
    }

    public function test_an_empty_rebuild_removes_stale_rows_only_for_the_requested_month_and_room(): void
    {
        $room = Room::create(['name' => 'Stale', 'slug' => 'stale']);
        $member = $this->member($room, 'Sponsor');
        foreach (['2026-08-01', '2026-09-01'] as $month) {
            MonthlySponsorStatistic::create(['room_id' => $room->id, 'room_user_id' => $member->id, 'month' => $month, 'total_sponsored' => 123]);
        }
        app(MonthlySponsorStatisticsService::class)->rebuild($room, CarbonImmutable::parse('2026-09-01'));
        $this->assertDatabaseCount('monthly_sponsor_statistics', 1);
        $this->assertDatabaseHas('monthly_sponsor_statistics', ['month' => '2026-08-01']);
    }

    public function test_command_skips_inactive_and_archived_rooms_even_when_explicitly_selected(): void
    {
        foreach ([RoomStatus::Inactive, RoomStatus::Archived] as $status) {
            $room = Room::create(['name' => $status->value, 'slug' => $status->value, 'status' => $status]);
            $member = $this->member($room, 'Existing sponsor');
            $this->sponsorship($room, $member, 90000, '2026-09-10');
            $this->sponsorship($room, $this->member($room, 'New sponsor'), 80000, '2026-09-10');
            MonthlySponsorStatistic::create([
                'room_id' => $room->id, 'room_user_id' => $member->id,
                'month' => '2026-09-01', 'total_sponsored' => 123,
            ]);
            $this->artisan('drinkflow:calculate-monthly-sponsors', [
                '--month' => '2026-09', '--room' => $room->slug,
            ])->expectsOutput('Active room not found.')->assertFailed();
        }

        $this->artisan('drinkflow:calculate-monthly-sponsors', ['--month' => '2026-09'])
            ->expectsOutput('Updated 2026-09: 0 sponsors across 0 rooms.')->assertSuccessful();
        $this->assertDatabaseCount('monthly_sponsor_statistics', 2);
        $this->assertSame([123, 123], MonthlySponsorStatistic::orderBy('id')->pluck('total_sponsored')->all());
    }

    public function test_both_dashboards_show_only_their_previous_month_top_three_and_escape_names(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 5)->startOfDay());
        $room = Room::create(['name' => 'Mens Est', 'slug' => 'mens-est', 'status' => 'active']);
        $viewer = $this->member($room, 'Viewer');
        foreach (['First <script>alert(1)</script>', 'Second hero', 'Third hero', 'Fourth hero'] as $index => $name) {
            $member = $this->member($room, $name);
            MonthlySponsorStatistic::create([
                'room_id' => $room->id, 'room_user_id' => $member->id, 'month' => '2026-09-01',
                'total_sponsored' => 40000 - $index * 10000, 'sponsored_campaigns' => 2,
            ]);
        }
        $other = Room::create(['name' => 'Other', 'slug' => 'other']);
        MonthlySponsorStatistic::create([
            'room_id' => $other->id, 'room_user_id' => $this->member($other, 'Other room hero')->id,
            'month' => '2026-09-01', 'total_sponsored' => 999999,
        ]);
        MonthlySponsorStatistic::create([
            'room_id' => $room->id, 'room_user_id' => $this->member($room, 'Current month hero')->id,
            'month' => '2026-10-01', 'total_sponsored' => 999999,
        ]);
        $admin = Admin::create(['name' => 'Admin', 'email' => 'honors-admin@example.test', 'password' => bcrypt('password'), 'status' => 'active']);
        $admin->rooms()->attach($room);
        foreach (['vi', 'en', 'ja'] as $locale) {
            $this->app->setLocale($locale);
            foreach ([
                $this->actingAs($viewer->globalUser, 'web')->withSession(['locale' => $locale])->get('/rooms/mens-est/dashboard'),
                $this->actingAs($admin, 'admin')->withSession(['locale' => $locale])->get('/admin/mens-est/dashboard'),
            ] as $response) {
                $response->assertOk()->assertSee('data-sponsor-honors', false)->assertSee('09/2026')
                    ->assertSee('First &lt;script&gt;alert(1)&lt;/script&gt;', false)
                    ->assertSee('Second hero')->assertSee('Third hero')->assertDontSee('Fourth hero')
                    ->assertDontSee('Other room hero')->assertDontSee('Current month hero')
                    ->assertDontSee('<script>alert(1)</script>', false)->assertDontSee('sponsor_honors.');
            }
        }
    }

    public function test_ties_are_stable_and_empty_month_has_no_fake_winners(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 5)->startOfDay());
        $room = Room::create(['name' => 'Ties', 'slug' => 'ties']);
        $service = app(MonthlySponsorStatisticsService::class);
        $this->assertSame([], $service->previousMonthHonors($room)['sponsors']);
        $first = $this->member($room, 'First ID');
        $second = $this->member($room, 'Second ID');
        foreach ([$second, $first] as $member) {
            MonthlySponsorStatistic::create(['room_id' => $room->id, 'room_user_id' => $member->id, 'month' => '2026-09-01', 'total_sponsored' => 10000]);
        }
        $this->assertSame(['First ID', 'Second ID'], array_column($service->previousMonthHonors($room)['sponsors'], 'name'));
    }

    private function member(Room $room, string $name): RoomUser
    {
        $user = GlobalUser::create(['name' => $name, 'email' => uniqid('honors-', true).'@example.test', 'status' => 'active']);

        return RoomUser::create(['room_id' => $room->id, 'global_user_id' => $user->id, 'display_name' => $name, 'status' => 'active']);
    }

    private function sponsorship(Room $room, RoomUser $sponsor, int $amount, string $date): Order
    {
        $campaign = Campaign::create([
            'room_id' => $room->id, 'name' => 'Coffee', 'restaurant' => 'Cafe', 'status' => CampaignStatus::Closed,
            'sponsor_type' => Campaign::SPONSOR_TYPE_FULL,
            'sponsor_allocations' => [['room_user_id' => $sponsor->id, 'percentage' => 100]],
        ]);
        $campaign->forceFill(['created_at' => $date])->save();

        return Order::create([
            'room_id' => $room->id, 'campaign_id' => $campaign->id, 'room_user_id' => $sponsor->id,
            'subtotal' => $amount, 'final_amount' => 0, 'status' => OrderStatus::Completed,
        ]);
    }
}
