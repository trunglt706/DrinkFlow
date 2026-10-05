<?php

declare(strict_types=1);

namespace App\Services\Payment;

use App\Enums\OrderStatus;
use App\Enums\RoomUserStatus;
use App\Models\GlobalUser;
use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class UserPaymentsService
{
    /** @var list<string> */
    private const UNPAID_STATUSES = [OrderStatus::Submitted->value, OrderStatus::Confirmed->value];

    public function __construct(private readonly VietQrService $vietQr) {}

    /** @return array<string, mixed> */
    public function getPaymentsData(GlobalUser $user, Request $request): array
    {
        $breadcrumbs = [
            ['label' => __('global.rooms.breadcrumb_personal'), 'url' => route('user.me.dashboard')],
            ['label' => __('global.orders.payments_cta'), 'url' => route('user.me.payments')],
        ];
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $nextMonthStart = $monthStart->copy()->addMonth();
        $unpaidPlaceholders = implode(', ', array_fill(0, count(self::UNPAID_STATUSES), '?'));
        $metrics = $this->ownedOrders($user)
            ->selectRaw("COUNT(CASE WHEN status IN ({$unpaidPlaceholders}) THEN 1 END) AS unpaid_count", self::UNPAID_STATUSES)
            ->selectRaw("COALESCE(SUM(CASE WHEN status IN ({$unpaidPlaceholders}) THEN final_amount ELSE 0 END), 0) AS unpaid_amount", self::UNPAID_STATUSES)
            ->selectRaw('COUNT(CASE WHEN status = ? THEN 1 END) AS paid_count', [OrderStatus::Completed->value])
            ->selectRaw('COUNT(CASE WHEN status = ? AND created_at >= ? AND created_at < ? THEN 1 END) AS paid_month_count', [OrderStatus::Completed->value, $monthStart, $nextMonthStart])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? AND created_at >= ? AND created_at < ? THEN final_amount ELSE 0 END), 0) AS paid_month_amount', [OrderStatus::Completed->value, $monthStart, $nextMonthStart])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? AND created_at >= ? AND created_at < ? THEN sponsor_amount ELSE 0 END), 0) AS sponsor_month_amount', [OrderStatus::Completed->value, $monthStart, $nextMonthStart])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN final_amount ELSE 0 END), 0) AS paid_amount', [OrderStatus::Completed->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN sponsor_amount ELSE 0 END), 0) AS sponsor_amount', [OrderStatus::Completed->value])
            ->first();

        $unpaidCount = (int) ($metrics?->unpaid_count ?? 0);
        $totalUnpaidAmount = (int) ($metrics?->unpaid_amount ?? 0);
        $paidCount = (int) ($metrics?->paid_count ?? 0);
        $paidThisMonthCount = (int) ($metrics?->paid_month_count ?? 0);
        $paidThisMonthAmount = (int) ($metrics?->paid_month_amount ?? 0);
        $totalSponsorReceived = (int) ($metrics?->sponsor_month_amount ?? 0);
        if ($paidThisMonthCount === 0 && $paidCount > 0) {
            $paidThisMonthCount = $paidCount;
            $paidThisMonthAmount = (int) ($metrics?->paid_amount ?? 0);
        }
        if ($totalSponsorReceived === 0) {
            $totalSponsorReceived = (int) ($metrics?->sponsor_amount ?? 0);
        }

        $activeFilter = (string) $request->input('filter', 'all');
        $sort = (string) $request->input('sort', 'due_asc');
        $displayedOrders = $this->filteredOrdersQuery($user, $activeFilter, $sort)
            ->with(['room.paymentAccounts', 'campaign.paymentAccount', 'items'])
            ->paginate(15)
            ->withQueryString();

        $primaryRoom = $user->roomUsers()
            ->with('room.paymentAccounts')
            ->where('status', RoomUserStatus::Active->value)
            ->first()?->room;
        $defaultPayment = $primaryRoom?->paymentAccounts->firstWhere('is_default', true)
            ?? $primaryRoom?->paymentAccounts->first();
        $defaultBank = $defaultPayment ? [
            'bank_name' => $defaultPayment->bank_name,
            'bank_code' => $defaultPayment->bank_code,
            'account_number' => $defaultPayment->getRawOriginal('account_number'),
            'account_name' => $defaultPayment->account_name,
        ] : [
            'bank_name' => __('global.payments.no_bank_configured'),
            'bank_code' => '',
            'account_number' => '',
            'account_name' => '',
        ];

        $qrPayloads = [];
        foreach ($displayedOrders as $order) {
            if (! in_array($order->status->value, self::UNPAID_STATUSES, true)) {
                continue;
            }
            $account = $order->campaign?->paymentAccount
                ?? $order->room?->paymentAccounts?->firstWhere('is_default', true)
                ?? $order->room?->paymentAccounts?->first();
            if ($account) {
                $qrPayloads[$order->id] = $this->vietQr->generate($account, (int) $order->final_amount, (string) $order->code);
            }
        }

        return [
            'user' => $user,
            'displayedOrders' => $displayedOrders,
            'totalCount' => $displayedOrders->total(),
            'unpaidCount' => $unpaidCount,
            'paidCount' => $paidCount,
            'totalUnpaidAmount' => $totalUnpaidAmount,
            'paidThisMonthAmount' => $paidThisMonthAmount,
            'paidThisMonthCount' => $paidThisMonthCount,
            'totalSponsorReceived' => $totalSponsorReceived,
            'activeFilter' => $activeFilter,
            'sort' => $sort,
            'defaultBank' => $defaultBank,
            'qrPayloads' => $qrPayloads,
            'breadcrumbs' => $breadcrumbs,
        ];
    }

    /** @return Builder<Order> */
    public function exportQuery(GlobalUser $user, Request $request): Builder
    {
        return $this->filteredOrdersQuery(
            $user,
            (string) $request->input('filter', 'all'),
            (string) $request->input('sort', 'due_asc'),
        )->with(['room', 'campaign']);
    }

    /** @return Builder<Order> */
    private function ownedOrders(GlobalUser $user): Builder
    {
        return Order::query()->whereIn('room_user_id', $user->roomUsers()
            ->select('room_users.id')
            ->where('status', RoomUserStatus::Active->value));
    }

    /** @return Builder<Order> */
    private function filteredOrdersQuery(GlobalUser $user, string $filter, string $sort): Builder
    {
        $query = $this->ownedOrders($user);
        if ($filter === 'unpaid') {
            $query->whereIn('status', self::UNPAID_STATUSES);
        } elseif ($filter === 'paid') {
            $query->where('status', OrderStatus::Completed->value);
        } else {
            $query->whereIn('status', [...self::UNPAID_STATUSES, OrderStatus::Completed->value]);
        }

        return match ($sort) {
            'amount_desc' => $query->orderByDesc('final_amount')->orderByDesc('id'),
            'amount_asc' => $query->orderBy('final_amount')->orderByDesc('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };
    }
}
