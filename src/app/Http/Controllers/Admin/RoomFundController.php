<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\RoomFundTransactionType;
use App\Enums\RoomUserStatus;
use App\Exports\RoomFundLedgerExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoomFundTransactionRequest;
use App\Models\Room;
use App\Services\RoomFund\RoomFundService;
use App\Support\Helpers\FormatHelper;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Room fund ledger of the admin (/admin/{room}/fund): balance, history, deposits / expenses / adjustments and export.
 * The admin.room middleware has checked access to the room.
 */
class RoomFundController extends Controller
{
    /**
     * Fund balance, this month's totals and the ledger (newest first, optionally of one type).
     *
     * @param Request $request Current request (`type` filter).
     * @param Room $room Room whose fund is shown.
     * @param RoomFundService $fund Fund ledger service.
     * @return View|JsonResponse Fund page, or only the ledger table (JSON `html`) for the page script's filter and pagination.
     */
    public function index(Request $request, Room $room, RoomFundService $fund): View|JsonResponse
    {
        $type = RoomFundTransactionType::tryFrom((string) $request->query('type', ''));
        $entries = $fund->history($room, $type)->paginate(30)->withQueryString();

        if ($request->expectsJson()) {
            return response()->json([
                'html' => view('admin.fund.partials.ledger', ['room' => $room, 'entries' => $entries, 'type' => $type])->render(),
                'export_url' => route('admin.fund.export', array_filter(['room' => $room, 'type' => $type?->value])),
            ]);
        }

        return view('admin.fund.index', [
            'room' => $room,
            'balance' => $fund->balance($room),
            'monthTotals' => $fund->totals($room, now()->startOfMonth(), now()->addMonthNoOverflow()->startOfMonth()),
            'entries' => $entries,
            'type' => $type,
            'members' => $room->roomUsers()->where('status', RoomUserStatus::Active->value)->orderBy('display_name')->get(['id', 'display_name', 'user_code']),
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
     * Download the whole ledger (or one type of entry) as XLSX.
     *
     * @param Request $request Current request (`type` filter).
     * @param Room $room Room whose fund is exported.
     * @param RoomFundService $fund Fund ledger service.
     * @return BinaryFileResponse Spreadsheet attachment.
     */
    public function export(Request $request, Room $room, RoomFundService $fund): BinaryFileResponse
    {
        $type = RoomFundTransactionType::tryFrom((string) $request->query('type', ''));

        return Excel::download(new RoomFundLedgerExport($fund->history($room, $type)), 'drinkflow-'.$room->slug.'-fund-'.now()->format('Y-m-d').'.xlsx');
    }
}
