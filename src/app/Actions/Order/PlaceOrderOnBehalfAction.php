<?php

declare(strict_types=1);

namespace App\Actions\Order;

use App\Enums\OrderStatus;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\Order;
use App\Models\Room;
use App\Models\RoomUser;
use App\Services\Audit\AuditService;
use Illuminate\Validation\ValidationException;

class PlaceOrderOnBehalfAction
{
    public function __construct(
        private readonly CreateOrderAction $createOrderAction,
        private readonly AuditService $audit,
    ) {
    }

    /**
     * Let a room admin place an order for a member in the room's running campaign.
     *
     * The order belongs to the member exactly as if they had ordered themselves (same menu, price,
     * sponsorship and debt-ceiling rules via CreateOrderAction); it only additionally records which
     * admin placed it and writes an audit entry.
     *
     * @param Room $room Room being managed.
     * @param Campaign $campaign Campaign to order from; must belong to the room and accept orders.
     * @param RoomUser $member Member the order is placed for; must belong to the room.
     * @param Admin $admin Admin placing the order.
     * @param array<string, mixed> $data Validated payload (items[], note).
     * @return Order Created order with items loaded.
     * @throws ValidationException When the campaign/member is outside the room or the member already has an active order.
     */
    public function execute(Room $room, Campaign $campaign, RoomUser $member, Admin $admin, array $data): Order
    {
        if ($campaign->room_id !== $room->id || $member->room_id !== $room->id) {
            throw ValidationException::withMessages([
                'room_user_id' => __('admin.on_behalf_member_invalid'),
            ]);
        }

        $hasActiveOrder = $member->orders()
            ->where('campaign_id', $campaign->id)
            ->whereIn('status', OrderStatus::activeValues())
            ->exists();
        if ($hasActiveOrder) {
            throw ValidationException::withMessages([
                'room_user_id' => __('admin.on_behalf_member_has_order'),
            ]);
        }

        $order = $this->createOrderAction->execute($campaign, $member, [
            'items' => array_map(static function (array $item): array {
                unset($item['proxy_user_code']);

                return $item;
            }, $data['items'] ?? []),
            'note' => $data['note'] ?? null,
        ], allowLocked: true);

        $order->placed_by_admin_id = $admin->id;
        $order->save();

        $this->audit->record('order.placed_on_behalf', 'order', $order->id, $room->id, [], [
            'code' => $order->code,
            'room_user_id' => $member->id,
            'final_amount' => (int) $order->final_amount,
        ], ['placed_by_admin_id' => $admin->id]);

        return $order;
    }
}
