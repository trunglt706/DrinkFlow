<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Actions\Room\SendRoomEventNotificationAction;
use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomUser;
use App\Services\Birthday\RoomBirthdayService;
use App\Services\Occasion\RoomEventSettingsService;
use App\Services\Occasion\RoomEventWishWallService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Stand-alone birthday celebration page of a room (/rooms/{room}/birthday), opened from the birthday banner and the
 * morning notification: it celebrates every member whose birthday is within the celebration window. The room
 * middleware has checked that the viewer is an active member of the room.
 */
class BirthdayController extends Controller
{
    /**
     * Show the celebration page while at least one member's birthday is within the window.
     *
     * @param Request $request Current request (room attributes set by the room middleware).
     * @param Room $room Room whose members are celebrated.
     * @param RoomBirthdayService $birthdays Birthday lookup.
     * @param RoomEventSettingsService $settings Music and sound settings of the page.
     * @param RoomEventWishWallService $wall Wishes wall of the page.
     * @return View Celebration page.
     */
    public function show(Request $request, Room $room, RoomBirthdayService $birthdays, RoomEventSettingsService $settings, RoomEventWishWallService $wall): View
    {
        $room = $request->attributes->get('room') ?? $room;
        $celebrants = $birthdays->celebrants($room);
        // Locally the page can be previewed without any birthday in the window, with a "Demo" celebrant.
        if ($celebrants === [] && app()->isLocal()) {
            $celebrants = [$birthdays->demoCelebrant($room)];
        }
        abort_if($celebrants === [], 404);
        $member = $request->attributes->get('room_user');

        return view('user.birthday.show', [
            'room' => $room,
            'celebrants' => $celebrants,
            'viewerCode' => (string) $member?->user_code,
            'audio' => $settings->forEvent($room, SendRoomEventNotificationAction::TYPE_BIRTHDAY),
            'wishes' => $settings->wishes($room, SendRoomEventNotificationAction::TYPE_BIRTHDAY),
            'wall' => $member instanceof RoomUser ? $wall->wall($room, SendRoomEventNotificationAction::TYPE_BIRTHDAY, $member) : null,
        ]);
    }
}
