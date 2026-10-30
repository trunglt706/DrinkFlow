<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Superadmin permissions of the game catalogue (Content → Games): game.view and game.manage.
 *
 * Accounts and roles that hold every permission existing before this migration (full access, e.g. the "Full" role)
 * receive the new ones with the `all` scope, so a full-access Superadmin keeps full access. Others are granted by hand.
 * Idempotent: rows already added by `permissions:sync` are kept.
 */
return new class extends Migration
{
    /** @var array<int, string> */
    private const KEYS = ['game.view', 'game.manage'];

    public function up(): void
    {
        $now = now();
        $previousIds = DB::table('permissions')->whereNotIn('key', self::KEYS)->pluck('id');
        DB::table('permissions')->insertOrIgnore(array_map(static fn (string $key): array => [
            'key' => $key,
            'group' => 'game',
            'created_at' => $now,
            'updated_at' => $now,
        ], self::KEYS));
        $newIds = DB::table('permissions')->whereIn('key', self::KEYS)->pluck('id');
        if ($previousIds->isEmpty()) {
            return;
        }

        foreach (['superadmin_permissions' => 'superadmin_id', 'superadmin_role_permissions' => 'superadmin_role_id'] as $table => $owner) {
            $fullAccess = DB::table($table)
                ->whereIn('permission_id', $previousIds)
                ->groupBy($owner)
                ->havingRaw('COUNT(DISTINCT permission_id) = ?', [$previousIds->count()])
                ->pluck($owner);
            foreach ($fullAccess as $ownerId) {
                DB::table($table)->insertOrIgnore($newIds->map(static fn ($permissionId): array => [
                    $owner => $ownerId,
                    'permission_id' => $permissionId,
                    'scope' => 'all',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            }
        }
    }

    public function down(): void
    {
        // Assignments go with the permissions (cascade on delete).
        DB::table('permissions')->whereIn('key', self::KEYS)->delete();
    }
};
