<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Exports\AdminReportExport;
use App\Http\Controllers\Controller;
use App\Models\Campaign;
use App\Models\Room;
use App\Services\Admin\AdminReportService;
use App\Services\Reporting\MonthlySponsorStatisticsService;
use App\Support\Helpers\DateRangeHelper;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    /**
     * Return JSON telemetry & aggregated report metrics for the room.
     *
     * @param Request $request Incoming HTTP request.
     * @param AdminReportService $reportService Report analytics service.
     * @return JsonResponse Report payload.
     */
    public function index(Request $request, AdminReportService $reportService): JsonResponse
    {
        /** @var Room $room */
        $room = $request->attributes->get('room');
        $metrics = $reportService->getReportMetrics($request, $room);

        return response()->json(['data' => $metrics]);
    }

    /**
     * Download every report tab for the selected period as an Excel statement.
     *
     * @param Request $request Incoming HTTP request (optional date_from / date_to filters).
     * @param AdminReportService $reportService Report analytics service.
     * @return BinaryFileResponse XLSX download.
     */
    public function export(Request $request, AdminReportService $reportService): BinaryFileResponse
    {
        /** @var Room $room */
        $room = $request->attributes->get('room');
        $metrics = $reportService->getReportMetrics($request, $room);

        return Excel::download(
            new AdminReportExport($metrics),
            'drinkflow-'.$room->slug.'-report-'.now()->format('Ymd').'.xlsx'
        );
    }

    /**
     * Display the standalone Reports & Deep Dive Analytics page.
     *
     * @param Request $request Incoming request.
     * @param Room $room Room entity.
     * @param AdminReportService $reportService Report analytics service.
     * @param MonthlySponsorStatisticsService $sponsorStatistics Monthly sponsor badges for the badges tab (?badge_year=YYYY).
     * @return View Blade view.
     */
    public function page(Request $request, Room $room, AdminReportService $reportService, MonthlySponsorStatisticsService $sponsorStatistics): View
    {
        // Badge tab year: a 4-digit year up to the current one; anything else falls back to last month's year.
        $defaultBadgeYear = (int) now()->startOfMonth()->subMonth()->format('Y');
        $badgeYear = filter_var($request->query('badge_year'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 2000, 'max_range' => (int) now()->format('Y')]]);

        $campaigns = Campaign::where('room_id', $room->id)->withCount('orders')->latest()->take(5)->get();
        $roomMembersCount = $room->roomUsers()->count();
        $stats = $reportService->getReportStats($request, $room);

        // First visit (no range in the URL): pre-fill the picker with the service's default, the last 7 days.
        [$dateFrom, $dateTo] = $request->hasAny(['date_from', 'date_to', 'from', 'to', 'period'])
            ? [(string) $request->input('date_from', ''), (string) $request->input('date_to', '')]
            : DateRangeHelper::lastDays();

        return view('admin.reports', [
            'room' => $room,
            'campaigns' => $campaigns,
            'roomMembersCount' => $roomMembersCount,
            'stats' => $stats,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'badgeReport' => $sponsorStatistics->yearlyBadgeReport($room, $badgeYear === false ? $defaultBadgeYear : $badgeYear),
            'activeReportTab' => $request->query('report_tab') === 'badges' ? 'badges' : 'campaigns',
        ]);
    }
}
