<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\User\JoinRoomAction;
use App\Enums\CampaignStatus;
use App\Enums\OrderStatus;
use App\Models\Campaign;
use App\Models\CampaignItem;
use App\Models\GlobalUser;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Room;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignDetailsEndpointTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The campaign details JSON must expose each item's menu image so the debts page
     * order modal can display it alongside the item name.
     *
     * @return void
     */
    public function test_campaign_details_exposes_item_image_url(): void
    {
        $user = GlobalUser::create([
            'name' => 'Debts Member',
            'normalized_name' => 'DEBTS MEMBER',
            'email' => 'debts-member@company.com',
            'status' => 'active',
        ]);
        $room = Room::create(['name' => 'Kinh Doanh', 'slug' => 'kinh-doanh', 'status' => 'active']);
        $roomUser = app(JoinRoomAction::class)->execute($user, $room, 'Chrome', 'campaign-details-hash');

        $campaign = Campaign::create([
            'room_id' => $room->id,
            'name' => 'Campaign With Images',
            'restaurant' => 'DrinkFlow Cafe',
            'status' => CampaignStatus::Active,
        ]);

        $campaignItem = CampaignItem::create([
            'campaign_id' => $campaign->id,
            'name' => 'Trà sữa trân châu',
            'category' => 'Trà sữa',
            'image_url' => 'https://cdn.drinkflow.test/items/tra-sua.jpg',
            'base_price' => 35000,
            'status' => 'active',
        ]);

        $order = Order::create([
            'room_id' => $room->id,
            'campaign_id' => $campaign->id,
            'room_user_id' => $roomUser->id,
            'subtotal' => 35000,
            'final_amount' => 35000,
            'status' => OrderStatus::Submitted,
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'campaign_item_id' => $campaignItem->id,
            'item_name' => 'Trà sữa trân châu',
            'unit_price' => 35000,
            'quantity' => 1,
            'line_subtotal' => 35000,
        ]);

        $response = $this->actingAs($user, 'web')
            ->getJson(route('user.campaigns.details', ['room' => $room->slug, 'campaign' => $campaign->id]));

        $response->assertOk()
            ->assertJsonPath('data.orders.0.items.0.image_url', 'https://cdn.drinkflow.test/items/tra-sua.jpg')
            ->assertJsonPath('data.orders.0.orderer_email', 'd***@company.com');
    }
}
