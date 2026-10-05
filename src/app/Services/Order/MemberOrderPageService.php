<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Enums\CampaignStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Debt;
use App\Models\Order;
use App\Models\Room;
use App\Models\RoomUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Builds the data shared by the member order list (/rooms/{room}/orders) and the single order
 * page (/rooms/{room}/orders/{order:code}/view).
 *
 * Both pages render the same view, so the query and the payment-confirmation block live here
 * instead of being duplicated in the two controllers.
 */
final class MemberOrderPageService
{
    /**
     * Relations the order view reads, whether the order comes from the list or from the URL.
     *
     * @var list<string>
     */
    private const ORDER_RELATIONS = [
        'items.toppings',
        'items.campaignItem',
        'children.roomUser.globalUser',
        'children.items.toppings',
        'children.items.campaignItem',
    ];

    /**
     * Assemble the view data for a member's order page.
     *
     * @param Room $room Room being viewed.
     * @param RoomUser $roomUser Member viewing the page.
     * @param Order|null $pinnedOrder Order taken from the URL, or null for the plain order list.
     * @return array{
     *     orders: LengthAwarePaginator<int, Order>,
     *     activeOrder: Order|null,
     *     paymentConfirmationDetails: array{requestedAt: ?string, content: ?string, approvedBy: ?string, approvedAt: ?string}|null
     * }
     */
    public function build(Room $room, RoomUser $roomUser, ?Order $pinnedOrder = null): array
    {
        $orders = $this->paginatedOrders($room, $roomUser);

        $activeOrder = $pinnedOrder ?? $orders->firstWhere('room_user_id', $roomUser->id) ?? $orders->first();
        if ($activeOrder instanceof Order) {
            // A pinned order is loaded from the URL binding, so it carries none of the relations
            // the list query eager loads.
            $activeOrder->loadMissing(self::ORDER_RELATIONS);
            $activeOrder->loadMissing('campaign.paymentAccount');
            if ($activeOrder->campaign !== null && ! isset($activeOrder->campaign->active_orders_subtotal)) {
                $activeOrder->campaign->loadSum([
                    'orders as active_orders_subtotal' => static fn (Builder $query): Builder => $query
                        ->where('status', '!=', OrderStatus::Cancelled->value)
                        ->whereNull('cancelled_at'),
                ], 'subtotal');
            }
        }

        return [
            'orders' => $orders,
            'activeOrder' => $activeOrder,
            'paymentConfirmationDetails' => $this->paymentConfirmationDetails($activeOrder),
        ];
    }

    /**
     * Whether the room currently has a live campaign, i.e. whether the order list can be shown.
     *
     * The list page redirects to the dashboard without one; the single order page does not, because
     * a notification can point at an order whose campaign has already been closed.
     *
     * @param Room $room Room being checked.
     * @return bool True when the room has an active campaign.
     */
    public function roomHasLiveCampaign(Room $room): bool
    {
        return $room->campaigns()->where('status', CampaignStatus::Active->value)->exists();
    }

    /**
     * Paginated non-cancelled orders of the member in the room's live campaign.
     *
     * @param Room $room Room owning the orders.
     * @param RoomUser $roomUser Member whose orders are listed.
     * @return LengthAwarePaginator<int, Order> Paginated orders, newest first.
     */
    public function paginatedOrders(Room $room, RoomUser $roomUser): LengthAwarePaginator
    {
        return Order::query()
            ->where('room_id', $room->id)
            ->visibleToMember($roomUser)
            ->inLiveCampaign()
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->with([
                ...self::ORDER_RELATIONS,
                'campaign' => static fn (BelongsTo $query): BelongsTo => $query
                    ->with('paymentAccount')
                    ->withSum([
                        'orders as active_orders_subtotal' => static fn (Builder $orders): Builder => $orders
                            ->where('status', '!=', OrderStatus::Cancelled->value)
                            ->whereNull('cancelled_at'),
                    ], 'subtotal'),
            ])
            ->latest()
            ->paginate(20);
    }

    /**
     * Payment request / approval details shown in the confirmation and details modals.
     *
     * @param Order|null $order Order currently displayed.
     * @return array{requestedAt: ?string, content: ?string, approvedBy: ?string, approvedAt: ?string}|null
     *                                                                                                        Details, or null without an order.
     */
    private function paymentConfirmationDetails(?Order $order): ?array
    {
        if (! $order instanceof Order) {
            return null;
        }

        $debt = Debt::query()
            ->where('campaign_id', $order->campaign_id)
            ->where('room_user_id', $order->room_user_id)
            ->with('payments.createdByAdmin')
            ->first();

        $approvalPayment = $order->payment_status === PaymentStatus::Paid
            ? $debt?->payments->sortByDesc('paid_at')->first()
            : null;

        return [
            'requestedAt' => $debt?->payment_requested_at?->format('d/m/Y H:i'),
            'content' => $debt?->note ?: $order->code,
            'approvedBy' => $approvalPayment?->createdByAdmin?->name,
            'approvedAt' => $approvalPayment?->paid_at?->format('d/m/Y H:i'),
        ];
    }
}
