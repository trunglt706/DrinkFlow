<?php

declare(strict_types=1);

namespace App\Http\Controllers\Superadmin;

use App\Actions\User\SetGlobalUserStatusAction;
use App\Enums\GlobalUserStatus;
use App\Enums\RoomUserStatus;
use App\Events\ForceReloadRequested;
use App\Events\RoomMembershipUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\SetStatusRequest;
use App\Models\GlobalUser;
use App\Models\RoomUser;
use App\Models\RoomUserDevice;
use App\Actions\Superadmin\DeleteGlobalUserAction;
use App\Actions\Superadmin\MergeGlobalUsersAction;
use App\Services\Auth\DeviceTrustService;
use App\Services\Audit\AuditService;
use App\Http\Requests\MergeGlobalUsersRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GlobalUserController extends Controller
{
    /**
     * Handle the index operation.
     * @param Request $request Parameter value.
     * @return JsonResponse Result of the operation.
     */
    public function index(Request $request): JsonResponse
    {
        $query = GlobalUser::query()->withCount('roomUsers')->with('oauthIdentities:id,global_user_id,provider,provider_user_id,provider_email,linked_at,last_login_at')->latest();
        if ($request->filled('q')) {
            $term = '%' . $request->string('q')->toString() . '%';
            $query->where(fn($q) => $q->where('name', 'like', $term)->orWhere('normalized_name', 'like', strtoupper($term))->orWhere('email', 'like', $term));
        }
        $status = GlobalUserStatus::tryFrom($request->string('status')->toString());
        // Soft-deleted accounts only show up when explicitly filtered for.
        $status !== null
            ? $query->where('status', $status->value)
            : $query->where('status', '!=', GlobalUserStatus::Deleted->value);
        return response()->json(['data' => $query->paginate(\App\Constants\Pagination::ADMIN_PER_PAGE)]);
    }

    /**
     * Handle the show operation.
     * @param GlobalUser $globalUser Parameter value.
     * @return JsonResponse Result of the operation.
     */
    public function show(GlobalUser $globalUser): JsonResponse
    {
        $globalUser->loadCount(['roomUsers', 'orders'])
            ->load(['oauthIdentities:id,global_user_id,provider,provider_user_id,provider_email,linked_at,last_login_at', 'roomUsers.room']);

        return response()->json(['data' => $globalUser]);
    }

    /**
     * Handle the status operation.
     * @param SetStatusRequest $request Parameter value.
     * @param GlobalUser $globalUser Parameter value.
     * @param SetGlobalUserStatusAction $action Parameter value.
     * @return JsonResponse Result of the operation.
     */
    public function status(SetStatusRequest $request, GlobalUser $globalUser, SetGlobalUserStatusAction $action): JsonResponse
    {
        $result = $action->execute($globalUser, $request->validated('status'));
        return response()->json(['data' => $result]);
    }

    /**
     * Handle the remove membership operation.
     * @param GlobalUser $globalUser Parameter value.
     * @param RoomUser $roomUser Parameter value.
     * @param AuditService $audit Parameter value.
     * @return JsonResponse Result of the operation.
     * @throws ValidationException If member has outstanding debts in the room.
     */
    public function removeMembership(GlobalUser $globalUser, RoomUser $roomUser, AuditService $audit): JsonResponse
    {
        abort_unless($roomUser->global_user_id === $globalUser->id, 404);

        if ($roomUser->hasOutstandingDebts()) {
            throw ValidationException::withMessages([
                'room_user' => __('admin.cannot_remove_member_with_outstanding_debt'),
            ]);
        }

        $before = ['status' => $roomUser->status?->value];
        DB::transaction(function () use ($roomUser): void {
            $roomUser->update(['status' => RoomUserStatus::Removed]);
            $roomUser->devices()->whereNull('revoked_at')->update(['revoked_at' => now()]);
        });
        $audit->record('room_user.membership_removed', 'room_user', $roomUser->id, $roomUser->room_id, $before, ['status' => RoomUserStatus::Removed->value]);
        RoomMembershipUpdated::dispatch($roomUser->fresh());

        return response()->json(['data' => ['removed' => true]]);
    }

    /**
     * Soft-delete a global user: the account is marked "deleted" and its room memberships removed,
     * while order and debt history is kept (see DeleteGlobalUserAction).
     *
     * @param GlobalUser $globalUser Target global user.
     * @param DeleteGlobalUserAction $action Soft-delete action (audit logged there).
     * @return JsonResponse Result of the operation.
     * @throws ValidationException If the user has outstanding debts or is already deleted.
     */
    public function destroy(GlobalUser $globalUser, DeleteGlobalUserAction $action): JsonResponse
    {
        $action->execute($globalUser);

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * Handle the revoke device operation.
     * @param GlobalUser $globalUser Parameter value.
     * @param RoomUser $roomUser Parameter value.
     * @param RoomUserDevice $device Parameter value.
     * @param DeviceTrustService $trust Parameter value.
     * @param AuditService $audit Parameter value.
     * @return JsonResponse Result of the operation.
     */
    public function revokeDevice(GlobalUser $globalUser, RoomUser $roomUser, RoomUserDevice $device, DeviceTrustService $trust, AuditService $audit): JsonResponse
    {
        abort_unless($roomUser->global_user_id === $globalUser->id && $device->room_user_id === $roomUser->id, 404);
        $trust->revoke($device);
        $audit->record('room_user.device_revoked', 'room_user_device', $device->id, $roomUser->room_id, [], ['revoked_at' => $device->fresh()->revoked_at]);
        return response()->json(['data' => ['revoked' => true]]);
    }

    /**
     * Handle the merge operation.
     * @param MergeGlobalUsersRequest $request Parameter value.
     * @param MergeGlobalUsersAction $action Parameter value.
     * @return JsonResponse Result of the operation.
     */
    public function merge(MergeGlobalUsersRequest $request, MergeGlobalUsersAction $action): JsonResponse
    {
        $data = $request->validated();
        $source = GlobalUser::findOrFail($data['source_id']);
        $target = GlobalUser::findOrFail($data['target_id']);
        $result = $action->execute($source, $target);
        return response()->json(['data' => $result]);
    }
}
