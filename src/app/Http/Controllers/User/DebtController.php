<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Actions\Debt\ConfirmDebtPaymentAction;
use App\Actions\Debt\SubmitDebtPaymentRequestAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmDebtPaymentRequest;
use App\Http\Requests\SnoozeDebtReminderRequest;
use App\Services\Debt\DebtAutoReminderService;
use App\Services\Debt\DebtPaymentRequestService;
use App\Services\Debt\UserRoomDebtService;
use App\Support\Helpers\FormatHelper;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DebtController extends Controller
{
    /**
     * Hiển thị danh sách công nợ trong phòng hoặc trả về JSON API (/rooms/{slug}/debts).
     *
     * @param  \Illuminate\Http\Request  $request  Đối tượng HTTP Request hiện tại
     * @param  \App\Services\Debt\UserRoomDebtService  $service  Service xử lý công nợ phòng
     * @param  DebtPaymentRequestService  $requests  Consolidated payment request read service.
     * @param  DebtAutoReminderService  $reminders  Automatic reminder service (snooze state of each debt).
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Contracts\View\View  Phản hồi JSON hoặc Giao diện View
     */
    public function index(Request $request, UserRoomDebtService $service, DebtPaymentRequestService $requests, DebtAutoReminderService $reminders): JsonResponse|View
    {
        $room = $request->attributes->get('room');
        $roomUser = $request->attributes->get('room_user');
        $user = $request->attributes->get('global_user') ?? $request->user('web');

        if ($request->expectsJson()) {
            $debts = $service->queryVisibleDebts($room, $roomUser)->with('campaign.paymentAccount')->latest()->paginate(20);
            return response()->json(['data' => $debts]);
        }

        $data = $service->getDebtViewData($room, $roomUser, $user);

        return view('user.debts', array_merge($data, $requests->memberViewData($room, $roomUser, $data['debts']->getCollection()), [
            'reminderSnoozes' => $reminders->snoozeStates($data['ledgerDebts']->getCollection(), $room),
        ]));
    }

    /**
     * Submit a debt payment for admin approval.
     *
     * With a `debt_id` the single debt is confirmed; without it every eligible debt is bundled into
     * one consolidated payment request (at least two debts are required).
     *
     * @param ConfirmDebtPaymentRequest $request Validated member request.
     * @param ConfirmDebtPaymentAction $single Single-debt confirmation action.
     * @param SubmitDebtPaymentRequestAction $bundle Consolidated request action.
     * @return JsonResponse Success response.
     */
    public function confirmPayment(ConfirmDebtPaymentRequest $request, ConfirmDebtPaymentAction $single, SubmitDebtPaymentRequestAction $bundle): JsonResponse
    {
        $room = $request->attributes->get('room');
        $roomUser = $request->attributes->get('room_user');

        $debtId = $request->validated('debt_id');
        $content = $request->validated('transfer_content');
        $content = $content !== null && $content !== '' ? (string) $content : null;

        if ($debtId === null) {
            $paymentRequest = $bundle->execute($room, $roomUser, $content);

            return response()->json([
                'success' => true,
                'message' => __('room.debts.request_submitted', [
                    'code' => $paymentRequest->code,
                    'amount' => FormatHelper::formatCurrency((int) $paymentRequest->original_amount),
                ]),
                'payment_status' => 'pending',
                'payment_request_id' => $paymentRequest->id,
                'updated_count' => $paymentRequest->children->count(),
            ]);
        }

        $updatedDebts = $single->execute($room, $roomUser, (int) $debtId, $content);

        return response()->json([
            'success' => true,
            'message' => __('room.orders.payment_submitted_success'),
            'payment_status' => 'pending',
            'updated_count' => $updatedDebts->count(),
            'payment_confirmation' => [
                'requestedAt' => now()->format('d/m/Y H:i'),
                'content' => $content,
                'approvedBy' => null,
                'approvedAt' => null,
            ],
        ]);
    }

    /**
     * Postpone the automatic reminders of one of the member's outstanding debts.
     *
     * @param SnoozeDebtReminderRequest $request Validated member request.
     * @param DebtAutoReminderService $reminders Automatic reminder service.
     * @return JsonResponse Success response with the date reminders resume.
     */
    public function snoozeReminders(SnoozeDebtReminderRequest $request, DebtAutoReminderService $reminders): JsonResponse
    {
        $room = $request->attributes->get('room');
        $debt = $reminders->snooze($room, $request->attributes->get('room_user'), (int) $request->validated('debt_id'));
        $state = $reminders->snoozeStates([$debt], $room)[$debt->id] ?? ['until' => null, 'remaining' => 0];

        return response()->json([
            'success' => true,
            'message' => __('debt_reminder.snoozed', ['date' => $state['until']]),
            'snooze' => $state,
        ]);
    }
}
