<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomUser;
use App\Services\Reporting\MonthlySponsorStatisticsService;
use App\Services\Reporting\YearRecapService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Achievements of the viewer in a room (/rooms/{room}/achievements): the TOP 1-3 sponsor medals earned in each month
 * of the current year, the medal totals and a word of encouragement. The room middleware has checked that the viewer
 * is an active member of the room.
 */
class AchievementController extends Controller
{
    /**
     * Show the viewer's achievements of the current year.
     *
     * @param Request $request Current request (room attributes set by the room middleware).
     * @param Room $room Room.
     * @param MonthlySponsorStatisticsService $statistics Monthly sponsor statistics.
     * @param YearRecapService $recaps Year recaps (link to the latest one).
     * @return View Achievements page.
     */
    public function index(Request $request, Room $room, MonthlySponsorStatisticsService $statistics, YearRecapService $recaps): View
    {
        $room = $request->attributes->get('room') ?? $room;
        $roomUser = $request->attributes->get('room_user');
        abort_unless($roomUser instanceof RoomUser, 404);

        return view('user.achievements', [
            'room' => $room,
            'roomUser' => $roomUser,
            'achievements' => $statistics->memberYearAchievements($room, $roomUser, now()->year),
            // Link to the most recent year recap (this year during the recap season, otherwise last year).
            'recapYear' => $recaps->latestYear($room),
        ]);
    }
}
