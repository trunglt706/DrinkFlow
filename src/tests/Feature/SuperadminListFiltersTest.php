<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CampaignStatus;
use App\Enums\FeedbackStatus;
use App\Enums\PermissionScope;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\Feedback;
use App\Models\GlobalUser;
use App\Models\PermissionRecord;
use App\Models\Room;
use App\Models\RoomUser;
use App\Models\Superadmin;
use App\Models\SuperadminAdmin;
use App\Models\SuperadminNotification;
use App\Models\SystemNotificationChannel;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Agent / date filters of the superadmin lists (global users, campaigns, feedback, versions) and the
 * channel test ping of the notifications page.
 */
class SuperadminListFiltersTest extends TestCase
{
    use RefreshDatabase;

    private Superadmin $owner;

    private Admin $alice;

    private Admin $bob;

    private Room $aliceRoom;

    private Room $bobRoom;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->createSuperadmin(['name' => 'Owner', 'email' => 'owner@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $this->alice = Admin::create(['name' => 'Alice Agent', 'email' => 'alice@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $this->bob = Admin::create(['name' => 'Bob Agent', 'email' => 'bob@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $this->aliceRoom = Room::create(['name' => 'Alice room', 'slug' => 'alice-room', 'status' => 'active', 'owner_admin_id' => $this->alice->id]);
        $this->bobRoom = Room::create(['name' => 'Bob room', 'slug' => 'bob-room', 'status' => 'active', 'owner_admin_id' => $this->bob->id]);
    }

    /**
     * Member of one room.
     *
     * @param Room $room Room joined.
     * @param string $name User name.
     * @return GlobalUser Member.
     */
    private function member(Room $room, string $name): GlobalUser
    {
        $user = GlobalUser::create(['name' => $name, 'normalized_name' => mb_strtoupper($name), 'email' => mb_strtolower(str_replace(' ', '.', $name)).'@example.test', 'status' => 'active']);
        RoomUser::create(['room_id' => $room->id, 'global_user_id' => $user->id, 'display_name' => $name, 'status' => 'active']);

        return $user;
    }

    /**
     * Superadmin managing Alice only, with the given permissions on that scope.
     *
     * @param array<int, string> $keys Permission keys.
     * @return Superadmin Scoped Superadmin.
     */
    private function managingAlice(array $keys): Superadmin
    {
        $superadmin = Superadmin::create(['name' => 'Scoped', 'email' => 'scoped@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        foreach ($keys as $key) {
            $superadmin->permissions()->attach(PermissionRecord::query()->where('key', $key)->value('id'), ['scope' => PermissionScope::Managed->value]);
        }
        SuperadminAdmin::query()->create(['superadmin_id' => $superadmin->id, 'admin_id' => $this->alice->id, 'is_primary' => true, 'assigned_at' => now()]);

        return $superadmin;
    }

    public function test_global_users_filter_by_the_agent_owning_their_rooms(): void
    {
        $this->member($this->aliceRoom, 'Alice Member');
        $this->member($this->bobRoom, 'Bob Member');

        $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.global-users.page', ['agent' => $this->alice->id]))
            ->assertOk()
            ->assertSee('Alice Member')
            ->assertDontSee('Bob Member')
            ->assertSee('<option value="'.$this->bob->id.'"', false);
    }

    public function test_agent_filter_only_offers_and_applies_agents_in_scope(): void
    {
        $this->member($this->aliceRoom, 'Alice Member');
        $this->member($this->bobRoom, 'Bob Member');
        $scoped = $this->managingAlice(['global_user.view', 'agent.view']);

        $this->actingAs($scoped, 'superadmin')->get(route('superadmin.global-users.page'))
            ->assertOk()
            ->assertSee('<option value="'.$this->alice->id.'"', false)
            ->assertDontSee('<option value="'.$this->bob->id.'"', false);
        // An Agent outside the scope is ignored rather than used to probe its rooms.
        $this->actingAs($scoped, 'superadmin')->get(route('superadmin.global-users.page', ['agent' => $this->bob->id]))
            ->assertOk()
            ->assertSee('Alice Member')
            ->assertSee('Bob Member');
    }

    public function test_agent_filter_is_hidden_without_the_agent_permission(): void
    {
        $scoped = $this->managingAlice(['global_user.view']);

        $this->actingAs($scoped, 'superadmin')->get(route('superadmin.global-users.page'))
            ->assertOk()
            ->assertDontSee(__('superadmin.common.all_agents'));
    }

    public function test_user_profile_memberships_have_no_devices_column(): void
    {
        $user = $this->member($this->aliceRoom, 'Alice Member');

        $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.global-users.detail.page', $user))
            ->assertOk()
            ->assertSee(__('superadmin.users.room_memberships'))
            ->assertSee('<tr><td colspan="3">', false);
    }

    public function test_campaigns_filter_by_agent_and_tag_rooms_with_their_owner(): void
    {
        Campaign::create(['room_id' => $this->aliceRoom->id, 'status' => CampaignStatus::Active, 'name' => 'Alice lunch', 'restaurant' => 'Shop', 'type' => 'food', 'deadline_at' => now()->addHour()]);
        Campaign::create(['room_id' => $this->bobRoom->id, 'status' => CampaignStatus::Active, 'name' => 'Bob tea', 'restaurant' => 'Shop', 'type' => 'drink', 'deadline_at' => now()->addHour()]);

        $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.campaigns.page', ['agent' => $this->alice->id]))
            ->assertOk()
            ->assertSee('Alice lunch')
            ->assertDontSee('Bob tea')
            // The Room select narrows itself to the chosen Agent (dependent-select.js).
            ->assertSee('data-depends-on="agent"', false)
            ->assertSee('data-parent="'.$this->alice->id.'"', false)
            ->assertSee('data-parent="'.$this->bob->id.'"', false);
    }

    public function test_feedbacks_filter_by_agent(): void
    {
        $aliceMember = $this->member($this->aliceRoom, 'Alice Member');
        $bobMember = $this->member($this->bobRoom, 'Bob Member');
        foreach ([[$aliceMember, 'Alice says hi'], [$bobMember, 'Bob says hi']] as [$user, $content]) {
            Feedback::forceCreate(['global_user_id' => $user->id, 'rating' => 4, 'subsystem' => 'all', 'content' => $content, 'status' => FeedbackStatus::Active->value, 'user_display_name' => $user->name]);
        }

        $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.feedbacks.page', ['status' => 'all', 'agent' => $this->bob->id]))
            ->assertOk()
            ->assertSee('Bob says hi')
            ->assertDontSee('Alice says hi');
    }

    public function test_versions_filter_by_release_date(): void
    {
        Version::create(['version' => 'v1.0.0', 'title' => 'Spring release', 'release_date' => '2026-03-10']);
        Version::create(['version' => 'v2.0.0', 'title' => 'Autumn release', 'release_date' => '2026-09-20']);

        $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.versions.page', ['date_from' => '2026-06-01']))
            ->assertOk()->assertSee('Autumn release')->assertDontSee('Spring release');
        $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.versions.page', ['date_to' => '2026-06-01']))
            ->assertOk()->assertSee('Spring release')->assertDontSee('Autumn release');
        $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.versions.page', ['date_from' => 'soon']))
            ->assertOk()->assertSee('Spring release')->assertSee('Autumn release');
    }

    public function test_notifications_page_filters_the_inbox_by_date_and_channels_by_type(): void
    {
        $old = SuperadminNotification::query()->create(['superadmin_id' => $this->owner->id, 'type' => 'test', 'title' => 'Old alert', 'body' => 'Older body']);
        // Read, so the header bell (unread only) does not list it on every page.
        $old->forceFill(['created_at' => now()->subDays(20), 'read_at' => now()->subDays(19)])->save();
        SuperadminNotification::query()->create(['superadmin_id' => $this->owner->id, 'type' => 'test', 'title' => 'Fresh alert', 'body' => 'Fresh body']);
        SystemNotificationChannel::create(['type' => 'slack', 'name' => 'Slack ops', 'status' => 'active', 'config_encrypted' => json_encode(['webhook_url' => 'https://hooks.slack.com/services/T/B/X'])]);
        SystemNotificationChannel::create(['type' => 'telegram', 'name' => 'Telegram ops', 'status' => 'active', 'config_encrypted' => json_encode(['bot_token' => 'x', 'chat_id' => '1'])]);

        // The inbox lists the signed-in Superadmin's own notifications (same source as the header bell).
        $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.notifications.page'))
            ->assertOk()->assertSee('Old alert')->assertSee('Fresh alert');
        $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.notifications.page', ['date_from' => now()->subDays(3)->toDateString()]))
            ->assertOk()->assertSee('Fresh alert')->assertDontSee('Old alert')
            ->assertSee('id="inbox-date-range"', false);
        $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.notifications.page', ['channel_type' => 'telegram']))
            ->assertOk()->assertSee('Telegram ops')->assertDontSee('Slack ops');
        // An unknown type is ignored.
        $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.notifications.page', ['channel_type' => 'pigeon']))
            ->assertOk()->assertSee('Telegram ops')->assertSee('Slack ops');
    }

    public function test_system_channels_offer_a_test_ping(): void
    {
        Http::fake(['*' => Http::response('ok')]);
        // Stored like the channel form does: the JSON configuration, encrypted by the model.
        $channel = SystemNotificationChannel::create([
            'type' => 'slack', 'name' => 'Ops alerts', 'status' => 'active',
            'config_encrypted' => json_encode(['webhook_url' => 'https://hooks.slack.com/services/T00/B00/X00'], JSON_THROW_ON_ERROR),
        ]);

        $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.notifications.page'))
            ->assertOk()
            ->assertSee('data-action="test-channel" data-channel-id="'.$channel->id.'"', false)
            ->assertSee(__('superadmin.notifications.test_ping'));
        $this->actingAs($this->owner, 'superadmin')->postJson(route('superadmin.notifications.test', $channel))
            ->assertOk()
            ->assertJsonPath('data.channel_id', $channel->id);
        Http::assertSentCount(1);

        // A viewer without settings.manage gets no test button.
        $viewer = $this->managingAlice(['settings.view']);
        $this->actingAs($viewer, 'superadmin')->get(route('superadmin.notifications.page'))
            ->assertOk()
            ->assertDontSee('data-action="test-channel"', false);
    }
}
