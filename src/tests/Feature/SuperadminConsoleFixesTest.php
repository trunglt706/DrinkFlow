<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Superadmin\DeleteGlobalUserAction;
use App\Enums\CampaignStatus;
use App\Enums\OrderStatus;
use App\Events\CampaignCancelled;
use App\Models\Admin;
use App\Models\Superadmin;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\GlobalUser;
use App\Models\Order;
use App\Models\Room;
use App\Models\RoomUser;
use App\Models\RoomUserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SuperadminConsoleFixesTest extends TestCase
{
    use RefreshDatabase;

    private Superadmin $root;
    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = $this->createSuperadmin([
            'name' => 'Root', 'email' => 'root-fixes@drinkflow.test',
            'password' => 'password123', 'status' => 'active',
        ]);
        $this->room = Room::create(['name' => 'Fix Room', 'slug' => 'fix-room', 'status' => 'active']);
    }

    /**
     * @return array{0: GlobalUser, 1: RoomUser}
     */
    private function memberWithOrderHistory(string $email = 'member-fixes@drinkflow.test'): array
    {
        $user = GlobalUser::create(['name' => 'Member', 'email' => $email]);
        $roomUser = RoomUser::create([
            'room_id' => $this->room->id, 'global_user_id' => $user->id,
            'display_name' => 'Member', 'status' => 'active',
        ]);
        $campaign = Campaign::create([
            'room_id' => $this->room->id, 'status' => CampaignStatus::Archived,
            'name' => 'Old campaign', 'restaurant' => 'Shop',
        ]);
        Order::create([
            'room_id' => $this->room->id, 'campaign_id' => $campaign->id, 'room_user_id' => $roomUser->id,
            'subtotal' => 30000, 'final_amount' => 30000, 'status' => OrderStatus::Completed,
        ]);

        return [$user, $roomUser];
    }

    public function test_deleting_a_user_with_order_history_soft_deletes_the_account(): void
    {
        [$user, $roomUser] = $this->memberWithOrderHistory();
        $device = RoomUserDevice::create(['room_user_id' => $roomUser->id, 'device_uuid' => 'device-fixes', 'token_hash' => 'x', 'verified_at' => now()]);

        $this->actingAs($this->root, 'superadmin')->deleteJson("/superadmin/global-users/{$user->id}")->assertOk();

        $this->assertDatabaseHas('global_users', ['id' => $user->id, 'status' => 'deleted']);
        $this->assertDatabaseHas('room_users', ['id' => $roomUser->id, 'status' => 'removed']);
        $this->assertNotNull($device->fresh()->revoked_at);
        $this->assertSame(1, Order::where('room_user_id', $roomUser->id)->count(), 'Order history must be kept.');
        $this->assertSame(1, AuditLog::where('event', 'global_user.deleted')->where('target_id', $user->id)->count());

        // A second delete, or bringing the account back through the status endpoint, is refused.
        $this->actingAs($this->root, 'superadmin')->deleteJson("/superadmin/global-users/{$user->id}")->assertStatus(422);
        $this->actingAs($this->root, 'superadmin')->patchJson("/superadmin/global-users/{$user->id}/status", ['status' => 'active'])->assertStatus(422);
        $this->assertDatabaseHas('global_users', ['id' => $user->id, 'status' => 'deleted']);
    }

    public function test_status_endpoint_cannot_mark_a_user_deleted(): void
    {
        [$user] = $this->memberWithOrderHistory();

        $this->actingAs($this->root, 'superadmin')->patchJson("/superadmin/global-users/{$user->id}/status", ['status' => 'deleted'])->assertStatus(422);
        $this->assertDatabaseHas('global_users', ['id' => $user->id, 'status' => 'active']);
    }

    public function test_deleted_users_are_hidden_from_lists_unless_filtered(): void
    {
        [$user] = $this->memberWithOrderHistory();
        $user->update(['status' => 'deleted']);
        GlobalUser::create(['name' => 'Visible Person', 'email' => 'visible-fixes@drinkflow.test']);

        $this->actingAs($this->root, 'superadmin')->get('/superadmin/global-users/page')
            ->assertOk()->assertSee('Visible Person')->assertDontSee($this->maskedEmail('member-fixes@drinkflow.test'));
        $this->actingAs($this->root, 'superadmin')->get('/superadmin/global-users/page?status=deleted')
            ->assertOk()->assertSee($this->maskedEmail('member-fixes@drinkflow.test'))->assertDontSee('Visible Person');
        $this->actingAs($this->root, 'superadmin')->getJson('/superadmin/global-users')
            ->assertOk()->assertJsonMissing(['email' => 'member-fixes@drinkflow.test']);
    }

    public function test_deleted_user_cannot_use_the_app(): void
    {
        [$user] = $this->memberWithOrderHistory();
        $this->actingAs($this->root, 'superadmin')->deleteJson("/superadmin/global-users/{$user->id}")->assertOk();

        $this->actingAs($user->fresh(), 'web')->get('/me')->assertForbidden();
    }

    public function test_room_admin_cannot_add_a_deleted_account_back_to_a_room(): void
    {
        [$user] = $this->memberWithOrderHistory();
        app(DeleteGlobalUserAction::class)->execute($user);

        $roomAdmin = Admin::create([
            'name' => 'Room Admin', 'email' => 'room-admin-fixes@drinkflow.test',
            'password' => 'password123', 'status' => 'active',
        ]);
        $roomAdmin->rooms()->attach($this->room);
        $this->actingAs($roomAdmin, 'admin')
            ->postJson("/admin/{$this->room->slug}/room-users", ['name' => 'Member', 'email' => 'member-fixes@drinkflow.test'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_force_cancel_archives_the_campaign_without_notifying_or_touching_orders(): void
    {
        Event::fake([CampaignCancelled::class]);
        [, $roomUser] = $this->memberWithOrderHistory();
        $campaign = Campaign::create([
            'room_id' => $this->room->id, 'status' => CampaignStatus::Active,
            'name' => 'Live campaign', 'restaurant' => 'Shop',
        ]);
        $order = Order::create([
            'room_id' => $this->room->id, 'campaign_id' => $campaign->id, 'room_user_id' => $roomUser->id,
            'subtotal' => 20000, 'final_amount' => 20000, 'status' => OrderStatus::Submitted,
        ]);

        $this->actingAs($this->root, 'superadmin')->postJson("/superadmin/campaigns/{$campaign->id}/force-cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');

        $this->assertSame(OrderStatus::Submitted, $order->fresh()->status);
        Event::assertNotDispatched(CampaignCancelled::class);
        $this->assertSame(1, AuditLog::where('event', 'campaign.force_cancelled')->where('target_id', $campaign->id)->count());

        // Already archived (or closed/cancelled) campaigns cannot be force-cancelled.
        $this->actingAs($this->root, 'superadmin')->postJson("/superadmin/campaigns/{$campaign->id}/force-cancel")->assertStatus(422);
        $closed = Campaign::create(['room_id' => $this->room->id, 'status' => CampaignStatus::Closed, 'name' => 'Closed', 'restaurant' => 'Shop']);
        $this->actingAs($this->root, 'superadmin')->postJson("/superadmin/campaigns/{$closed->id}/force-cancel")->assertStatus(422);
        $this->assertSame(CampaignStatus::Closed, $closed->fresh()->status);
    }

    public function test_admin_and_room_actions_are_audited_once(): void
    {
        $admin = Admin::create([
            'name' => 'Operator', 'email' => 'operator-fixes@drinkflow.test',
            'password' => 'password123', 'status' => 'active',
        ]);

        $this->actingAs($this->root, 'superadmin')->patchJson("/superadmin/admins/{$admin->id}/status", ['status' => 'cancelled'])->assertOk();
        $this->actingAs($this->root, 'superadmin')->patchJson("/superadmin/rooms/{$this->room->id}/status", ['status' => 'inactive'])->assertOk();
        $this->actingAs($this->root, 'superadmin')->postJson('/superadmin/admins', [
            'name' => 'New Admin', 'email' => 'new-admin-fixes@drinkflow.test',
            'password' => 'password123', 'password_confirmation' => 'password123', 'status' => 'active',
        ])->assertCreated();

        $this->assertSame(1, AuditLog::where('target_type', 'admin')->where('target_id', $admin->id)->count());
        $this->assertSame(1, AuditLog::where('target_type', 'room')->where('target_id', $this->room->id)->count());
        $this->assertSame(1, AuditLog::where('event', 'admin.created')->count());
    }

    public function test_room_detail_and_dashboard_have_no_hardcoded_labels(): void
    {
        $this->actingAs($this->root, 'superadmin')->get("/superadmin/rooms/{$this->room->id}/page")
            ->assertOk()
            ->assertSee('<title>'.e(__('superadmin.rooms.profile')).' · DrinkFlow</title>', false)
            ->assertDontSee("'Enable room'", false)
            ->assertDontSee('Đã cập nhật trạng thái Room.', false);

        $this->actingAs($this->root, 'superadmin')->get('/superadmin')
            ->assertOk()
            ->assertSee(__('superadmin.dashboard.room_admins'))
            ->assertDontSee('</span>System', false);
    }
}
