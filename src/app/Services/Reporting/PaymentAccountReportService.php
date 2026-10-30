<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\DebtStatus;
use App\Enums\OrderStatus;
use App\Models\Debt;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\Room;
use App\Support\Helpers\FormatHelper;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class PaymentAccountReportService
{
    /**
     * Summarise money flow per receiving payment account of a room.
     *
     * Orders and debts are attributed to the account selected on their campaign. Campaigns without a
     * selected account (or whose account was deleted) are grouped in one row with a null account id.
     * Consolidated payment requests (parent debt rows without a campaign) are never counted, so a debt
     * is not counted twice.
     *
     * @param Room $room Target room.
     * @param Carbon $from Inclusive period start (orders/debts created_at).
     * @param Carbon $to Inclusive period end (orders/debts created_at).
     * @return Collection<int, array{payment_account_id: ?int, bank_code: ?string, bank_name: ?string, account_number: ?string, account_name: ?string, order_count: int, total_received: int, total_outstanding: int}> Accounts with activity, highest received amount first.
     */
    public function build(Room $room, Carbon $from, Carbon $to): Collection
    {
        $orderCounts = Order::query()
            ->where('orders.room_id', $room->id)
            ->whereBetween('orders.created_at', [$from, $to])
            ->whereNotIn('orders.status', [OrderStatus::Cancelled->value])
            ->join('campaigns', 'campaigns.id', '=', 'orders.campaign_id')
            ->selectRaw('campaigns.payment_account_id, COUNT(orders.id) as order_count')
            ->groupBy('campaigns.payment_account_id')
            ->pluck('order_count', 'payment_account_id');

        $debtTotals = Debt::query()
            ->where('debts.room_id', $room->id)
            ->whereBetween('debts.created_at', [$from, $to])
            ->join('campaigns', 'campaigns.id', '=', 'debts.campaign_id')
            ->selectRaw('
                campaigns.payment_account_id,
                SUM(debts.paid_amount) as total_received,
                SUM(CASE WHEN debts.status IN (?, ?, ?) THEN debts.remaining_amount ELSE 0 END) as total_outstanding
            ', DebtStatus::outstandingValues())
            ->groupBy('campaigns.payment_account_id')
            ->get()
            ->keyBy(fn (Debt $row): string => (string) $row->payment_account_id);

        // pluck() keys a null account id as ''; keyBy above matches that.
        $accountKeys = collect($orderCounts->keys())->merge($debtTotals->keys())->map(fn ($key): string => (string) $key)->unique();
        if ($accountKeys->isEmpty()) {
            return collect();
        }

        $accounts = PaymentAccount::query()
            ->where('room_id', $room->id)
            ->whereIn('id', $accountKeys->filter()->map(fn (string $key): int => (int) $key)->values())
            ->get()
            ->keyBy('id');

        return $accountKeys
            ->map(function (string $key) use ($accounts, $orderCounts, $debtTotals): array {
                $account = $key !== '' ? $accounts->get((int) $key) : null;
                $debt = $debtTotals->get($key);

                return [
                    'payment_account_id' => $account?->id,
                    'bank_code' => $account?->bank_code,
                    'bank_name' => $account?->bank_name,
                    'account_number' => $account ? FormatHelper::mask((string) $account->getRawOriginal('account_number')) : null,
                    // Full number for the room's admin, revealed on demand by the show/hide toggle (exports keep the masked one).
                    'account_number_full' => $account ? (string) $account->getRawOriginal('account_number') : null,
                    'account_name' => $account?->account_name,
                    'order_count' => (int) ($orderCounts[$key] ?? 0),
                    'total_received' => (int) ($debt?->total_received ?? 0),
                    'total_outstanding' => (int) ($debt?->total_outstanding ?? 0),
                ];
            })
            // Unknown ids (account from another room) fold into the "no account" row via a null id.
            ->groupBy(fn (array $row): string => (string) $row['payment_account_id'])
            ->map(fn (Collection $rows): array => array_merge($rows->first(), [
                'order_count' => $rows->sum('order_count'),
                'total_received' => $rows->sum('total_received'),
                'total_outstanding' => $rows->sum('total_outstanding'),
            ]))
            ->sortByDesc('total_received')
            ->values();
    }
}
