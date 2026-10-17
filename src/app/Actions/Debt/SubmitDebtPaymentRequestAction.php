<?php

declare(strict_types=1);

namespace App\Actions\Debt;

use App\Enums\DebtStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Events\RoomRealtimeEvent;
use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\Debt;
use App\Models\Order;
use App\Models\Room;
use App\Models\RoomUser;
use App\Services\Audit\AuditService;
use App\Services\Debt\DebtPaymentRequestService;
use App\Services\Payment\ReceivingAccountResolver;
use App\Support\Helpers\FormatHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitDebtPaymentRequestAction
{
    public function __construct(
        private readonly DebtPaymentRequestService $requests,
        private readonly ReceivingAccountResolver $accounts
    ) {}

    /**
     * Bundle all of a member's eligible debts into one consolidated payment request awaiting approval.
     *
     * The amount is always recomputed from the debts locked inside the transaction; nothing sent by
     * the browser is trusted. The member row is locked first so concurrent submissions (double
     * clicks, two tabs) run one after the other: the second one sees the debts already bundled and
     * fails unless two new eligible debts exist.
     *
     * @param Room $room Current room.
     * @param RoomUser $roomUser Member submitting the request.
     * @param string|null $transferContent Bank transfer content the member used, defaults to the member code.
     * @return Debt The pending payment request with its children loaded.
     * @throws ValidationException When the member is not in the room, the room has no receiving account, fewer than two debts are eligible, or a debt was bundled concurrently.
     */
    public function execute(Room $room, RoomUser $roomUser, ?string $transferContent = null): Debt
    {
        if ($roomUser->room_id !== $room->id) {
            throw ValidationException::withMessages(['debt' => __('room.debts.unauthorized_debt_access')]);
        }

        // Pay-all is transferred to the room account; without one a "paid" report cannot be valid.
        $this->accounts->ensureConfigured($this->accounts->forRoom($room), 'debt');

        $request = DB::transaction(function () use ($room, $roomUser, $transferContent): Debt {
            RoomUser::query()->whereKey($roomUser->id)->lockForUpdate()->firstOrFail();

            $debts = $this->requests->eligibleDebtsQuery($room, $roomUser)->orderBy('id')->lockForUpdate()->get();
            if ($debts->count() < DebtPaymentRequestService::MIN_DEBTS) {
                throw ValidationException::withMessages(['debt' => __('room.debts.request_needs_two')]);
            }

            $total = (int) $debts->sum('remaining_amount');
            $requestedAt = now();
            $parent = Debt::create([
                'room_id' => $room->id,
                'campaign_id' => null,
                'room_user_id' => $roomUser->id,
                'original_amount' => $total,
                'sponsor_amount' => 0,
                'adjustment_amount' => 0,
                'paid_amount' => 0,
                'remaining_amount' => $total,
                'status' => DebtStatus::Pending,
                'payment_requested_at' => $requestedAt,
                'payment_content' => $transferContent ?: $roomUser->user_code,
            ]);

            $ids = $debts->pluck('id')->all();
            // A debt may come from a rejected request; it is re-linked only if that parent is still rejected.
            // Plain ID list instead of a subquery: MySQL rejects an UPDATE that selects from the same table.
            $rejectedParentIds = Debt::paymentRequests()
                ->whereIn('id', $debts->pluck('parent_id')->filter()->unique()->all())
                ->where('status', DebtStatus::Rejected->value)
                ->pluck('id')
                ->all();
            $linked = Debt::query()
                ->whereIn('id', $ids)
                ->where(static fn ($query) => $query->whereNull('parent_id')->orWhereIn('parent_id', $rejectedParentIds))
                ->update(['parent_id' => $parent->id]);
            if ($linked !== count($ids)) {
                throw ValidationException::withMessages(['debt' => __('room.debts.request_conflict')]);
            }

            Order::query()
                ->where('room_id', $room->id)
                ->where('room_user_id', $roomUser->id)
                ->whereIn('campaign_id', $debts->pluck('campaign_id')->all())
                ->where('status', '!=', OrderStatus::Cancelled->value)
                ->where('payment_status', PaymentStatus::Unpaid->value)
                ->update(['payment_status' => PaymentStatus::Pending->value]);

            app(AuditService::class)->record('debt.payment_request_submitted', 'debt', $parent->id, $room->id, [], [
                'status' => DebtStatus::Pending->value,
                'amount' => $total,
                'debt_ids' => $ids,
            ]);

            return $parent->load(['children' => fn ($query) => $query->orderBy('id')]);
        });

        $this->notifyAdmins($room, $roomUser, $request);

        return $request;
    }

    /**
     * Tell room admins (notification center and realtime channel) that a request awaits review.
     *
     * @param Room $room Current room.
     * @param RoomUser $roomUser Member who submitted the request.
     * @param Debt $request New payment request with children loaded.
     * @return void
     */
    private function notifyAdmins(Room $room, RoomUser $roomUser, Debt $request): void
    {
        $userName = $roomUser->globalUser?->name ?? $roomUser->display_name ?? ('User #'.$roomUser->id);
        $amount = (int) $request->original_amount;
        $debtIds = $request->children->pluck('id')->all();
        $body = __('admin.payment_request_submitted_body', [
            'user' => $userName,
            'amount' => FormatHelper::formatCurrency($amount),
            'count' => count($debtIds),
            'code' => $request->code,
        ]);

        $room->admins()->each(function (Admin $admin) use ($room, $request, $userName, $amount, $debtIds, $body): void {
            AdminNotification::create([
                'admin_id' => $admin->id,
                'room_id' => $room->id,
                'type' => 'debt.payment_submitted',
                'title' => __('admin.payment_request_submitted_title'),
                'body' => $body,
                'data' => [
                    'payment_request_id' => $request->id,
                    'debt_ids' => $debtIds,
                    'amount' => $amount,
                    'user_name' => $userName,
                ],
            ]);
        });

        RoomRealtimeEvent::dispatch('debt.payment_submitted', $room->id, [
            'payment_request_id' => $request->id,
            'debt_ids' => $debtIds,
            'room_id' => $room->id,
            'room_user_id' => $roomUser->id,
            'user_name' => $userName,
            'user_code' => $roomUser->user_code,
            'amount' => $amount,
            'status' => DebtStatus::Pending->value,
            'message' => $body,
        ]);
    }
}
