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
use App\Services\Order\PublicOrderCheckService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class UpgradeJsonDataExposureTest extends TestCase
{
    use RefreshDatabase;

    /** The member order list cannot carry another member's order through campaign relations. */
    public function test_order_list_json_only_contains_orders_visible_to_the_requester(): void
    {
        [$room, $campaign, $viewer, $viewerMember, $other, $otherMember] = $this->roomWithTwoMembers();
        $mine = $this->order($room, $campaign, $viewerMember, 'submitted', 31000);
        $theirs = $this->order($room, $campaign, $otherMember, 'submitted', 97000);

        $response = $this->actingAs($viewer, 'web')->getJson(route('user.orders.index', $room));

        $response->assertOk()->assertJsonPath('data.data.0.code', $mine->code);
        $payload = $response->getContent();
        $this->assertStringNotContainsString((string) $theirs->code, $payload);
        $this->assertStringNotContainsString('97000', $payload);
        $this->assertArrayNotHasKey('orders', $response->json('data.data.0.campaign'));
    }

    /** Lookup contact details remain recognizable but cannot be harvested in full, and requests are throttled. */
    public function test_member_lookup_masks_contact_details_and_is_rate_limited(): void
    {
        [$room, , $viewer, , $other, $otherMember] = $this->roomWithTwoMembers();

        $first = $this->actingAs($viewer, 'web')->getJson(route('user.room-members.lookup', [$room, 'q' => $otherMember->user_code]));
        $first->assertOk()
            ->assertJsonPath('email', 'o***@example.test')
            ->assertJsonPath('phone', '09*****321');
        $first->assertDontSee($other->email, false)->assertDontSee($other->phone, false);

        for ($attempt = 2; $attempt <= 20; $attempt++) {
            $this->actingAs($viewer, 'web')
                ->getJson(route('user.room-members.lookup', [$room, 'q' => $otherMember->user_code]))
                ->assertOk();
        }

        $this->actingAs($viewer, 'web')
            ->getJson(route('user.room-members.lookup', [$room, 'q' => $otherMember->user_code]))
            ->assertTooManyRequests();
    }

    /** Member-facing Room, Campaign, Order and payment JSON use allow-listed resource fields. */
    public function test_member_json_resources_do_not_expose_room_internal_columns(): void
    {
        [$room, $campaign, $viewer, $viewerMember] = $this->roomWithTwoMembers();
        $room->update(['settings' => ['private_token' => 'never-return-this']]);
        PaymentAccount::create([
            'room_id' => $room->id,
            'bank_code' => 'VCB',
            'bank_name' => 'Vietcombank',
            'account_number' => '0123456789',
            'account_name' => 'DRINKFLOW',
            'is_default' => true,
            'status' => 'active',
        ]);
        $order = $this->order($room, $campaign, $viewerMember, 'completed', 42000);

        $responses = [
            $this->actingAs($viewer, 'web')->getJson(route('user.rooms.show', $room)),
            $this->actingAs($viewer, 'web')->getJson(route('user.campaigns.show', [$room, $campaign])),
            $this->actingAs($viewer, 'web')->getJson(route('user.orders.show', [$room, $order])),
            $this->actingAs($viewer, 'web')->getJson(route('user.me.rooms')),
            $this->actingAs($viewer, 'web')->getJson(route('user.me.payments')),
        ];

        foreach ($responses as $response) {
            $response->assertOk();
            $payload = $response->getContent();
            $this->assertStringNotContainsString('owner_admin_id', $payload);
            $this->assertStringNotContainsString('"settings"', $payload);
            $this->assertStringNotContainsString('private_token', $payload);
            $this->assertStringNotContainsString('payment_accounts', $payload);
        }
    }

    /** Public order checks never return a complete member or proxy-order email address. */
    public function test_public_order_check_masks_member_emails(): void
    {
        [$room, $campaign, $viewer, $viewerMember] = $this->roomWithTwoMembers();
        $this->order($room, $campaign, $viewerMember, 'submitted', 51000);
        $params = [
            'campaign' => $campaign->id,
            'hash' => app(PublicOrderCheckService::class)->hash($campaign),
        ];
        $url = URL::temporarySignedRoute('public.order-check.lookup', now()->addMinute(), $params);

        $response = $this->postJson($url, ['identifier' => $viewer->email]);

        $response->assertOk()->assertJsonPath('orders.0.member_email', 'v***@example.test');
        $response->assertDontSee($viewer->email, false);
    }

    /**
     * @return array{Room, Campaign, GlobalUser, RoomUser, GlobalUser, RoomUser}
     */
    private function roomWithTwoMembers(): array
    {
        $room = Room::create([
            'name' => 'Privacy Room',
            'slug' => 'privacy-room',
            'status' => 'active',
        ]);
        $campaign = Campaign::create([
            'room_id' => $room->id,
            'name' => 'Privacy Campaign',
            'restaurant' => 'Safe Cafe',
            'status' => CampaignStatus::Active,
        ]);
        $viewer = GlobalUser::create([
            'name' => 'Viewer',
            'email' => 'viewer@example.test',
            'phone' => '0987654321',
            'status' => 'active',
        ]);
        $other = GlobalUser::create([
            'name' => 'Other Member',
            'email' => 'other@example.test',
            'phone' => '0912345321',
            'status' => 'active',
        ]);

        return [
            $room,
            $campaign,
            $viewer,
            app(JoinRoomAction::class)->execute($viewer, $room, 'viewer-device', 'viewer-hash'),
            $other,
            app(JoinRoomAction::class)->execute($other, $room, 'other-device', 'other-hash'),
        ];
    }

    private function order(Room $room, Campaign $campaign, RoomUser $member, string $status, int $amount): Order
    {
        return Order::create([
            'room_id' => $room->id,
            'campaign_id' => $campaign->id,
            'room_user_id' => $member->id,
            'subtotal' => $amount,
            'final_amount' => $amount,
            'status' => OrderStatus::from($status),
        ]);
    }
}
