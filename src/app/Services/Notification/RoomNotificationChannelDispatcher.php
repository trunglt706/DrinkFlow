<?php

declare(strict_types=1);

namespace App\Services\Notification;

use App\Models\NotificationChannel;
use App\Models\Room;
use App\Services\Notification\Concerns\SendsNotificationChannelPayloads;
use App\Support\Helpers\FormatHelper;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class RoomNotificationChannelDispatcher
{
    use SendsNotificationChannelPayloads;

    /**
     * Deliver a payload to every enabled and correctly configured room channel.
     *
     * @param Room $room Room owning the channels.
     * @param array<string, mixed> $payload Driver-neutral payload.
     * @return int Number of channel delivery attempts.
     */
    public function dispatch(Room $room, array $payload): int
    {
        $attempts = 0;
        $room->notificationChannels()->where('status', NotificationChannel::STATUS_ENABLED)->each(function (NotificationChannel $channel) use ($payload, &$attempts): void {
            try {
                $config = $this->config($channel);
                if ($config === []) {
                    return;
                }
                $this->send($channel->type, $config, $payload);
                $attempts++;
            } catch (\Throwable $exception) {
                Log::warning('Room notification channel delivery failed.', [
                    'channel_id' => $channel->id,
                    'type' => $channel->type,
                    'event' => $payload['event'] ?? null,
                    'exception' => $exception::class,
                    'error' => $exception->getMessage(),
                ]);
            }
        });

        return $attempts;
    }

    /**
     * Deliver a payload to one channel and report the outcome to the caller (per-channel delivery tracking,
     * e.g. sponsor draw results), instead of swallowing the error like {@see dispatch()}.
     *
     * @param NotificationChannel $channel Enabled channel of the room.
     * @param array<string, mixed> $payload Driver-neutral payload.
     * @return void
     * @throws \RuntimeException When the channel is not configured.
     * @throws \Throwable When the provider rejects the message.
     */
    public function sendTo(NotificationChannel $channel, array $payload): void
    {
        $config = $this->config($channel);
        if ($config === []) {
            throw new \RuntimeException('Notification channel is not configured.');
        }
        $this->send($channel->type, $config, $payload);
    }

    /**
     * Send a non-sensitive test payload through one configured channel.
     *
     * @param NotificationChannel $channel Configured channel.
     * @param string $template Template name (e.g., test_ping, campaign.created, campaign.closed, campaign.cancelled, debt.reminder).
     * @return void
     */
    public function test(NotificationChannel $channel, string $template = 'test_ping'): void
    {
        $payload = $this->buildTestPayload($template, $channel->room);
        $this->send($channel->type, $this->config($channel), $payload);
    }

    /**
     * Build realistic test payload for a given notification template.
     *
     * @param string $template Template identifier.
     * @param Room|null $room Associated room if available.
     * @return array<string, mixed> Driver-neutral notification payload.
     */
    public function buildTestPayload(string $template, ?Room $room = null): array
    {
        $roomName = $room ? $room->name : 'DrinkFlow Team';
        $roomSlug = $room ? $room->slug : 'general';
        $sampleCode = 'CMP-'.now()->format('Ymd').'-DEMO';

        return match ($template) {
            'campaign.created' => [
                'event' => 'campaign.created',
                'title' => __('messages.campaign_created_title', ['code' => $sampleCode]),
                'room_name' => $roomName,
                'campaign' => [
                    'id' => 999,
                    'name' => 'Trà Sữa Phê La (Mẫu thử)',
                    'restaurant' => 'Phê La Tea & Coffee',
                    'deadline' => now()->addMinutes(45)->toIso8601String(),
                    'sponsor_name' => 'Team Lead',
                    'sponsor_type' => 'fixed',
                    'sponsorship_amount' => 20000,
                    'max_product_budget' => 60000,
                    'order_url' => url("/rooms/{$roomSlug}/campaigns"),
                ],
                'message' => implode("\n", [
                    __('messages.campaign_created_title', ['code' => $sampleCode]),
                    __('messages.campaign_name', ['name' => 'Trà Sữa Phê La (Mẫu thử)']),
                    __('messages.campaign_restaurant', ['restaurant' => 'Phê La Tea & Coffee']),
                    __('messages.campaign_deadline', ['date' => FormatHelper::formatDateTime(now()->addMinutes(45), 'd/m/Y H:i')]),
                    __('messages.campaign_product_budget', ['amount' => FormatHelper::formatCurrency(60000)]),
                    __('messages.campaign_sponsorship', ['sponsor' => 'Team Lead', 'amount' => FormatHelper::formatCurrency(20000)]),
                    __('messages.campaign_order', ['url' => url("/rooms/{$roomSlug}/campaigns")]),
                ]),
            ],
            'campaign.closed' => [
                'event' => 'campaign.closed',
                'title' => __('messages.campaign_closed_title', ['code' => $sampleCode]),
                'room_name' => $roomName,
                'campaign' => [
                    'id' => 999,
                    'name' => 'Trà Sữa Phê La (Mẫu thử)',
                    'restaurant' => 'Phê La Tea & Coffee',
                ],
                'message' => implode("\n", [
                    __('messages.campaign_closed_title', ['code' => $sampleCode]),
                    __('messages.campaign_name', ['name' => 'Trà Sữa Phê La (Mẫu thử)']),
                    __('messages.campaign_restaurant', ['restaurant' => 'Phê La Tea & Coffee']),
                    __('messages.campaign_deadline', ['date' => FormatHelper::formatDateTime(now()->subMinutes(5), 'd/m/Y H:i')]),
                    __('messages.campaign_payment', ['url' => url("/rooms/{$roomSlug}/debts")]),
                ]),
            ],
            'campaign.cancelled' => [
                'event' => 'campaign.cancelled',
                'title' => __('messages.campaign_cancelled_title'),
                'room_name' => $roomName,
                'campaign' => [
                    'id' => 999,
                    'name' => 'Trà Sữa Phê La (Mẫu thử)',
                    'restaurant' => 'Phê La Tea & Coffee',
                ],
                'message' => implode("\n", [
                    __('messages.campaign_cancelled_title'),
                    __('messages.campaign_name', ['name' => 'Trà Sữa Phê La (Mẫu thử)']),
                    __('messages.campaign_restaurant', ['restaurant' => 'Phê La Tea & Coffee']),
                    __('messages.campaign_cancelled_body'),
                ]),
            ],
            'debt.reminder' => [
                'event' => 'debt.reminder',
                'title' => __('messages.debt_channel_reminder_title'),
                'room_name' => $roomName,
                'message' => __('admin.debt_channel_reminder_body', [
                    'count' => 3,
                    'amount' => FormatHelper::formatCurrency(95000),
                ]),
            ],
            default => [
                'event' => 'notification.test',
                'title' => __('messages.test_ping_title'),
                'room_name' => $roomName,
                'message' => __('messages.test_ping_body'),
            ],
        };
    }

    /**
     * Decrypt and decode a channel configuration without exposing it to callers.
     *
     * @param NotificationChannel $channel Channel entity.
     * @return array<string, string> Channel configuration.
     */
    private function config(NotificationChannel $channel): array
    {
        $encrypted = $channel->getRawOriginal('config_encrypted');
        if (! is_string($encrypted) || $encrypted === '') {
            return [];
        }
        $decrypted = Crypt::decrypt($encrypted);
        $decoded = json_decode(is_string($decrypted) ? $decrypted : '', true, 512, JSON_THROW_ON_ERROR);

        return is_array($decoded) ? array_filter($decoded, 'is_string') : [];
    }

}
