<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\FeedbackStatus;
use App\Enums\Permission;
use App\Enums\PermissionScope;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\Feedback;
use App\Models\PermissionRecord;
use App\Models\Room;
use App\Models\Superadmin;
use App\Services\Audit\AuditService;
use App\Services\Guide\UserGuideService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Superadmin console: agent activity labels, room toggle confirmation, feedback edit/delete, permission
 * matrix toggles + catalog sync, handbook + back-to-top, audit actor filter and the compact health grid.
 */
class SuperadminConsoleImprovementsTest extends TestCase
{
    use RefreshDatabase;

    private Superadmin $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = $this->createSuperadmin(['name' => 'Owner', 'email' => 'owner@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
    }

    /**
     * @param array<string, PermissionScope> $grants Permission key => scope.
     */
    private function superadminWith(string $email, array $grants): Superadmin
    {
        $superadmin = Superadmin::create(['name' => 'SA '.$email, 'email' => $email, 'password' => 'password123', 'status' => 'active']);
        foreach ($grants as $key => $scope) {
            $superadmin->permissions()->attach(PermissionRecord::query()->where('key', $key)->value('id'), ['scope' => $scope->value]);
        }

        return $superadmin;
    }

    private function feedback(): Feedback
    {
        return Feedback::forceCreate(['rating' => 2, 'subsystem' => 'all', 'content' => 'Giao dien kho dung', 'status' => FeedbackStatus::Active->value, 'user_display_name' => 'Old name']);
    }

    public function test_agent_activity_shows_the_mapped_event_label_and_actor_name(): void
    {
        $agent = Admin::create(['name' => 'Agent A', 'email' => 'agent@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
        $this->actingAs($this->owner, 'superadmin');
        app(AuditService::class)->record('agent.activated', 'admin', $agent->id);

        $this->get(route('superadmin.agents.show', ['admin' => $agent->id, 'tab' => 'activity']))
            ->assertOk()
            ->assertSee(AuditLog::labelForEvent('agent.activated'))
            ->assertSee('data-activity-event="agent.activated"', false)
            ->assertSee('Owner');
        $this->assertNotSame('agent.activated', AuditLog::labelForEvent('agent.activated'));
    }

    public function test_room_toggle_is_wired_to_a_confirmation_modal(): void
    {
        $room = Room::create(['name' => 'Lobby', 'slug' => 'lobby', 'status' => 'active']);

        $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.rooms.detail.page', $room))
            ->assertOk()
            ->assertSee(__('superadmin.rooms.confirm_disable_room'), false)
            ->assertSee(__('superadmin.modals.confirm.room_disable'), false)
            ->assertSee(__('superadmin.modals.confirm.room_enable'), false);
    }

    public function test_superadmin_edits_a_feedback_and_the_change_is_audited(): void
    {
        $feedback = $this->feedback();

        $this->actingAs($this->owner, 'superadmin')->putJson(route('superadmin.feedbacks.update', $feedback), [
            'rating' => 4, 'subsystem' => 'room', 'content' => '  Giao diện khó dùng  ', 'user_display_name' => 'New name',
        ])->assertOk()->assertJsonPath('data.rating', 4);

        $feedback->refresh();
        $this->assertSame(4, $feedback->rating);
        $this->assertSame('room', $feedback->subsystem);
        $this->assertSame('Giao diện khó dùng', $feedback->content);
        $this->assertSame('New name', $feedback->user_display_name);
        $this->assertSame(FeedbackStatus::Active, $feedback->status);
        $log = AuditLog::query()->where('event', 'feedback.updated')->sole();
        $this->assertSame(2, $log->before_data['rating']);
        $this->assertSame(4, $log->after_data['rating']);
    }

    public function test_feedback_edit_is_validated(): void
    {
        $feedback = $this->feedback();

        $this->actingAs($this->owner, 'superadmin')->putJson(route('superadmin.feedbacks.update', $feedback), [
            'rating' => 9, 'subsystem' => 'unknown', 'content' => 'x',
        ])->assertUnprocessable()->assertJsonValidationErrors(['rating', 'subsystem', 'content']);
        $this->assertSame('Giao dien kho dung', $feedback->fresh()->content);
    }

    public function test_superadmin_deletes_a_feedback_and_the_page_offers_edit_and_delete(): void
    {
        $feedback = $this->feedback();

        $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.feedbacks.page', ['status' => 'all']))
            ->assertOk()
            ->assertSee('data-action="feedback-edit"', false)
            ->assertSee('data-action="feedback-delete"', false)
            ->assertSee('id="edit-feedback-modal"', false);

        $this->actingAs($this->owner, 'superadmin')->deleteJson(route('superadmin.feedbacks.destroy', $feedback))->assertOk();
        $this->assertDatabaseMissing('feedbacks', ['id' => $feedback->id]);
        $this->assertTrue(AuditLog::query()->where('event', 'feedback.deleted')->where('target_id', $feedback->id)->exists());
    }

    public function test_feedback_edit_and_delete_require_feedback_manage(): void
    {
        $feedback = $this->feedback();
        $viewer = $this->superadminWith('viewer@drinkflow.test', ['feedback.view' => PermissionScope::All]);

        $this->actingAs($viewer, 'superadmin')->putJson(route('superadmin.feedbacks.update', $feedback), [
            'rating' => 5, 'subsystem' => 'all', 'content' => 'Changed text',
        ])->assertForbidden();
        $this->actingAs($viewer, 'superadmin')->deleteJson(route('superadmin.feedbacks.destroy', $feedback))->assertForbidden();
        $this->assertDatabaseHas('feedbacks', ['id' => $feedback->id, 'content' => 'Giao dien kho dung']);
    }

    public function test_superadmin_page_puts_permissions_full_width_below_the_profile_with_toggles(): void
    {
        $other = $this->superadminWith('other@drinkflow.test', ['agent.view' => PermissionScope::All]);

        $html = $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.superadmins.show', $other))
            ->assertOk()
            ->assertSee('data-permission-matrix', false)
            ->assertSee('data-permission-toggle-all="1"', false)
            ->assertSee('data-permission-toggle-all="0"', false)
            ->assertSee('data-permission-group-toggle', false)
            ->assertSee(route('superadmin.permissions.sync'), false)
            ->assertDontSee('<div class="sa-split">', false)
            ->getContent();

        // The profile form comes before the permission section.
        $this->assertLessThan(strpos($html, 'data-superadmin-permissions'), strpos($html, route('superadmin.superadmins.update', $other)));
    }

    public function test_permission_sync_adds_missing_catalog_permissions_without_touching_grants(): void
    {
        $missing = Permission::FeedbackManage->value;
        PermissionRecord::query()->where('key', $missing)->delete();
        $grantsBefore = $this->owner->permissions()->count();

        $this->actingAs($this->owner, 'superadmin')->from(route('superadmin.superadmins.show', $this->owner))
            ->post(route('superadmin.permissions.sync'))
            ->assertRedirect(route('superadmin.superadmins.show', $this->owner))
            ->assertSessionHas('status', __('superadmin.permissions.synced_added', ['count' => 1, 'keys' => $missing]));

        $this->assertDatabaseHas('permissions', ['key' => $missing]);
        $this->assertSame($grantsBefore, $this->owner->permissions()->count());
        $this->assertTrue(AuditLog::query()->where('event', 'permissions.catalog_synced')->exists());

        $this->actingAs($this->owner, 'superadmin')->post(route('superadmin.permissions.sync'))
            ->assertSessionHas('status', __('superadmin.permissions.synced_none'));
    }

    public function test_permission_sync_requires_superadmin_manage(): void
    {
        $viewer = $this->superadminWith('viewer@drinkflow.test', ['superadmin.view' => PermissionScope::All]);

        $this->actingAs($viewer, 'superadmin')->post(route('superadmin.permissions.sync'))->assertForbidden();
    }

    public function test_handbook_is_private_and_linked_from_the_toolbar_with_a_back_to_top_button(): void
    {
        $articles = app(UserGuideService::class)->list(UserGuideService::AUDIENCE_SUPERADMIN);
        $this->assertNotEmpty($articles);
        $this->assertStringStartsWith(resource_path(), app(UserGuideService::class)->directory(UserGuideService::AUDIENCE_SUPERADMIN));

        $this->get(route('superadmin.guides.index'))->assertRedirect();

        $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.dashboard'))
            ->assertOk()
            ->assertSee(route('superadmin.guides.index'), false)
            ->assertSee('data-sa-go-top', false);
        $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.guides.index'))
            ->assertOk()
            ->assertSee($articles[0]['title'])
            ->assertSee(route('superadmin.guides.show', $articles[0]['slug']), false);
        $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.guides.show', '07-phan-quyen'))
            ->assertOk()
            ->assertSee('guide-article', false)
            ->assertSee(__('superadmin.permissions.sync'));
        $this->actingAs($this->owner, 'superadmin')->get('/superadmin/guides/README')->assertNotFound();
        $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.guides.show', 'missing-article'))->assertNotFound();
    }

    public function test_audit_log_can_be_filtered_by_superadmin_actor(): void
    {
        $other = $this->superadminWith('other@drinkflow.test', ['audit.view' => PermissionScope::All]);
        $this->actingAs($this->owner, 'superadmin');
        app(AuditService::class)->record('package.updated', 'package', 1);
        $this->actingAs($other, 'superadmin');
        app(AuditService::class)->record('package.created', 'package', 2);

        $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.audit.page', ['actor_id' => $other->id]))
            ->assertOk()
            ->assertSee('data-audit-actor-filter', false)
            ->assertSee(AuditLog::labelForEvent('package.created'))
            ->assertDontSee('<strong class="block">'.AuditLog::labelForEvent('package.updated').'</strong>', false);
    }

    public function test_system_health_uses_the_compact_grid(): void
    {
        $this->actingAs($this->owner, 'superadmin')->get(route('superadmin.system.page'))
            ->assertOk()
            ->assertSee('class="sa-health-list sa-health-grid"', false);
    }
}
