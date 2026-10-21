<?php

declare(strict_types=1);

namespace App\Services\Debt;

use App\Enums\CampaignStatus;
use App\Enums\DebtStatus;
use App\Enums\OrderStatus;
use App\Enums\RoomStatus;
use App\Models\Debt;
use App\Models\GlobalUser;
use App\Models\PaymentAccount;
use App\Models\Room;
use App\Models\RoomUser;
use App\Services\Payment\ReceivingAccountResolver;
use App\Services\Payment\VietQrService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class UserRoomDebtService
{
    public function __construct(
        private readonly VietQrService $vietQr,
        private readonly ReceivingAccountResolver $accounts
    ) {}

    /**
     * Thu thập danh sách công nợ trong phòng, tính toán tổng nợ chưa trả và tạo payload VietQR chuyển khoản nhanh.
     *
     * @param  Room  $room  Đối tượng phòng hiện tại
     * @param  RoomUser  $roomUser  Thành viên phòng của người dùng hiện tại
     * @param  GlobalUser|null  $user  Tài khoản người dùng toàn hệ thống
     * @return array<string, mixed> Mảng dữ liệu cho View công nợ phòng
     */
    public function getDebtViewData(Room $room, RoomUser $roomUser, ?GlobalUser $user): array
    {
        $baseDebtsQuery = $this->queryVisibleDebts($room, $roomUser);
        $debts = (clone $baseDebtsQuery)->with('campaign.paymentAccount')->latest()->paginate(20);
        // Merged ledger: a debt bundled into a pending or approved payment request is revealed by
        // expanding the request, never as a row of its own. Debts of a rejected request are free again,
        // so they come back as ordinary rows with their own QR.
        // `debts` is kept unfiltered for the legacy markup until the page switches to the component.
        $ledgerDebts = (clone $baseDebtsQuery)
            ->notLockedByPaymentRequest()
            ->with('campaign.paymentAccount')
            ->latest()
            ->paginate(20);

        $unpaidDebts = (clone $baseDebtsQuery)
            ->whereIn('status', DebtStatus::outstandingValues())
            ->get();
        $totalUnpaidAmount = (int) $unpaidDebts->sum('remaining_amount');
        // Pay-all covers debts not frozen by a pending/approved payment request, so the debts of a
        // rejected request are counted again (same rule as DebtPaymentRequestService::eligibleDebtsQuery).
        $totalPayableAmount = (int) (clone $baseDebtsQuery)
            ->whereIn('status', [DebtStatus::Unpaid->value, DebtStatus::Partial->value])
            ->where('remaining_amount', '>', 0)
            ->notLockedByPaymentRequest()
            ->sum('remaining_amount');

        $paidThisMonth = (clone $baseDebtsQuery)
            ->where('status', DebtStatus::Paid->value)
            ->where('updated_at', '>=', now()->startOfMonth())
            ->where('updated_at', '<', now()->addMonthNoOverflow()->startOfMonth())
            ->selectRaw('COUNT(*) AS aggregate_count, COALESCE(SUM(paid_amount), 0) AS aggregate_amount')
            ->first();
        $totalPaidMonthAmount = (int) ($paidThisMonth?->aggregate_amount ?? 0);
        $totalPaidMonthCount = (int) ($paidThisMonth?->aggregate_count ?? 0);

        // Default Payment Account in this room for VietQR
        $paymentAccount = $this->accounts->forRoom($room);
        $vietqrData = null;
        $payerName = $roomUser->payerName();
        // Pay-all transfer content is the member's name plus a short member reference (TV{id}) that bank
        // reconciliation matches to this member.
        $payAllContent = $this->vietQr->payAllContent($payerName, (int) $roomUser->id);
        if ($paymentAccount && $totalPayableAmount > 0) {
            $transferContent = $payAllContent;
            $vietqrData = [
                'bank_code' => $paymentAccount->bank_code,
                'bank_name' => $paymentAccount->bank_name,
                'account_number' => $paymentAccount->account_number,
                'account_name' => $paymentAccount->account_name,
                'amount' => $totalPayableAmount,
                'transfer_content' => $transferContent,
                'payload' => $this->vietQr->generate($paymentAccount, $totalPayableAmount, $transferContent),
            ];
        }

        // QR payloads cover every visible debt, including the bundled ones that only appear inside
        // their payment request: a child of a rejected request is still payable on its own.
        $qrMaps = $this->qrMapsFor((clone $baseDebtsQuery)->with('campaign.paymentAccount')->get(), $paymentAccount, $payerName);
        $qrPayloads = $qrMaps['qrPayloads'];
        $qrAccounts = $qrMaps['qrAccounts'];
        $transferContents = $qrMaps['transferContents'];

        $activeCampaign = $room->campaigns()
            ->where('status', CampaignStatus::Active->value)
            ->where(static function (Builder $query): void {
                $query->whereNull('deadline')->orWhere('deadline', '>', now());
            })
            ->first();
        $userRooms = $user ? $user->rooms()->where('rooms.status', RoomStatus::Active)->get() : collect();
        $unreadCount = $user ? DB::table('user_notifications')->where('global_user_id', $user->id)->whereNull('read_at')->count() : 0;

        return [
            'room' => $room,
            'roomUser' => $roomUser,
            'user' => $user,
            'debts' => $debts,
            'ledgerDebts' => $ledgerDebts,
            'unpaidDebts' => $unpaidDebts,
            'totalUnpaidAmount' => $totalUnpaidAmount,
            'totalPayableAmount' => $totalPayableAmount,
            'totalPaidMonthAmount' => $totalPaidMonthAmount,
            'totalPaidMonthCount' => $totalPaidMonthCount,
            'totalSponsorAmount' => (int) (clone $baseDebtsQuery)->sum('sponsor_amount'),
            'vietqrData' => $vietqrData,
            'qrPayloads' => $qrPayloads,
            'qrAccounts' => $qrAccounts,
            'transferContents' => $transferContents,
            'payAllContent' => $payAllContent,
            'activeCampaign' => $activeCampaign ? [
                'name' => $activeCampaign->name,
                'time_remaining' => $activeCampaign->deadline ? ($activeCampaign->deadline->isFuture() ? $activeCampaign->deadline->diffForHumans(['parts' => 2, 'short' => true]) : '00:00') : '14:22',
            ] : null,
            'userRooms' => $userRooms,
            'unreadNotificationsCount' => $unreadCount,
        ];
    }

    /**
     * Build the VietQR payload, receiving account and transfer content of every debt.
     *
     * A campaign debt is paid to that campaign's own active account only; without one the QR modal
     * reports "not configured" instead of a QR for another account. Debts without a campaign use the
     * room account.
     *
     * @param  iterable<Debt>  $debts  Debts (top-level and bundled) to build QR data for.
     * @param  PaymentAccount|null  $roomAccount  Room-level fallback account.
     * @param  string  $payerName  Name used in the transfer content.
     * @return array{qrPayloads: array<int, string>, qrAccounts: array<int, array{bank_name: string, account_number: string, account_name: string}>, transferContents: array<int, string>}
     *                                                                                                                                                                                     VietQR payload, account details and transfer content keyed by debt ID.
     */
    private function qrMapsFor(iterable $debts, ?PaymentAccount $roomAccount, string $payerName): array
    {
        $qrPayloads = [];
        $qrAccounts = [];
        $transferContents = [];

        foreach ($debts as $debt) {
            $transferContents[$debt->id] = $this->vietQr->transferContent((string) $debt->code, $payerName);
            $account = $debt->campaign !== null ? $this->accounts->forCampaign($debt->campaign) : $roomAccount;

            if ($account) {
                $qrAccounts[$debt->id] = [
                    'bank_name' => $account->bank_name,
                    'account_number' => $account->account_number,
                    'account_name' => $account->account_name,
                ];
            }

            if ($account && (int) $debt->remaining_amount > 0) {
                $qrPayloads[$debt->id] = $this->vietQr->generate(
                    $account,
                    (int) $debt->remaining_amount,
                    $transferContents[$debt->id],
                );
            }
        }

        return [
            'qrPayloads' => $qrPayloads,
            'qrAccounts' => $qrAccounts,
            'transferContents' => $transferContents,
        ];
    }

    /**
     * Build the room-user debt query while excluding debts backed only by cancelled orders.
     *
     * A debt stays visible when the member has a live order in the campaign, or when the member
     * has no order there at all: that is a sponsor debt (the sponsor pays for orders placed by
     * others, including orders placed on the sponsor's behalf) and must not be hidden.
     *
     * @param  Room  $room  Current room.
     * @param  RoomUser  $roomUser  Current room member.
     * @return Builder<Debt> Visible debt query.
     */
    public function queryVisibleDebts(Room $room, RoomUser $roomUser): Builder
    {
        return Debt::query()
            ->where('room_id', $room->id)
            ->where('room_user_id', $roomUser->id)
            ->where(static function (Builder $query) use ($roomUser): void {
                $query
                    ->whereExists(static function (\Illuminate\Database\Query\Builder $orderQuery) use ($roomUser): void {
                        $orderQuery->select(DB::raw(1))
                            ->from('orders')
                            ->whereColumn('orders.campaign_id', 'debts.campaign_id')
                            ->where('orders.room_user_id', $roomUser->id)
                            ->where('orders.status', '!=', OrderStatus::Cancelled->value);
                    })
                    ->orWhereNotExists(static function (\Illuminate\Database\Query\Builder $orderQuery) use ($roomUser): void {
                        $orderQuery->select(DB::raw(1))
                            ->from('orders')
                            ->whereColumn('orders.campaign_id', 'debts.campaign_id')
                            ->where('orders.room_user_id', $roomUser->id);
                    });
            });
    }
}
