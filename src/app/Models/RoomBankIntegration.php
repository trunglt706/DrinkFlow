<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BankProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A room's bank-feed connection: the provider posts incoming transfers to the room's secret webhook URL and
 * authenticates with the room's API key (encrypted at rest, never sent back to the browser).
 */
class RoomBankIntegration extends Model
{
    protected $fillable = ['room_id', 'provider', 'webhook_token', 'api_key', 'is_active', 'last_received_at'];

    protected $hidden = ['api_key', 'webhook_token'];

    protected function casts(): array
    {
        return [
            'provider' => BankProvider::class,
            'api_key' => 'encrypted',
            'is_active' => 'boolean',
            'last_received_at' => 'datetime',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
