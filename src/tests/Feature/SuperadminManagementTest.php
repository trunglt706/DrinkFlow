<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\PermissionScope;
use App\Models\PermissionRecord;
use App\Models\Superadmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * T06: Superadmin CRUD, permission/scope assignment and the last-manager guard.
 */
class SuperadminManagementTest extends TestCase
{
    use RefreshDatabase;

    private Superadmin $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        $this->owner = $this->createSuperadmin(['name' => 'Owner', 'email' => 'owner@drinkflow.test', 'password' => 'password123', 'status' => 'active']);
    }

    /**
     * Superadmin holding only the given permissions.
     *
     * @param string $email Account email.
     * @param array<string, PermissionScope> $grants Permission key => scope.
     * @return Superadmin Superadmin.
     */
    private function superadminWith(string $email, array $grants = []): Superadmin
    {
        $superadmin = Superadmin::create(['name' => 'Member', 'email' => $email, 'password' => 'password123', 'status' => 'active']);
        foreach ($grants as $key => $scope) {
            $superadmin->permissions()->attach(PermissionRecord::query()->where('key', $key)->value('id'), ['scope' => $scope->value]);
        }

        return $superadmin;
    }

    public function test_list_and_detail_pages_render_and_menu_links_to_them(): void
    {
        $member = $this->superadminWith('member@drinkflow.test');
        $this->actingAs($this->owner, 'superadmin');

        $this->get(route('superadmin.superadmins.index'))->assertOk()
            ->assertSee($this->maskedEmail('member@drinkflow.test'))
            ->assertSee(__('superadmin.superadmins.title'));
        $this->get(route('superadmin.superadmins.index', ['q' => 'owner']))->assertOk()
            ->assertSee($this->maskedEmail('owner@drinkflow.test'))
            ->assertDontSee($this->maskedEmail('member@drinkflow.test'));
        $this->get(route('superadmin.superadmins.show', $member))->assertOk()
            ->assertSee(Permission::AgentView->value)
            ->assertSee(route('superadmin.superadmins.permissions', $member), false);
        $this->get(route('superadmin.dashboard'))->assertOk()
            ->assertSee(route('superadmin.superadmins.index'), false);
    }

    public function test_create_starts_without_permissions_and_is_audited(): void
    {
        $this->actingAs($this->owner, 'superadmin');

        $response = $this->post(route('superadmin.superadmins.store'), [
            'name' => 'New Ops', 'email' => 'NEW-ops@drinkflow.test', 'password' => 'password123', 'password_confirmation' => 'password123',
        ]);

        $created = Superadmin::query()->where('email', 'new-ops@drinkflow.test')->firstOrFail();
        $response->assertRedirect(route('superadmin.superadmins.show', $created));
        $this->assertSame('active', $created->getStatusValue());
        $this->assertSame(0, $created->permissions()->count());
        $this->assertTrue(Hash::check('password123', $created->password));
        $this->assertDatabaseHas('audit_logs', ['event' => 'superadmin.created', 'target_id' => $created->id]);
    }

    public function test_create_rejects_duplicate_email_and_short_password(): void
    {
        $this->actingAs($this->owner, 'superadmin');

        $this->postJson(route('superadmin.superadmins.store'), [
            'name' => 'Dup', 'email' => 'owner@drinkflow.test', 'password' => 'short', 'password_confirmation' => 'short',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
        $this->assertSame(1, Superadmin::query()->count());
    }

    public function test_update_keeps_password_when_blank_and_changes_it_when_given(): void
    {
        $member = $this->superadminWith('member@drinkflow.test');
        $this->actingAs($this->owner, 'superadmin');

        $this->put(route('superadmin.superadmins.update', $member), [
            'name' => 'Renamed', 'email' => 'member@drinkflow.test', 'status' => 'active', 'password' => '', 'password_confirmation' => '',
        ])->assertRedirect(route('superadmin.superadmins.show', $member));
        $this->assertSame('Renamed', $member->fresh()->name);
        $this->assertTrue(Hash::check('password123', $member->fresh()->password));

        $this->put(route('superadmin.superadmins.update', $member), [
            'name' => 'Renamed', 'email' => 'member@drinkflow.test', 'status' => 'suspended', 'password' => 'newpassword1', 'password_confirmation' => 'newpassword1',
        ])->assertRedirect();
        $this->assertSame('suspended', $member->fresh()->getStatusValue());
        $this->assertTrue(Hash::check('newpassword1', $member->fresh()->password));
    }

    public function test_permissions_are_synced_with_scopes_and_non_scoped_permissions_stay_all(): void
    {
        $member = $this->superadminWith('member@drinkflow.test', [Permission::AuditView->value => PermissionScope::All]);
        $this->actingAs($this->owner, 'superadmin');

        $this->put(route('superadmin.superadmins.permissions', $member), [
            'permissions' => [Permission::AgentView->value, Permission::SettingsView->value],
            'scopes' => [Permission::AgentView->value => 'managed', Permission::SettingsView->value => 'managed'],
        ])->assertRedirect(route('superadmin.superadmins.show', $member));

        $member = $member->fresh();
        $this->assertSame(PermissionScope::Managed, $member->scopeFor(Permission::AgentView));
        $this->assertSame(PermissionScope::All, $member->scopeFor(Permission::SettingsView));
        $this->assertFalse($member->hasPermission(Permission::AuditView));
        $this->assertDatabaseHas('audit_logs', ['event' => 'superadmin.permissions_updated', 'target_id' => $member->id]);

        // Unchecking everything submits no `permissions` field and removes every grant.
        $this->put(route('superadmin.superadmins.permissions', $member), [])->assertRedirect();
        $this->assertSame(0, $member->permissions()->count());
    }

    public function test_invalid_permission_or_scope_is_rejected(): void
    {
        $member = $this->superadminWith('member@drinkflow.test');
        $this->actingAs($this->owner, 'superadmin');

        $this->putJson(route('superadmin.superadmins.permissions', $member), ['permissions' => ['root.everything']])
            ->assertUnprocessable()->assertJsonValidationErrors(['permissions.0']);
        $this->putJson(route('superadmin.superadmins.permissions', $member), [
            'permissions' => [Permission::AgentView->value], 'scopes' => [Permission::AgentView->value => 'galaxy'],
        ])->assertUnprocessable();
        $this->assertSame(0, $member->permissions()->count());
    }

    public function test_last_manager_cannot_be_suspended_demoted_or_deleted(): void
    {
        $manager = $this->superadminWith('manager@drinkflow.test', [Permission::SuperadminManage->value => PermissionScope::All]);
        // Remove the owner so `$manager` is the only one left that can manage superadmins.
        $this->owner->permissions()->detach(PermissionRecord::query()->where('key', Permission::SuperadminManage->value)->value('id'));
        $this->actingAs($manager, 'superadmin');
        $other = $this->superadminWith('other@drinkflow.test', [Permission::SuperadminManage->value => PermissionScope::All]);
        $other->update(['status' => 'suspended']);

        // Own account: neither suspend nor delete.
        $this->put(route('superadmin.superadmins.update', $manager), [
            'name' => 'Manager', 'email' => 'manager@drinkflow.test', 'status' => 'suspended',
        ])->assertSessionHasErrors('superadmin');
        $this->delete(route('superadmin.superadmins.destroy', $manager))->assertSessionHasErrors('superadmin');
        // Removing the only active manager's `superadmin.manage` is refused as well.
        $this->put(route('superadmin.superadmins.permissions', $manager), ['permissions' => [Permission::AuditView->value]])
            ->assertSessionHasErrors('superadmin');

        $this->assertSame('active', $manager->fresh()->getStatusValue());
        $this->assertTrue($manager->fresh()->hasPermission(Permission::SuperadminManage));
    }

    public function test_a_manager_can_be_removed_while_another_active_manager_remains(): void
    {
        $member = $this->superadminWith('member@drinkflow.test', [Permission::SuperadminManage->value => PermissionScope::All]);
        $this->actingAs($this->owner, 'superadmin');

        $this->put(route('superadmin.superadmins.permissions', $member), ['permissions' => [Permission::AuditView->value]])
            ->assertSessionHasNoErrors();
        $this->assertFalse($member->fresh()->hasPermission(Permission::SuperadminManage));

        $this->delete(route('superadmin.superadmins.destroy', $member))->assertRedirect(route('superadmin.superadmins.index'));
        $this->assertDatabaseMissing('superadmins', ['id' => $member->id]);
        $this->assertDatabaseMissing('superadmin_permissions', ['superadmin_id' => $member->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'superadmin.deleted', 'target_id' => $member->id]);
    }

    public function test_view_permission_cannot_change_superadmins(): void
    {
        $viewer = $this->superadminWith('viewer@drinkflow.test', [Permission::SuperadminView->value => PermissionScope::All]);
        $this->actingAs($viewer, 'superadmin');

        $this->get(route('superadmin.superadmins.index'))->assertOk()->assertDontSee(__('superadmin.superadmins.create'));
        $this->get(route('superadmin.superadmins.show', $this->owner))->assertOk()->assertDontSee(__('superadmin.superadmins.save_permissions'));
        $this->post(route('superadmin.superadmins.store'), ['name' => 'X', 'email' => 'x@drinkflow.test', 'password' => 'password123', 'password_confirmation' => 'password123'])->assertForbidden();
        $this->put(route('superadmin.superadmins.permissions', $viewer), ['permissions' => Permission::values()])->assertForbidden();
        $this->delete(route('superadmin.superadmins.destroy', $this->owner))->assertForbidden();

        $this->assertSame(1, $viewer->permissions()->count());
        $this->assertDatabaseHas('superadmins', ['id' => $this->owner->id]);
    }

    public function test_pages_are_forbidden_without_superadmin_permission(): void
    {
        $this->actingAs($this->superadminWith('none@drinkflow.test'), 'superadmin');
        $this->get(route('superadmin.superadmins.index'))->assertForbidden();
        $this->get(route('superadmin.superadmins.show', $this->owner))->assertForbidden();
    }
}
