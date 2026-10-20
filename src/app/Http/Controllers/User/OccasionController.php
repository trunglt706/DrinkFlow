<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Enums\RoomOccasion;
use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Services\Occasion\RoomEventService;
use App\Services\Occasion\RoomEventSettingsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Stand-alone occasion pages of a room (/rooms/{room}/1-1, /tet, /8-3, /20-10, /19-11, /25-12), opened from the
 * events banner and the greeting notification: each is reachable only while its occasion is shown (on the day, Tết
 * 7 days before and after), or at any date in the local environment. The room middleware has checked that the viewer
 * is an active member of the room.
 */
class OccasionController extends Controller
{
    /**
     * Show the page of an occasion being celebrated today.
     *
     * @param Request $request Current request (room attributes set by the room middleware).
     * @param Room $room Room celebrating.
     * @param string $occasion Occasion slug (constrained by the route).
     * @param RoomEventService $events Room events.
     * @return View Occasion page.
     */
    public function show(Request $request, Room $room, string $occasion, RoomEventService $events, RoomEventSettingsService $settings): View
    {
        $room = $request->attributes->get('room') ?? $room;
        $current = RoomOccasion::fromSlug($occasion);
        // Locally every occasion page opens whatever the date, to preview it.
        abort_if($current === null || (! app()->isLocal() && ! $events->isActive($room, $current)), 404);

        return view('user.occasions.'.str_replace('_', '-', $current->value), [
            'room' => $room,
            'occasion' => $current,
            'year' => $events->occurrence($current, $events->today($room))['date']->year,
            'audio' => $settings->forEvent($room, $current->value),
            'wishes' => $settings->wishes($room, $current->value),
        ]);
    }
}
