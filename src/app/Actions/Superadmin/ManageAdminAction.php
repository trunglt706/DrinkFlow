<?php

declare(strict_types=1);

namespace App\Actions\Superadmin;

use App\Enums\AdminStatus;
use App\Models\Admin;
use App\Services\Audit\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Events\AdminRoomsUpdated;

class ManageAdminAction
{
    /**
     * Handle the create operation.
     * @param array $data Parameter value.
     * @return Admin Result of the operation.
     */
    public function create(array $data): Admin
    {
        return DB::transaction(function () use ($data): Admin {
            $admin = Admin::create($data);
            app(AuditService::class)->record('admin.created', 'admin', $admin->id, null, [], $admin->only(['name', 'email', 'status']));
            return $admin->fresh();
        });
    }

    /**
     * Handle the update operation.
     * @param Admin $admin Parameter value.
     * @param array $data Parameter value.
     * @return Admin Result of the operation.
     */
    public function update(Admin $admin, array $data): Admin
    {
        return DB::transaction(function () use ($admin, $data): Admin {
            $before = $admin->only(['name', 'email', 'status']);
            $admin->update($data);
            app(AuditService::class)->record('admin.updated', 'admin', $admin->id, null, $before, $admin->fresh()->only(array_keys($before)));
            return $admin->fresh();
        });
    }

    /**
     * Handle the set status operation.
     * @param Admin $admin Parameter value.
     * @param string $status Parameter value.
     * @return Admin Result of the operation.
     */
    public function setStatus(Admin $admin, string $status): Admin
    {
        return DB::transaction(function () use ($admin, $status): Admin {
            if (! in_array($status, AdminStatus::manageableValues(), true)) throw ValidationException::withMessages(['status' => __('superadmin.actions.invalid_admin_status')]);
            $before = ['status' => $admin->status];
            $admin->update(['status' => $status]);
            app(AuditService::class)->record('admin.status_updated', 'admin', $admin->id, null, $before, ['status' => $status]);
            return $admin->fresh();
        });
    }

    /**
     * Handle the reset password operation.
     * @param Admin $admin Parameter value.
     * @param string $password Parameter value.
     * @return void Result of the operation.
     */
    public function resetPassword(Admin $admin, string $password): void
    {
        $admin->update(['password' => $password]);
        app(AuditService::class)->record('admin.password_reset', 'admin', $admin->id);
    }

    /**
     * Handle the sync rooms operation.
     * @param Admin $admin Parameter value.
     * @param array $roomIds Parameter value.
     * @return Admin Result of the operation.
     */
    public function syncRooms(Admin $admin, array $roomIds): Admin
    {
        return DB::transaction(function () use ($admin, $roomIds): Admin {
            $before = $admin->rooms()->pluck('rooms.id')->sort()->values()->all();
            // Rooms the Agent owns stay accessible; ownership changes go through RoomOwnershipService.
            $admin->rooms()->sync(array_values(array_unique([...array_map('intval', $roomIds), ...$admin->ownedRooms()->pluck('id')->map(static fn ($id): int => (int) $id)->all()])));
            $after = $admin->rooms()->pluck('rooms.id')->sort()->values()->all();
            app(AuditService::class)->record('admin.rooms_updated', 'admin', $admin->id, null, ['room_ids' => $before], ['room_ids' => $after]);
            event(new AdminRoomsUpdated($admin->id, $after));
            return $admin->fresh('rooms');
        });
    }

    /**
     * Delete an Agent account and everything tied to it.
     *
     * Platform invoices and payments are financial records: an Agent that has any is refused (suspend it
     * instead), which also avoids the restricting foreign keys of those tables.
     *
     * @param Admin $admin Agent to delete.
     * @return void
     * @throws ValidationException When the Agent has billing history.
     */
    public function delete(Admin $admin): void
    {
        if ($admin->invoices()->exists() || $admin->platformPayments()->exists()) {
            throw ValidationException::withMessages(['admin' => __('platform.agents.delete_blocked_billing')]);
        }

        DB::transaction(function () use ($admin): void {
            app(AuditService::class)->record('admin.deleted', 'admin', $admin->id, null, $admin->only(['name', 'email', 'status']), [], [
                'room_ids' => $admin->rooms()->pluck('rooms.id')->all(),
            ]);
            $this->removeRelations($admin);
            $admin->delete();
        });
    }

    /**
     * Remove everything tied to an admin account before it is deleted.
     *
     * Done explicitly (not only through the foreign keys) so it also holds where FK enforcement is off:
     * - owned records are deleted: room assignments, audit-log links, notifications, crawler previews;
     * - "created / placed / updated by" references on shared data are cleared (the data itself is kept);
     * - audit logs and security events stay as history (actor_type = admin, actor_id = the deleted id).
     *
     * @param Admin $admin Admin being deleted.
     */
    private function removeRelations(Admin $admin): void
    {
        $admin->rooms()->detach();
        $admin->linkedAuditLogs()->detach();
        $admin->notifications()->delete();
        DB::table('crawler_previews')->where('admin_id', $admin->id)->delete();

        foreach ([
            'campaigns' => 'creator_admin_id',
            'orders' => 'placed_by_admin_id',
            'debt_adjustments' => 'admin_id',
            'debt_payments' => 'created_by_admin_id',
            'system_settings' => 'updated_by_admin_id',
            'versions' => 'created_by_admin_id',
        ] as $table => $column) {
            DB::table($table)->where($column, $admin->id)->update([$column => null]);
        }
    }
}
