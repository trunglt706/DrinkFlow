<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Enums\CampaignStatus;
use App\Enums\OrderPlacementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Debt;
use App\Models\Order;
use App\Models\Room;
use App\Models\RoomUser;
use Carbon\CarbonImmutable;
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
    public function __construct(private readonly OrderItemRatingService $ratings) {}

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
     *     paymentConfirmationDetails: array{requestedAt: ?string, content: ?string, approvedBy: ?string, approvedAt: ?string}|null,
     *     itemRatings: array<int, array{can_rate: bool, url: string, stars: int|null, comment: string|null, commented_at: string|null, locked: bool}>
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
            // Stars of the viewer's own received items (their order only, not the proxy orders).
            'itemRatings' => $activeOrder instanceof Order ? $this->ratings->forItems($room, $activeOrder->items->each->setRelation('order', $activeOrder), $roomUser) : [],
        ];
    }

    /** History filter by who placed the order: the member for themselves, or on someone's behalf. */
    public const HISTORY_TYPE_SELF = 'self';

    public const HISTORY_TYPE_PROXY = 'proxy';

    public const HISTORY_TYPES = [self::HISTORY_TYPE_SELF, self::HISTORY_TYPE_PROXY];

    public const HISTORY_PER_PAGE = 10;

    /**
     * The member's earlier orders (every campaign except the live one, whose order is shown at the top of the page):
     * their own orders and the proxy orders they placed for others, newest first, 10 per page.
     *
     * @param Room $room Room of the orders.
     * @param RoomUser $roomUser Member viewing their history.
     * @param array{code?: string, date_from?: string, date_to?: string, type?: string, status?: string} $filters
     *        Validated filters: part of the order code, creation dates (Y-m-d, room timezone), `self` (placed by the
     *        member for themselves) or `proxy` (placed on someone's behalf: for another member, or for the member by
     *        another member or a room admin), order status.
     * @return LengthAwarePaginator<int, Order> Orders with campaign, member and parent member loaded.
     */
    public function history(Room $room, RoomUser $roomUser, array $filters): LengthAwarePaginator
    {
        $timezone = (string) ($room->timezone ?: config('app.display_timezone', config('app.timezone')));
        $query = Order::query()
            ->where('room_id', $room->id)
            ->visibleToMember($roomUser)
            ->whereDoesntHave('campaign', static fn (Builder $campaign): Builder => $campaign->where('status', CampaignStatus::Active->value))
            ->with(['campaign:id,name,restaurant', 'roomUser:id,display_name,global_user_id', 'roomUser.globalUser:id,name', 'parent:id,room_user_id', 'parent.roomUser:id,display_name,global_user_id', 'parent.roomUser.globalUser:id,name'])
            ->withCount('items')
            ->latest('id');

        if (isset($filters['code'])) {
            $query->where('code', 'like', '%'.addcslashes($filters['code'], '%_\\').'%');
        }
        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', CarbonImmutable::createFromFormat('Y-m-d', $filters['date_from'], $timezone)->startOfDay()->utc());
        }
        if (isset($filters['date_to'])) {
            $query->where('created_at', '<', CarbonImmutable::createFromFormat('Y-m-d', $filters['date_to'], $timezone)->addDay()->startOfDay()->utc());
        }
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (($filters['type'] ?? null) === self::HISTORY_TYPE_SELF) {
            $query->where('room_user_id', $roomUser->id)->ofPlacementType(OrderPlacementType::Self);
        } elseif (($filters['type'] ?? null) === self::HISTORY_TYPE_PROXY) {
            $query->where(static fn (Builder $proxy): Builder => $proxy->whereNotNull('parent_id')->orWhereNotNull('placed_by_admin_id'));
        }

        return $query->paginate(self::HISTORY_PER_PAGE, ['*'], 'history_page')->withQueryString()->fragment('order-history');
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
