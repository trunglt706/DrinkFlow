<?php

declare(strict_types=1);

namespace App\Actions\Debt;

use App\Enums\DebtStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\RoomRealtimeEvent;
use App\Models\Admin;
use App\Models\Debt;
use App\Models\Order;
use App\Models\Room;
use App\Services\Audit\AuditService;
use App\Services\Debt\DebtPaymentRequestService;
use App\Services\Notification\UserNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class RejectDebtPaymentRequestAction
{
    /**
     * Reject a pending consolidated payment request.
     *
     * No balance changes. The bundled debts keep their link to the rejected request for traceability
     * until the member bundles them again: meanwhile they are listed as ordinary debts, payable one by
     * one or through a new consolidated request (which moves them to that request).
     *
     * @param Room $room Room owning the request.
     * @param int $requestId Payment request (parent debt) ID.
     * @param Admin|null $admin Reviewing admin.
     * @param string $reason Reason shown to the member.
     * @return Debt The rejected request with its children loaded.
     * @throws ValidationException When the request was already reviewed.
     * @throws ConflictHttpException When the request no longer has its bundled debts.
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException When the request does not exist in the room.
     */
    public function execute(Room $room, int $requestId, ?Admin $admin, string $reason): Debt
    {
        $request = DB::transaction(function () use ($room, $requestId, $admin, $reason): Debt {
            $request = Debt::paymentRequests()->where('room_id', $room->id)->whereKey($requestId)->lockForUpdate()->firstOrFail();
            if ($request->status !== DebtStatus::Pending) {
                throw ValidationException::withMessages(['debt' => __('admin.payment_request_already_reviewed')]);
            }
            $children = Debt::query()->where('parent_id', $request->id)->orderBy('id')->lockForUpdate()->get();
            if ($children->count() < DebtPaymentRequestService::MIN_DEBTS) {
                throw new ConflictHttpException(__('admin.payment_request_missing_debts'));
            }

            $request->status = DebtStatus::Rejected;
            $request->reviewed_at = now();
            $request->reviewed_by_admin_id = $admin?->id;
            $request->review_reason = $reason;
            $request->save();

            // Orders were flagged "awaiting confirmation" when the request was submitted; they are unpaid again.
            foreach ($children as $child) {
                Order::query()
                    ->where('room_id', $child->room_id)
                    ->where('campaign_id', $child->campaign_id)
                    ->where('room_user_id', $child->room_user_id)
                    ->where('status', '!=', OrderStatus::Cancelled->value)
                    ->where('payment_status', PaymentStatus::Pending->value)
                    ->update(['payment_status' => PaymentStatus::Unpaid->value]);
            }

            app(AuditService::class)->record('debt.payment_request_rejected', 'debt', $request->id, $room->id,
                ['status' => DebtStatus::Pending->value],
                ['status' => DebtStatus::Rejected->value],
                ['reason' => $reason, 'debt_ids' => $children->pluck('id')->all()]
            );

            return $request->setRelation('children', $children);
        });

        $request->loadMissing('roomUser');
        if ($request->roomUser) {
            app(UserNotificationService::class)->toRoomUser(
                $request->roomUser,
                'debt.payment_rejected',
                __('room.debts.request_rejected_title'),
                __('room.debts.request_rejected_body', ['code' => $request->code, 'reason' => $reason]),
                ['payment_request_id' => $request->id, 'debt_ids' => $request->children->pluck('id')->all()]
            );
        }

        RoomRealtimeEvent::dispatch('debt.payment_rejected', $room->id, [
            'payment_request_id' => $request->id,
            'debt_ids' => $request->children->pluck('id')->all(),
            'room_user_id' => $request->room_user_id,
            'status' => DebtStatus::Rejected->value,
        ]);

        return $request;
    }
}
