<?php

namespace Tests\Feature;

use App\Actions\Order\CreateOrderAction;
use App\Actions\User\JoinRoomAction;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\CampaignItem;
use App\Models\GlobalUser;
use App\Models\Room;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

class DrinkflowOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_join_room_is_idempotent_and_binds_device(): void
    {
        $user = GlobalUser::create(['name' => 'Nguyễn Thành Trung', 'normalized_name' => 'NGUYEN THANH TRUNG', 'email' => 'trung@company.com']);
        $room = Room::create(['name' => 'IT', 'slug' => 'it']);
        $action = app(JoinRoomAction::class);
        $first = $action->execute($user, $room, 'device-1', 'hash-1');
        $second = $action->execute($user, $room, 'device-1', 'hash-2');
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('room_users', 1);
        $this->assertDatabaseHas('room_user_devices', ['room_user_id' => $first->id, 'token_hash' => 'hash-2']);
    }

    public function test_create_order_uses_server_prices_and_prevents_duplicate_active_order(): void
    {
        $user = GlobalUser::create(['name' => 'Trung', 'normalized_name' => 'TRUNG', 'email' => 'trung2@company.com']);
        $room = Room::create(['name' => 'IT', 'slug' => 'it-2']);
        $roomUser = app(JoinRoomAction::class)->execute($user, $room, 'device-2', 'hash');
        $campaign = Campaign::create(['room_id' => $room->id, 'name' => 'Lunch', 'restaurant' => 'Cafe', 'status' => CampaignStatus::Active, 'delivery_fee' => 5000, 'discount' => 1000]);
        $item = CampaignItem::create(['campaign_id' => $campaign->id, 'name' => 'Tea', 'normalized_name' => 'TEA', 'base_price' => 20000, 'status' => 'active']);
        $action = app(CreateOrderAction::class);
        $order = $action->execute($campaign, $roomUser, ['items' => [['item_id' => $item->id, 'quantity' => 2]], 'discount_amount' => 999999, 'sponsor_amount' => 999999]);
        $this->assertSame(40000, $order->final_amount);
        $this->assertDatabaseHas('user_notifications', ['global_user_id' => $user->id, 'type' => 'order.created']);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $action->execute($campaign, $roomUser, ['items' => [['item_id' => $item->id, 'quantity' => 1]]]);
    }

    public function test_user_can_place_order_after_previous_order_cancelled(): void
    {
        $user = GlobalUser::create(['name' => 'Reorder User', 'normalized_name' => 'REORDER USER', 'email' => 'reorder@company.com']);
        $room = Room::create(['name' => 'IT', 'slug' => 'it-reorder']);
        $roomUser = app(JoinRoomAction::class)->execute($user, $room, 'device-reorder', 'hash-reorder');
        $campaign = Campaign::create(['room_id' => $room->id, 'name' => 'Lunch Reorder', 'restaurant' => 'Cafe', 'status' => CampaignStatus::Active]);
        $item = CampaignItem::create(['campaign_id' => $campaign->id, 'name' => 'Coffee', 'normalized_name' => 'COFFEE', 'base_price' => 25000, 'status' => 'active']);
        $action = app(CreateOrderAction::class);

        $order1 = $action->execute($campaign, $roomUser, ['items' => [['item_id' => $item->id, 'quantity' => 1]]]);
        $this->assertSame(25000, $order1->final_amount);

        // Cancel order 1
        $order1->update(['status' => \App\Enums\OrderStatus::Cancelled]);

        // Placing a new order should succeed
        $order2 = $action->execute($campaign, $roomUser, ['items' => [['item_id' => $item->id, 'quantity' => 2]]]);
        $this->assertSame(50000, $order2->final_amount);
    }

    public function test_duplicate_order_response_links_to_existing_order(): void
    {
        $user = GlobalUser::create(['name' => 'An', 'normalized_name' => 'AN', 'email' => 'an-order@company.com']);
        $room = Room::create(['name' => 'IT', 'slug' => 'it-order-link']);
        $roomUser = app(JoinRoomAction::class)->execute($user, $room, 'device-link', 'hash-link');
        $campaign = Campaign::create(['room_id' => $room->id, 'name' => 'Lunch link', 'restaurant' => 'Cafe', 'status' => CampaignStatus::Active]);
        $item = CampaignItem::create(['campaign_id' => $campaign->id, 'name' => 'Tea link', 'normalized_name' => 'TEA LINK', 'base_price' => 20000, 'status' => 'active']);
        app(CreateOrderAction::class)->execute($campaign, $roomUser, ['items' => [['item_id' => $item->id, 'quantity' => 1]]]);

        $response = $this->withoutMiddleware(ValidateCsrfToken::class)
            ->actingAs($user, 'web')
            ->postJson(route('user.orders.store', [$room, $campaign]), ['items' => [['item_id' => $item->id, 'quantity' => 1]]]);

        $response->assertUnprocessable()
            ->assertJsonPath('code', 'active_order_exists')
            ->assertJsonPath('order_status', 'submitted')
            ->assertJsonStructure(['code', 'order_id', 'order_status', 'order_url']);
    }
}
