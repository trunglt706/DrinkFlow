<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\User\JoinRoomAction;
use App\Enums\CampaignStatus;
use App\Enums\DebtStatus;
use App\Enums\GlobalUserStatus;
use App\Enums\OrderStatus;
use App\Enums\RoomStatus;
use App\Enums\RoomUserStatus;
use App\Models\Campaign;
use App\Models\Debt;
use App\Models\GlobalUser;
use App\Models\Order;
use App\Models\Room;
use App\Models\RoomUser;
use App\Models\RoomUserDevice;
use App\Services\Auth\DeviceTrustService;
use App\Services\Dashboard\UserRoomDashboardService;
use App\Services\Debt\UserRoomDebtService;
use App\Services\Order\PublicOrderCheckService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserRoomPerformanceUpgradeTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_dashboard_monthly_trend_executes_few_queries_and_uses_cache(): void
    {
        Carbon::setTestNow('2026-10-08 12:00:00');
        Cache::flush();

        $room = Room::create(['name' => 'Perf Room', 'slug' => 'perf-trend-room', 'status' => RoomStatus::Active]);
        $campaign = Campaign::create([
            'room_id' => $room->id,
            'name' => 'Trend Campaign',
            'restaurant' => 'Test Rest',
            'status' => CampaignStatus::Active,
        ]);
        $user = GlobalUser::create(['name' => 'Trend User', 'email' => 'trend@example.test', 'status' => GlobalUserStatus::Active]);
        $member = RoomUser::create([
            'room_id' => $room->id,
            'global_user_id' => $user->id,
            'user_code' => 'TREND01',
            'display_name' => 'Trend User',
            'status' => RoomUserStatus::Active,
        ]);

        $order = Order::create([
            'code' => 'ORD-TR-01',
            'room_id' => $room->id,
            'room_user_id' => $member->id,
            'campaign_id' => $campaign->id,
            'subtotal' => 50000,
            'final_amount' => 50000,
            'status' => OrderStatus::Completed,
        ]);
        $order->forceFill(['created_at' => '2026-10-08 10:00:00'])->saveQuietly();
        $order->items()->create([
            'item_name' => 'Item 1',
            'unit_price' => 25000,
            'quantity' => 2,
            'line_subtotal' => 50000,
        ]);

        $service = app(UserRoomDashboardService::class);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $trend1 = $service->getDashboardData($room, $member, $user)['monthlyItemTrend'];
        $queriesForTrend = collect(DB::getQueryLog())->filter(
            static fn (array $q): bool => str_contains($q['query'], 'total_subtotal') || str_contains($q['query'], 'total_quantity')
        )->count();

        $this->assertCount(8, $trend1['room']);
        $this->assertCount(8, $trend1['personal']);
        $this->assertSame(2, $trend1['room'][7]['items_count']);
        $this->assertSame(50000, $trend1['personal'][7]['value_amount']);
        $this->assertSame(4, $queriesForTrend);

        // Second call should hit the cache without running trend queries
        DB::flushQueryLog();
        $trend2 = $service->getDashboardData($room, $member, $user)['monthlyItemTrend'];
        $queriesForTrend2 = collect(DB::getQueryLog())->filter(
            static fn (array $q): bool => str_contains($q['query'], 'total_subtotal') || str_contains($q['query'], 'total_quantity')
        )->count();

        $this->assertSame($trend1, $trend2);
        $this->assertSame(0, $queriesForTrend2);
    }

    public function test_room_request_does_not_rewrite_last_active_or_last_seen_within_five_minutes(): void
    {
        Carbon::setTestNow('2026-10-08 12:00:00');
        $room = Room::create(['name' => 'Active Room', 'slug' => 'active-room-perf', 'status' => RoomStatus::Active]);
        $user = GlobalUser::create(['name' => 'Active User', 'email' => 'active@example.test', 'status' => GlobalUserStatus::Active]);
        $member = RoomUser::create([
            'room_id' => $room->id,
            'global_user_id' => $user->id,
            'user_code' => 'ACT01',
            'display_name' => 'Active User',
            'status' => RoomUserStatus::Active,
            'last_active_at' => Carbon::parse('2026-10-08 11:58:00'),
        ]);

        $trustService = app(DeviceTrustService::class);
        $token = $trustService->issue($member, 'device-uuid-123');
        $device = RoomUserDevice::where('device_uuid', 'device-uuid-123')->first();
        $device->update(['last_seen_at' => Carbon::parse('2026-10-08 11:58:00')]);

        $updateCount = 0;
        DB::listen(static function (QueryExecuted $query) use (&$updateCount): void {
            if (str_starts_with(strtolower(trim($query->sql)), 'update')) {
                $updateCount++;
            }
        });

        // Request 1 within 2 minutes: should not trigger UPDATE for last_active_at or last_seen_at
        $this->actingAs($user, 'web')
            ->withCookie('drinkflow_device_uuid', 'device-uuid-123')
            ->withCookie('drinkflow_trusted_token', $token)
            ->get(route('user.dashboard', $room->slug))
            ->assertOk();

        $this->assertSame(0, $updateCount);

        // Move time past 5 minutes: now it should update
        Carbon::setTestNow('2026-10-08 12:06:00');
        $this->actingAs($user, 'web')
            ->withCookie('drinkflow_device_uuid', 'device-uuid-123')
            ->withCookie('drinkflow_trusted_token', $token)
            ->get(route('user.dashboard', $room->slug))
            ->assertOk();

        $this->assertGreaterThan(0, $updateCount);
    }

    public function test_lookup_member_and_public_order_check_use_direct_sql_lookups(): void
    {
        $room = Room::create(['name' => 'Lookup Room', 'slug' => 'lookup-perf-room', 'status' => RoomStatus::Active]);
        $campaign = Campaign::create([
            'room_id' => $room->id,
            'name' => 'Lookup Campaign',
            'restaurant' => 'Lookup Rest',
            'status' => CampaignStatus::Active,
        ]);

        $alice = GlobalUser::create(['name' => 'Alice User', 'email' => 'alice@company.test', 'phone' => '0912345678', 'status' => GlobalUserStatus::Active]);
        $aliceMember = RoomUser::create([
            'room_id' => $room->id,
            'global_user_id' => $alice->id,
            'user_code' => 'ALICE01',
            'display_name' => 'Alice',
            'status' => RoomUserStatus::Active,
        ]);

        $bob = GlobalUser::create(['name' => 'Bob User', 'email' => 'bob@company.test', 'phone' => '0987654321', 'status' => GlobalUserStatus::Active]);
        $bobMember = RoomUser::create([
            'room_id' => $room->id,
            'global_user_id' => $bob->id,
            'user_code' => 'BOB01',
            'display_name' => 'Bob',
            'status' => RoomUserStatus::Active,
        ]);

        // Member lookup endpoint
        $response = $this->actingAs($alice, 'web')
            ->getJson(route('user.room-members.lookup', ['room' => $room->slug, 'q' => 'BOB01']))
            ->assertOk();

        $response->assertJsonPath('user_code', 'BOB01');
        $response->assertJsonPath('display_name', 'Bob');

        // Lookup by email
        $responseEmail = $this->actingAs($alice, 'web')
            ->getJson(route('user.room-members.lookup', ['room' => $room->slug, 'q' => 'bob@company.test']))
            ->assertOk();
        $responseEmail->assertJsonPath('user_code', 'BOB01');

        // Lookup by phone
        $responsePhone = $this->actingAs($alice, 'web')
            ->getJson(route('user.room-members.lookup', ['room' => $room->slug, 'q' => '0987654321']))
            ->assertOk();
        $responsePhone->assertJsonPath('user_code', 'BOB01');

        // Public order check
        $order = Order::create([
            'code' => 'ORD-PUB-99',
            'room_id' => $room->id,
            'room_user_id' => $bobMember->id,
            'campaign_id' => $campaign->id,
            'subtotal' => 40000,
            'final_amount' => 40000,
            'status' => OrderStatus::Submitted,
        ]);
        $order->items()->create([
            'item_name' => 'Matcha',
            'unit_price' => 40000,
            'quantity' => 1,
            'line_subtotal' => 40000,
        ]);

        $orderCheckService = app(PublicOrderCheckService::class);
        $found = $orderCheckService->findOrders($campaign, 'ORD-PUB-99');
        $this->assertCount(1, $found);
        $this->assertSame('ORD-PUB-99', $found->first()->code);

        $foundByEmail = $orderCheckService->findOrders($campaign, 'bob@company.test');
        $this->assertCount(1, $foundByEmail);

        $foundEmpty = $orderCheckService->findOrders($campaign, 'nonexistent');
        $this->assertCount(0, $foundEmpty);
    }

    public function test_visible_debts_query_efficiently_handles_sponsor_and_cancelled_orders(): void
    {
        $room = Room::create(['name' => 'Debt Perf Room', 'slug' => 'debt-perf-room', 'status' => RoomStatus::Active]);
        $campaign1 = Campaign::create([
            'room_id' => $room->id,
            'name' => 'Camp 1',
            'restaurant' => 'Rest 1',
            'status' => CampaignStatus::Active,
        ]);
        $campaign2 = Campaign::create([
            'room_id' => $room->id,
            'name' => 'Camp 2',
            'restaurant' => 'Rest 2',
            'status' => CampaignStatus::Active,
        ]);

        $user = GlobalUser::create(['name' => 'Debt User', 'email' => 'debt@example.test', 'status' => GlobalUserStatus::Active]);
        $member = RoomUser::create([
            'room_id' => $room->id,
            'global_user_id' => $user->id,
            'user_code' => 'DEBT01',
            'display_name' => 'Debt User',
            'status' => RoomUserStatus::Active,
        ]);

        // 1. Debt with active order -> Visible
        $debt1 = Debt::create([
            'room_id' => $room->id,
            'room_user_id' => $member->id,
            'campaign_id' => $campaign1->id,
            'code' => 'DEBT-01',
            'original_amount' => 50000,
            'amount' => 50000,
            'remaining_amount' => 50000,
            'status' => DebtStatus::Unpaid,
        ]);
        Order::create([
            'code' => 'ORD-D1',
            'room_id' => $room->id,
            'room_user_id' => $member->id,
            'campaign_id' => $campaign1->id,
            'subtotal' => 50000,
            'final_amount' => 50000,
            'status' => OrderStatus::Submitted,
        ]);

        // 2. Debt with ONLY cancelled order -> Hidden
        $debt2 = Debt::create([
            'room_id' => $room->id,
            'room_user_id' => $member->id,
            'campaign_id' => $campaign2->id,
            'code' => 'DEBT-02',
            'original_amount' => 60000,
            'amount' => 60000,
            'remaining_amount' => 60000,
            'status' => DebtStatus::Unpaid,
        ]);
        Order::create([
            'code' => 'ORD-D2',
            'room_id' => $room->id,
            'room_user_id' => $member->id,
            'campaign_id' => $campaign2->id,
            'subtotal' => 60000,
            'final_amount' => 60000,
            'status' => OrderStatus::Cancelled,
        ]);

        // 3. Sponsor debt with NO personal orders in campaign -> Visible
        $campaign3 = Campaign::create([
            'room_id' => $room->id,
            'name' => 'Camp 3',
            'restaurant' => 'Rest 3',
            'status' => CampaignStatus::Active,
        ]);
        $debt3 = Debt::create([
            'room_id' => $room->id,
            'room_user_id' => $member->id,
            'campaign_id' => $campaign3->id,
            'code' => 'DEBT-03',
            'original_amount' => 100000,
            'amount' => 100000,
            'remaining_amount' => 100000,
            'status' => DebtStatus::Unpaid,
        ]);

        $service = app(UserRoomDebtService::class);
        $visibleDebts = $service->queryVisibleDebts($room, $member)->pluck('code')->all();

        $this->assertContains('DEBT-01', $visibleDebts);
        $this->assertNotContains('DEBT-02', $visibleDebts);
        $this->assertContains('DEBT-03', $visibleDebts);
    }
}
