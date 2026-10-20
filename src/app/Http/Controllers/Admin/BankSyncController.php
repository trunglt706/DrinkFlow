<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\BankTransactionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignBankTransactionRequest;
use App\Http\Requests\UpdateRoomBankIntegrationRequest;
use App\Models\BankTransaction;
use App\Models\Room;
use App\Services\BankSync\BankReconciliationService;
use App\Services\BankSync\RoomBankIntegrationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Automatic bank reconciliation of the admin (/admin/{room}/bank-sync): the SePay connection, received transfers and
 * the unmatched ones to assign or dismiss. The admin.room middleware has checked access to the room.
 */
class BankSyncController extends Controller
{
    /**
     * Connection settings and received transactions (newest first, optionally of one status).
     *
     * @param Request $request Current request (`status` filter).
     * @param Room $room Current room.
     * @param RoomBankIntegrationService $integrations Bank feed settings.
     * @return View Reconciliation page.
     */
    public function index(Request $request, Room $room, RoomBankIntegrationService $integrations): View
    {
        $status = BankTransactionStatus::tryFrom((string) $request->query('status', ''));
        $integration = $integrations->forRoom($room);

        return view('admin.bank-sync.index', [
            'room' => $room,
            'integration' => $integration,
            'webhookUrl' => $integrations->webhookUrl($integration),
            'status' => $status,
            'unmatchedCount' => BankTransaction::query()->where('room_id', $room->id)->where('status', BankTransactionStatus::Unmatched->value)->count(),
            'transactions' => BankTransaction::query()
                ->where('room_id', $room->id)
                ->when($status !== null, static fn ($query) => $query->where('status', $status->value))
                ->with(['debt.campaign', 'roomUser', 'resolver'])
                ->orderByDesc('id')
                ->paginate(30)
                ->withQueryString(),
        ]);
    }

    /**
     * Save the connection settings.
     *
     * @param UpdateRoomBankIntegrationRequest $request Validated settings.
     * @param Room $room Current room.
     * @param RoomBankIntegrationService $integrations Bank feed settings.
     * @return RedirectResponse Back to the page.
     */
    public function settings(UpdateRoomBankIntegrationRequest $request, Room $room, RoomBankIntegrationService $integrations): RedirectResponse
    {
        $integrations->update($room, $request->boolean('is_active'), $request->validated('api_key'), $request->boolean('regenerate_token'));

        return redirect()->route('admin.bank-sync.index', $room)->with('status', __('bank_sync.settings_saved'));
    }

    /**
     * Assign an unmatched transfer to a debt.
     *
     * @param AssignBankTransactionRequest $request Validated debt code.
     * @param Room $room Current room.
     * @param BankTransaction $transaction Transaction of the room.
     * @param BankReconciliationService $reconciliation Reconciliation service.
     * @return RedirectResponse Back to the page.
     */
    public function assign(AssignBankTransactionRequest $request, Room $room, BankTransaction $transaction, BankReconciliationService $reconciliation): RedirectResponse
    {
        abort_unless($transaction->room_id === $room->id, 404);
        $reconciliation->assign($transaction, $room, (string) $request->validated('debt_code'), $request->user('admin'));

        return back()->with('status', __('bank_sync.assigned'));
    }

    /**
     * Dismiss an unmatched transfer.
     *
     * @param Request $request Current request.
     * @param Room $room Current room.
     * @param BankTransaction $transaction Transaction of the room.
     * @param BankReconciliationService $reconciliation Reconciliation service.
     * @return RedirectResponse Back to the page.
     */
    public function ignore(Request $request, Room $room, BankTransaction $transaction, BankReconciliationService $reconciliation): RedirectResponse
    {
        abort_unless($transaction->room_id === $room->id, 404);
        $reconciliation->ignore($transaction, $room, $request->user('admin'));

        return back()->with('status', __('bank_sync.ignored'));
    }
}
