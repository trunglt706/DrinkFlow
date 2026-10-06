<?php

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\DebtStatus;
use App\Models\Admin;
use App\Models\Superadmin;
use App\Models\AuditLog;
use App\Models\Campaign;
use App\Models\Debt;
use App\Models\GlobalUser;
use App\Models\OAuthIdentity;
use App\Models\Room;
use App\Models\RoomUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SuperadminFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function superadmin(): Superadmin
    {
        return $this->createSuperadmin(['name' => 'Root', 'email' => 'root@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
    }

    public function test_superadmin_can_create_room_and_assign_admin(): void
    {
        $root = $this->superadmin();
        $room = Room::create(['name' => 'IT', 'slug' => 'it']);
        $response = $this->actingAs($root, 'superadmin')->postJson('/superadmin/admins', ['name' => 'Operator', 'email' => 'operator@drinkflow.test', 'password' => 'password123', 'room_ids' => [$room->id]]);

        $response->assertCreated()->assertJsonPath('data.rooms.0.id', $room->id);
        $this->assertDatabaseHas('admin_rooms', ['admin_id' => $response->json('data.id'), 'room_id' => $room->id]);
        $this->actingAs($root, 'superadmin')->postJson('/superadmin/rooms', ['name' => 'Marketing', 'slug' => 'marketing'])->assertCreated();
    }

    public function test_superadmin_can_sync_admin_rooms_and_archive_room(): void
    {
        $root = $this->superadmin();
        $admin = Admin::create(['name' => 'Operator', 'email' => 'operator-rooms@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $room = Room::create(['name' => 'Operations', 'slug' => 'operations', 'status' => 'active']);

        $this->actingAs($root, 'superadmin')->putJson("/superadmin/admins/{$admin->id}/rooms", ['room_ids' => [$room->id]])
            ->assertOk()
            ->assertJsonPath('data.id', $admin->id)
            ->assertJsonPath('data.rooms.0.id', $room->id);
        $this->assertDatabaseHas('admin_rooms', ['admin_id' => $admin->id, 'room_id' => $room->id]);

        $this->actingAs($root, 'superadmin')->patchJson("/superadmin/rooms/{$room->id}", ['name' => 'Operations HQ', 'slug' => 'operations-hq'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Operations HQ');
        $this->actingAs($root, 'superadmin')->patchJson("/superadmin/rooms/{$room->id}/status", ['status' => 'archived'])
            ->assertOk()
            ->assertJsonPath('data.status', 'archived');
    }

    /**
     * The room create modal must be able to assign responsible admins and an initial status
     * in the same request that creates the room.
     *
     * @return void
     */
    public function test_superadmin_can_create_room_with_status_and_admins(): void
    {
        $root = $this->superadmin();
        $admin = Admin::create(['name' => 'Room Lead', 'email' => 'room-lead@drinkflow.test', 'password' => 'password123', 'status' => 'active']);

        $response = $this->actingAs($root, 'superadmin')->postJson('/superadmin/rooms', [
            'name' => 'Sales', 'slug' => 'sales', 'status' => 'inactive', 'admin_ids' => [$admin->id],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'inactive')
            ->assertJsonPath('data.admins.0.id', $admin->id);
        $this->assertDatabaseHas('admin_rooms', ['admin_id' => $admin->id, 'room_id' => $response->json('data.id')]);
    }

    /**
     * The room detail page's update feature must be able to change name, slug, status and the
     * assigned admins together, and unassign an admin previously assigned to the room.
     *
     * @return void
     */
    public function test_superadmin_can_update_room_details_and_reassign_admins(): void
    {
        $root = $this->superadmin();
        $oldAdmin = Admin::create(['name' => 'Old Lead', 'email' => 'old-lead@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $newAdmin = Admin::create(['name' => 'New Lead', 'email' => 'new-lead@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $room = Room::create(['name' => 'Support', 'slug' => 'support', 'status' => 'active']);
        $room->admins()->sync([$oldAdmin->id]);

        $response = $this->actingAs($root, 'superadmin')->patchJson("/superadmin/rooms/{$room->id}", [
            'name' => 'Customer Support', 'slug' => 'customer-support', 'status' => 'active', 'admin_ids' => [$newAdmin->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Customer Support')
            ->assertJsonPath('data.slug', 'customer-support')
            ->assertJsonCount(1, 'data.admins')
            ->assertJsonPath('data.admins.0.id', $newAdmin->id);
        $this->assertDatabaseHas('admin_rooms', ['admin_id' => $newAdmin->id, 'room_id' => $room->id]);
        $this->assertDatabaseMissing('admin_rooms', ['admin_id' => $oldAdmin->id, 'room_id' => $room->id]);
    }

    /**
     * The room status endpoint previously only accepted 'active'/'disabled'/'archived', but
     * RoomStatus::class only backs 'active'/'inactive'/'archived' — meaning "disable room" would
     * throw an uncaught enum ValueError. Confirm the room can actually be set to inactive now.
     *
     * @return void
     */
    public function test_superadmin_can_set_room_status_to_inactive(): void
    {
        $root = $this->superadmin();
        $room = Room::create(['name' => 'Finance', 'slug' => 'finance', 'status' => 'active']);

        $this->actingAs($root, 'superadmin')->patchJson("/superadmin/rooms/{$room->id}/status", ['status' => 'inactive'])
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');
        $this->assertDatabaseHas('rooms', ['id' => $room->id, 'status' => 'inactive']);
    }

    /**
     * Deleting a room must also remove its RESTRICT-linked data (members, campaigns, settled debts)
     * and must not write an audit log that points at the deleted room.
     *
     * @return void
     */
    public function test_superadmin_can_delete_room_with_members_campaigns_and_settled_debts(): void
    {
        $root = $this->superadmin();
        $room = Room::create(['name' => 'Legacy', 'slug' => 'legacy', 'status' => 'active']);
        $roomUser = RoomUser::create([
            'room_id' => $room->id,
            'global_user_id' => GlobalUser::create(['name' => 'Member', 'email' => 'member-legacy@example.test'])->id,
            'display_name' => 'Member',
            'status' => 'active',
        ]);
        $campaign = Campaign::create(['room_id' => $room->id, 'status' => CampaignStatus::Active, 'name' => 'Old lunch', 'restaurant' => 'Shop', 'type' => 'food', 'deadline_at' => now()->addHour()]);
        Debt::create([
            'room_id' => $room->id,
            'campaign_id' => $campaign->id,
            'room_user_id' => $roomUser->id,
            'original_amount' => 50000,
            'sponsor_amount' => 0,
            'sponsor_type' => 'full',
            'adjustment_amount' => 0,
            'paid_amount' => 50000,
            'remaining_amount' => 0,
            'status' => DebtStatus::Paid,
        ]);

        $this->actingAs($root, 'superadmin')->deleteJson("/superadmin/rooms/{$room->id}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('rooms', ['id' => $room->id]);
        $this->assertDatabaseMissing('room_users', ['id' => $roomUser->id]);
        $this->assertDatabaseMissing('campaigns', ['id' => $campaign->id]);
        $this->assertDatabaseMissing('debts', ['room_id' => $room->id]);
        $this->assertSame(1, AuditLog::where('event', 'room.deleted')->where('target_id', $room->id)->whereNull('room_id')->count());
    }

    public function test_admins_can_no_longer_be_promoted_to_superadmin(): void
    {
        $root = $this->superadmin();
        $admin = Admin::create(['name' => 'Agent', 'email' => 'agent-role@drinkflow.test', 'password' => 'password123', 'status' => 'active']);

        // Superadmins are separate accounts: there is no role to switch on an Admin any more.
        $this->actingAs($root, 'superadmin')->patchJson("/superadmin/admins/{$admin->id}/role", ['role' => 'superadmin'])->assertNotFound();
        $this->actingAs($root, 'superadmin')->putJson("/superadmin/admins/{$admin->id}", ['role' => 'superadmin'])->assertOk();
        $this->assertSame(0, \App\Models\Superadmin::query()->where('email', $admin->email)->count());
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('admins', 'role'));
    }

    public function test_global_user_detail_exposes_identity_metadata_without_credentials(): void
    {
        $root = $this->superadmin();
        $user = GlobalUser::create(['name' => 'User', 'normalized_name' => 'USER', 'email' => 'user@drinkflow.test']);
        OAuthIdentity::create(['global_user_id' => $user->id, 'provider' => 'google', 'provider_user_id' => 'google-1', 'provider_email' => $user->email]);

        $this->actingAs($root, 'superadmin')->getJson("/superadmin/global-users/{$user->id}")
            ->assertOk()->assertJsonPath('data.oauth_identities.0.provider_user_id', 'google-1')->assertJsonMissing(['access_token' => 'secret']);
    }

    public function test_secret_system_setting_is_saved_encrypted_and_never_returned(): void
    {
        $root = $this->superadmin();
        $this->actingAs($root, 'superadmin')->putJson('/superadmin/system/settings', ['settings' => [['key' => 'oauth.client_secret', 'value' => 'top-secret', 'type' => 'string', 'is_secret' => true]]])->assertOk();
        $this->assertDatabaseMissing('system_settings', ['value' => 'top-secret']);
        $this->actingAs($root, 'superadmin')->getJson('/superadmin/system')->assertOk()->assertJsonPath('data.settings.0.value', null);
    }

    public function test_typed_non_secret_system_setting_can_be_updated(): void
    {
        $root = $this->superadmin();

        $this->actingAs($root, 'superadmin')->putJson('/superadmin/system/settings', ['settings' => [
            ['key' => 'orders.daily_limit', 'value' => 25, 'type' => 'integer', 'is_secret' => false],
            ['key' => 'orders.allow_cash', 'value' => true, 'type' => 'boolean', 'is_secret' => false],
        ]])->assertOk();

        $this->actingAs($root, 'superadmin')->getJson('/superadmin/system')->assertOk()
            ->assertJsonPath('data.settings.0.key', 'orders.allow_cash')
            ->assertJsonPath('data.settings.0.value', true)
            ->assertJsonPath('data.settings.1.value', 25);
    }

    public function test_maintenance_schedule_is_saved_and_returned_to_superadmin(): void
    {
        $root = $this->superadmin();
        $payload = ['enabled' => true, 'starts_at' => '2026-09-12 08:30:00', 'ends_at' => '2026-09-12 12:00:00'];

        $this->actingAs($root, 'superadmin')->putJson('/superadmin/system/maintenance', $payload)
            ->assertOk()
            ->assertJsonPath('data.starts_at', $payload['starts_at'])
            ->assertJsonPath('data.ends_at', $payload['ends_at']);

        $this->actingAs($root, 'superadmin')->getJson('/superadmin/system')
            ->assertOk()
            ->assertJsonPath('data.maintenance.starts_at', $payload['starts_at'])
            ->assertJsonPath('data.maintenance.ends_at', $payload['ends_at']);
    }

    public function test_system_reset_requires_exact_confirmation_and_preserves_superadmin(): void
    {
        $root = $this->superadmin();
        $room = Room::create(['name' => 'IT', 'slug' => 'reset-it']);
        $this->actingAs($root, 'superadmin')->postJson('/superadmin/system/reset', ['password' => 'password123', 'phrase' => 'reset drinkflow'])->assertStatus(422);
        $this->assertDatabaseHas('rooms', ['id' => $room->id]);

        $this->actingAs($root, 'superadmin')->postJson('/superadmin/system/reset', ['password' => 'password123', 'phrase' => 'RESET DRINKFLOW'])->assertOk();
        $this->assertDatabaseHas('superadmins', ['id' => $root->id, 'status' => 'active']);
        $this->assertDatabaseMissing('rooms', ['id' => $room->id]);
    }

    public function test_superadmin_can_block_and_unblock_admin(): void
    {
        $root = $this->superadmin();
        $admin = Admin::create(['name' => 'Operator', 'email' => 'operator-block@drinkflow.test', 'password' => 'password123', 'status' => 'active']);

        $this->actingAs($root, 'superadmin')->patchJson("/superadmin/admins/{$admin->id}/status", ['status' => 'suspended'])
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');
        $this->assertDatabaseHas('admins', ['id' => $admin->id, 'status' => 'suspended']);

        $this->actingAs($root, 'superadmin')->get('/superadmin/agents?status=suspended')
            ->assertOk()
            ->assertSee($this->maskedEmail('operator-block@drinkflow.test'))
            ->assertSee('status-pill status-suspended', false);

        $this->actingAs($root, 'superadmin')->patchJson("/superadmin/admins/{$admin->id}/status", ['status' => 'unknown'])->assertStatus(422);

        $this->actingAs($root, 'superadmin')->patchJson("/superadmin/admins/{$admin->id}/status", ['status' => 'active'])
            ->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_agent_list_counts_and_filters_agents_without_a_package(): void
    {
        $root = $this->superadmin();
        $package = \App\Models\Package::create(['code' => 'std', 'name' => 'Standard', 'monthly_price' => 500000, 'room_limit' => 5, 'status' => 'active']);
        $subscribed = Admin::create(['name' => 'Subscribed agent', 'email' => 'subscribed@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        app(\App\Services\Subscription\SubscriptionService::class)->activate($subscribed, $package);
        Admin::create(['name' => 'Packageless agent', 'email' => 'packageless@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        // Pending sign-ups belong to the registration queue, not to this count.
        Admin::create(['name' => 'Queued agent', 'email' => 'queued@drinkflow.test', 'password' => 'password123', 'status' => 'pending']);

        $this->actingAs($root, 'superadmin')->get(route('superadmin.agents.index'))
            ->assertOk()
            ->assertSee('data-agents-without-package="1"', false)
            ->assertSee(__('platform.agents.without_package_count', ['count' => 1]))
            ->assertSee(route('superadmin.agents.index', ['package' => 'none']), false);

        $this->actingAs($root, 'superadmin')->get(route('superadmin.agents.index', ['package' => 'none']))
            ->assertOk()
            ->assertSee('Packageless agent')
            ->assertDontSee('Subscribed agent')
            ->assertDontSee('Queued agent');

        // The count follows the current search, like the list.
        $this->actingAs($root, 'superadmin')->get(route('superadmin.agents.index', ['q' => 'Subscribed']))
            ->assertOk()
            ->assertSee('data-agents-without-package="0"', false);
    }

    public function test_agent_list_shows_the_package_of_every_row(): void
    {
        $root = $this->superadmin();
        $package = \App\Models\Package::create(['code' => 'std', 'name' => 'Standard plan', 'monthly_price' => 500000, 'room_limit' => 5, 'status' => 'active']);
        foreach (['Agent One', 'Agent Two', 'Agent Three'] as $index => $name) {
            $agent = Admin::create(['name' => $name, 'email' => "agent-{$index}@drinkflow.test", 'password' => 'password123', 'status' => 'active']);
            app(\App\Services\Subscription\SubscriptionService::class)->activate($agent, $package);
        }

        // Every row (not only the first) shows its package and quota instead of the out-of-scope mask.
        $html = $this->actingAs($root, 'superadmin')->get(route('superadmin.agents.index'))->assertOk()->getContent();
        $this->assertSame(3, substr_count($html, '<td>Standard plan</td>'));
        $this->assertSame(0, substr_count($html, '>•••<'));
    }

    public function test_former_admin_pages_redirect_and_the_agent_page_hosts_account_actions(): void
    {
        $root = $this->superadmin();
        $admin = Admin::create(['name' => 'Operator', 'email' => 'operator-page@drinkflow.test', 'password' => 'password123', 'status' => 'active']);

        // The former Agent pages were merged into Agents: they now redirect there, keeping the filters.
        $this->actingAs($root, 'superadmin')->get('/superadmin/admins/page?q=operator&status=active')
            ->assertStatus(301)->assertRedirect(route('superadmin.agents.index', ['q' => 'operator', 'status' => 'active']));
        $this->actingAs($root, 'superadmin')->get("/superadmin/admins/{$admin->id}/page")
            ->assertStatus(301)->assertRedirect(route('superadmin.agents.show', $admin));

        // Room access, password reset and deletion now live on the Agent page (modals of its page script).
        $this->actingAs($root, 'superadmin')->get(route('superadmin.agents.show', $admin))
            ->assertOk()
            ->assertSee('id="room-access-modal"', false)
            ->assertSee('id="reset-password-modal"', false)
            ->assertSee('data-password-toggle="reset-password"', false)
            ->assertSee('data-password-toggle="reset-password-confirmation"', false)
            ->assertSee('id="tpl-rooms-loading"', false)
            ->assertSee('id="agent-delete"', false);
        $this->actingAs($root, 'superadmin')->get(route('superadmin.agents.show', ['admin' => $admin, 'tab' => 'rooms']))
            ->assertOk()
            ->assertSee('id="edit-rooms"', false)
            ->assertSee(__('superadmin.admins.no_assigned_rooms_description'));

        $this->actingAs($root, 'superadmin')->get('/superadmin/agents?q=no-such-admin')
            ->assertOk()
            ->assertSee(__('platform.agents.empty'));
    }

    public function test_global_user_pages_render_empty_states_and_distinct_block_button(): void
    {
        $root = $this->superadmin();
        $user = GlobalUser::create(['name' => 'Alice', 'normalized_name' => 'ALICE', 'email' => 'alice-empty@drinkflow.test', 'status' => 'active']);

        $this->actingAs($root, 'superadmin')->get('/superadmin/global-users/page')
            ->assertOk()
            ->assertSee('class="sa-menu-item is-warning" type="button" data-action="toggle-user-status" data-user-id="'.$user->id.'"', false);

        $this->actingAs($root, 'superadmin')->get('/superadmin/global-users/page?q=no-such-user')
            ->assertOk()
            ->assertSee(__('superadmin.users.no_results_title'))
            ->assertSee(__('superadmin.users.no_results_description'));

        $this->actingAs($root, 'superadmin')->get("/superadmin/global-users/{$user->id}/page")
            ->assertOk()
            ->assertSee(__('superadmin.common.loading_title'))
            ->assertSee('id="tpl-no-memberships"', false)
            ->assertSee('id="tpl-load-failed"', false);
    }

    public function test_campaign_registry_filters_by_room_and_shows_actions_by_status(): void
    {
        $root = $this->superadmin();
        $roomA = Room::create(['name' => 'Room A', 'slug' => 'room-a']);
        $roomB = Room::create(['name' => 'Room B', 'slug' => 'room-b']);
        $active = Campaign::create(['room_id' => $roomA->id, 'status' => CampaignStatus::Active, 'name' => 'Active lunch', 'restaurant' => 'Shop', 'type' => 'food', 'deadline_at' => now()->addHour()]);
        $draft = Campaign::create(['room_id' => $roomB->id, 'status' => CampaignStatus::Draft, 'name' => 'Draft tea', 'restaurant' => 'Shop', 'type' => 'drink', 'deadline_at' => now()->addHour()]);

        $this->actingAs($root, 'superadmin')->get('/superadmin/campaigns/page')
            ->assertOk()
            ->assertSee('data-action="force-close" data-campaign-id="'.$active->id.'"', false)
            ->assertSee('data-action="force-cancel" data-campaign-id="'.$draft->id.'"', false)
            ->assertDontSee('data-action="force-close" data-campaign-id="'.$draft->id.'"', false)
            ->assertSee('id="confirm-modal-description"', false);

        $this->actingAs($root, 'superadmin')->get('/superadmin/campaigns/page?room_id='.$roomB->id)
            ->assertOk()
            ->assertSee('Draft tea')
            ->assertDontSee('Active lunch');

        $this->actingAs($root, 'superadmin')->get('/superadmin/campaigns/page?room_id='.$roomA->id.'&status=cancelled')
            ->assertOk()
            ->assertSee(__('superadmin.campaigns.no_results_title'));
    }

    public function test_audit_page_lists_only_superadmin_logs_with_translated_events(): void
    {
        $root = $this->superadmin();
        $admin = Admin::create(['name' => 'Operator', 'email' => 'operator-audit@drinkflow.test', 'password' => 'password123', 'status' => 'active']);

        $this->actingAs($root, 'superadmin')->get('/superadmin/audit-logs/page')
            ->assertOk()->assertSee(__('superadmin.audit.no_events_title'));

        $log = fn (string $actorType, ?int $actorId, string $event) => AuditLog::create(['actor_type' => $actorType, 'actor_id' => $actorId, 'event' => $event, 'target_type' => 'campaign', 'target_id' => 1, 'created_at' => now()]);
        $log('superadmin', $root->id, 'campaign.force_closed');
        $log('admin', $admin->id, 'room.settings_updated');
        $log('user', 99, 'user.logged_in');
        $log('system', null, 'system.reset');

        // Only actions performed by superadmins are shown; room admins, users and the system are not.
        $this->actingAs($root, 'superadmin')->get('/superadmin/audit-logs/page')
            ->assertOk()
            ->assertSee(__('admin.audit_event_campaign_force_closed'))
            ->assertSee(__('admin.audit_target_campaign'))
            ->assertDontSee('room.settings_updated')
            ->assertDontSee('Operator')
            ->assertDontSee('user.logged_in')
            ->assertDontSee('system.reset');

        // An actor_type parameter cannot widen the list to other actors.
        $this->actingAs($root, 'superadmin')->get('/superadmin/audit-logs/page?actor_type=admin')
            ->assertOk()->assertDontSee('room.settings_updated')->assertSee('campaign.force_closed');

        $this->actingAs($root, 'superadmin')->get('/superadmin/audit-logs/page?event=room.settings_updated')
            ->assertOk()->assertSee(__('superadmin.audit.no_results_title'));
    }

    public function test_system_page_uses_reset_modal_and_empty_states(): void
    {
        $root = $this->superadmin();

        $this->actingAs($root, 'superadmin')->get('/superadmin/system/page')
            ->assertOk()
            ->assertSee('id="reset-system-modal"', false)
            ->assertSee('data-modal-open="reset-system-modal"', false)
            ->assertSee('id="maintenance-form" class="sa-health-list" data-no-loading', false)
            ->assertSee(__('superadmin.system.reset_phrase_label', ['phrase' => \App\Actions\Superadmin\ResetSystemAction::CONFIRMATION_PHRASE]))
            ->assertDontSee('id="settings-form"', false)
            ->assertSee(__('superadmin.common.loading_title'))
            ->assertDontSee('prompt(', false);
    }

    public function test_layout_confirms_logout_and_offers_sidebar_toggle(): void
    {
        $root = $this->superadmin();

        $this->actingAs($root, 'superadmin')->get('/superadmin/versions/page')
            ->assertOk()
            ->assertSee('id="superadmin-logout-modal"', false)
            ->assertSee('data-modal-open="superadmin-logout-modal"', false)
            ->assertSee('data-sidebar-toggle', false)
            ->assertSee(__('superadmin.layout.toggle_sidebar'))
            ->assertDontSee('id="top-socket-status"', false);
    }

    public function test_versions_page_uses_modal_editor_and_empty_states(): void
    {
        $root = $this->superadmin();

        $this->actingAs($root, 'superadmin')->get('/superadmin/versions/page')
            ->assertOk()
            ->assertSee('id="version-modal"', false)
            ->assertSee('id="version-md-upload"', false)
            ->assertSee('data-md-tab="preview"', false)
            ->assertSee(__('superadmin.versions.no_releases_title'))
            ->assertDontSee('prompt(', false);

        $this->actingAs($root, 'superadmin')->get('/superadmin/versions/page?q=missing')
            ->assertOk()
            ->assertSee(__('superadmin.versions.no_results_title'));

        \App\Models\Version::create(['version' => 'v9.9.9', 'title' => 'Big release', 'changelog' => '## Notes', 'release_date' => '2026-09-24', 'important' => true]);

        $this->actingAs($root, 'superadmin')->get('/superadmin/versions/page')
            ->assertOk()
            ->assertSee('data-action="edit-release"', false)
            ->assertSee('data-action="delete-release"', false)
            ->assertSee('2026-09-24');
    }

    public function test_version_changelog_preview_renders_safe_markdown(): void
    {
        $root = $this->superadmin();

        $html = $this->actingAs($root, 'superadmin')->postJson('/superadmin/versions/preview', [
            'changelog' => "## Features\n- **Fast**\n\n<script>alert(1)</script>\n\n[bad](javascript:alert(1))",
        ])->assertOk()->json('data.html');

        $this->assertStringContainsString('<h2>Features</h2>', $html);
        $this->assertStringContainsString('<strong>Fast</strong>', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('javascript:', $html);

        $this->actingAs($root, 'superadmin')->postJson('/superadmin/versions/preview', [
            'changelog' => str_repeat('a', \App\Http\Requests\VersionRequest::CHANGELOG_MAX_LENGTH + 1),
        ])->assertStatus(422);
    }

    public function test_superadmin_can_update_version_with_markdown_changelog(): void
    {
        $root = $this->superadmin();
        $version = \App\Models\Version::create(['version' => 'v1.0.0', 'title' => 'First']);

        $this->actingAs($root, 'superadmin')->putJson("/superadmin/versions/{$version->id}", [
            'version' => 'v1.0.1', 'title' => 'First fix', 'changelog' => "## Fixes\n- Bug", 'release_date' => '2026-09-24',
            'important' => false, 'force_refresh' => true,
        ])->assertOk()->assertJsonPath('data.version', 'v1.0.1');

        $this->assertDatabaseHas('versions', ['id' => $version->id, 'title' => 'First fix', 'changelog' => "## Fixes\n- Bug", 'force_refresh' => true]);
    }

    public function test_superadmin_page_routes_render_for_active_superadmin(): void
    {
        $root = $this->superadmin();
        // Profile pages check the record (and the scope) before rendering, so they need real ones.
        $room = Room::create(['name' => 'Page room', 'slug' => 'page-room', 'status' => 'active']);
        $admin = Admin::create(['name' => 'Page admin', 'email' => 'page-admin@drinkflow.test', 'password' => 'password123', 'status' => 'active']);

        foreach ([
            '/superadmin',
            '/superadmin/rooms/page',
            "/superadmin/rooms/{$room->slug}/page",
            '/superadmin/agents',
            "/superadmin/agents/{$admin->id}",
            '/superadmin/global-users/page',
            '/superadmin/global-users/1/page',
            '/superadmin/campaigns/page',
            '/superadmin/notifications/page',
            '/superadmin/system/page',
            '/superadmin/audit-logs/page',
            '/superadmin/security-events/page',
            '/superadmin/socket/page',
            '/superadmin/queue/page',
            '/superadmin/versions/page',
        ] as $uri) {
            $this->actingAs($root, 'superadmin')->get($uri)->assertOk();
        }
        // An unknown record is not found instead of an empty profile shell.
        $this->actingAs($root, 'superadmin')->get('/superadmin/rooms/missing-room/page')->assertNotFound();
        $this->actingAs($root, 'superadmin')->get('/superadmin/admins/999999/page')->assertNotFound();
    }
}
