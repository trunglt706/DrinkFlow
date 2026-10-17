<?php

declare(strict_types=1);

namespace App\Services\Debt;

use App\Enums\DebtStatus;
use App\Models\Debt;
use App\Models\Room;
use App\Models\RoomUser;
use App\Support\Helpers\FormatHelper;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Read side of consolidated debt payment requests: which debts can be bundled, the member's
 * debt/credit summary, and display payloads for the member and admin pages.
 */
class DebtPaymentRequestService
{
    /** A consolidated request only exists for two or more debts; a single debt uses the single-payment flow. */
    public const MIN_DEBTS = 2;

    public function __construct(
        private readonly UserRoomDebtService $roomDebts,
        private readonly DebtCreditService $credit,
    ) {}

    /**
     * Query the member's campaign debts that can be bundled into a new request.
     *
     * Eligible debts are visible to the member, unpaid or partially paid, still owe money, have no
     * single-payment confirmation pending and are not frozen by a pending or approved request. Debts of
     * a rejected request are eligible again and move to the new request when bundled.
     *
     * @param Room $room Current room.
     * @param RoomUser $roomUser Room member.
     * @return Builder<Debt> Eligible debt query.
     */
    public function eligibleDebtsQuery(Room $room, RoomUser $roomUser): Builder
    {
        return $this->roomDebts->queryVisibleDebts($room, $roomUser)
            ->whereIn('status', [DebtStatus::Unpaid->value, DebtStatus::Partial->value])
            ->where('remaining_amount', '>', 0)
            ->notLockedByPaymentRequest();
    }

    /**
     * Summarize the member's debt, pending request and credit figures.
     *
     * @param Room $room Current room.
     * @param RoomUser $roomUser Room member.
     * @return array{total: int, pending: int, submittable: int, submittable_count: int, can_submit: bool, credit_enabled: bool, credit_limit: int, credit_available: int|null}
     *         Real outstanding debt, amount awaiting approval, amount that can still be requested, and credit left.
     */
    public function summary(Room $room, RoomUser $roomUser): array
    {
        $visible = $this->roomDebts->queryVisibleDebts($room, $roomUser);
        $eligible = $this->eligibleDebtsQuery($room, $roomUser)->get(['id', 'remaining_amount']);
        $policy = $this->credit->policy($room);

        return [
            'total' => (int) (clone $visible)->whereIn('status', DebtStatus::outstandingValues())->sum('remaining_amount'),
            'pending' => (int) (clone $visible)
                ->whereIn('parent_id', $this->pendingRequestIds($room, $roomUser))
                ->sum('remaining_amount'),
            'submittable' => (int) $eligible->sum('remaining_amount'),
            'submittable_count' => $eligible->count(),
            'can_submit' => $eligible->count() >= self::MIN_DEBTS,
            'credit_enabled' => $policy['enabled'],
            'credit_limit' => $policy['ceiling'],
            'credit_available' => $policy['enabled'] ? max(0, $policy['ceiling'] - $this->credit->used($roomUser)) : null,
        ];
    }

    /**
     * Collect the payment-request data the member debt page renders.
     *
     * @param Room $room Current room.
     * @param RoomUser $roomUser Room member.
     * @param iterable<Debt> $debts Campaign debts listed on the page.
     * @return array{paymentSummary: array<string, mixed>, paymentRequests: Collection<int, Debt>, debtRequests: array<int, Debt>, paymentRequestDetails: array<string, array<string, mixed>>}
     *         Summary figures, the member's recent requests, the request of each listed bundled debt,
     *         and the pre-built detail payload of each request.
     */
    public function memberViewData(Room $room, RoomUser $roomUser, iterable $debts): array
    {
        $requests = $this->requestsFor($room, $roomUser);

        return [
            'paymentSummary' => $this->summary($room, $roomUser),
            'paymentRequests' => $requests,
            'debtRequests' => $this->requestsByChild($debts),
            // Pre-built modal payloads, keyed by request code, so the page needs no extra request.
            'paymentRequestDetails' => $requests
                ->mapWithKeys(fn (Debt $request): array => [(string) $request->code => $this->formatForMember($request)])
                ->all(),
        ];
    }

