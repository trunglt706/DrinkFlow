<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle of a game room: created waiting, the owner starts it (first round opened) and finishes it by hand.
 */
enum GameRoomStatus: string
{
    case Waiting = 'waiting';
    case Playing = 'playing';
    case Finished = 'finished';

    /**
     * Translated label shown next to the room name.
     *
     * @return string Label in the current locale.
     */
    public function label(): string
    {
        return __('games.status.'.$this->value);
    }

    /**
     * Whether new results and rounds are accepted.
     *
     * @return bool True only while playing.
     */
    public function acceptsResults(): bool
    {
        return $this === self::Playing;
    }

    /**
     * Whether the room still counts as active in the owner's list filter.
     *
     * @return bool True for waiting and playing rooms.
     */
    public function isActive(): bool
    {
        return $this !== self::Finished;
    }
}
