<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Outbound notification channel of a global user in the Utilities area (game results). The configuration holds
 * credentials: it is stored encrypted with APP_KEY and never serialized.
 */
class GameNotificationChannel extends Model
{
    /** Supported drivers, delivered by {@see \App\Services\Notification\Concerns\SendsNotificationChannelPayloads}. */
    public const TYPES = ['slack', 'telegram', 'chatwork', 'webhook'];

    /** Configuration keys each driver needs before a message can be sent. */
    public const REQUIRED_KEYS = [
        'slack' => ['webhook_url'],
        'telegram' => ['bot_token', 'chat_id'],
        'chatwork' => ['api_token', 'room_id'],
        'webhook' => ['webhook_url'],
    ];

    /** Configuration keys holding credentials; their stored values are never sent back to the browser. */
    public const SECRET_KEYS = ['bot_token', 'api_token', 'secret_token', 'webhook_url'];

    protected $fillable = ['owner_id', 'name', 'type', 'config_encrypted', 'is_active'];

    protected $hidden = ['config_encrypted'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'config_encrypted' => 'encrypted'];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(GlobalUser::class, 'owner_id');
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(GameRoom::class, 'game_notification_channel_id');
    }

    /**
     * Channels of one owner.
     *
     * @param Builder<GameNotificationChannel> $query Query.
     * @param GlobalUser $owner Owner.
     * @return Builder<GameNotificationChannel> Scoped query.
     */
    public function scopeOwnedBy(Builder $query, GlobalUser $owner): Builder
    {
        return $query->where('owner_id', $owner->id);
    }

    /**
     * Decrypted driver configuration.
     *
     * @return array<string, string> Configuration (string values only).
     */
    public function config(): array
    {
        $decoded = json_decode((string) $this->config_encrypted, true);

        return is_array($decoded) ? array_filter($decoded, 'is_string') : [];
    }
}
