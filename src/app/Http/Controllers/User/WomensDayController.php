<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Room;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Stand-alone Vietnamese Women's Day page of a room (/rooms/{room}/20-10), opened from the 20/10 notification: a
 * rose bud that blooms on click and reveals a slideshow of wishes. The room middleware has checked that the viewer
 * is an active member of the room.
 */
class WomensDayController extends Controller
{
    /**
     * Show the greeting page.
     *
     * @param Request $request Current request (room attributes set by the room middleware).
     * @param Room $room Room sending the wishes.
     * @return View Greeting page.
     */
    public function show(Request $request, Room $room): View
    {
        return view('user.womens-day.show', [
            'room' => $request->attributes->get('room') ?? $room,
        ]);
    }
}
