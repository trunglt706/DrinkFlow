<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Room;
use App\Support\Helpers\DateRangeHelper;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AuditController extends Controller
{
    /**
     * Handle the index operation.
     *
     * @param Request $request Parameter value.
     * @return JsonResponse Result of the operation.
     */
    public function index(Request $request): JsonResponse
    {
        $room = $request->attributes->get('room');
        $query = AuditLog::query()->where('room_id', $room->id)->latest('created_at');

        if ($request->filled('event')) {
            $query->where('event', $request->string('event')->toString());
        }

        if ($request->filled('target_type')) {
            $query->where('target_type', $request->string('target_type')->toString());
        }

        $dateFrom = DateRangeHelper::parseCarbonSafe((string) $request->input('date_from', $request->input('from')));
        $dateTo = DateRangeHelper::parseCarbonSafe((string) $request->input('date_to', $request->input('to')));

        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom->startOfDay());
        }

        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo->endOfDay());
        }

        return response()->json(['data' => $query->paginate(\App\Constants\Pagination::ADMIN_PER_PAGE)]);
    }

    /**
     * Display the standalone Audit Trail logs view.
     *
     * @param Request $request Incoming request.
     * @param Room $room Room entity.
     * @return View Blade view.
     */
    public function page(Request $request, Room $room): View
    {
        $query = AuditLog::query()
            ->where('room_id', $room->id)
            ->latest('created_at');

        if ($request->filled('event')) {
            $query->where('event', $request->string('event')->toString());
        }

        if ($request->filled('target_type')) {
            $query->where('target_type', $request->string('target_type')->toString());
        }

        if ($request->filled('actor')) {
            $actor = $request->string('actor')->toString();
            $query->where(function ($q) use ($actor) {
                $q->where('actor_type', 'like', "%{$actor}%");
                if (is_numeric($actor)) {
                    $q->orWhere('actor_id', (int) $actor);
                }
            });
        }

        // No date parameter at all (first visit) → last 7 days. An explicitly empty range ("Tất cả") shows everything.
        if (! $request->hasAny(['date_from', 'date_to', 'from', 'to'])) {
            [$defaultFrom, $defaultTo] = DateRangeHelper::lastDays();
            $dateFromStr = $defaultFrom;
            $dateToStr = $defaultTo;
            $dateFrom = DateRangeHelper::parseCarbonSafe($defaultFrom);
            $dateTo = DateRangeHelper::parseCarbonSafe($defaultTo);
        } else {
            $dateFromStr = (string) $request->input('date_from', $request->input('from', ''));
            $dateToStr = (string) $request->input('date_to', $request->input('to', ''));
            $dateFrom = DateRangeHelper::parseCarbonSafe($dateFromStr);
            $dateTo = DateRangeHelper::parseCarbonSafe($dateToStr);
        }

        if ($dateFrom) {
            $query->where('created_at', '>=', $dateFrom->startOfDay());
        }

        if ($dateTo) {
            $query->where('created_at', '<=', $dateTo->endOfDay());
        }

        $logs = $query->paginate(\App\Constants\Pagination::ADMIN_PER_PAGE)->withQueryString();

        $cachedFilterData = Cache::remember(
            "audit:room:{$room->id}:dropdown_filters",
            600,
            static function () use ($room): array {
                return [
                    'events' => AuditLog::where('room_id', $room->id)->distinct()->pluck('event')->filter()->values(),
                    'targetTypes' => AuditLog::where('room_id', $room->id)->distinct()->pluck('target_type')->filter()->values(),
                ];
            }
        );

        return view('admin.audit', [
            'room' => $room,
            'logs' => $logs,
            'events' => $cachedFilterData['events'],
            'targetTypes' => $cachedFilterData['targetTypes'],
            'filters' => [
                'event' => $request->input('event', ''),
                'target_type' => $request->input('target_type', ''),
                'actor' => $request->input('actor', ''),
                'date_from' => $dateFromStr,
                'date_to' => $dateToStr,
            ],
        ]);
    }
}
