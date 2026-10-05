<?php

declare(strict_types=1);

namespace App\Actions\Order;

use App\Actions\Debt\ApproveDebtPaymentAction;
use App\Enums\DebtStatus;
use App\Models\Campaign;
use App\Models\Debt;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class AdminApproveOrderPaymentAction
{
    public function __construct(
        private readonly ApproveDebtPaymentAction $approveDebtPaymentAction,
    ) {
    }

    /**
     * Confirm payment for a specific order by admin and approve/settle corresponding campaign debt.
     *
     * @param Order $order Order entity to confirm payment for.
     * @return Order Updated order entity.
     */
    public function execute(Order $order): Order
    {
        $debt = DB::transaction(function () use ($order): Debt {
            $lockedOrder = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();

            $existingDebt = Debt::query()
                ->where('campaign_id', $lockedOrder->campaign_id)
                ->where('room_user_id', $lockedOrder->room_user_id)
                ->lockForUpdate()
                ->first();

            if ($existingDebt !== null) {
                return $existingDebt;
            }

            return Debt::firstOrCreate(
                [
                    'campaign_id' => $lockedOrder->campaign_id,
                    'room_user_id' => $lockedOrder->room_user_id,
                ],
                [
                    'room_id' => $lockedOrder->room_id,
                    'original_amount' => (int) $lockedOrder->final_amount,
                    'sponsor_amount' => (int) $lockedOrder->sponsor_amount,
                    'sponsor_type' => $lockedOrder->campaign?->sponsor_type ?? Campaign::SPONSOR_TYPE_NONE,
                    'sponsor_description' => $lockedOrder->campaign?->sponsor_description,
                    'adjustment_amount' => 0,
                    'paid_amount' => 0,
                    'remaining_amount' => (int) $lockedOrder->final_amount,
                    'status' => DebtStatus::Pending,
                    'payment_content' => $lockedOrder->code,
                ]
            );
        });

        if ($debt->status !== DebtStatus::Paid || (int) $debt->remaining_amount > 0) {
            $this->approveDebtPaymentAction->execute($debt);
        }

        return $order->fresh();
    }
}
