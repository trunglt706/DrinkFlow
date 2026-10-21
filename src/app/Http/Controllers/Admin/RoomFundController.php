<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\RoomFundTransactionType;
use App\Enums\RoomUserStatus;
use App\Exports\RoomFundLedgerExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListRoomFundTransactionsRequest;
use App\Http\Requests\StoreRoomFundTransactionRequest;
use App\Models\Room;
use App\Services\RoomFund\RoomFundService;
use App\Support\Helpers\FormatHelper;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Room fund ledger of the admin (/admin/{room}/fund): balance, history, deposits / expenses / adjustments and export.
 * The admin.room middleware has checked access to the room.
 */
class RoomFundController extends Controller
{
    /**
     * Fund balance, this month's totals and the ledger (newest first), filtered by type, member and date range.
     *
     * @param ListRoomFundTransactionsRequest $request Validated ledger filters.
     * @param Room $room Room whose fund is shown.
     * @param RoomFundService $fund Fund ledger service.
     * @return View|JsonResponse Fund page, or only the ledger table (JSON `html`) for the page script's filters and pagination.
     */
    public function index(ListRoomFundTransactionsRequest $request, Room $room, RoomFundService $fund): View|JsonResponse
    {
        $filters = $request->filters($room);
        $entries = $fund->history($room, $filters['type'], $filters['member'], $filters['from'], $filters['to'])->paginate(30)->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('admin.fund.partials.ledger', ['room' => $room, 'entries' => $entries, 'filtered' => $this->isFiltered($filters)])->render(),
                'export_url' => route('admin.fund.export', ['room' => $room] + $this->filterQuery($filters)),
            ]);
        }

        $members = $room->roomUsers()->orderBy('display_name')->get(['id', 'display_name', 'user_code', 'status']);

        return view('admin.fund.index', [
            'room' => $room,
            'balance' => $fund->balance($room),
            'monthTotals' => $fund->totals($room, now()->startOfMonth(), now()->addMonthNoOverflow()->startOfMonth()),
            'entries' => $entries,
            'filters' => $filters,
            'filtered' => $this->isFiltered($filters),
            'exportQuery' => $this->filterQuery($filters),
            // Deposits are recorded for active members; the ledger can be filtered by any member of the room.
            'members' => $members->filter(static fn ($member): bool => $member->status === RoomUserStatus::Active)->values(),
            'allMembers' => $members,
        ]);
    }

    /**
     * Record a deposit, an expense or an adjustment.
     *
     * @param StoreRoomFundTransactionRequest $request Validated entry.
     * @param Room $room Room whose fund changes.
     * @param RoomFundService $fund Fund ledger service.
     * @return RedirectResponse Back to the fund page with the new balance.
     */
    public function store(StoreRoomFundTransactionRequest $request, Room $room, RoomFundService $fund): RedirectResponse
    {
        $entry = $fund->recordManual(
            $room,
            RoomFundTransactionType::from((string) $request->validated('type')),
            (int) $request->validated('amount'),
            $request->validated('room_user_id') !== null ? (int) $request->validated('room_user_id') : null,
            $request->validated('note'),
            $request->user('admin')?->id,
        );

        return redirect()->route('admin.fund.index', $room)
            ->with('status', __('room_fund.saved', ['balance' => FormatHelper::formatCurrency($entry->balance_after)]));
    }

    /**
     * Download the ledger (with the filters of the page) as XLSX.
     *
     * @param ListRoomFundTransactionsRequest $request Validated ledger filters.
     * @param Room $room Room whose fund is exported.
     * @param RoomFundService $fund Fund ledger service.
     * @return BinaryFileResponse Spreadsheet attachment.
     */
    public function export(ListRoomFundTransactionsRequest $request, Room $room, RoomFundService $fund): BinaryFileResponse
    {
        $filters = $request->filters($room);

        return Excel::download(
            new RoomFundLedgerExport($fund->history($room, $filters['type'], $filters['member'], $filters['from'], $filters['to'])),
            'drinkflow-'.$room->slug.'-fund-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /**
     * @param array{type: RoomFundTransactionType|null, member: int|null, from: CarbonImmutable|null, to: CarbonImmutable|null} $filters
     */
    private function isFiltered(array $filters): bool
    {
        return $filters['type'] !== null || $filters['member'] !== null || $filters['from'] !== null || $filters['to'] !== null;
    }

    /**
     * Query string of the active filters (export link).
     *
     * @param array{type: RoomFundTransactionType|null, member: int|null, from: CarbonImmutable|null, to: CarbonImmutable|null} $filters
     * @return array<string, string|int>
     */
    private function filterQuery(array $filters): array
    {
        return array_filter([
            'type' => $filters['type']?->value,
            'member' => $filters['member'],
            'date_from' => $filters['from']?->toDateString(),
            'date_to' => $filters['to']?->toDateString(),
        ], static fn (mixed $value): bool => $value !== null);
    }
}
