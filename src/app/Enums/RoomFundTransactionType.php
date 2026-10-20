<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Kind of a room fund ledger entry. The sign of the amount follows the kind, except for adjustments.
 */
enum RoomFundTransactionType: string
{
    /** Money paid into the fund (a member's contribution or other income); positive. */
    case Deposit = 'deposit';

    /** Money spent from the fund by the admin; negative. */
    case Expense = 'expense';

    /** A debt paid with the fund (payment method `room_fund`); negative. */
    case DebtPayment = 'debt_payment';

    /** Correction of an earlier mistake, positive or negative (entries are never edited or deleted). */
    case Adjustment = 'adjustment';

    /**
     * Kinds the admin records by hand on the fund page.
     *
     * @return list<string> Type values.
     */
    public static function manualValues(): array
    {
        return [self::Deposit->value, self::Expense->value, self::Adjustment->value];
    }
}
