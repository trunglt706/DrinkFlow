<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\OrderStatus;
use App\Models\Campaign;
use App\Models\GlobalUser;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Room;
use App\Models\RoomUser;
use App\Services\Order\OrderItemRatingService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ratings of received order items: only the member who received the order rates it, once per item (rating again
 * updates it); the dish average follows the dish across campaigns of the room and is shown on the menu.
 */
class OrderItemRatingTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->room = Room::create(['name' => 'Rating Room', 'slug' => 'rating-room', 'status' => 'active', 'timezone' => 'UTC']);
    }

    private function member(string $name): RoomUser
    {
        $user = GlobalUser::create(['name' => $name, 'email' => strtolower($name).uniqid().'@example.test', 'status' => 'active']);

        return RoomUser::create(['room_id' => $this->room->id, 'global_user_id' => $user->id, 'display_name' => $name, 'status' => 'active']);
    }

    private function item(RoomUser $member, OrderStatus $status, string $restaurant = 'Phúc Long', string $name = 'Trà sữa'): OrderItem
    {
        $campaign = Campaign::create(['room_id' => $this->room->id, 'name' => 'Chiều', 'restaurant' => $restaurant, 'status' => CampaignStatus::Closed]);
        $order = Order::create(['room_id' => $this->room->id, 'campaign_id' => $campaign->id, 'room_user_id' => $member->id, 'subtotal' => 30000, 'final_amount' => 30000, 'status' => $status]);

        return $order->items()->create(['item_name' => $name, 'unit_price' => 30000, 'quantity' => 1, 'line_subtotal' => 30000]);
    }

    private function rate(RoomUser $member, OrderItem $item, array $data): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($member->globalUser, 'web')->postJson(route('user.order-items.rating', [$this->room->slug, $item->id]), $data);
    }

    public function test_stars_change_until_the_comment_is_sent_then_the_rating_is_final(): void
    {
        \Illuminate\Support\Carbon::setTestNow('2026-10-21 08:30:00');
        $lan = $this->member('Lan');
        $item = $this->item($lan, OrderStatus::Completed);
        $page = fn () => $this->actingAs($lan->globalUser, 'web')->get(route('user.orders.page', [$this->room->slug, $item->order->code]))->assertOk();

        // Stars alone are saved and can be changed.
        $this->rate($lan, $item, ['stars' => 4])->assertOk()
            ->assertJsonPath('rating.stars', 4)->assertJsonPath('rating.comment', null)->assertJsonPath('rating.locked', false)->assertJsonPath('rating.count', 1);
        $this->rate($lan, $item, ['stars' => 5])->assertOk()->assertJsonPath('rating.stars', 5);
        $page()->assertSee('data-item-rating', false)->assertSee('data-stars="5"', false)->assertSee('data-rating-comment', false);

        // A sent comment cannot be blank; once sent, the rating is final.
        $this->rate($lan, $item, ['stars' => 5, 'comment' => '   '])->assertStatus(422)->assertJsonValidationErrors('comment');
        $this->rate($lan, $item, ['stars' => 4, 'comment' => '  Ngon, hơi ngọt  '])->assertOk()
            ->assertJsonPath('message', __('room.rating.comment_sent'))
            ->assertJsonPath('rating.stars', 4)->assertJsonPath('rating.comment', 'Ngon, hơi ngọt')
            ->assertJsonPath('rating.locked', true)->assertJsonPath('rating.commented_at', '08:30 21/10/2026');
        $this->rate($lan, $item, ['stars' => 1])->assertStatus(422)->assertJsonPath('errors.comment.0', __('room.rating.locked'));
        $this->rate($lan, $item, ['stars' => 2, 'comment' => 'Đổi ý'])->assertStatus(422);
        $this->assertDatabaseCount('order_item_ratings', 1);
        $this->assertDatabaseHas('order_item_ratings', ['order_item_id' => $item->id, 'stars' => 4, 'comment' => 'Ngon, hơi ngọt', 'restaurant_key' => 'PHUC LONG', 'item_key' => 'TRA SUA']);

        // The page shows the final rating as text with its time, without inputs.
        $page()->assertSee('data-locked', false)->assertSee('Ngon, hơi ngọt')->assertSee(__('room.rating.sent_at', ['time' => '08:30 21/10/2026']))
            ->assertDontSee('data-rating-comment ', false)->assertDontSee('data-rating-star=', false);
        \Illuminate\Support\Carbon::setTestNow();
    }

    public function test_ratings_are_validated_and_limited_to_the_member_s_received_orders(): void
    {
        $lan = $this->member('Lan');
        $minh = $this->member('Minh');
        $received = $this->item($lan, OrderStatus::Delivering);
        $pending = $this->item($lan, OrderStatus::Submitted);

        foreach ([[], ['stars' => 0], ['stars' => 6], ['stars' => 'x'], ['stars' => 3, 'comment' => str_repeat('a', OrderItemRatingService::MAX_COMMENT + 1)]] as $data) {
            $this->rate($lan, $received, $data)->assertStatus(422);
        }
        $this->rate($lan, $pending, ['stars' => 3])->assertNotFound();
        $this->rate($minh, $received, ['stars' => 1])->assertNotFound();
        $this->actingAs($lan->globalUser, 'web')->post(route('user.order-items.rating', [$this->room->slug, $received->id]), ['stars' => 3])->assertNotFound();
        $this->assertDatabaseCount('order_item_ratings', 0);
    }

    public function test_dish_average_is_shown_on_the_menu_and_unrated_items_on_the_dashboard(): void
    {
        $lan = $this->member('Lan');
        $minh = $this->member('Minh');
        $this->rate($lan, $this->item($lan, OrderStatus::Completed, 'Phúc Long', 'Trà Sữa'), ['stars' => 5])->assertOk();
        $this->rate($minh, $this->item($minh, OrderStatus::Completed, 'phuc long', 'tra sua'), ['stars' => 4])->assertOk()->assertJsonPath('rating.average', 4.5)->assertJsonPath('rating.count', 2);

        $campaign = Campaign::create(['room_id' => $this->room->id, 'name' => 'Hôm nay', 'restaurant' => 'Phúc Long', 'status' => CampaignStatus::Active]);
        $menuItem = $campaign->items()->create(['name' => 'Trà sữa', 'base_price' => 30000, 'status' => 'active']);
        $other = $campaign->items()->create(['name' => 'Cà phê', 'base_price' => 25000, 'status' => 'active']);
        $this->assertSame([$menuItem->id => ['average' => 4.5, 'count' => 2]], app(OrderItemRatingService::class)->averagesForMenu($campaign, [$menuItem, $other]));
        $this->actingAs($lan->globalUser, 'web')->get(route('user.campaigns.index', $this->room->slug))
            ->assertOk()->assertSee('data-item-average="'.$menuItem->id.'"', false)->assertDontSee('data-item-average="'.$other->id.'"', false);

        // Received but unrated items are listed on the room dashboard until rated.
        $unrated = $this->item($lan, OrderStatus::Completed, 'Highlands', 'Freeze');
        $this->actingAs($lan->globalUser, 'web')->get(route('user.dashboard', $this->room->slug))->assertOk()->assertSee('data-ratings-pending', false)->assertSee('Freeze');
        $this->rate($lan, $unrated, ['stars' => 3])->assertOk();
        $this->actingAs($lan->globalUser, 'web')->get(route('user.dashboard', $this->room->slug))->assertOk()->assertDontSee('data-ratings-pending', false);
    }
}
