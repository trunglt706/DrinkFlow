<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomUser;
use App\Services\Reporting\YearRecapService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Year recap page of the viewer in a room (/rooms/{room}/recap/{year}): a stand-alone story of their year and a
 * shareable summary card. Past years open at any time, the current year from the start of the recap season. The
 * room middleware has checked that the viewer is an active member of the room.
 */
class YearRecapController extends Controller
{
    /**
     * Show the viewer's recap of a year.
     *
     * @param Request $request Current request (room attributes set by the room middleware).
     * @param Room $room Room.
     * @param int $year Year (constrained by the route).
     * @param YearRecapService $recaps Year recaps.
     * @return View Recap page (an empty state when the viewer ordered nothing that year).
     */
    public function show(Request $request, Room $room, int $year, YearRecapService $recaps): View
    {
        $room = $request->attributes->get('room') ?? $room;
        $member = $request->attributes->get('room_user');
        abort_unless($member instanceof RoomUser, 403);
        abort_unless($recaps->isAvailable($room, $year), 404);

        return view('user.recap', [
            'room' => $room,
            'member' => $member,
            'year' => $year,
            'recap' => $recaps->forMember($room, $member, $year),
        ]);
    }
}
