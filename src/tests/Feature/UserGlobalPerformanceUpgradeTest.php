<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\User\JoinRoomAction;
use App\Enums\CampaignStatus;
use App\Enums\OrderStatus;
use App\Models\Campaign;
use App\Models\GlobalUser;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Room;
use App\Models\RoomUser;
use App\Models\UserNotification;
use App\Services\Analytics\UserGlobalAnalyticsService;
use App\Services\Order\UserOrdersService;
use App\Services\Payment\UserPaymentsService;
use App\Services\Room\UserRoomsService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserGlobalPerformanceUpgradeTest extends TestCase
{
    use RefreshDatabase;

    private int $orderSequence = 0;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_analytics_uses_sql_aggregates_and_real_calendar_weeks(): void
    {
        CarbonImmutable::setTestNow('2026-10-08 12:00:00');
        [$user, $member, $room, $campaign] = $this->context('analytics-performance');
        $this->order($member, $room, $campaign, 100, 20, '2026-08-03 10:00:00', 'Coffee', 2);
        $this->order($member, $room, $campaign, 300, 0, '2026-10-02 10:00:00', 'Coffee', 1);
        $this->order($member, $room, $campaign, 200, 50, '2026-10-07 10:00:00', 'Tea', 3);
        $this->order($member, $room, $campaign, 999, 0, '2026-10-07 11:00:00', 'Ignored', 9, OrderStatus::Cancelled);

        DB::flushQueryLog();
        DB::enableQueryLog();
        $data = app(UserGlobalAnalyticsService::class)->getAnalyticsViewData($user);
        $queryCount = count(DB::getQueryLog());

        $this->assertSame(3, $data['totalOrders']);
        $this->assertSame(600, $data['totalSpent']);
        $this->assertSame(70, $data['totalSponsor']);
        $this->assertSame(300, $data['weeklyStats'][3]['spent']);
        $this->assertSame(200, $data['weeklyStats'][4]['spent']);
        $this->assertSame('Tea', $data['favoriteItem']);
        $this->assertSame(3, $data['roomStats'][0]['order_count']);
        $this->assertLessThanOrEqual(7, $queryCount);

        for ($index = 0; $index < 20; $index++) {
            $this->order($member, $room, $campaign, 10, 0, '2026-07-01 10:00:00', 'Bulk', 1);
        }
        DB::flushQueryLog();
        app(UserGlobalAnalyticsService::class)->getAnalyticsViewData($user);
        $this->assertSame($queryCount, count(DB::getQueryLog()));
    }

    public function test_room_cards_use_aggregates_without_loading_order_models(): void
    {
        [$user, $member, $room, $campaign] = $this->context('room-card-performance');
        $this->order($member, $room, $campaign, 12000, 0, '2026-09-01 10:00:00');
        $this->order($member, $room, $campaign, 18000, 0, '2026-09-02 10:00:00');

        $queries = [];
        DB::listen(static function (QueryExecuted $query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });
        $data = app(UserRoomsService::class)->getRoomsData($user, Request::create('/me/rooms'));
        $card = $data['roomUsers']->first();

        $this->assertSame(2, $card->orders_count);
        $this->assertSame(30000, $card->total_spent);
        $this->assertNotNull($card->last_order_time);
        $this->assertFalse(collect($queries)->contains(
            static fn (string $sql): bool => str_starts_with($sql, 'select * from "orders"') || str_starts_with($sql, 'select * from `orders`')
        ));
    }

    public function test_payments_are_paginated_and_qr_is_limited_to_the_current_unpaid_page(): void
    {
        [$user, $member, $room, $campaign] = $this->context('payment-performance');
        PaymentAccount::create([
            'room_id' => $room->id,
            'bank_code' => 'VCB',
            'bank_name' => 'Vietcombank',
            'account_number' => '123456789',
            'account_name' => 'DRINKFLOW',
            'is_default' => true,
            'status' => 'active',
        ]);
        for ($index = 0; $index < 20; $index++) {
            $this->order($member, $room, $campaign, 10000 + $index, 0, "2026-09-01 10:{$index}:00");
        }
        $unpaidCampaign = Campaign::create([
            'room_id' => $room->id,
            'name' => 'Unpaid campaign',
            'restaurant' => 'Cafe unpaid',
            'status' => CampaignStatus::Active,
        ]);
        $this->order($member, $room, $unpaidCampaign, 25000, 0, '2026-10-05 10:00:00', status: OrderStatus::Submitted);

        $data = app(UserPaymentsService::class)->getPaymentsData(
            $user,
            Request::create('/me/payments'),
        );

        $this->assertCount(15, $data['displayedOrders']);
        $this->assertSame(21, $data['displayedOrders']->total());
        $this->assertSame(1, $data['unpaidCount']);
        $this->assertCount(1, $data['qrPayloads']);
        $this->assertSame(21, app(UserPaymentsService::class)->exportQuery($user, Request::create('/'))->count());
    }

    public function test_index_friendly_date_ranges_include_day_boundaries(): void
    {
        CarbonImmutable::setTestNow('2026-10-15 12:00:00');
        [$user, $member, $room, $campaign] = $this->context('date-boundaries');
        $this->order($member, $room, $campaign, 100, 0, '2026-09-30 23:59:59');
        $this->order($member, $room, $campaign, 200, 0, '2026-10-01 00:00:00');
        $this->order($member, $room, $campaign, 300, 0, '2026-10-31 23:59:59');
        $service = app(UserOrdersService::class);

        $page = $service->getOrdersPageData($user, Request::create('/me/orders'));
        $api = $service->getOrdersApiData($user, Request::create('/analytics', 'GET', [
            'from' => '2026-10-01',
            'to' => '2026-10-01',
        ]));

        $this->assertSame(2, $page['totalOrdersCount']);
        $this->assertSame(2, $page['orders']->total());
        $this->assertSame(1, $api->total());
        $this->assertSame(200, (int) $api->first()->final_amount);
    }

    public function test_global_layout_executes_one_notification_list_and_one_count_query(): void
    {
        [$user] = $this->context('notification-summary');
        UserNotification::create([
            'global_user_id' => $user->id,
            'type' => 'campaign.created',
            'title' => 'Unread',
            'body' => 'One notification',
        ]);
        $notificationQueries = [];
        DB::listen(static function (QueryExecuted $query) use (&$notificationQueries): void {
            if (str_contains(strtolower($query->sql), 'user_notifications')) {
                $notificationQueries[] = $query->sql;
            }
        });

        $this->actingAs($user, 'web')->get('/me/orders')->assertOk();

        $this->assertCount(2, $notificationQueries);
    }

    /** @return array{GlobalUser, RoomUser, Room, Campaign} */
    private function context(string $slug): array
    {
        $user = GlobalUser::create(['name' => 'Performance User', 'email' => $slug.'@example.test', 'status' => 'active']);
        $room = Room::create(['name' => 'Room '.$slug, 'slug' => $slug, 'status' => 'active']);
        $member = app(JoinRoomAction::class)->execute($user, $room, 'device-'.$slug, 'hash-'.$slug);
        $campaign = Campaign::create([
            'room_id' => $room->id,
            'name' => 'Campaign '.$slug,
            'restaurant' => 'Cafe '.$slug,
            'status' => CampaignStatus::Active,
        ]);

        return [$user, $member, $room, $campaign];
    }

    private function order(
        RoomUser $member,
        Room $room,
        Campaign $campaign,
        int $amount,
        int $sponsor,
        string $createdAt,
        string $item = 'Coffee',
        int $quantity = 1,
        OrderStatus $status = OrderStatus::Completed,
    ): Order {
        $order = Order::create([
            'code' => 'PERF-'.str_pad((string) ++$this->orderSequence, 6, '0', STR_PAD_LEFT),
            'room_id' => $room->id,
            'room_user_id' => $member->id,
            'campaign_id' => $campaign->id,
            'subtotal' => $amount + $sponsor,
            'sponsor_amount' => $sponsor,
            'final_amount' => $amount,
            'status' => $status,
        ]);
        $order->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();
        $order->items()->create([
            'item_name' => $item,
            'unit_price' => $amount,
            'quantity' => $quantity,
            'line_subtotal' => $amount,
        ]);

        return $order;
    }
}
