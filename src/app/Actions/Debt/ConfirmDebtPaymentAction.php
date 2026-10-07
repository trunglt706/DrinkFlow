<?php

declare(strict_types=1);

namespace App\Actions\Debt;

use App\Enums\DebtStatus;
use App\Enums\PaymentStatus;
use App\Events\RoomRealtimeEvent;
use App\Models\Admin;
use App\Models\AdminNotification;
use App\Models\Debt;
use App\Models\Order;
use App\Models\Room;
use App\Models\RoomUser;
use App\Services\Payment\ReceivingAccountResolver;
use App\Support\Helpers\FormatHelper;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConfirmDebtPaymentAction
{
    public function __construct(private readonly ReceivingAccountResolver $accounts) {}

    /**
     * Mark one debt as pending admin confirmation and notify room admins.
     *
     * Paying several debts at once goes through SubmitDebtPaymentRequestAction instead. A debt
     * bundled into a pending or approved payment request cannot be confirmed on its own.
     *
     * @param Room $room Target room entity.
     * @param RoomUser $roomUser Room user who submitted the debt payment confirmation.
     * @param int $debtId Debt being paid.
     * @param ?string $transferContent Custom transfer content / reference text.
     * @return Collection<int, Debt> Updated debt entities.
     * @throws ValidationException If unauthorized, the debt is settled, already pending, bundled into a request, or has no receiving account.
     */
    public function execute(Room $room, RoomUser $roomUser, int $debtId, ?string $transferContent = null): Collection
    {
        if ($roomUser->room_id !== $room->id) {
            throw ValidationException::withMessages([
                'debt' => __('room.debts.unauthorized_debt_access'),
            ]);
        }

        $paymentRequestedAt = now();
        $fallbackContent = $transferContent ?: $roomUser->user_code;

        /** @var Collection<int, Debt> $updatedDebts */
        $updatedDebts = DB::transaction(function () use ($room, $roomUser, $debtId, $fallbackContent, $paymentRequestedAt): Collection {
            RoomUser::query()->whereKey($roomUser->id)->lockForUpdate()->firstOrFail();

            /** @var Collection<int, Debt> $debts */
            $debts = Debt::query()
                ->where('room_id', $room->id)
                ->where('room_user_id', $roomUser->id)
                ->whereKey($debtId)
                ->lockForUpdate()
                ->get();

            if ($debts->isEmpty()) {
                throw ValidationException::withMessages([
                    'debt' => __('room.debts.no_debts_to_confirm'),
                ]);
            }

            if ($debts->contains(static fn (Debt $debt): bool => $debt->isLockedByPaymentRequest())) {
                throw ValidationException::withMessages(['debt' => __('room.debts.request_locked')]);
            }

            if ($debts->contains(static fn (Debt $debt): bool => $debt->status === DebtStatus::Pending)) {
                throw ValidationException::withMessages([
                    'debt' => __('room.debts.payment_already_pending'),
                ]);
            }

            // Without a receiving account there is nowhere to transfer to, so a "paid" report cannot be valid.
            foreach ($debts as $debt) {
                $this->accounts->ensureConfigured($this->accounts->forDebt($debt, $room), 'debt');
            }

            foreach ($debts as $debt) {
                if ($debt->status !== DebtStatus::Paid) {
                    $debt->status = DebtStatus::Pending;
                    $debt->payment_requested_at = $paymentRequestedAt;
                    if (!empty($fallbackContent)) {
                        $debt->payment_content = $fallbackContent;
                    }
                    $debt->save();

                    // Synchronize matching campaign orders to pending payment status
                    if ($debt->campaign_id) {
                        Order::query()
                            ->where('campaign_id', $debt->campaign_id)
                            ->where('room_user_id', $roomUser->id)
                            ->where('payment_status', '!=', PaymentStatus::Paid)
                            ->update(['payment_status' => PaymentStatus::Pending->value]);
                    }
                }
            }

            return $debts;
        });

        $totalConfirmedAmount = (int) $updatedDebts->sum('remaining_amount');
        if ($totalConfirmedAmount === 0) {
            $totalConfirmedAmount = (int) $updatedDebts->sum('original_amount');
        }

        $userName = $roomUser->globalUser?->name ?? $roomUser->display_name ?? ('User #' . $roomUser->id);
        $amountFmt = FormatHelper::formatCurrency($totalConfirmedAmount);

        // Notify room admins via AdminNotification table
        $room->admins()->each(function (Admin $admin) use ($room, $userName, $amountFmt, $totalConfirmedAmount, $updatedDebts): void {
            AdminNotification::create([
                'admin_id' => $admin->id,
                'room_id' => $room->id,
                'type' => 'debt.payment_submitted',
                'title' => __('admin.payment_confirmation_request_title'),
                'body' => __('admin.debt_payment_confirmation_body', [
                    'name' => $userName,
                    'amount' => $amountFmt,
                    'count' => $updatedDebts->count(),
                ]),
                'data' => [
                    'debt_ids' => $updatedDebts->pluck('id')->all(),
                    'amount' => $totalConfirmedAmount,
                    'user_name' => $userName,
                ],
            ]);
        });

        // Broadcast realtime socket event to room channel
        RoomRealtimeEvent::dispatch('debt.payment_submitted', $room->id, [
            'debt_ids' => $updatedDebts->pluck('id')->all(),
            'room_id' => $room->id,
            'room_user_id' => $roomUser->id,
            'user_name' => $userName,
            'user_code' => $roomUser->room_user_code ?? $roomUser->user_code,
            'amount' => $totalConfirmedAmount,
            'status' => DebtStatus::Pending->value,
            'message' => __('admin.debt_payment_confirmation_message', [
                'name' => $userName,
                'amount' => $amountFmt,
            ]),
        ]);

        return $updatedDebts;
    }
}
