<?php

declare(strict_types=1);

namespace App\Services\Game;

use App\Enums\GameRoomStatus;
use App\Models\GameNotificationChannel;
use App\Models\GameRoom;
use App\Models\GlobalUser;
use App\Services\Audit\AuditService;
use App\Services\Notification\Concerns\SendsNotificationChannelPayloads;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Notification channels of the Utilities area and delivery of game results through them.
 *
 * A global user keeps their own list of channels (Slack, Telegram, Chatwork, webhook); a game room picks one, like it
 * picks a rule set. When the room finishes its final leaderboard is sent there, and the owner can send it again.
 * Delivery is best-effort: a failing provider never blocks finishing the room, it is logged and reported to the owner.
 */
class GameNotificationService
{
    use SendsNotificationChannelPayloads;

    /** Outcome of a results delivery. */
    public const SENT = 'sent';

    public const NO_CHANNEL = 'no_channel';

    public const FAILED = 'failed';

    public function __construct(
        private readonly GameStatisticsService $statistics,
        private readonly AuditService $audit,
    ) {}

    /**
     * Channels of an owner, active ones first, then by name.
     *
     * @param GlobalUser $owner Owner.
     * @return Collection<int, GameNotificationChannel> Channels.
     */
    public function forOwner(GlobalUser $owner): Collection
    {
        return GameNotificationChannel::query()->ownedBy($owner)
            ->orderByDesc('is_active')->orderBy('name')->orderBy('id')->get();
    }

    /**
     * Create or update a channel. A blank credential keeps the stored one when the type is unchanged, so secrets are
     * never echoed back to the edit form; every key the driver needs must end up with a value.
     *
     * @param GlobalUser $owner Owner (the channel's owner when updating).
     * @param array{name: string, type: string, is_active?: bool, config?: array<string, string|null>} $data Validated data.
     * @param GameNotificationChannel|null $channel Channel being updated, null to create one.
     * @return GameNotificationChannel Saved channel.
     * @throws ValidationException When a required credential is missing.
     */
    public function save(GlobalUser $owner, array $data, ?GameNotificationChannel $channel = null): GameNotificationChannel
    {
        $type = (string) $data['type'];
        $submitted = array_map(static fn ($value): string => trim((string) $value), $data['config'] ?? []);
        $config = $submitted;
        if ($channel !== null && $channel->type === $type) {
            foreach (GameNotificationChannel::SECRET_KEYS as $key) {
                if (($submitted[$key] ?? '') === '') {
                    unset($config[$key]);
                }
            }
            $config = array_replace($channel->config(), $config);
        }
        // Keep only the keys this driver uses, so switching type never leaves another driver's secrets behind.
        $allowed = [...GameNotificationChannel::REQUIRED_KEYS[$type], ...($type === 'webhook' ? ['secret_token'] : [])];
        $config = array_filter(array_intersect_key($config, array_flip($allowed)), static fn (string $value): bool => $value !== '');
        $missing = array_values(array_diff(GameNotificationChannel::REQUIRED_KEYS[$type], array_keys($config)));
        if ($missing !== []) {
            throw ValidationException::withMessages(['config.'.$missing[0] => __('games.channels.errors.required')]);
        }

        $before = $channel === null ? [] : ['name' => $channel->name, 'type' => $channel->type, 'is_active' => $channel->is_active];
        $channel ??= new GameNotificationChannel(['owner_id' => $owner->id]);
        $channel->fill([
            'name' => (string) $data['name'],
            'type' => $type,
            'is_active' => (bool) ($data['is_active'] ?? true),
            'config_encrypted' => json_encode($config, JSON_THROW_ON_ERROR),
        ])->save();
        $this->audit->record($before === [] ? 'game_notification_channel.created' : 'game_notification_channel.updated',
            'game_notification_channel', (int) $channel->id, null, $before,
            ['name' => $channel->name, 'type' => $channel->type, 'is_active' => $channel->is_active]);

        return $channel;
    }

