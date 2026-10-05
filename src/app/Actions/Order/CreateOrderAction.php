<?php

declare(strict_types=1);

namespace App\Actions\Order;

use App\Enums\GlobalUserStatus;
use App\Enums\OrderStatus;
use App\Enums\RoomUserStatus;
use App\Events\OrderCreated;
use App\Models\Campaign;
use App\Models\Order;
use App\Models\RoomUser;
use App\Services\Order\OrderPricingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateOrderAction
{
    public function __construct(private readonly OrderPricingService $pricing)
    {
    }

    /**
     * Create a new order for a room user in an active campaign.
     *
     * @param Campaign $campaign Campaign entity.
     * @param RoomUser $roomUser Room user placing the order.
     * @param array<string, mixed> $data Order items and options payload.
     * @param int|null $parentId Parent order ID when creating a child (proxy) order.
     * @param bool $allowEmpty Whether an empty placeholder parent order is allowed.
     * @param bool $allowLocked Whether to accept orders while an admin has locked member ordering (admin "order on behalf").
     * @return Order Created order entity with items and toppings loaded.
     * @throws ValidationException If campaign is unavailable, user inactive, or items invalid.
     */
    public function execute(Campaign $campaign, RoomUser $roomUser, array $data, ?int $parentId = null, bool $allowEmpty = false, bool $allowLocked = false): Order
    {
        $campaign->refresh();
        $roomUser->refresh();
        $roomUser->loadMissing('globalUser');
        if ($campaign->room_id !== $roomUser->room_id) {
            throw ValidationException::withMessages([
                'campaign' => __('admin.campaign_unavailable'),
            ]);
        }
        $this->ensureCampaignIsOrderable($campaign, $allowLocked);
        if ($roomUser->status !== RoomUserStatus::Active || $roomUser->globalUser->status !== GlobalUserStatus::Active) {
            throw ValidationException::withMessages([
                'user' => __('admin.account_inactive'),
            ]);
        }

        $order = DB::transaction(function () use ($campaign, $roomUser, $data, $parentId, $allowLocked, $allowEmpty): Order {
            $campaign = Campaign::query()->lockForUpdate()->findOrFail($campaign->id);
            // Serialize daily order numbering across campaigns in the same room.
            \App\Models\Room::query()->whereKey($campaign->room_id)->lockForUpdate()->firstOrFail();
            $this->ensureCampaignIsOrderable($campaign, $allowLocked);

            $hasActiveOrder = Order::query()
                ->where('campaign_id', $campaign->id)
                ->where('room_user_id', $roomUser->id)
                ->whereIn('status', OrderStatus::activeValues())
                ->exists();

            if ($hasActiveOrder) {
                throw ValidationException::withMessages([
                    'active_order_exists' => __('global.orders.active_order_exists'),
                ]);
            }

            $items = $data['items'] ?? [];
            if ($items === [] && ! $allowEmpty) {
                throw ValidationException::withMessages([
                    'items' => __('admin.order_must_have_items'),
                ]);
            }
            ['lines' => $snapshots, 'subtotal' => $subtotal] = $this->pricing->priceLines($campaign, $items);

            $discount = 0;
            $delivery = 0;
            $sponsor = $this->pricing->sponsorAmount($campaign, $snapshots, $subtotal, $delivery, $discount);
            $final = max(0, $subtotal + $delivery - $discount - $sponsor);
            $this->pricing->enforceDebtPolicy($roomUser, $final);
            $order = Order::create([
                'parent_id'       => $parentId,
                'room_id'         => $campaign->room_id,
                'campaign_id'     => $campaign->id,
                'room_user_id'    => $roomUser->id,
                'payment_method'  => $data['payment_method'] ?? null,
                'subtotal'        => $subtotal,
                'delivery_amount' => $delivery,
                'discount_amount' => $discount,
                'sponsor_amount'  => $sponsor,
                'final_amount'    => $final,
                'status'          => OrderStatus::Submitted,
                'note'            => $data['note'] ?? null,
                'submitted_at'    => now(),
            ]);
            $this->pricing->createItems($order, $snapshots);
            return $order->load('items.toppings');
        });

        OrderCreated::dispatch($order);

        return $order;
    }

    /**
     * Ensure that a campaign still accepts orders at the current time.
     *
     * @param Campaign $campaign Campaign being ordered from.
     * @param bool $allowLocked Whether an admin ordering lock is ignored.
     * @return void
     * @throws ValidationException When the campaign is not live, its deadline has passed or ordering is locked.
     */
    private function ensureCampaignIsOrderable(Campaign $campaign, bool $allowLocked = false): void
    {
        $orderable = $allowLocked ? $campaign->isOpenForOrders() : $campaign->isOrderable();
        if (! $orderable) {
            throw ValidationException::withMessages([
                'campaign' => $campaign->orderingClosedMessage(),
            ]);
        }
    }
}
