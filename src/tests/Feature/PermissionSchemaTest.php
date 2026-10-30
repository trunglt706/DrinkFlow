<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Permission;
use App\Enums\PermissionScope;
use App\Models\PermissionRecord;
use App\Models\Superadmin;
use App\Services\Authorization\PermissionCatalogService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * T04: `permissions` catalog and `superadmin_permissions` assignments.
 */
class PermissionSchemaTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2026_09_30_030000_create_permissions_tables.php';

    /** Permission groups required by the SaaS extension plan. */
    private const REQUIRED_GROUPS = ['agent', 'package', 'subscription', 'revenue', 'debt', 'room', 'global_user', 'feedback', 'version', 'game', 'settings', 'security', 'audit', 'queue', 'superadmin'];

    /** Later migrations adding permissions to the catalog, run after MIGRATION. */
    private const FOLLOW_UP_MIGRATIONS = ['migrations/2026_10_30_110000_add_game_permissions.php'];

    private function superadmin(string $email = 'perm-root@drinkflow.test'): Superadmin
    {
        return Superadmin::create(['name' => 'Root', 'email' => $email, 'password' => 'password123', 'status' => 'active']);
    }

    public function test_catalog_covers_every_required_group_and_matches_the_database(): void
    {
        $groups = array_values(array_unique(array_map(static fn (Permission $p): string => $p->group(), Permission::cases())));
        $this->assertEqualsCanonicalizing(self::REQUIRED_GROUPS, $groups);

        // The migration snapshot equals the enum today; `permissions:sync` covers later additions.
        $this->assertEqualsCanonicalizing(Permission::values(), PermissionRecord::query()->pluck('key')->all());
        foreach (PermissionRecord::all() as $record) {
            $this->assertSame($record->permission()?->group(), $record->group);
        }
    }

    public function test_every_permission_group_and_scope_is_translated(): void
    {
        foreach (['vi', 'en', 'ja'] as $locale) {
            app()->setLocale($locale);
            foreach (Permission::cases() as $permission) {
                $this->assertStringNotContainsString('superadmin.permissions', $permission->label(), "{$locale}: {$permission->value}");
                $this->assertStringNotContainsString('superadmin.permissions', $permission->groupLabel(), "{$locale}: {$permission->group()}");
            }
            foreach (PermissionScope::cases() as $scope) {
                $this->assertStringNotContainsString('superadmin.permissions', __('superadmin.permissions.scopes.'.$scope->value));
            }
        }
    }

    public function test_only_agent_data_permissions_support_the_managed_scope(): void
    {
        $this->assertTrue(Permission::AgentView->supportsScope());
        $this->assertTrue(Permission::RevenueView->supportsScope());
        $this->assertTrue(Permission::RoomManage->supportsScope());
        $this->assertFalse(Permission::PackageManage->supportsScope());
        $this->assertFalse(Permission::SettingsManage->supportsScope());
        $this->assertFalse(Permission::SuperadminManage->supportsScope());
    }

    public function test_assignments_store_scope_and_grantor_with_integrity_constraints(): void
    {
        $owner = $this->superadmin();
        $operator = $this->superadmin('operator@drinkflow.test');
        $agentView = PermissionRecord::query()->where('key', Permission::AgentView->value)->sole();

        $operator->permissions()->attach($agentView->id, ['scope' => PermissionScope::Managed->value, 'granted_by_superadmin_id' => $owner->id]);
        $grant = $operator->permissions()->sole()->pivot;
        $this->assertSame(PermissionScope::Managed, $grant->scope);
        $this->assertSame($owner->id, (int) $grant->granted_by_superadmin_id);

        // One row per superadmin and permission.
        try {
            DB::table('superadmin_permissions')->insert(['superadmin_id' => $operator->id, 'permission_id' => $agentView->id, 'scope' => 'all']);
            $this->fail('Duplicate grant was accepted.');
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }

        // Deleting the grantor keeps the grant; deleting the holder removes it.
        $owner->delete();
        $this->assertNull(DB::table('superadmin_permissions')->where('superadmin_id', $operator->id)->value('granted_by_superadmin_id'));
        $operator->delete();
        $this->assertSame(0, DB::table('superadmin_permissions')->count());
        $this->assertSame(count(Permission::cases()), PermissionRecord::count());
    }

    public function test_permission_keys_are_unique(): void
    {
        $this->expectException(QueryException::class);
        PermissionRecord::create(['key' => Permission::AgentView->value, 'group' => 'agent']);
    }

    public function test_migration_grants_all_permissions_to_existing_superadmins(): void
    {
        $migration = require database_path(self::MIGRATION);
        $migration->down();
        $this->assertFalse(Schema::hasTable('permissions'));

        $existing = $this->superadmin('existing@drinkflow.test');
        $migration->up();
        // A full-access superadmin also receives the permissions added by later migrations.
        foreach (self::FOLLOW_UP_MIGRATIONS as $followUp) {
            (require database_path($followUp))->up();
        }

        $grants = DB::table('superadmin_permissions')->where('superadmin_id', $existing->id)->get();
        $this->assertCount(count(Permission::cases()), $grants);
        $this->assertSame(['all'], $grants->pluck('scope')->unique()->values()->all());
    }

    public function test_sync_adds_missing_permissions_and_prunes_stale_ones_only_on_request(): void
    {
        $catalog = app(PermissionCatalogService::class);
        $holder = $this->superadmin();
        PermissionRecord::query()->where('key', Permission::QueueManage->value)->delete();
        $stale = PermissionRecord::create(['key' => 'legacy.removed', 'group' => 'legacy']);
        $holder->permissions()->attach($stale->id, ['scope' => 'all']);

        $result = $catalog->sync();
        $this->assertSame([Permission::QueueManage->value], $result['added']);
        $this->assertSame(['legacy.removed'], $result['stale']);
        $this->assertTrue(PermissionRecord::query()->where('key', 'legacy.removed')->exists());
        $this->assertSame([], $catalog->sync()['added']);

        $this->artisan('permissions:sync', ['--prune' => true])->assertSuccessful();
        $this->assertFalse(PermissionRecord::query()->where('key', 'legacy.removed')->exists());
        $this->assertSame(0, $holder->permissions()->count());
    }

    public function test_grant_all_adds_missing_permissions_without_changing_existing_scopes(): void
    {
        $catalog = app(PermissionCatalogService::class);
        $superadmin = $this->superadmin();
        $roomView = PermissionRecord::query()->where('key', Permission::RoomView->value)->sole();
        $superadmin->permissions()->attach($roomView->id, ['scope' => PermissionScope::Managed->value]);

        $this->assertSame(count(Permission::cases()) - 1, $catalog->grantAll($superadmin));
        $this->assertSame(0, $catalog->grantAll($superadmin));
        $this->assertSame(PermissionScope::Managed, $superadmin->permissions()->where('permissions.id', $roomView->id)->sole()->pivot->scope);
        $this->assertSame(count(Permission::cases()), $superadmin->permissions()->count());
    }
}
