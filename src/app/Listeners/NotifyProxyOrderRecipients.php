<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\NotificationType;
use App\Events\ProxyOrdersCreated;
use App\Services\Notification\UserNotificationService;

final class NotifyProxyOrderRecipients
{
    public function __construct(private readonly UserNotificationService $notifications)
    {
    }

    /**
     * Notify each member who received an order placed on their behalf.
     *
     * @param ProxyOrdersCreated $event Proxy parent and child orders.
     * @return void
     */
    public function handle(ProxyOrdersCreated $event): void
    {
        $orderer = $event->parentOrder->roomUser?->globalUser?->name
            ?? $event->parentOrder->roomUser?->display_name
            ?? __('messages.someone');

        foreach ($event->childOrders as $childOrder) {
            $recipient = $childOrder->roomUser;
            if ($recipient === null) {
                continue;
            }

            $this->notifications->toRoomUser(
                $recipient,
                NotificationType::OrderProxyReceived->value,
                __('messages.order_proxy_received_title'),
                __('messages.order_proxy_received_body', [
                    'order_code' => $childOrder->code,
                    'orderer' => $orderer,
                ]),
                [
                    'order_id' => $childOrder->id,
                    'order_code' => $childOrder->code,
                    'parent_order_id' => $event->parentOrder->id,
                    'room_id' => $childOrder->room_id,
                    'orderer_name' => $orderer,
                ],
            );
        }
    }
}
