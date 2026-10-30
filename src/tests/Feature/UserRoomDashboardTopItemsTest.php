<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\GlobalUserStatus;
use App\Enums\OrderStatus;
use App\Enums\RoomStatus;
use App\Enums\RoomUserStatus;
use App\Models\Campaign;
use App\Models\GlobalUser;
use App\Models\Room;
use App\Models\RoomUser;
use App\Services\Dashboard\UserRoomDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoomDashboardTopItemsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Rank five selected items across valid orders in the live campaign.
     *
     * @return void
     */
    public function test_dashboard_ranks_top_five_items_and_excludes_cancelled_orders(): void
    {
        $room = Room::create(['name' => 'Technology', 'slug' => 'technology-top-items', 'status' => RoomStatus::Active]);
        $campaign = Campaign::create([
            'room_id' => $room->id,
            'name' => 'Live coffee',
            'restaurant' => 'Test Restaurant',
            'status' => CampaignStatus::Active,
        ]);
        $user = $this->user('current@example.test');
        $member = $this->member($room, $user, 'CURRENT');
        $order = $member->orders()->create([
            'room_id' => $room->id, 'campaign_id' => $campaign->id,
            'subtotal' => 210000, 'final_amount' => 210000, 'status' => OrderStatus::Submitted,
        ]);
        foreach (['A' => 6, 'B' => 5, 'C' => 4, 'D' => 3, 'E' => 2, 'F' => 1] as $name => $quantity) {
            $order->items()->create([
                'item_name' => 'Drink '.$name, 'unit_price' => 10000,
                'quantity' => $quantity, 'line_subtotal' => $quantity * 10000,
            ]);
        }

        $other = $this->member($room, $this->user('cancelled@example.test'), 'CANCELLED');
        $cancelled = $other->orders()->create([
            'room_id' => $room->id, 'campaign_id' => $campaign->id,
            'subtotal' => 990000, 'final_amount' => 990000, 'status' => OrderStatus::Cancelled,
        ]);
        $cancelled->items()->create([
            'item_name' => 'Cancelled drink', 'unit_price' => 10000,
            'quantity' => 99, 'line_subtotal' => 990000,
        ]);

        $data = app(UserRoomDashboardService::class)->getDashboardData($room, $member, $user);
        $popularItems = $data['activeCampaign']['popular_items'];

        $this->assertCount(5, $popularItems);
        $this->assertSame(['Drink A', 'Drink B', 'Drink C', 'Drink D', 'Drink E'], $popularItems->pluck('name')->all());
        $this->assertSame([6, 5, 4, 3, 2], $popularItems->pluck('quantity')->all());
        $this->assertNotContains('Cancelled drink', $popularItems->pluck('name'));
    }

    /**
     * The placed-orders shortcut only shows once the member has a valid order in the live campaign.
     *
     * @return void
     */
    public function test_placed_orders_button_is_hidden_until_member_orders_in_live_campaign(): void
    {
        $room = Room::create(['name' => 'Technology', 'slug' => 'technology-placed-orders', 'status' => RoomStatus::Active]);
        $campaign = Campaign::create([
            'room_id' => $room->id,
            'name' => 'Live coffee',
            'restaurant' => 'Test Restaurant',
            'status' => CampaignStatus::Active,
        ]);
        $user = $this->user('placed@example.test');
        $member = $this->member($room, $user, 'PLACED');
        $dashboardUrl = route('user.dashboard', $room->slug);
        $service = app(UserRoomDashboardService::class);

        $this->assertFalse($service->getDashboardData($room, $member, $user)['activeCampaign']['has_ordered']);
        $this->actingAs($user, 'web')->get($dashboardUrl)->assertOk()
            ->assertSee(route('user.campaigns.index', $room->slug), false)
            ->assertDontSee(__('room.dashboard.placed_orders'));

        // A cancelled order does not count as having ordered.
        $order = $member->orders()->create([
            'room_id' => $room->id, 'campaign_id' => $campaign->id,
            'subtotal' => 10000, 'final_amount' => 10000, 'status' => OrderStatus::Cancelled,
        ]);
        $this->assertFalse($service->getDashboardData($room, $member, $user)['activeCampaign']['has_ordered']);

        $order->update(['status' => OrderStatus::Submitted]);
        $this->assertTrue($service->getDashboardData($room, $member, $user)['activeCampaign']['has_ordered']);
        $this->actingAs($user, 'web')->get($dashboardUrl)->assertOk()->assertSee(__('room.dashboard.placed_orders'));
    }

    /**
     * The sponsorship box shows the campaign's real policy, per-product cap and sponsored amount (no hardcoded budget).
     *
     * @return void
     */
    public function test_dashboard_sponsor_box_uses_campaign_policy(): void
    {
        $room = Room::create(['name' => 'Technology', 'slug' => 'technology-sponsor-box', 'status' => RoomStatus::Active]);
        $campaign = Campaign::create([
            'room_id' => $room->id, 'name' => 'Live coffee', 'restaurant' => 'Test Restaurant',
            'status' => CampaignStatus::Active, 'sponsor_type' => Campaign::SPONSOR_TYPE_PER_ITEM, 'max_budget' => 45000,
        ]);
        $user = $this->user('sponsor-box@example.test');
        $member = $this->member($room, $user, 'SPONSOR');
        $member->orders()->create([
            'room_id' => $room->id, 'campaign_id' => $campaign->id, 'subtotal' => 30000,
            'final_amount' => 30000, 'sponsor_amount' => 30000, 'status' => OrderStatus::Submitted,
        ]);
        $member->orders()->create([
            'room_id' => $room->id, 'campaign_id' => $campaign->id, 'subtotal' => 50000,
            'final_amount' => 50000, 'sponsor_amount' => 50000, 'status' => OrderStatus::Cancelled,
        ]);

        $this->actingAs($user, 'web')->get(route('user.dashboard', $room->slug))->assertOk()
            ->assertSee(__('room.campaign.sponsor_type_per_item'))
            ->assertSee(__('room.dashboard.sponsor_item_cap', ['amount' => \App\Support\Helpers\FormatHelper::formatCurrency(45000)]))
            ->assertSee(__('room.dashboard.sponsor_used_total', ['amount' => \App\Support\Helpers\FormatHelper::formatCurrency(30000)]))
            ->assertDontSee('20.000');

        $campaign->update(['sponsor_type' => Campaign::SPONSOR_TYPE_NONE, 'max_budget' => null]);
        $this->actingAs($user, 'web')->get(route('user.dashboard', $room->slug))->assertOk()
            ->assertSee(__('room.campaign.sponsor_type_none'))
            ->assertDontSee(__('room.dashboard.sponsor_used_total', ['amount' => \App\Support\Helpers\FormatHelper::formatCurrency(30000)]));
    }

    /**
     * Dashboard and campaign page show how many members have ordered, counting each member once and ignoring cancelled orders.
     *
     * @return void
     */
    public function test_dashboard_and_campaign_page_show_ordered_members_count(): void
    {
        $room = Room::create(['name' => 'Technology', 'slug' => 'technology-ordered-count', 'status' => RoomStatus::Active]);
        $campaign = Campaign::create([
            'room_id' => $room->id, 'name' => 'Live coffee', 'restaurant' => 'Test Restaurant',
            'status' => CampaignStatus::Active, 'deadline' => now()->addHour(),
        ]);
        $user = $this->user('ordered-count@example.test');
        $member = $this->member($room, $user, 'COUNTA');
        $noOne = trans_choice('room.campaign.ordered_members_count', 0, ['count' => 0]);

        $this->actingAs($user, 'web')->get(route('user.dashboard', $room->slug))->assertOk()->assertSee($noOne);
        $this->actingAs($user, 'web')->get(route('user.campaigns.index', $room->slug))->assertOk()->assertSee($noOne);

        // A member who cancelled and re-ordered is counted once.
        foreach ([OrderStatus::Cancelled, OrderStatus::Submitted] as $status) {
            $member->orders()->create([
                'room_id' => $room->id, 'campaign_id' => $campaign->id,
                'subtotal' => 30000, 'final_amount' => 30000, 'status' => $status,
            ]);
        }
        $other = $this->member($room, $this->user('ordered-count-b@example.test'), 'COUNTB');
        $other->orders()->create([
            'room_id' => $room->id, 'campaign_id' => $campaign->id,
            'subtotal' => 30000, 'final_amount' => 30000, 'status' => OrderStatus::Submitted,
        ]);
        $cancelled = $this->member($room, $this->user('ordered-count-c@example.test'), 'COUNTC');
        $cancelled->orders()->create([
            'room_id' => $room->id, 'campaign_id' => $campaign->id,
            'subtotal' => 30000, 'final_amount' => 30000, 'status' => OrderStatus::Cancelled,
        ]);

        $two = trans_choice('room.campaign.ordered_members_count', 2, ['count' => 2]);
        $this->assertSame(2, $campaign->orderedMembersCount());
        $this->actingAs($user, 'web')->get(route('user.dashboard', $room->slug))->assertOk()->assertSee($two);
        $this->actingAs($user, 'web')->get(route('user.campaigns.index', $room->slug))->assertOk()->assertSee($two);
    }

    /**
     * The orders page renders a submitted order whose items carry toppings (priced by unit_price).
     *
     * @return void
     */
    public function test_orders_page_renders_order_with_toppings(): void
    {
        $room = Room::create(['name' => 'Technology', 'slug' => 'technology-topping-order', 'status' => RoomStatus::Active]);
        $campaign = Campaign::create([
            'room_id' => $room->id,
            'name' => 'Live coffee',
            'restaurant' => 'Test Restaurant',
            'status' => CampaignStatus::Active,
        ]);
        $user = $this->user('topping@example.test');
        $member = $this->member($room, $user, 'TOPPING');
        $order = $member->orders()->create([
            'room_id' => $room->id, 'campaign_id' => $campaign->id,
            'subtotal' => 35000, 'final_amount' => 35000, 'status' => OrderStatus::Submitted,
        ]);
        $item = $order->items()->create([
            'item_name' => 'Milk tea', 'unit_price' => 30000, 'quantity' => 1, 'line_subtotal' => 35000,
        ]);
        $item->toppings()->create([
            'topping_name' => 'Pearl', 'unit_price' => 5000, 'quantity' => 1, 'subtotal' => 5000,
        ]);

        $this->actingAs($user, 'web')
            ->get(route('user.orders.index', $room->slug))
            ->assertOk()
            ->assertSee('Pearl')
            ->assertSee('+'.\App\Support\Helpers\FormatHelper::formatCurrency(5000), false);
    }

    /**
     * The "My Orders" page must also let a member open the room-wide orders modal for their
     * order's campaign, reusing the shared campaign-orders-modal component.
     *
     * @return void
     */
    public function test_orders_page_shows_room_orders_button_and_modal(): void
    {
        $room = Room::create(['name' => 'Technology', 'slug' => 'technology-orders-room-modal', 'status' => RoomStatus::Active]);
        $campaign = Campaign::create([
            'room_id' => $room->id, 'name' => 'Live coffee', 'restaurant' => 'Test Restaurant',
            'status' => CampaignStatus::Active,
        ]);
        $user = $this->user('room-orders-btn@example.test');
        $member = $this->member($room, $user, 'RMORDBTN');
        $order = $member->orders()->create([
            'room_id' => $room->id, 'campaign_id' => $campaign->id,
            'subtotal' => 30000, 'final_amount' => 30000, 'status' => OrderStatus::Submitted,
        ]);
        $order->items()->create([
            'item_name' => 'Milk tea', 'unit_price' => 30000, 'quantity' => 1, 'line_subtotal' => 30000,
        ]);

        $this->actingAs($user, 'web')
            ->get(route('user.orders.index', $room->slug))
            ->assertOk()
            ->assertSee('openCampaignDetail('.$campaign->id.')', false)
            ->assertSee(__('room.campaign.view_room_orders_button'))
            ->assertSee('campaignModalOpen', false);
    }

    /**
     * The leaderboard must rank the campaigns' actual sponsors (sponsor_name / sponsor_allocations),
     * never the members who merely received the subsidy on their own order, counting completed orders only.
     *
     * @return void
     */
    public function test_dashboard_ranks_top_sponsors_by_total_sponsor_amount(): void
    {
        $room = Room::create(['name' => 'Technology', 'slug' => 'technology-top-sponsors', 'status' => RoomStatus::Active]);
        $member = $this->member($room, $this->user('member@example.test'), 'MEMBER');

        $topCampaign = Campaign::create([
            'room_id' => $room->id, 'name' => 'Coffee A', 'restaurant' => 'Test Restaurant',
            'status' => CampaignStatus::Active, 'sponsor_type' => 'per_item', 'sponsor_name' => 'Công ty ABC',
        ]);
        $member->orders()->create([
            'room_id' => $room->id, 'campaign_id' => $topCampaign->id,
            'subtotal' => 100000, 'sponsor_amount' => 80000, 'final_amount' => 20000, 'status' => OrderStatus::Completed,
        ]);

        $secondCampaign = Campaign::create([
            'room_id' => $room->id, 'name' => 'Coffee B', 'restaurant' => 'Test Restaurant',
            'status' => CampaignStatus::Active, 'sponsor_type' => 'per_item', 'sponsor_name' => 'Phòng Marketing',
        ]);
        $member->orders()->create([
            'room_id' => $room->id, 'campaign_id' => $secondCampaign->id,
            'subtotal' => 60000, 'sponsor_amount' => 30000, 'final_amount' => 30000, 'status' => OrderStatus::Completed,
        ]);

        // Orders that have not finished yet must not count toward the sponsor total.
        $pendingCampaign = Campaign::create([
            'room_id' => $room->id, 'name' => 'Coffee E', 'restaurant' => 'Test Restaurant',
            'status' => CampaignStatus::Active, 'sponsor_type' => 'per_item', 'sponsor_name' => 'Pending Sponsor',
        ]);
        $member->orders()->create([
            'room_id' => $room->id, 'campaign_id' => $pendingCampaign->id,
            'subtotal' => 400000, 'sponsor_amount' => 400000, 'final_amount' => 0, 'status' => OrderStatus::Delivering,
        ]);

        // A cancelled order's sponsor amount must not count toward its campaign's sponsor total.
        $cancelledCampaign = Campaign::create([
            'room_id' => $room->id, 'name' => 'Coffee C', 'restaurant' => 'Test Restaurant',
            'status' => CampaignStatus::Active, 'sponsor_type' => 'per_item', 'sponsor_name' => 'Ignored Sponsor',
        ]);
        $member->orders()->create([
            'room_id' => $room->id, 'campaign_id' => $cancelledCampaign->id,
            'subtotal' => 500000, 'sponsor_amount' => 500000, 'final_amount' => 0, 'status' => OrderStatus::Cancelled,
        ]);

        // A member's own order subsidy must never surface them as a "sponsor" without a real
        // sponsor_name / sponsor_allocations on the campaign.
        $noSponsorCampaign = Campaign::create([
            'room_id' => $room->id, 'name' => 'Coffee D', 'restaurant' => 'Test Restaurant',
            'status' => CampaignStatus::Active, 'sponsor_type' => 'none',
        ]);
        $member->orders()->create([
            'room_id' => $room->id, 'campaign_id' => $noSponsorCampaign->id,
            'subtotal' => 90000, 'sponsor_amount' => 90000, 'final_amount' => 0, 'status' => OrderStatus::Completed,
        ]);

        $data = app(UserRoomDashboardService::class)->getDashboardData($room, $member, null);

        $this->assertSame(['Công ty ABC', 'Phòng Marketing'], array_column($data['topSponsors'], 'name'));
        $this->assertSame([80000, 30000], array_column($data['topSponsors'], 'amount'));
    }

    /**
     * The sponsor leaderboard lists every sponsor of the current month (no top-N cut), highest amount
     * first, and leaves out campaigns created in an earlier month.
     *
     * @return void
     */
    public function test_dashboard_top_sponsors_list_every_sponsor_of_the_current_month(): void
    {
        $room = Room::create(['name' => 'Technology', 'slug' => 'technology-top-sponsors-month', 'status' => RoomStatus::Active]);
        $member = $this->member($room, $this->user('month@example.test'), 'MONTH');

        foreach (range(1, 8) as $rank) {
            $campaign = Campaign::create([
                'room_id' => $room->id, 'name' => 'Coffee '.$rank, 'restaurant' => 'Test Restaurant',
                'status' => CampaignStatus::Closed, 'sponsor_type' => 'per_item', 'sponsor_name' => 'Sponsor '.$rank,
            ]);
            $member->orders()->create([
                'room_id' => $room->id, 'campaign_id' => $campaign->id,
                'subtotal' => 100000, 'sponsor_amount' => $rank * 10000, 'final_amount' => 100000 - $rank * 10000,
                'status' => OrderStatus::Completed,
            ]);
        }

        $lastMonthCampaign = Campaign::create([
            'room_id' => $room->id, 'name' => 'Old coffee', 'restaurant' => 'Test Restaurant',
            'status' => CampaignStatus::Closed, 'sponsor_type' => 'per_item', 'sponsor_name' => 'Last month sponsor',
        ]);
        $lastMonthCampaign->forceFill(['created_at' => now()->startOfMonth()->subDay()])->saveQuietly();
        $member->orders()->create([
            'room_id' => $room->id, 'campaign_id' => $lastMonthCampaign->id,
            'subtotal' => 900000, 'sponsor_amount' => 900000, 'final_amount' => 0, 'status' => OrderStatus::Completed,
        ]);

        $data = app(UserRoomDashboardService::class)->getDashboardData($room, $member, null);

        $this->assertCount(8, $data['topSponsors']);
        $this->assertSame('Sponsor 8', $data['topSponsors'][0]['name']);
        $this->assertSame('Sponsor 1', $data['topSponsors'][7]['name']);
        $this->assertNotContains('Last month sponsor', array_column($data['topSponsors'], 'name'));
    }

    /**
     * The monthly trend has one entry per day from the 1st of the month to today, with a room-wide
     * series and a series of the current member's own completed orders.
     *
     * @return void
     */
    public function test_dashboard_monthly_trend_aggregates_room_and_personal_items_per_day(): void
    {
        $room = Room::create(['name' => 'Technology', 'slug' => 'technology-monthly-trend', 'status' => RoomStatus::Active]);
        $campaign = Campaign::create([
            'room_id' => $room->id,
            'name' => 'Trend campaign',
            'restaurant' => 'Test Restaurant',
            'status' => CampaignStatus::Active,
        ]);
        $member = $this->member($room, $this->user('trend@example.test'), 'TREND');

        $todayOrder = $member->orders()->create([
            'room_id' => $room->id, 'campaign_id' => $campaign->id,
            'subtotal' => 50000, 'final_amount' => 50000, 'status' => OrderStatus::Completed,
        ]);
        $todayOrder->items()->create([
            'item_name' => 'Drink today', 'unit_price' => 25000, 'quantity' => 2, 'line_subtotal' => 50000,
        ]);
        // A fully sponsored order still counts at its full order value.
        $sponsoredCampaign = Campaign::create([
            'room_id' => $room->id,
            'name' => 'Sponsored trend campaign',
            'restaurant' => 'Test Restaurant',
            'status' => CampaignStatus::Closed,
        ]);
        $sponsoredOrder = $member->orders()->create([
            'room_id' => $room->id, 'campaign_id' => $sponsoredCampaign->id,
            'subtotal' => 30000, 'sponsor_amount' => 30000, 'final_amount' => 0, 'status' => OrderStatus::Completed,
        ]);
        $sponsoredOrder->items()->create([
            'item_name' => 'Sponsored drink', 'unit_price' => 30000, 'quantity' => 1, 'line_subtotal' => 30000,
        ]);
        // Orders that have not finished yet are excluded from the trend.
        $pendingOrder = $member->orders()->create([
            'room_id' => $room->id, 'campaign_id' => $campaign->id,
            'subtotal' => 70000, 'final_amount' => 70000, 'status' => OrderStatus::Ordered,
        ]);
        $pendingOrder->items()->create([
            'item_name' => 'Pending drink', 'unit_price' => 35000, 'quantity' => 2, 'line_subtotal' => 70000,
        ]);
        // Another member's order counts for the room but not in the current member's personal series.
        $other = $this->member($room, $this->user('other-trend@example.test'), 'OTHER');
        $otherOrder = $other->orders()->create([
            'room_id' => $room->id, 'campaign_id' => $campaign->id,
            'subtotal' => 20000, 'final_amount' => 20000, 'status' => OrderStatus::Completed,
        ]);
        $otherOrder->items()->create([
            'item_name' => 'Other drink', 'unit_price' => 20000, 'quantity' => 1, 'line_subtotal' => 20000,
        ]);

        $data = app(UserRoomDashboardService::class)->getDashboardData($room, $member, null);
        $trend = $data['monthlyItemTrend'];
        $days = (int) now()->day;

        $this->assertCount($days, $trend['room']);
        $this->assertCount($days, $trend['personal']);
        $this->assertSame('1', $trend['room'][0]['day']);
        $this->assertSame(4, $trend['room'][$days - 1]['items_count']);
        $this->assertSame(100000, $trend['room'][$days - 1]['value_amount']);
        $this->assertSame(3, $trend['personal'][$days - 1]['items_count']);
        $this->assertSame(80000, $trend['personal'][$days - 1]['value_amount']);

        // The chart data attributes must hold plain JSON that the browser can JSON.parse().
        $html = $this->actingAs($member->globalUser, 'web')->get(route('user.dashboard', $room->slug))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-monthly-trend="([^"]*)"/', $html);
        preg_match('/data-monthly-trend="([^"]*)"/', $html, $matches);
        $decoded = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true);
        $this->assertIsArray($decoded);
        $this->assertSame(100000, $decoded['room'][$days - 1]['value_amount']);
        $this->assertSame(80000, $decoded['personal'][$days - 1]['value_amount']);
    }

    /**
     * Create an active global account.
     *
     * @param string $email Unique account email.
     * @return GlobalUser Created account.
     */
    private function user(string $email): GlobalUser
    {
        return GlobalUser::create(['name' => 'Member', 'email' => $email, 'status' => GlobalUserStatus::Active]);
    }

    /**
     * Create an active membership.
     *
     * @param Room $room Target room.
     * @param GlobalUser $user Global account.
     * @param string $code Unique room code.
     * @return RoomUser Created membership.
     */
    private function member(Room $room, GlobalUser $user, string $code): RoomUser
    {
        return RoomUser::create([
            'room_id' => $room->id, 'global_user_id' => $user->id,
            'user_code' => $code, 'display_name' => $user->name,
            'normalized_name' => strtoupper($user->name), 'status' => RoomUserStatus::Active,
            'joined_at' => now(),
        ]);
    }
}
