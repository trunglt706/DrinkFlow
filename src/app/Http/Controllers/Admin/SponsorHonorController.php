<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RecalculateSponsorHonorsRequest;
use App\Models\Room;
use App\Services\Reporting\MonthlySponsorStatisticsService;
use Illuminate\Http\RedirectResponse;

class SponsorHonorController extends Controller
{
    /**
     * Rebuild the previous month's sponsor podium on demand (e.g. when the scheduled run found no data yet).
     *
     * @param RecalculateSponsorHonorsRequest $request Authorized request (rate limited per admin account).
     * @param Room $room Current room.
     * @param MonthlySponsorStatisticsService $statistics Monthly sponsor snapshot service.
     * @return RedirectResponse Back to the dashboard with the rebuild result.
     */
    public function recalculate(RecalculateSponsorHonorsRequest $request, Room $room, MonthlySponsorStatisticsService $statistics): RedirectResponse
    {
        $count = $statistics->rebuildPreviousMonth($room);

        return back()->with('sponsor_honors_status', $count > 0
            ? __('sponsor_honors.recalculated', ['count' => $count])
            : __('sponsor_honors.recalculated_empty'));
    }
}
