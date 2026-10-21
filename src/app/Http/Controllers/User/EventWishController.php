<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoomEventWishRequest;
use App\Models\Room;
use App\Models\RoomUser;
use App\Services\Occasion\RoomEventWishWallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Wishes wall of the room event pages (birthday and occasions), used by the page script: list the wall, write a
 * wish, give a heart, delete one's own wish. The room middleware has checked that the viewer is an active member.
 */
class EventWishController extends Controller
{
    public function __construct(private readonly RoomEventWishWallService $wall) {}

    /**
     * Current wall of an event.
     *
     * @param Request $request Current request (room attributes set by the room middleware).
     * @param Room $room Room.
     * @param string $type `birthday` or a RoomOccasion value (constrained by the route).
     * @return JsonResponse Wishes, newest first.
     */
    public function index(Request $request, Room $room, string $type): JsonResponse
    {
        [$room, $member] = $this->context($request, $room);

        return response()->json(['wishes' => $this->wall->wall($room, $type, $member)]);
    }

    /**
     * Write a wish on the wall of an event being shown.
     *
     * @param StoreRoomEventWishRequest $request Validated wish.
     * @param Room $room Room.
     * @param string $type `birthday` or a RoomOccasion value (constrained by the route).
     * @return JsonResponse The wall with the new wish.
     */
    public function store(StoreRoomEventWishRequest $request, Room $room, string $type): JsonResponse
    {
        [$room, $member] = $this->context($request, $room);
        $this->wall->post($room, $member, $type, (string) $request->validated('message'), $request->validated('recipient'));

        return response()->json(['message' => __('occasions.wall.sent'), 'wishes' => $this->wall->wall($room, $type, $member)], 201);
    }

    /**
     * Give or take back a heart.
     *
     * @param Request $request Current request.
     * @param Room $room Room.
     * @param int $wish Wish id.
     * @return JsonResponse Heart state and count.
     */
    public function heart(Request $request, Room $room, int $wish): JsonResponse
    {
        [$room, $member] = $this->context($request, $room);

        return response()->json($this->wall->toggleHeart($room, $member, $wish));
    }

    /**
     * Delete one of the viewer's own wishes.
     *
     * @param Request $request Current request.
     * @param Room $room Room.
     * @param int $wish Wish id.
     * @return JsonResponse Confirmation.
     */
    public function destroy(Request $request, Room $room, int $wish): JsonResponse
    {
        [$room, $member] = $this->context($request, $room);
        $this->wall->delete($room, $member, $wish);

        return response()->json(['message' => __('occasions.wall.deleted')]);
    }

    /**
     * Room and member resolved by the room middleware.
     *
     * @return array{0: Room, 1: RoomUser}
     */
    private function context(Request $request, Room $room): array
    {
        $member = $request->attributes->get('room_user');
        abort_unless($member instanceof RoomUser, 403);

        return [$request->attributes->get('room') ?? $room, $member];
    }
}
