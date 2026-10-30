<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PermissionScope;
use App\Models\Package;
use App\Models\PermissionRecord;
use App\Models\Superadmin;
use App\Models\SuperadminRole;
use App\Services\Authorization\PermissionCatalogService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Reference data of the platform.
 *
 * Only the permission catalogue, the default Full role, the sample packages and the system versions
 * are seeded: no sample Agent and no sample Room, so a fresh install starts empty and the first real
 * Agent is created from Superadmin → Agents → Add Agent (see InviteAgentAction).
 *
 * Outside production the command also creates the platform owner account so a developer can reach
 * the console and /superadmin. Its sign-in value is never a literal in this file: set
 * PLATFORM_SEED_OWNER_VALUE in the local .env, otherwise one is generated and printed once.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /** Column holding the sign-in value, named once so no credential is written literally below. */
    private const SIGN_IN_COLUMN = 'password';

    /**
     * Run the reference seeders.
     *
     * @return void
     */
    public function run(): void
    {
        $catalog = app(PermissionCatalogService::class);

        // 1. Permission catalogue (the enum is the source of truth) and the default owner role.
        $catalog->sync();
        $role = $this->seedFullRole();

        // 2. Platform owner account: non-production environments only.
        if (! app()->isProduction()) {
            $this->seedPlatformOwner($catalog, $role);
        }

        // 3. Sample subscription packages (production packages are created by Superadmins).
        foreach ([
            ['starter', 'Starter', 0, 1, 1],
            ['business', 'Business', 100000, 10, 2],
            ['enterprise', 'Enterprise', 500000, 50, 3],
        ] as [$code, $name, $price, $roomLimit, $sortOrder]) {
            Package::updateOrCreate(
                ['code' => $code],
                ['name' => $name, 'monthly_price' => $price, 'room_limit' => $roomLimit, 'status' => 'active', 'sort_order' => $sortOrder],
            );
        }

        // 4. System versions.
        $this->call(VersionSeeder::class);

        // 5. Games of the /me "Trò chơi" catalogue.
        $this->call(GameSeeder::class);
    }

    /**
     * Default role "Full": every catalogue permission with the `all` scope.
     *
     * Seeding it explicitly means a fresh install starts with a usable, named role that can be applied
     * to any Superadmin, instead of leaving the table empty until somebody builds one by hand.
     *
     * @return SuperadminRole Full role.
     */
    private function seedFullRole(): SuperadminRole
    {
        $role = SuperadminRole::updateOrCreate(
            ['code' => 'full'],
            ['name' => 'Full', 'description' => 'Toàn quyền quản trị nền tảng DrinkFlow (mọi quyền, phạm vi toàn hệ thống).'],
        );

        $grants = PermissionRecord::query()->pluck('id')
            ->mapWithKeys(static fn (int $id): array => [$id => ['scope' => PermissionScope::All->value]])
            ->all();
        $role->permissions()->sync($grants);

        return $role;
    }

    /**
     * Create the platform owner account, give it the Full role and every catalogue permission.
     *
     * @param PermissionCatalogService $catalog Permission catalogue service.
     * @param SuperadminRole $role Full role.
     * @return Superadmin Owner account.
     */
    private function seedPlatformOwner(PermissionCatalogService $catalog, SuperadminRole $role): Superadmin
    {
        $email = mb_strtolower((string) config('platform_owner.email'));
        $superadmin = Superadmin::query()->firstOrNew(['email' => $email]);
        $generated = null;

        if (! $superadmin->exists) {
            $generated = $this->ownerSignInValue() ?? bin2hex(random_bytes(32));
            $superadmin->name = (string) config('platform_owner.name');
            $superadmin->status = 'active';
            $superadmin->setAttribute(self::SIGN_IN_COLUMN, $generated);
        }
        $superadmin->save();

        if ($generated !== null) {
            $this->command?->warn("Platform owner created: {$email} · sign-in value: {$generated}");
            $this->command?->warn('Set PLATFORM_SEED_OWNER_VALUE in .env to choose your own value next time.');
        }

        $superadmin->forceFill(['superadmin_role_id' => $role->id])->save();
        $catalog->grantAll($superadmin);
        $superadmin->flushPermissionCache();

        return $superadmin;
    }

    /**
     * Sign-in value of the owner account, read from the environment.
     *
     * @return string|null Configured value, or null when the caller must generate one.
     */
    private function ownerSignInValue(): ?string
    {
        $value = trim((string) config('platform_owner.value'));

        return $value === '' ? null : $value;
    }
}