    /**
     * Delete a channel; rooms using it simply stop sending (foreign key set to null).
     *
     * @param GameNotificationChannel $channel Channel.
     * @return void
     */
    public function delete(GameNotificationChannel $channel): void
    {
        $this->audit->record('game_notification_channel.deleted', 'game_notification_channel', (int) $channel->id, null,
            ['name' => $channel->name, 'type' => $channel->type], []);
        $channel->delete();
    }

    /**
     * Values for the edit form: plain settings are shown, credentials are only flagged as configured.
     *
     * @param GameNotificationChannel $channel Channel.
     * @return array{config: array<string, string>, secrets_configured: list<string>} Form data.
     */
    public function editable(GameNotificationChannel $channel): array
    {
        $config = $channel->config();

        return [
            'config' => array_diff_key($config, array_flip(GameNotificationChannel::SECRET_KEYS)),
            'secrets_configured' => array_values(array_intersect(GameNotificationChannel::SECRET_KEYS, array_keys($config))),
        ];
    }

    /**
     * Send a short test message through a channel.
     *
     * @param GameNotificationChannel $channel Channel.
     * @return bool True when the provider accepted the message.
     */
    public function sendTest(GameNotificationChannel $channel): bool
    {
        return $this->deliver($channel, [
            'event' => 'notification.test',
            'title' => __('games.channels.test_title'),
            'message' => __('games.channels.test_message', ['name' => $channel->name]),
        ]);
    }

    /**
     * Send the final leaderboard of a finished room to its channel and remember when it was sent.
     *
     * @param GameRoom $room Finished room.
     * @return string One of SENT, NO_CHANNEL, FAILED.
     */
    public function notifyResults(GameRoom $room): string
    {
        $room->loadMissing(['notificationChannel', 'game']);
        $channel = $room->notificationChannel;
        if ($room->status !== GameRoomStatus::Finished || $channel === null || ! $channel->is_active) {
            return self::NO_CHANNEL;
        }
        if (! $this->deliver($channel, $this->resultsPayload($room))) {
            return self::FAILED;
        }
        $room->forceFill(['results_notified_at' => now()])->save();
        $this->audit->record('game_room.results_notified', 'game_room', (int) $room->id, null, [], ['channel_id' => $channel->id]);

        return self::SENT;
    }

    /**
     * Driver-neutral message of a room's final leaderboard.
     *
     * @param GameRoom $room Room.
     * @return array{event: string, title: string, message: string} Payload.
     */
    public function resultsPayload(GameRoom $room): array
    {
        $dashboard = $this->statistics->dashboard($room);
        $medals = [1 => '🥇', 2 => '🥈', 3 => '🥉'];
        $lines = [__('games.notify.summary', [
            'game' => $room->game?->name ?? '',
            'rounds' => $dashboard['summary']['rounds'],
            'results' => $dashboard['summary']['results'],
        ])];
        foreach ($dashboard['leaderboard'] as $row) {
            $points = $row['points'];
            $lines[] = __('games.notify.line', [
                'medal' => $medals[$row['rank']] ?? '▫️',
                'rank' => $row['rank'],
                'name' => $row['name'],
                'points' => ($points > 0 ? '+' : '').$points,
                'wins' => $row['wins'],
                'losses' => $row['losses'],
            ]);
        }

        return [
            'event' => 'game_room.finished',
            'title' => __('games.notify.title', ['room' => $room->name]),
            'message' => implode("\n", $lines),
        ];
    }

    /**
     * Deliver one payload; failures are logged without the channel's credentials.
     *
     * @param GameNotificationChannel $channel Channel.
     * @param array<string, mixed> $payload Driver-neutral payload.
     * @return bool True on success.
     */
    private function deliver(GameNotificationChannel $channel, array $payload): bool
    {
        try {
            $this->send($channel->type, $channel->config(), $payload);

            return true;
        } catch (\Throwable $exception) {
            Log::warning('Game notification channel delivery failed.', [
                'channel_id' => $channel->id,
                'type' => $channel->type,
                'event' => $payload['event'] ?? null,
                'exception' => $exception::class,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}
