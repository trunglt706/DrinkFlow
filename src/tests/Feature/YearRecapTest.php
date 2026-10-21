<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\OrderStatus;
use App\Models\Campaign;
use App\Models\GlobalUser;
use App\Models\MemberYearRecap;
use App\Models\MonthlySponsorStatistic;
use App\Models\Order;
use App\Models\Room;
use App\Models\RoomUser;
use App\Services\Reporting\YearRecapService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Members' year recap: figures of the year (room timezone), stored in advance by the season command, the recap page
 * (past years, the current year from 15 December) and the dashboard banner of the season.
 */
class YearRecapTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-12-20 10:00:00');
        $this->room = Room::create(['name' => 'Recap Room', 'slug' => 'recap-room', 'status' => 'active', 'timezone' => 'Asia/Ho_Chi_Minh']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function member(string $name): RoomUser
    {
        $user = GlobalUser::create(['name' => $name, 'email' => strtolower($name).uniqid().'@example.test', 'status' => 'active']);

        return RoomUser::create(['room_id' => $this->room->id, 'global_user_id' => $user->id, 'display_name' => $name, 'status' => 'active']);
    }

    /**
     * @param array<string, int> $items Item name => quantity.
     */
    private function order(RoomUser $member, string $at, string $shop, array $items, OrderStatus $status = OrderStatus::Completed, ?Order $parent = null, CampaignStatus $campaignStatus = CampaignStatus::Closed): Order
    {
        $campaign = Campaign::create(['room_id' => $this->room->id, 'name' => 'C'.$this->sequence, 'restaurant' => $shop, 'status' => $campaignStatus]);
        $order = Order::create([
            'code' => sprintf('RC-%03d', ++$this->sequence), 'room_id' => $this->room->id, 'campaign_id' => $campaign->id, 'room_user_id' => $member->id,
            'parent_id' => $parent?->id, 'subtotal' => 50000, 'sponsor_amount' => 10000, 'final_amount' => 40000, 'status' => $status,
        ]);
        $order->forceFill(['created_at' => $at])->save();
        foreach ($items as $name => $quantity) {
            $order->items()->create(['item_name' => $name, 'unit_price' => 25000, 'quantity' => $quantity, 'line_subtotal' => 25000 * $quantity]);
        }

        return $order;
    }

    public function test_recap_figures_of_the_year(): void
    {
        $lan = $this->member('Lan');
        $minh = $this->member('Minh');
        $first = $this->order($lan, '2026-03-02 02:00:00', 'Phúc Long', ['Trà Sữa' => 2, 'Bạc xỉu' => 1]);
        $this->order($lan, '2026-03-20 03:00:00', 'Phúc Long', ['tra sua' => 3]);
        $this->order($lan, '2026-07-01 03:00:00', 'Highlands', ['Freeze' => 1]);
        // Not counted: cancelled order, cancelled campaign, last year (31/12 23:00 room time = 16:00 UTC), another room.
        $this->order($lan, '2026-08-01 03:00:00', 'Phúc Long', ['Trà sữa' => 9], OrderStatus::Cancelled);
        $this->order($lan, '2026-08-02 03:00:00', 'Phúc Long', ['Trà sữa' => 9], OrderStatus::Completed, null, CampaignStatus::Cancelled);
        $this->order($lan, '2025-12-31 16:00:00', 'Phúc Long', ['Trà sữa' => 9]);
        // Minh drank more; Lan ordered once for Minh.
        $this->order($minh, '2026-05-05 03:00:00', 'Highlands', ['Freeze' => 10]);
        $this->order($minh, '2026-05-06 03:00:00', 'Highlands', ['Freeze' => 1], OrderStatus::Completed, $first);
        MonthlySponsorStatistic::create(['room_id' => $this->room->id, 'room_user_id' => $lan->id, 'month' => '2026-04-01', 'total_sponsored' => 120000, 'sponsored_campaigns' => 2]);

        $recap = app(YearRecapService::class)->forMember($this->room, $lan, 2026);

        $this->assertSame(3, $recap['orders']);
        $this->assertSame(7, $recap['cups']);
        $this->assertSame(['name' => 'Trà Sữa', 'cups' => 5], $recap['favorite_item']);
        $this->assertSame(['name' => 'Phúc Long', 'orders' => 2], $recap['favorite_shop']);
        $this->assertSame(['month' => 3, 'cups' => 6], $recap['busiest_month']);
        $this->assertSame('02/03', $recap['first_order']);
        $this->assertSame(120000, $recap['spent']);
        $this->assertSame(30000, $recap['sponsored_received']);
        $this->assertSame(120000, $recap['sponsored_given']);
        $this->assertSame(1, $recap['proxy_orders']);
        $this->assertSame([2, 2, 18], [$recap['rank'], $recap['ranked_members'], $recap['room_cups']]);
        $this->assertSame('connoisseur', $recap['title']);
        $this->assertDatabaseHas('member_year_recaps', ['room_id' => $this->room->id, 'room_user_id' => $lan->id, 'year' => 2026]);
        $this->assertNull(app(YearRecapService::class)->forMember($this->room, $lan, 2024));
    }

    public function test_season_command_stores_recaps_and_the_page_shows_them(): void
    {
        $lan = $this->member('Lan');
        $this->order($lan, '2026-06-10 03:00:00', 'Phúc Long', ['Trà sữa' => 2]);
        $this->order($lan, '2025-06-10 03:00:00', 'Highlands', ['Freeze' => 1]);

        $this->artisan('drinkflow:build-year-recaps')->assertSuccessful();
        $this->assertSame(1, MemberYearRecap::query()->where('year', 2026)->count());

        $this->actingAs($lan->globalUser, 'web')->get(route('user.dashboard', $this->room->slug))
            ->assertOk()->assertSee('data-recap-banner', false)->assertSee(route('user.rooms.recap', [$this->room->slug, 2026]), false);
        $this->actingAs($lan->globalUser, 'web')->get(route('user.rooms.recap', [$this->room->slug, 2026]))
            ->assertOk()->assertSee('data-recap-slide="summary"', false)->assertSee('Phúc Long')->assertSee(__('recap.titles.connoisseur'));
        // Past years open at any time; a year without orders shows an empty state.
        $this->actingAs($lan->globalUser, 'web')->get(route('user.rooms.recap', [$this->room->slug, 2025]))->assertOk()->assertSee('Highlands');
        $this->actingAs($lan->globalUser, 'web')->get(route('user.rooms.recap', [$this->room->slug, 2023]))->assertOk()->assertSee('data-recap-empty', false);
        $this->actingAs($lan->globalUser, 'web')->get(route('user.rooms.recap', [$this->room->slug, 2027]))->assertNotFound();

        // Outside the season: no banner, the current year is not open yet, the command does nothing.
        Carbon::setTestNow('2026-11-20 10:00:00');
        $this->actingAs($lan->globalUser, 'web')->get(route('user.dashboard', $this->room->slug))->assertOk()->assertDontSee('data-recap-banner', false);
        $this->actingAs($lan->globalUser, 'web')->get(route('user.rooms.recap', [$this->room->slug, 2026]))->assertNotFound();
        $this->actingAs($lan->globalUser, 'web')->get(route('user.rooms.achievements', $this->room->slug))->assertOk()->assertSee(route('user.rooms.recap', [$this->room->slug, 2025]), false);
        MemberYearRecap::query()->delete();
        $this->artisan('drinkflow:build-year-recaps')->assertSuccessful();
        $this->assertSame(0, MemberYearRecap::query()->count());

        // In January the previous year is in season.
        Carbon::setTestNow('2027-01-10 10:00:00');
        $this->assertSame(2026, app(YearRecapService::class)->seasonYear($this->room));
    }

    public function test_recap_is_only_for_members_of_the_room(): void
    {
        $other = Room::create(['name' => 'Other', 'slug' => 'other-recap', 'status' => 'active']);
        $user = GlobalUser::create(['name' => 'Stranger', 'email' => 'stranger-recap@example.test', 'status' => 'active']);
        RoomUser::create(['room_id' => $other->id, 'global_user_id' => $user->id, 'display_name' => 'Stranger', 'status' => 'active']);

        $this->assertNotSame(200, $this->actingAs($user, 'web')->get(route('user.rooms.recap', [$this->room->slug, 2025]))->getStatusCode());
    }
}
