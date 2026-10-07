<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\OrderDeleted;
use App\Models\RoomUser;
use App\Services\Notification\UserNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class NotifyOrderDeleted implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;
    public int $timeout = 10;
    public function handle(OrderDeleted $event): void
    {
        $roomUser = RoomUser::query()->find($event->order['room_user_id']);
        if (! $roomUser) {
            return;
        }
        app(UserNotificationService::class)->toRoomUser($roomUser, 'order.deleted', __('messages.order_deleted_title'), __('messages.order_deleted_body'), ['order_id' => $event->order['order_id'], 'room_id' => $event->order['room_id']]);
    }
}