    /**
     * List the member's payment requests, newest first, with their bundled debts.
     *
     * @param Room $room Current room.
     * @param RoomUser $roomUser Room member.
     * @param int $limit Maximum number of requests.
     * @return Collection<int, Debt> Payment requests.
     */
    public function requestsFor(Room $room, RoomUser $roomUser, int $limit = 10): Collection
    {
        return Debt::paymentRequests()
            ->where('room_id', $room->id)
            ->where('room_user_id', $roomUser->id)
            ->with([
                'reviewer:id,name',
                'children' => fn ($query) => $query->orderBy('id')->with(['campaign:id,name', 'payments']),
            ])
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Map each bundled debt ID to its request, for the debts listed on a page.
     *
     * @param iterable<Debt> $debts Campaign debts shown on the page.
     * @return array<int, Debt> Payment request keyed by child debt ID.
     */
    public function requestsByChild(iterable $debts): array
    {
        $parentIds = collect($debts)->pluck('parent_id')->filter()->unique()->values();
        if ($parentIds->isEmpty()) {
            return [];
        }
        $requests = Debt::paymentRequests()->whereIn('id', $parentIds)->get()->keyBy('id');

        return collect($debts)
            ->filter(static fn (Debt $debt): bool => $debt->parent_id !== null && $requests->has($debt->parent_id))
            ->mapWithKeys(static fn (Debt $debt): array => [$debt->id => $requests->get($debt->parent_id)])
            ->all();
    }

    /**
     * Build the localized payload the admin review modal renders for one request.
     *
     * The frozen amount of each child is its current balance while the request is pending (balance
     * changes are blocked) and the payment booked under the request code once approved; `conflict`
     * flags a pending request whose children no longer add up to the total captured at submission.
     *
     * @param Debt $request Payment request with roomUser.globalUser, reviewer, children.campaign and children.payments loaded.
     * @return array<string, mixed> Display-ready request data.
     */
    public function formatForAdmin(Debt $request): array
    {
        $status = $request->getStatusValue();
        $childrenTotal = (int) $request->children->sum('remaining_amount');
        $isPending = $status === DebtStatus::Pending->value;

        return [
            'id' => $request->id,
            'code' => (string) $request->code,
            'status' => $status,
            'status_label' => __('admin.payment_request_status_'.$status),
            'member' => (string) ($request->roomUser?->globalUser?->name ?? $request->roomUser?->display_name ?? 'Member #'.$request->room_user_id),
            'member_meta' => collect([$request->roomUser?->globalUser?->email, $request->roomUser?->user_code])->filter()->implode(' · '),
            'requested_at' => $request->payment_requested_at ? FormatHelper::formatDateTime($request->payment_requested_at, 'H:i d/m/Y') : '',
            'transfer_content' => (string) $request->payment_content,
            'amount' => (int) $request->original_amount,
            'current_total' => $childrenTotal,
            'conflict' => $isPending && $childrenTotal !== (int) $request->original_amount,
            'reviewed_at' => $request->reviewed_at ? FormatHelper::formatDateTime($request->reviewed_at, 'H:i d/m/Y') : '',
            'reviewer' => (string) ($request->reviewer?->name ?? ''),
            'review_reason' => (string) $request->review_reason,
            'debts' => $request->children->map(static fn (Debt $child): array => [
                'code' => (string) $child->code,
                'campaign' => (string) ($child->campaign?->name ?? 'N/A'),
                'amount' => $status === DebtStatus::Approved->value
                    ? (int) $child->payments->where('reference', $request->code)->sum('amount')
                    : (int) $child->remaining_amount,
                'note' => (string) $child->note,
            ])->values()->all(),
        ];
    }

    /**
     * Build the localized payload the member page renders in the payment-request detail modal.
     *
     * Mirrors {@see self::formatForAdmin()} but with member-facing wording: the frozen amount of each
     * child is its current balance while the request is pending, and the amount booked under the
     * request code once it was approved.
     *
     * @param Debt $request Payment request with children.campaign and children.payments loaded.
     * @return array<string, mixed> Display-ready request data.
     */
    public function formatForMember(Debt $request): array
    {
        $status = $request->getStatusValue();
        $isApproved = $status === DebtStatus::Approved->value;
        $childrenTotal = (int) $request->children->sum('remaining_amount');
        $isPending = $status === DebtStatus::Pending->value;

        return [
            'id' => $request->id,
            'code' => (string) $request->code,
            'status' => $status,
            'status_label' => __('room.debts.request_status_'.$status),
            'requested_at' => $request->payment_requested_at ? FormatHelper::formatDateTime($request->payment_requested_at, 'H:i d/m/Y') : '',
            'transfer_content' => (string) $request->payment_content,
            'amount' => (int) $request->original_amount,
            'amount_formatted' => FormatHelper::formatCurrency((int) $request->original_amount),
            'current_total' => $childrenTotal,
            'conflict' => $isPending && $childrenTotal !== (int) $request->original_amount,
            'reviewed_at' => $request->reviewed_at ? FormatHelper::formatDateTime($request->reviewed_at, 'H:i d/m/Y') : '',
            'reviewer' => (string) ($request->reviewer?->name ?? ''),
            'review_reason' => (string) $request->review_reason,
            'debts_count' => $request->children->count(),
            'debts' => $request->children->map(static fn (Debt $child): array => [
                'code' => (string) $child->code,
                'campaign' => (string) ($child->campaign?->name ?? __('global.common.campaign')),
                'amount' => $isApproved
                    ? (int) $child->payments->where('reference', $request->code)->sum('amount')
                    : (int) $child->remaining_amount,
                'amount_formatted' => FormatHelper::formatCurrency($isApproved
                    ? (int) $child->payments->where('reference', $request->code)->sum('amount')
                    : (int) $child->remaining_amount),
                'note' => (string) $child->note,
            ])->values()->all(),
        ];
    }

    /**
     * Collect the IDs of the member's pending payment requests.
     *
     * @param Room $room Current room.
     * @param RoomUser $roomUser Room member.
     * @return array<int, int> Pending request IDs.
     */
    private function pendingRequestIds(Room $room, RoomUser $roomUser): array
    {
        return Debt::paymentRequests()
            ->where('room_id', $room->id)
            ->where('room_user_id', $roomUser->id)
            ->where('status', DebtStatus::Pending->value)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }
}
