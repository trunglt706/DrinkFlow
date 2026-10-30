<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\User\AdminAddRoomUserAction;
use App\Actions\User\RestoreRoomUserAction;
use App\Actions\User\SetRoomUserStatusAction;
use App\Enums\RoomUserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SetStatusRequest;
use App\Http\Requests\BulkRoomUserActionRequest;
use App\Http\Requests\StoreRoomUserRequest;
use App\Models\Room;
use App\Models\RoomUser;
use App\Models\RoomUserDevice;
use App\Services\Auth\DeviceTrustService;
use App\Services\Audit\AuditService;
use App\Services\Reporting\MonthlySponsorStatisticsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RoomUserController extends Controller
{
    /**
     * Handle the index operation.
     */
    public function index(Request $request, Room $room): JsonResponse
    {
        $query = $room->roomUsers()->with('globalUser')->withCount(['devices', 'orders', 'debts'])->latest();
        if ($request->filled('q')) {
            $term = trim($request->string('q')->toString());
            $query->where(function ($query) use ($term): void {
                $query->where('user_code', 'like', "%{$term}%")->orWhere('display_name', 'like', "%{$term}%")->orWhereHas('globalUser', fn ($global) => $global->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%"));
            });
        }
        if ($request->filled('status')) $query->where('status', $request->string('status')->toString());
        return response()->json(['data' => $query->paginate(\App\Constants\Pagination::ADMIN_PER_PAGE)]);
    }

    /**
     * Store and add a new or existing user to the room.
     *
     * @param StoreRoomUserRequest $request Validated user input.
     * @param Room $room Target room.
     * @param AdminAddRoomUserAction $action Action adding user.
     * @return JsonResponse Created room user response.
     */
    public function store(StoreRoomUserRequest $request, Room $room, AdminAddRoomUserAction $action): JsonResponse
    {
        $roomUser = $action->execute($room, $request->validated());

        return response()->json([
            'success' => true,
            'message' => __('admin.user_created_success'),
            'data' => $roomUser,
        ], 201);
    }

    /**
     * Display the standalone Room Users & Device Trust directory view.
     *
     * @param Request $request Incoming request.
     * @param Room $room Room entity.
     * @param \App\Services\Admin\AdminRoomUserService $userService Room user service.
     * @param MonthlySponsorStatisticsService $sponsorStatistics Previous month's sponsor ranks.
     * @param \App\Services\Birthday\RoomBirthdayService $birthdays Marks the members born this month.
     * @return View Blade view.
     */
    public function page(Request $request, Room $room, \App\Services\Admin\AdminRoomUserService $userService, MonthlySponsorStatisticsService $sponsorStatistics, \App\Services\Birthday\RoomBirthdayService $birthdays): View
    {
        $query = $room->roomUsers()
            ->with(['globalUser', 'devices'])
            ->withCount(['devices', 'orders', 'debts'])
            // Most recently active members first (never-active members, NULL, come last), then newest members.
            ->orderByDesc('last_active_at')
            ->latest()
            ->orderByDesc('id');

        $search = trim($request->string('q')->toString());
        if ($search !== '') {
            $normalizedSearch = mb_strtolower($search);
            $query->where(function ($userQuery) use ($normalizedSearch): void {
                $userQuery->whereRaw('LOWER(user_code) LIKE ?', ['%' . $normalizedSearch . '%'])
                    ->orWhereRaw('LOWER(display_name) LIKE ?', ['%' . $normalizedSearch . '%'])
                    ->orWhereHas('globalUser', function ($globalQuery) use ($normalizedSearch): void {
                        $globalQuery->whereRaw('LOWER(name) LIKE ?', ['%' . $normalizedSearch . '%'])
                            ->orWhereRaw('LOWER(email) LIKE ?', ['%' . $normalizedSearch . '%']);
                    });
            });
        }

        $status = $request->string('status')->toString();
        if ($status !== '' && $status !== 'all') {
            $query->where('status', $status);
        }

        $roomUsers = $query->paginate(\App\Constants\Pagination::ADMIN_PER_PAGE)->withQueryString();

        $metrics = $userService->getDirectoryMetrics($room);

        return view('admin.users', array_merge([
            'room' => $room,
            'roomUsers' => $roomUsers,
            'statusFilters' => collect(RoomUserStatus::cases())->map(static fn (RoomUserStatus $status): array => [
                'value' => $status->value,
                'label' => __('admin.status_'.$status->value),
            ])->values()->all(),
            'filters' => ['q' => $search, 'status' => $status !== '' ? $status : 'all'],
            'sponsorRanks' => $sponsorStatistics->previousMonthRanks($room),
            // Members born this month (room timezone) on this page: day/month only, shown on hover.
            'birthdayDates' => $roomUsers->getCollection()
                ->filter(static fn ($roomUser): bool => $birthdays->isInCurrentMonth($room, $roomUser->globalUser?->birthday))
                ->mapWithKeys(static fn ($roomUser): array => [$roomUser->id => $roomUser->globalUser->birthday->format('d/m')])
                ->all(),
        ], $metrics));
    }

    /**
     * Handle the show operation.
     */
    public function show(Room $room, RoomUser $roomUser, \App\Services\Admin\AdminRoomUserService $userService): JsonResponse
    {
        $this->assertRoom($room, $roomUser);
        return response()->json(['data' => $userService->formatUserDetail($roomUser)]);
    }

    /**
     * Handle the status operation.
     */
    public function status(SetStatusRequest $request, Room $room, RoomUser $roomUser, SetRoomUserStatusAction $action): JsonResponse
    {
        $this->assertRoom($room, $roomUser);
        return response()->json(['data' => $action->execute($roomUser, $request->validated('status'))]);
    }

    /**
     * Apply one status action to multiple members of the current room.
     *
     * @param BulkRoomUserActionRequest $request Validated bulk action request.
     * @param Room $room Current room.
     * @param SetRoomUserStatusAction $action Membership status action.
     * @return JsonResponse Bulk operation result.
     */
    public function bulkAction(BulkRoomUserActionRequest $request, Room $room, SetRoomUserStatusAction $action): JsonResponse
    {
        $status = (string) $request->validated('action');
        $roomUserIds = array_map('intval', $request->validated('room_user_ids'));
        $roomUsers = $room->roomUsers()->whereIn('id', $roomUserIds)->get();

        abort_unless($roomUsers->count() === count($roomUserIds), 422, __('admin.invalid_room_users'));

        DB::transaction(function () use ($roomUsers, $status, $action): void {
            foreach ($roomUsers as $roomUser) {
                $action->execute($roomUser, $status);
            }
        });

        return response()->json([
            'success' => true,
            'count' => $roomUsers->count(),
            'message' => __('admin.bulk_users_updated', ['count' => $roomUsers->count()]),
        ]);
    }

    /**
     * Soft-remove a user from the room while preserving membership history.
     *
     * @param Room $room Current room.
     * @param RoomUser $roomUser Membership to remove.
     * @param SetRoomUserStatusAction $action Membership status action.
     * @return JsonResponse Removal result.
     */
    public function destroy(Room $room, RoomUser $roomUser, SetRoomUserStatusAction $action): JsonResponse
    {
        $this->assertRoom($room, $roomUser);

        return response()->json(['data' => $action->execute($roomUser, \App\Enums\RoomUserStatus::Removed->value)]);
    }

    /**
     * Restore a removed member back to the room as an active member.
     *
     * @param Room $room Current room.
     * @param RoomUser $roomUser Removed membership to restore.
     * @param RestoreRoomUserAction $action Restore action.
     * @return JsonResponse Restored membership.
     */
    public function restore(Room $room, RoomUser $roomUser, RestoreRoomUserAction $action): JsonResponse
    {
        $this->assertRoom($room, $roomUser);

        return response()->json(['data' => $action->execute($roomUser)]);
    }

    /**
     * Handle the revoke device operation.
     */
    public function revokeDevice(Room $room, RoomUser $roomUser, RoomUserDevice $device, DeviceTrustService $trust, AuditService $audit): JsonResponse
    {
        $this->assertRoom($room, $roomUser);
        abort_unless($device->room_user_id === $roomUser->id, 404);
        $trust->revoke($device);
        $audit->record('room_user.device_revoked', 'room_user_device', $device->id, $roomUser->room_id, [], ['revoked_at' => $device->fresh()->revoked_at]);
        return response()->json(['data' => ['revoked' => true]]);
    }

    /**
     * Assert that the room user belongs to the current room.
     */
    private function assertRoom(Room $room, RoomUser $roomUser): void
    {
        abort_unless($roomUser->room_id === $room->id, 404);
    }
}
