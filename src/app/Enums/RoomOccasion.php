<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Yearly occasions a room is greeted on (one notification per room and year, see room_occasion_notifications).
 */
enum RoomOccasion: string
{
    /** Vietnamese Women's Day, 20 October. */
    case WomensDay = 'womens_day';

    /**
     * Month of the occasion (1-12).
     */
    public function month(): int
    {
        return match ($this) {
            self::WomensDay => 10,
        };
    }

    /**
     * Day of the month of the occasion.
     */
    public function day(): int
    {
        return match ($this) {
            self::WomensDay => 20,
        };
    }
}
