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

    public function test_empty_podium_is_hidden_for_members_and_offers_recalculation_to_admins(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 5)->startOfDay());
        $room = Room::create(['name' => 'Empty', 'slug' => 'empty-room', 'status' => 'active']);
        $viewer = $this->member($room, 'Viewer');
        $admin = $this->roomAdmin($room, 'empty-admin@example.test');

        $this->actingAs($viewer->globalUser, 'web')->get('/rooms/empty-room/dashboard')
            ->assertOk()->assertDontSee('data-sponsor-honors', false)->assertDontSee(__('sponsor_honors.empty'));
        $this->actingAs($admin, 'admin')->get('/admin/empty-room/dashboard')
            ->assertOk()->assertSee('data-sponsor-honors', false)->assertSee(__('sponsor_honors.empty'))
            ->assertSee('data-sponsor-honors-recalculate', false)
            ->assertSee(route('admin.sponsor-honors.recalculate', $room), false);
    }

    public function test_admin_recalculation_rebuilds_previous_month_and_is_rate_limited_per_account(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 5)->startOfDay());
        $room = Room::create(['name' => 'Recalc', 'slug' => 'recalc', 'status' => 'active']);
        $this->sponsorship($room, $this->member($room, 'Late hero'), 25000, '2026-09-20');
        $admin = $this->roomAdmin($room, 'recalc-admin@example.test');
        $url = route('admin.sponsor-honors.recalculate', $room);
        $dashboard = route('admin.dashboard.page', $room);

        $this->actingAs($admin, 'admin')->from($dashboard)->post($url)
            ->assertRedirect($dashboard)->assertSessionHas('sponsor_honors_status', __('sponsor_honors.recalculated', ['count' => 1]));
        $this->assertDatabaseHas('monthly_sponsor_statistics', ['room_id' => $room->id, 'month' => '2026-09-01', 'total_sponsored' => 25000]);
        $this->actingAs($admin, 'admin')->get($dashboard)->assertSee('Late hero')->assertDontSee('data-sponsor-honors-recalculate', false);

        $this->actingAs($admin, 'admin')->from($dashboard)->post($url)->assertSessionHas('sponsor_honors_status');
        $this->actingAs($admin, 'admin')->from($dashboard)->post($url)->assertSessionHas('sponsor_honors_status');
        $this->actingAs($admin, 'admin')->from($dashboard)->post($url)
            ->assertRedirect($dashboard)->assertSessionHas('sponsor_honors_error')->assertSessionMissing('sponsor_honors_status');

        // The limit is per admin account: another admin of the same room (own session) can still recalculate.
        $other = $this->roomAdmin($room, 'recalc-other@example.test');
        $this->flushSession();
        $this->actingAs($other, 'admin')->from($dashboard)->post($url)->assertSessionHas('sponsor_honors_status');
    }

    public function test_recalculation_requires_an_admin_of_the_room(): void
    {
        $room = Room::create(['name' => 'Guarded', 'slug' => 'guarded', 'status' => 'active']);
        $outsider = $this->roomAdmin(Room::create(['name' => 'Elsewhere', 'slug' => 'elsewhere', 'status' => 'active']), 'outsider@example.test');
        $url = route('admin.sponsor-honors.recalculate', $room);

        $this->post($url)->assertRedirect();
        $this->actingAs($this->member($room, 'Member')->globalUser, 'web')->post($url)->assertRedirect();
        $this->actingAs($outsider, 'admin')->post($url)->assertRedirect(route('admin.login.page'));
        $this->assertDatabaseCount('monthly_sponsor_statistics', 0);
    }

    public function test_devtools_guard_is_only_rendered_on_user_pages_in_production(): void
    {
        $room = Room::create(['name' => 'Guard', 'slug' => 'guard-room', 'status' => 'active']);
        $viewer = $this->member($room, 'Viewer');

        $this->actingAs($viewer->globalUser, 'web')->get('/rooms/guard-room/dashboard')->assertOk()->assertDontSee("addEventListener('contextmenu'", false);

        $this->app['env'] = 'production';
        $this->actingAs($viewer->globalUser, 'web')->get('/rooms/guard-room/dashboard')->assertOk()->assertSee("addEventListener('contextmenu'", false);
        $this->actingAs($this->roomAdmin($room, 'guard-admin@example.test'), 'admin')->get('/admin/guard-room/dashboard')
            ->assertOk()->assertDontSee("addEventListener('contextmenu', (event) => event.preventDefault())", false);
    }

    public function test_previous_month_top_three_get_name_badges_in_directory_and_profile(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 5)->startOfDay());
        $room = Room::create(['name' => 'Badges', 'slug' => 'badges', 'status' => 'active']);
        $members = [];
        foreach (['Gold one', 'Silver one', 'Bronze one', 'Fourth one'] as $index => $name) {
            $members[$index] = $this->member($room, $name);
            MonthlySponsorStatistic::create(['room_id' => $room->id, 'room_user_id' => $members[$index]->id, 'month' => '2026-09-01', 'total_sponsored' => 40000 - $index * 10000]);
        }
        // Older months feed the profile history: gold in 08/2026, nothing in 07/2026 (rank 4), silver in 06/2026.
        $rival = $this->member($room, 'Rival');
        foreach ([['2026-08-01', 50000, 10000], ['2026-07-01', 1000, 9000], ['2026-06-01', 2000, 9000]] as [$month, $mine, $theirs]) {
            MonthlySponsorStatistic::create(['room_id' => $room->id, 'room_user_id' => $members[0]->id, 'month' => $month, 'total_sponsored' => $mine]);
            MonthlySponsorStatistic::create(['room_id' => $room->id, 'room_user_id' => $rival->id, 'month' => $month, 'total_sponsored' => $theirs]);
        }
        foreach ([1, 2] as $index) {
            MonthlySponsorStatistic::create(['room_id' => $room->id, 'room_user_id' => $members[$index]->id, 'month' => '2026-07-01', 'total_sponsored' => 5000]);
        }
        $admin = $this->roomAdmin($room, 'badges-admin@example.test');

        $this->actingAs($admin, 'admin')->get('/admin/badges/room-users/directory')->assertOk()
            ->assertSee('data-sponsor-badge="1"', false)->assertSee('data-sponsor-badge="2"', false)->assertSee('data-sponsor-badge="3"', false)
            ->assertSee(__('sponsor_honors.badge_tooltip', ['rank' => 1, 'month' => '09/2026']));

        $this->actingAs($members[0]->globalUser, 'web')->get('/rooms/badges/profile')->assertOk()
            ->assertSee('data-sponsor-badge="1"', false)->assertSee('data-sponsor-badge-history', false)
            ->assertSee('data-sponsor-badge-card="1"', false)->assertSee('data-sponsor-badge-card="2"', false)
            ->assertSee(__('sponsor_honors.history_month', ['month' => '08/2026']))
            ->assertSee(__('sponsor_honors.history_month', ['month' => '06/2026']))
            ->assertDontSee(__('sponsor_honors.history_month', ['month' => '07/2026']));
        $this->assertSame([
            ['rank' => 1, 'month' => '09/2026'], ['rank' => 1, 'month' => '08/2026'], ['rank' => 2, 'month' => '06/2026'],
        ], app(MonthlySponsorStatisticsService::class)->recentBadges($room, $members[0]));

        $this->actingAs($members[3]->globalUser, 'web')->get('/rooms/badges/profile')->assertOk()
            ->assertDontSee('data-sponsor-badge=', false)->assertDontSee('data-sponsor-badge-history', false);
    }

    public function test_campaign_details_flag_the_campaign_sponsors(): void
    {
        $room = Room::create(['name' => 'Flags', 'slug' => 'flags', 'status' => 'active']);
        $sponsor = $this->member($room, 'Sponsor');
        $order = $this->sponsorship($room, $sponsor, 30000, '2026-09-10');
        $guest = $this->member($room, 'Guest');
        Order::create(['room_id' => $room->id, 'campaign_id' => $order->campaign_id, 'room_user_id' => $guest->id, 'subtotal' => 20000, 'final_amount' => 0, 'status' => OrderStatus::Completed]);

        $orders = collect($this->actingAs($guest->globalUser, 'web')
            ->getJson(route('user.campaigns.details', ['room' => $room->slug, 'campaign' => $order->campaign]))
            ->assertOk()->json('data.orders'))->keyBy('orderer_name');
        $this->assertTrue($orders['Sponsor']['is_sponsor']);
        $this->assertFalse($orders['Guest']['is_sponsor']);
    }

    public function test_admin_report_badges_tab_lists_monthly_podiums_and_yearly_totals(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 5)->startOfDay());
        $room = Room::create(['name' => 'Report', 'slug' => 'report-room', 'status' => 'active']);
        $a = $this->member($room, 'Alice');
        $b = $this->member($room, 'Bob');
        foreach ([['2026-09-01', 30000, 10000], ['2026-08-01', 20000, 40000], ['2026-07-01', 5000, 1000], ['2025-12-01', 1000, 9000], ['2026-10-01', 99999, 1]] as [$month, $alice, $bob]) {
            MonthlySponsorStatistic::create(['room_id' => $room->id, 'room_user_id' => $a->id, 'month' => $month, 'total_sponsored' => $alice]);
            MonthlySponsorStatistic::create(['room_id' => $room->id, 'room_user_id' => $b->id, 'month' => $month, 'total_sponsored' => $bob]);
        }

        $report = app(MonthlySponsorStatisticsService::class)->yearlyBadgeReport($room, 2026);
        // The running month (10/2026) is not awarded yet; 2025 stays out of the 2026 report.
        $this->assertSame(['09/2026', '08/2026', '07/2026'], array_column($report['months'], 'month'));
        $this->assertSame([2026, 2025], $report['years']);
        $this->assertSame([
            ['name' => 'Alice', 'gold' => 2, 'silver' => 1, 'bronze' => 0, 'total' => 3],
            ['name' => 'Bob', 'gold' => 1, 'silver' => 2, 'bronze' => 0, 'total' => 3],
        ], $report['members']);

        $admin = $this->roomAdmin($room, 'report-admin@example.test');
        $this->actingAs($admin, 'admin')->get('/admin/report-room/reports/analytics?report_tab=badges&badge_year=2025')->assertOk()
            ->assertSee('id="rtab-badges"', false)->assertSee('data-badge-months', false)->assertSee('12/2025')
            ->assertDontSee('09/2026')->assertSee("switchReportTab?.('badges')", false);
        $this->actingAs($admin, 'admin')->get('/admin/report-room/reports/analytics?badge_year=abc')->assertOk()
            ->assertSee(__('sponsor_honors.report_title', ['year' => 2026]))->assertDontSee("switchReportTab?.('badges')", false);
    }

    public function test_admin_order_lists_mark_campaign_sponsors_with_their_share(): void
    {
        $room = Room::create(['name' => 'Orders', 'slug' => 'orders-room', 'status' => 'active']);
        $sponsor = $this->member($room, 'Sponsor member');
        $guest = $this->member($room, 'Guest member');
        $order = $this->sponsorship($room, $sponsor, 30000, now()->toDateTimeString());
        $order->campaign->update(['status' => CampaignStatus::Active, 'sponsor_allocations' => [
            ['room_user_id' => $sponsor->id, 'percentage' => 60], ['room_user_id' => $guest->id, 'percentage' => 0],
        ]]);
        Order::create(['room_id' => $room->id, 'campaign_id' => $order->campaign_id, 'room_user_id' => $guest->id, 'subtotal' => 20000, 'final_amount' => 0, 'status' => OrderStatus::Completed]);
        $admin = $this->roomAdmin($room, 'orders-admin@example.test');
        $tooltip = __('admin.order_sponsor_tooltip', ['percentage' => 60]);

        foreach ([route('admin.campaigns.orders', [$room, $order->campaign]), route('admin.orders.page', $room)] as $url) {
            $html = $this->actingAs($admin, 'admin')->get($url)->assertOk()->assertSee($tooltip)->getContent();
            $this->assertSame(1, substr_count($html, 'data-order-sponsor-mark'), $url);
        }
    }

    private function roomAdmin(Room $room, string $email): Admin
    {
        $admin = Admin::create(['name' => 'Admin', 'email' => $email, 'password' => bcrypt('password'), 'status' => 'active']);
        $admin->rooms()->attach($room);

        return $admin;
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
