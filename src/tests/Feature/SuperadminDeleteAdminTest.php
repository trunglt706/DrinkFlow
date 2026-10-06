<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\GlobalUser;
use App\Models\Order;
use App\Models\Room;
use App\Models\Superadmin;
use App\Models\RoomUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SuperadminDeleteAdminTest extends TestCase
{
    use RefreshDatabase;

    private function root(): Superadmin
    {
        return $this->createSuperadmin(['name' => 'Root', 'email' => 'root-delete@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
    }

    public function test_superadmin_deletes_an_admin_and_its_relations_while_shared_data_is_kept(): void
    {
        $root = $this->root();
        $admin = Admin::create(['name' => 'Operator', 'email' => 'operator-delete@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $room = Room::create(['name' => 'Ops', 'slug' => 'ops-delete', 'status' => 'active']);
        $admin->rooms()->attach($room);

        $campaign = Campaign::create(['room_id' => $room->id, 'name' => 'Trà', 'restaurant' => 'Cafe', 'status' => CampaignStatus::Active, 'creator_admin_id' => $admin->id]);
        $user = GlobalUser::create(['name' => 'Member', 'email' => 'member-delete@drinkflow.test', 'status' => 'active']);
        $member = RoomUser::create(['room_id' => $room->id, 'global_user_id' => $user->id, 'display_name' => 'Member', 'status' => 'active']);
        $order = Order::create(['room_id' => $room->id, 'campaign_id' => $campaign->id, 'room_user_id' => $member->id, 'subtotal' => 30000, 'final_amount' => 30000, 'status' => 'submitted']);
        $order->forceFill(['placed_by_admin_id' => $admin->id])->save();
        AdminNotification::create(['admin_id' => $admin->id, 'room_id' => $room->id, 'type' => 'order.created', 'title' => 'T', 'body' => 'B']);
        $history = AuditLog::create(['actor_type' => 'admin', 'actor_id' => $admin->id, 'event' => 'campaign.created', 'target_type' => 'campaign', 'target_id' => $campaign->id, 'room_id' => $room->id, 'created_at' => now()]);
        $history->admins()->attach($admin->id);

        $this->actingAs($root, 'superadmin')
            ->deleteJson("/superadmin/admins/{$admin->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('admins', ['id' => $admin->id]);
        $this->assertDatabaseMissing('admin_rooms', ['admin_id' => $admin->id]);
        $this->assertDatabaseMissing('admin_notifications', ['admin_id' => $admin->id]);
        $this->assertDatabaseMissing('admin_audit_logs', ['admin_id' => $admin->id]);

        // Shared data stays, only the link to the deleted admin is cleared.
        $this->assertNull($campaign->fresh()->creator_admin_id);
        $this->assertNull($order->fresh()->placed_by_admin_id);
        $this->assertNotNull(Room::find($room->id));

        // Activity history is kept, and the deletion itself is audited.
        $this->assertNotNull(AuditLog::find($history->id));
        $this->assertTrue(AuditLog::query()->where('event', 'admin.deleted')->where('target_id', $admin->id)->exists());
    }

    public function test_deleting_admins_never_touches_superadmin_accounts(): void
    {
        $root = $this->root();
        $admin = Admin::create(['name' => 'Agent', 'email' => $root->email, 'password' => 'password123', 'status' => 'suspended']);

        $this->actingAs($root, 'superadmin')->deleteJson("/superadmin/admins/{$admin->id}")->assertOk();
        $this->assertSame(0, DB::table('admins')->where('id', $admin->id)->count());
        $this->assertDatabaseHas('superadmins', ['id' => $root->id, 'email' => $root->email]);
    }

    public function test_agent_page_shows_the_delete_control(): void
    {
        $root = $this->root();
        $admin = Admin::create(['name' => 'Operator', 'email' => 'operator-ui@drinkflow.test', 'password' => 'password123', 'status' => 'active']);

        $this->actingAs($root, 'superadmin')->get(route('superadmin.agents.show', $admin))
            ->assertOk()
            ->assertSee('id="agent-delete"', false)
            ->assertDontSee('data-agent-delete-blocked', false);
    }

    public function test_agent_with_billing_history_is_kept_and_cannot_be_deleted(): void
    {
        $root = $this->root();
        $package = \App\Models\Package::create(['code' => 'std', 'name' => 'Standard', 'monthly_price' => 500000, 'room_limit' => 5, 'status' => 'active']);
        $admin = Admin::create(['name' => 'Billed', 'email' => 'billed@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        app(\App\Services\Subscription\SubscriptionService::class)->activate($admin, $package);
        $this->artisan('billing:generate-invoices')->assertSuccessful();
        $this->assertTrue($admin->invoices()->exists());

        // The page offers suspension instead: the delete button is disabled with the reason.
        $this->actingAs($root, 'superadmin')->get(route('superadmin.agents.show', $admin))
            ->assertOk()
            ->assertSee('data-agent-delete-blocked', false)
            ->assertSee(__('platform.agents.delete_unavailable'))
            ->assertDontSee('id="agent-delete"', false);
        // And the API refuses it with a message instead of failing on the invoices' foreign key.
        $this->actingAs($root, 'superadmin')->deleteJson(route('superadmin.admins.destroy', $admin))
            ->assertUnprocessable()
            ->assertJsonPath('message', __('platform.agents.delete_blocked_billing'));
        $this->assertDatabaseHas('admins', ['id' => $admin->id]);
    }

    public function test_superadmin_layout_uses_admin_brand_mark_without_zero_trust_block(): void
    {
        $this->actingAs($this->root(), 'superadmin')->get(route('superadmin.dashboard'))
            ->assertOk()
            ->assertSee('superadmin-logo', false)
            ->assertSee('M20 3H4v10c0 2.21', false)
            ->assertDontSee('superadmin-trust', false)
            ->assertDontSee('Zero Trust');
    }
}
