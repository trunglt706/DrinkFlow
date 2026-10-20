<?php

declare(strict_types=1);

namespace App\Services\BankSync;

use App\Enums\BankProvider;
use App\Models\Room;
use App\Models\RoomBankIntegration;
use App\Services\Audit\AuditService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Room admin settings of the bank feed: on/off, the provider API key and the secret webhook URL.
 */
class RoomBankIntegrationService
{
    /** Length of the random webhook URL token. */
    private const TOKEN_LENGTH = 48;

    public function __construct(private readonly AuditService $audit)
    {
    }

    /**
     * The room's connection, created (inactive, with a fresh webhook token) on first use.
     *
     * @param Room $room Room whose connection is read.
     * @return RoomBankIntegration Connection.
     */
    public function forRoom(Room $room): RoomBankIntegration
    {
        return RoomBankIntegration::query()->firstOrCreate(
            ['room_id' => $room->id],
            ['provider' => BankProvider::SePay, 'webhook_token' => $this->newToken(), 'is_active' => false],
        );
    }

    /**
     * Webhook URL the provider must post to.
     *
     * @param RoomBankIntegration $integration Room connection.
     * @return string Absolute URL containing the secret token.
     */
    public function webhookUrl(RoomBankIntegration $integration): string
    {
        return route('webhooks.bank', ['provider' => $integration->provider->value, 'token' => $integration->webhook_token]);
    }

    /**
     * Save the settings. A blank API key keeps the stored one; the connection cannot be switched on without a key.
     *
     * @param Room $room Room being configured.
     * @param bool $active Whether incoming notifications are accepted.
     * @param string|null $apiKey New API key, or null / blank to keep the current one.
     * @param bool $regenerateToken Issue a new webhook URL (the old one stops working).
     * @return RoomBankIntegration Saved connection.
     *
     * @throws ValidationException When switched on without an API key.
     */
    public function update(Room $room, bool $active, ?string $apiKey, bool $regenerateToken): RoomBankIntegration
    {
        $integration = $this->forRoom($room);
        $apiKey = $apiKey !== null ? trim($apiKey) : '';
        if ($apiKey !== '') {
            $integration->api_key = $apiKey;
        }
        if ($active && blank($integration->api_key)) {
            throw ValidationException::withMessages(['api_key' => __('bank_sync.api_key_required')]);
        }
        if ($regenerateToken) {
            $integration->webhook_token = $this->newToken();
        }
        $integration->is_active = $active;
        $integration->save();

        $this->audit->record('bank_integration.updated', 'room_bank_integration', $integration->id, $room->id, [], [
            'is_active' => $active,
            'api_key_changed' => $apiKey !== '',
            'token_regenerated' => $regenerateToken,
        ]);

        return $integration;
    }

    private function newToken(): string
    {
        return Str::random(self::TOKEN_LENGTH);
    }
}
