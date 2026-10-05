<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\GlobalUserStatus;
use App\Enums\RoomUserStatus;
use App\Models\Room;
use App\Models\UserNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendRoomNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param Room $room Target room to broadcast notification.
     * @param string $type Notification type.
     * @param string $title Notification title.
     * @param string|null $body Notification body text.
     * @param array<string, mixed> $data Extra payload metadata.
     * @param string|null $link Optional redirect URL.
     */
    public function __construct(
        public readonly Room $room,
        public readonly string $type,
        public readonly string $title,
        public readonly ?string $body = null,
        public readonly array $data = [],
        public readonly ?string $link = null,
    ) {
    }

    /**
     * Execute the job: chunk active members and bulk-insert notifications.
     */
    public function handle(): void
    {
        $now = now();
        $this->room->roomUsers()
            ->where('status', RoomUserStatus::Active->value)
            ->whereHas('globalUser', static fn ($query) => $query->where('status', GlobalUserStatus::Active->value))
            ->chunkById(100, function ($roomUsers) use ($now): void {
                $records = [];
                foreach ($roomUsers as $roomUser) {
                    $records[] = [
                        'global_user_id' => $roomUser->global_user_id,
                        'room_user_id' => $roomUser->id,
                        'type' => $this->type,
                        'title' => $this->title,
                        'body' => $this->body,
                        'link' => $this->link,
                        'data' => json_encode($this->data),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                if (! empty($records)) {
                    UserNotification::insert($records);
                }
            });
    }
}
