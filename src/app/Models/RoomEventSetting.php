<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventMusicMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Music and sound settings of one event page of a room (see App\Services\Occasion\RoomEventSettingsService).
 */
class RoomEventSetting extends Model
{
    protected $fillable = ['room_id', 'event_type', 'music_mode', 'music_url', 'music_path', 'music_name', 'sound_effects', 'wishes'];

    protected function casts(): array
    {
        return ['music_mode' => EventMusicMode::class, 'sound_effects' => 'boolean', 'wishes' => 'array'];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
