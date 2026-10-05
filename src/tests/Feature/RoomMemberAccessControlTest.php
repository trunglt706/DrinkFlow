<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\User\JoinRoomAction;
use App\Enums\CampaignStatus;
use App\Enums\OrderStatus;
use App\Enums\RoomStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\GlobalUser;
use App\Models\Order;
use App\Models\Package;
use App\Models\Room;
use App\Models\RoomSetting;
use App\Models\RoomUser;
use App\Services\Room\RoomAccessPolicy;
use App\Services\Subscription\SubscriptionService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomMemberAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    /** Inactive and archived rooms reject read and write member requests before controllers run. */
    public function test_non_active_rooms_block_all_member_requests(): void
    {
        foreach ([RoomStatus::Inactive, RoomStatus::Archived] as $status) {
            [$user, $member, $room, $campaign, $order] = $this->memberContext('room-'.$status->value);
            $room->update(['status' => $status]);
            $message = __($status === RoomStatus::Archived ? 'room.access.room_archived' : 'room.access.room_inactive');

            $this->actingAs($user, 'web')
                ->get(route('user.dashboard', $room))
                ->assertForbidden()
                ->assertSee($message);

            $this->actingAs($user, 'web')
                ->getJson(route('user.orders.index', $room))
                ->assertForbidden()
                ->assertJsonPath('message', $message);

            $this->actingAs($user, 'web')
                ->postJson(route('user.campaigns.decline', [$room, $campaign]))
                ->assertForbidden()
                ->assertJsonPath('message', $message);

            $this->actingAs($user, 'web')
                ->patchJson(route('user.campaigns.cart.proxy', [$room, $campaign, 0]), ['proxy_user_code' => null])
                ->assertForbidden();

            $this->actingAs($user, 'web')
                ->deleteJson(route('user.campaigns.cart.clear', [$room, $campaign]))
                ->assertForbidden();

            $this->actingAs($user, 'web')
                ->postJson(route('user.orders.confirm-payment', [$room, $order]))
                ->assertForbidden();

            $this->assertDatabaseMissing('campaign_participants', [
                'campaign_id' => $campaign->id,
                'room_user_id' => $member->id,
            ]);
        }
    }

    /** An ended owner subscription blocks the room entry, pages and JSON APIs for members. */
    public function test_ended_owner_subscription_blocks_all_member_access(): void
    {
        $owner = Admin::create([
            'name' => 'Room Owner',
            'email' => 'owner-access@example.test',
            'password' => 'password123',
            'status' => 'active',
        ]);
        $package = Package::create([
            'code' => 'access-package',
            'name' => 'Access Package',
            'monthly_price' => 100000,
            'room_limit' => 2,
            'status' => 'active',
        ]);
        $subscription = app(SubscriptionService::class)->activate($owner, $package);
        [$user, , $room] = $this->memberContext('expired-subscription-room', $owner);
        $subscription->update([
            'status' => SubscriptionStatus::Expired,
            'ended_at' => now(),
        ]);
        $message = __('room.access.subscription_blocked');

        $this->actingAs($user, 'web')
            ->get(route('user.rooms.show', $room))
            ->assertForbidden()
            ->assertSee($message);

        $this->actingAs($user, 'web')
            ->get(route('user.dashboard', $room))
            ->assertForbidden()
            ->assertSee($message);

        $this->actingAs($user, 'web')
            ->getJson(route('user.campaigns.index', $room))
            ->assertForbidden()
            ->assertJsonPath('message', $message);

        $guest = GlobalUser::create([
            'name' => 'Prospective Member',
            'email' => 'prospective@example.test',
            'status' => 'active',
        ]);
        $this->actingAs($guest, 'web')
            ->get(route('user.rooms.join.show', $room))
            ->assertForbidden()
            ->assertSee($message);
        $this->actingAs($guest, 'web')
            ->post(route('user.rooms.join', $room))
            ->assertForbidden();
        $this->assertDatabaseMissing('room_users', [
            'room_id' => $room->id,
            'global_user_id' => $guest->id,
        ]);
    }

    /** Without configured proxies, a forged X-Forwarded-For value never replaces REMOTE_ADDR. */
    public function test_forwarded_ip_header_is_ignored_without_a_trusted_proxy(): void
    {
        $user = GlobalUser::create([
            'name' => 'Direct Connection User',
            'email' => 'direct-connection@example.test',
            'status' => 'active',
        ]);
        $room = Room::create([
            'name' => 'Direct Connection Room',
            'slug' => 'direct-connection-room',
            'status' => RoomStatus::Active,
        ]);
        RoomSetting::create([
            'room_id' => $room->id,
            'key' => RoomAccessPolicy::BLOCKED_IPS,
            'value' => json_encode(['203.0.113.9']),
            'type' => RoomSetting::TYPE_JSON,
        ]);

        $this->actingAs($user, 'web')
            ->withHeader('X-Forwarded-For', '203.0.113.9')
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->get(route('user.rooms.join.show', $room))
            ->assertOk();

        $this->withHeader('X-Forwarded-For', '198.51.100.10')
            ->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])
            ->get(route('user.rooms.join.show', $room))
            ->assertForbidden()
            ->assertSee(__('room.access.ip_denied', ['ip' => '203.0.113.9']));
    }

    /** An explicitly configured proxy may supply the client IP used by room access rules. */
    public function test_forwarded_ip_header_is_used_only_from_an_explicitly_trusted_proxy(): void
    {
        config(['trustedproxy.proxies' => ['198.51.100.10']]);

        $user = GlobalUser::create([
            'name' => 'Proxied Connection User',
            'email' => 'proxied-connection@example.test',
            'status' => 'active',
        ]);
        $room = Room::create([
            'name' => 'Trusted Proxy Room',
            'slug' => 'trusted-proxy-room',
            'status' => RoomStatus::Active,
        ]);
        RoomSetting::create([
            'room_id' => $room->id,
            'key' => RoomAccessPolicy::BLOCKED_IPS,
            'value' => json_encode(['203.0.113.9']),
            'type' => RoomSetting::TYPE_JSON,
        ]);

        $this->actingAs($user, 'web')
            ->withHeader('X-Forwarded-For', '203.0.113.9')
            ->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->get(route('user.rooms.join.show', $room))
            ->assertForbidden()
            ->assertSee(__('room.access.ip_denied', ['ip' => '203.0.113.9']));
    }

    /**
     * @return array{GlobalUser, RoomUser, Room, Campaign, Order}
     */
    private function memberContext(string $slug, ?Admin $owner = null): array
    {
        $user = GlobalUser::create([
            'name' => 'Room Member '.$slug,
            'email' => $slug.'@example.test',
            'status' => 'active',
        ]);
        $room = Room::create([
            'owner_admin_id' => $owner?->id,
            'name' => 'Room '.$slug,
            'slug' => $slug,
            'status' => RoomStatus::Active,
        ]);
        if ($owner !== null) {
            $room->admins()->attach($owner);
        }
        $member = app(JoinRoomAction::class)->execute($user, $room, 'device-'.$slug, 'hash-'.$slug);
        $campaign = Campaign::create([
            'room_id' => $room->id,
            'name' => 'Campaign '.$slug,
            'restaurant' => 'Cafe',
            'status' => CampaignStatus::Active,
        ]);
        $order = Order::create([
            'room_id' => $room->id,
            'campaign_id' => $campaign->id,
            'room_user_id' => $member->id,
            'subtotal' => 25000,
            'final_amount' => 25000,
            'status' => OrderStatus::Submitted,
        ]);

        return [$user, $member, $room, $campaign, $order];
    }
}
