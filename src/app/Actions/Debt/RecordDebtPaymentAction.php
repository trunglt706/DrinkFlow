<?php

declare(strict_types=1);

namespace App\Actions\Debt;

use App\Enums\DebtStatus;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Services\Audit\AuditService;
use App\Services\Notification\UserNotificationService;
use App\Services\RoomFund\RoomFundService;
use App\Support\Helpers\FormatHelper;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RecordDebtPaymentAction
{
    /**
     * Record a debt payment against a room user's debt balance.
     *
     * @param Debt $debt Debt entity instance.
     * @param int $amount Paid amount in VND.
     * @param string $method Payment method ('cash', 'qr', 'transfer', 'room_fund').
     * @param ?string $reference External transaction reference or receipt note.
     * @return Debt Updated debt instance.
     * @throws ValidationException If payment amount is non-positive or exceeds balance.
     */
    public function execute(Debt $debt, int $amount, string $method, ?string $reference = null): Debt
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => __('admin.invalid_payment_amount'),
            ]);
        }
        $updated = DB::transaction(function () use ($debt, $amount, $method, $reference): Debt {
            $debt = Debt::whereKey($debt->id)->lockForUpdate()->firstOrFail();
            $before = $debt->remaining_amount;
            if ($amount > $before) {
                throw ValidationException::withMessages([
                    'amount' => __('admin.payment_exceeds_balance'),
                ]);
            }
            $payment = DebtPayment::create([
                'debt_id' => $debt->id,
                'amount' => $amount,
                'payment_method' => $method,
                'reference' => $reference,
                'created_by_admin_id' => request()->user('admin')?->id,
            ]);
            // Paid with the room fund: take it out of the fund ledger (rolls the payment back when the fund is short).
            app(RoomFundService::class)->chargeDebtPayment($payment, $debt);
            $debt->paid_amount += $amount;
            $debt->remaining_amount -= $amount;
            $debt->status = $debt->remaining_amount === 0 ? DebtStatus::Paid : DebtStatus::Partial;
            $debt->save();
            app(AuditService::class)->record(
                'debt.payment_recorded',
                'debt',
                $debt->id,
                $debt->room_id,
                ['remaining_amount' => $before],
                ['remaining_amount' => $debt->remaining_amount, 'paid_amount' => $debt->paid_amount]
            );

            return $debt->fresh(['roomUser']);
        });

        app(UserNotificationService::class)->toRoomUser(
            $updated->roomUser,
            'debt.updated',
            __('admin.payment_recorded_title'),
            __('admin.remaining_balance_prefix', ['amount' => FormatHelper::formatCurrency((int) $updated->remaining_amount)]),
            ['debt_id' => $updated->id, 'remaining_amount' => $updated->remaining_amount]
        );

        return $updated;
    }
}
