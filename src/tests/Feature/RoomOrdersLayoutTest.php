<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\User\JoinRoomAction;
use App\Enums\CampaignStatus;
use App\Enums\DebtStatus;
use App\Enums\OrderStatus;
use App\Models\Campaign;
use App\Models\Debt;
use App\Models\GlobalUser;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Room;
use App\Models\RoomUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomOrdersLayoutTest extends TestCase
{
    use RefreshDatabase;

    /**
     * While the campaign is live, the order page marks the orderer with the sponsor icon only when they sponsor it.
     *
     * @return void
     */
    public function test_live_order_page_marks_sponsors_of_the_campaign(): void
    {
        $room = Room::create(['name' => 'Live Room', 'slug' => 'live-room', 'status' => 'active']);
        $members = [];
        foreach (['sponsor', 'member'] as $name) {
            $user = GlobalUser::create(['name' => ucfirst($name), 'normalized_name' => strtoupper($name), 'email' => $name.'@company.com', 'status' => 'active']);
            $members[$name] = [$user, app(JoinRoomAction::class)->execute($user, $room, 'Chrome', $name.'-hash')];
        }
        $campaign = Campaign::create([
            'room_id' => $room->id,
            'name' => 'Live Tea',
            'restaurant' => 'Cafe',
            'status' => CampaignStatus::Active,
            'sponsor_type' => Campaign::SPONSOR_TYPE_PER_ITEM,
            'sponsor_allocations' => [['room_user_id' => $members['sponsor'][1]->id, 'percentage' => 100]],
        ]);
        foreach ($members as [$user, $roomUser]) {
            $order = Order::create(['room_id' => $room->id, 'campaign_id' => $campaign->id, 'room_user_id' => $roomUser->id, 'subtotal' => 30000, 'final_amount' => 30000, 'status' => OrderStatus::Submitted]);
            OrderItem::create(['order_id' => $order->id, 'item_name' => 'Tea', 'unit_price' => 30000, 'quantity' => 1, 'line_subtotal' => 30000]);
        }

        // The campaign orders modal always carries one (Alpine x-if) copy of the icon; the order card adds one for a sponsor.
        $marks = fn (GlobalUser $user): int => substr_count(
            (string) $this->actingAs($user, 'web')->get(route('user.orders.index', $room->slug))->assertOk()->getContent(),
            'data-campaign-sponsor-mark'
        );
        $this->assertSame(1, $marks($members['member'][0]));
        $this->assertSame(2, $marks($members['sponsor'][0]));
    }

    /**
     * Ensure the room order page uses the compact item layout and four-stage progress.
     *
     * @return void
     */
    public function test_order_page_shows_four_progress_steps_without_room_history(): void
    {
        $user = GlobalUser::create([
            'name' => 'Order Member',
            'normalized_name' => 'ORDER MEMBER',
            'email' => 'order-member@company.com',
            'status' => 'active',
        ]);
        $room = Room::create([
            'name' => 'Công Nghệ',
            'slug' => 'cong-nghe',
            'status' => 'active',
        ]);
        $roomUser = app(JoinRoomAction::class)->execute($user, $room, 'Chrome', 'order-layout-hash');
        $campaign = Campaign::create([
            'room_id' => $room->id,
            'name' => 'Coffee Live',
            'restaurant' => 'DrinkFlow Cafe',
            'status' => CampaignStatus::Active,
            'delivery_fee' => 10000,
            'discount' => 5000,
        ]);
        $order = Order::create([
            'room_id' => $room->id,
            'campaign_id' => $campaign->id,
            'room_user_id' => $roomUser->id,
            'subtotal' => 90000,
            'final_amount' => 90000,
            'status' => OrderStatus::Delivering,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'item_name' => 'Trà sữa ô long',
            'size_name' => 'L',
            'unit_price' => 45000,
            'quantity' => 2,
            'ice_percent' => 50,
            'sugar_percent' => 30,
            'line_subtotal' => 90000,
            'note' => 'Để riêng topping',
        ]);
        Debt::create([
            'room_id' => $room->id,
            'campaign_id' => $campaign->id,
            'room_user_id' => $roomUser->id,
            'original_amount' => 90000,
            'sponsor_amount' => 0,
            'paid_amount' => 0,
            'remaining_amount' => 90000,
            'status' => DebtStatus::Unpaid,
        ]);

        $cancelledCampaign = Campaign::create([
            'room_id' => $room->id,
            'name' => 'Cancelled Campaign',
            'restaurant' => 'Cancelled Cafe',
            'status' => CampaignStatus::Closed,
        ]);
        $cancelledOrder = Order::create([
            'room_id' => $room->id,
            'campaign_id' => $cancelledCampaign->id,
            'room_user_id' => $roomUser->id,
            'subtotal' => 50000,
            'final_amount' => 50000,
            'status' => OrderStatus::Cancelled,
        ]);
        OrderItem::create([
            'order_id' => $cancelledOrder->id,
            'item_name' => 'Cancelled Drink',
            'unit_price' => 50000,
            'quantity' => 1,
            'line_subtotal' => 50000,
        ]);
        Debt::create([
            'room_id' => $room->id,
            'campaign_id' => $cancelledCampaign->id,
            'room_user_id' => $roomUser->id,
            'original_amount' => 50000,
            'sponsor_amount' => 0,
            'paid_amount' => 0,
            'remaining_amount' => 50000,
            'status' => DebtStatus::Unpaid,
        ]);

        $response = $this->actingAs($user, 'web')->get('/rooms/cong-nghe/orders');

        $response->assertOk();
        $response->assertSeeText(__('room.orders.step_1_title'));
        $response->assertSeeText(__('room.orders.step_confirmed_title'));
        $response->assertSeeText(__('room.orders.step_delivered_title'));
        $response->assertSeeText(__('room.orders.step_completed_title'));
        $response->assertSee('Trà sữa ô long');
        $response->assertSee('95.000đ');
        $response->assertSee('10.000đ');
        $response->assertSee('5.000đ');
        $response->assertSee('Size L');
        $response->assertSee('30% đường');
        $response->assertSee('50% đá');
        $response->assertDontSee('Cancelled Drink');
        $response->assertSee(__('room.orders.confirm_modal_title'));
        $response->assertSee('bg-blue-600 hover:bg-blue-700', false);
        $response->assertSee('openPaymentConfirm', false);
        $response->assertSee('showGlobalLoading', false);
        $response->assertDontSee(__('room.orders.pay_now_vietqr').' (95.000đ)');
        $response->assertDontSee('Chờ duyệt thanh toán');
        $response->assertDontSee('confirm(', false);

        $debtResponse = $this->actingAs($user, 'web')->get('/rooms/cong-nghe/debts');
        $debtResponse->assertOk();
        $debtResponse->assertSee('Coffee Live');
        $debtResponse->assertDontSee('Cancelled Campaign');
        $debtResponse->assertDontSee('Cancelled Drink');
        $response->assertDontSee('Lịch sử đơn trong Room');
        $response->assertDontSee('5. Đang giao');

        $this->actingAs($user, 'web')
            ->get('/rooms/cong-nghe/debts')
            ->assertOk()
            ->assertSee('Coffee Live')
            ->assertDontSee('Cancelled Campaign');
    }

    /**
     * "My Orders" shows the live campaign's order at the top and the earlier orders in a history
     * list: the page and its tab stay available without a live campaign.
     *
     * @return void
     */
    public function test_my_orders_shows_the_live_order_and_the_history(): void
    {
        $user = GlobalUser::create(['name' => 'Live Only', 'email' => 'live-only@example.com', 'status' => 'active']);
        $room = Room::create(['name' => 'Live Room', 'slug' => 'live-only-room', 'code' => 'LIVE', 'status' => 'active']);
        $roomUser = app(JoinRoomAction::class)->execute($user, $room, 'Chrome', 'live-only-hash');
        $myOrdersTab = '>'.__('room.nav.my_orders').'</span>';

        $closedCampaign = Campaign::create(['room_id' => $room->id, 'name' => 'Old Campaign', 'restaurant' => 'Cafe', 'status' => CampaignStatus::Closed]);
        $closedOrder = Order::create([
            'room_id' => $room->id, 'campaign_id' => $closedCampaign->id, 'room_user_id' => $roomUser->id,
            'subtotal' => 20000, 'final_amount' => 20000, 'status' => OrderStatus::Completed,
        ]);
        OrderItem::create(['order_id' => $closedOrder->id, 'item_name' => 'Old Drink', 'unit_price' => 20000, 'quantity' => 1, 'line_subtotal' => 20000]);

        // No live campaign: the tab and the page show only the history (no invitation to order).
        $this->actingAs($user, 'web')->get(route('user.dashboard', $room->slug))->assertOk()->assertSee($myOrdersTab, false);
        $this->actingAs($user, 'web')->get(route('user.orders.index', $room->slug))
            ->assertOk()
            ->assertDontSee('data-orders-current-empty', false)
            ->assertDontSee(__('room.dashboard.enter_campaign'))
            ->assertSee('data-order-history-row="'.$closedOrder->code.'"', false)
            ->assertSee('data-order-history-copy', false);

        // Live campaign without an order from this member: the history is still there.
        $liveCampaign = Campaign::create(['room_id' => $room->id, 'name' => 'Live Campaign', 'restaurant' => 'Cafe', 'status' => CampaignStatus::Active]);
        $this->actingAs($user, 'web')->get(route('user.orders.index', $room->slug))
            ->assertOk()->assertSee(__('room.orders.no_current_order_title'))->assertSee('data-order-history-row="'.$closedOrder->code.'"', false);

        $liveOrder = Order::create([
            'room_id' => $room->id, 'campaign_id' => $liveCampaign->id, 'room_user_id' => $roomUser->id,
            'subtotal' => 30000, 'final_amount' => 30000, 'status' => OrderStatus::Submitted,
        ]);
        OrderItem::create(['order_id' => $liveOrder->id, 'item_name' => 'Live Drink', 'unit_price' => 30000, 'quantity' => 1, 'line_subtotal' => 30000]);

        $this->actingAs($user, 'web')->get(route('user.dashboard', $room->slug))->assertOk()->assertSee($myOrdersTab, false);
        // The live order is at the top (with its items) and is not repeated in the history.
        $this->actingAs($user, 'web')->get(route('user.orders.index', $room->slug))
            ->assertOk()
            ->assertSee('Live Drink')
            ->assertDontSee('Old Drink')
            ->assertSee('data-order-history-row="'.$closedOrder->code.'"', false)
            ->assertDontSee('data-order-history-row="'.$liveOrder->code.'"', false);
    }

    /**
     * The history filters by code, dates, self / proxy and status, 10 orders per page.
     *
     * @return void
     */
    public function test_my_orders_history_is_filtered_and_paginated(): void
    {
        $user = GlobalUser::create(['name' => 'History', 'email' => 'history@example.com', 'status' => 'active']);
        $friendUser = GlobalUser::create(['name' => 'Friend', 'email' => 'friend-history@example.com', 'status' => 'active']);
        $room = Room::create(['name' => 'History Room', 'slug' => 'history-room', 'code' => 'HIST', 'status' => 'active', 'timezone' => 'UTC']);
        $roomUser = app(JoinRoomAction::class)->execute($user, $room, 'Chrome', 'history-hash');
        $friend = app(JoinRoomAction::class)->execute($friendUser, $room, 'Chrome', 'friend-history-hash');
        $campaign = Campaign::create(['room_id' => $room->id, 'name' => 'Past', 'restaurant' => 'Cafe', 'status' => CampaignStatus::Closed]);
        $sequence = 0;
        $make = function (RoomUser $member, OrderStatus $status, string $date, ?Order $parent = null) use ($room, $campaign, &$sequence): Order {
            // Explicit codes: the generated ones count today's orders, which the back-dated rows would repeat.
            $order = Order::create([
                'code' => sprintf('HIST-%03d', ++$sequence),
                'room_id' => $room->id, 'campaign_id' => $campaign->id, 'room_user_id' => $member->id, 'parent_id' => $parent?->id,
                'subtotal' => 10000, 'final_amount' => 10000, 'status' => $status,
            ]);
            $order->forceFill(['created_at' => $date.' 10:00:00'])->save();

            return $order;
        };

        $orders = [];
        for ($i = 1; $i <= 11; $i++) {
            $orders[] = $make($roomUser, OrderStatus::Completed, sprintf('2026-09-%02d', $i));
        }
        $cancelled = $make($roomUser, OrderStatus::Cancelled, '2026-10-01');
        $proxy = $make($friend, OrderStatus::Completed, '2026-10-02', $orders[0]);
        $strangerOrder = $make($friend, OrderStatus::Completed, '2026-10-03');
        $page = fn (array $query = []) => $this->actingAs($user, 'web')->get(route('user.orders.index', ['room' => $room->slug] + $query))->assertOk();
        $row = static fn (Order $order): string => 'data-order-history-row="'.$order->code.'"';

        // 13 visible orders (the friend's own order is not), newest first, 10 per page.
        $page()->assertSee($row($proxy), false)->assertSee($row($cancelled), false)->assertDontSee($row($strangerOrder), false)
            ->assertSee('history_page=2', false)->assertDontSee($row($orders[0]), false);
        $page(['history_page' => 2])->assertSee($row($orders[0]), false);

        $page(['code' => $orders[3]->code])->assertSee($row($orders[3]), false)->assertDontSee($row($orders[4]), false);
        $page(['date_from' => '2026-09-05', 'date_to' => '2026-09-06'])
            ->assertSee($row($orders[4]), false)->assertSee($row($orders[5]), false)->assertDontSee($row($orders[3]), false)->assertDontSee($row($orders[6]), false);
        $page(['type' => 'proxy'])->assertSee($row($proxy), false)->assertSee(__('room.orders.history_proxy_for', ['name' => 'Friend']))->assertDontSee($row($orders[1]), false);
        $page(['type' => 'self', 'status' => 'cancelled'])->assertSee($row($cancelled), false)->assertDontSee($row($orders[1]), false)->assertDontSee($row($proxy), false);
        $page(['code' => 'NO-SUCH-CODE'])->assertSee(__('room.orders.history_empty_filtered'));

        // Invalid filters are refused.
        $this->actingAs($user, 'web')->get(route('user.orders.index', ['room' => $room->slug, 'status' => 'lost', 'type' => 'other']))->assertSessionHasErrors(['status', 'type']);
    }
}
