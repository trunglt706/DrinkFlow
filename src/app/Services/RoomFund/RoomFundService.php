<?php

declare(strict_types=1);

namespace App\Services\RoomFund;

use App\Enums\PaymentMethod;
use App\Enums\RoomFundTransactionType;
use App\Models\Debt;
use App\Models\DebtPayment;
use App\Models\Room;
use App\Models\RoomFundTransaction;
use App\Services\Audit\AuditService;
use App\Support\Helpers\FormatHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Room fund ledger: deposits, expenses, adjustments and debts paid with the fund.
 *
 * Every write locks the room row, reads the latest balance and appends one entry with the new balance, so concurrent
 * writes are serialized and the balance never goes below zero.
 */
class RoomFundService
{
    public function __construct(private readonly AuditService $audit)
    {
    }

    /**
     * Current balance of the room fund.
     *
     * @param Room $room Room whose fund is read.
     * @return int Balance in VND.
     */
    public function balance(Room $room): int
    {
        return (int) (RoomFundTransaction::query()->where('room_id', $room->id)->latest('id')->value('balance_after') ?? 0);
    }

    /**
     * Money in and out of the fund between two instants.
     *
     * @param Room $room Room whose fund is summarized.
     * @param Carbon $from Inclusive start.
     * @param Carbon $to Exclusive end.
     * @return array{in: int, out: int} Totals in VND (out is positive).
     */
    public function totals(Room $room, Carbon $from, Carbon $to): array
    {
        $row = RoomFundTransaction::query()
            ->where('room_id', $room->id)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->selectRaw('COALESCE(SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END), 0) AS money_in')
            ->selectRaw('COALESCE(SUM(CASE WHEN amount < 0 THEN -amount ELSE 0 END), 0) AS money_out')
            ->first();

        return ['in' => (int) ($row?->money_in ?? 0), 'out' => (int) ($row?->money_out ?? 0)];
    }

    /**
     * Ledger entries of the room, newest first, optionally of one type.
     *
     * @param Room $room Room whose ledger is listed.
     * @param RoomFundTransactionType|null $type Only this kind of entry.
     * @return Builder<RoomFundTransaction>
     */
    public function history(Room $room, ?RoomFundTransactionType $type = null): Builder
    {
        return RoomFundTransaction::query()
            ->where('room_id', $room->id)
            ->when($type !== null, static fn (Builder $query) => $query->where('type', $type->value))
            ->with(['roomUser', 'debt.campaign', 'creator'])
            ->orderByDesc('id');
    }

    /**
     * Record an entry made by hand on the fund page: a deposit, an expense or an adjustment.
     *
     * @param Room $room Room whose fund changes.
     * @param RoomFundTransactionType $type Deposit, Expense or Adjustment.
     * @param int $amount Positive amount; for an adjustment, signed (negative takes money out).
     * @param int|null $roomUserId Member who paid in the deposit, if any.
     * @param string|null $note Description of the entry.
     * @param int|null $adminId Admin recording it.
     * @return RoomFundTransaction Appended entry.
     *
     * @throws ValidationException When the amount is invalid or the balance would go below zero.
     */
    public function recordManual(Room $room, RoomFundTransactionType $type, int $amount, ?int $roomUserId, ?string $note, ?int $adminId): RoomFundTransaction
    {
        $signed = match ($type) {
            RoomFundTransactionType::Deposit => abs($amount),
            RoomFundTransactionType::Expense => -abs($amount),
            RoomFundTransactionType::Adjustment => $amount,
            RoomFundTransactionType::DebtPayment => throw ValidationException::withMessages(['type' => __('room_fund.invalid_type')]),
        };
        if ($signed === 0) {
            throw ValidationException::withMessages(['amount' => __('room_fund.invalid_amount')]);
        }

        $entry = DB::transaction(fn (): RoomFundTransaction => $this->append($room, $type, $signed, [
            'room_user_id' => $type === RoomFundTransactionType::Deposit ? $roomUserId : null,
            'note' => $note,
            'created_by_admin_id' => $adminId,
        ]));
        $this->audit->record('room_fund.'.$type->value, 'room_fund_transaction', $entry->id, $room->id, [], [
            'amount' => $entry->amount,
            'balance_after' => $entry->balance_after,
        ]);

        return $entry;
    }

    /**
     * Take a debt payment made with the fund out of the ledger. Must run inside the payment's transaction, so the
     * payment is rolled back when the fund cannot cover it. Payments with another method are ignored.
     *
     * @param DebtPayment $payment Payment just recorded.
     * @param Debt $debt Debt it pays.
     * @return RoomFundTransaction|null Appended entry, or null for a payment that is not paid with the fund.
     *
     * @throws ValidationException When the fund balance is lower than the payment.
     */
    public function chargeDebtPayment(DebtPayment $payment, Debt $debt): ?RoomFundTransaction
    {
        if ($payment->payment_method !== PaymentMethod::RoomFund->value) {
            return null;
        }

        return $this->append(Room::query()->findOrFail($debt->room_id), RoomFundTransactionType::DebtPayment, -(int) $payment->amount, [
            'room_user_id' => $debt->room_user_id,
            'debt_id' => $debt->id,
            'debt_payment_id' => $payment->id,
            'note' => $debt->code,
            'created_by_admin_id' => $payment->created_by_admin_id,
        ]);
    }

    /**
     * Append one entry while the room row is locked.
     *
     * @param array<string, mixed> $attributes Other columns of the entry.
     *
     * @throws ValidationException When the balance would go below zero.
     */
    private function append(Room $room, RoomFundTransactionType $type, int $amount, array $attributes): RoomFundTransaction
    {
        Room::query()->whereKey($room->id)->lockForUpdate()->value('id');
        $current = $this->balance($room);
        $balance = $current + $amount;
        if ($balance < 0) {
            throw ValidationException::withMessages(['amount' => __('room_fund.insufficient', ['balance' => FormatHelper::formatCurrency($current)])]);
        }

        return RoomFundTransaction::create($attributes + [
            'room_id' => $room->id,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $balance,
        ]);
    }
}
