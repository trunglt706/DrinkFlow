<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Helpers\LunarCalendar;
use Carbon\CarbonImmutable;

/**
 * Yearly occasions a room celebrates: each has its own page (/rooms/{room}/{slug}), a slide in the room events banner
 * while it is shown, and one greeting notification per room and year (see room_occasion_notifications).
 * Members' birthdays are the other kind of room event (App\Services\Birthday).
 */
enum RoomOccasion: string
{
    case NewYear = 'new_year';
    case LunarNewYear = 'lunar_new_year';
    case InternationalWomensDay = 'intl_womens_day';
    case WomensDay = 'womens_day';
    case MensDay = 'mens_day';
    case Christmas = 'christmas';

    /**
     * URL segment of the occasion page.
     */
    public function slug(): string
    {
        return match ($this) {
            self::NewYear => '1-1',
            self::LunarNewYear => 'tet',
            self::InternationalWomensDay => '8-3',
            self::WomensDay => '20-10',
            self::MensDay => '19-11',
            self::Christmas => '25-12',
        };
    }

    /**
     * Occasion of a page URL segment.
     */
    public static function fromSlug(string $slug): ?self
    {
        foreach (self::cases() as $occasion) {
            if ($occasion->slug() === $slug) {
                return $occasion;
            }
        }

        return null;
    }

    /**
     * Route constraint matching every occasion slug.
     */
    public static function slugPattern(): string
    {
        return implode('|', array_map(static fn (self $occasion): string => preg_quote($occasion->slug(), '/'), self::cases()));
    }

    /**
     * Days before and after the occasion during which it is shown (0: only on the day itself).
     */
    public function windowDays(): int
    {
        return $this === self::LunarNewYear ? 7 : 0;
    }

    /**
     * Date of the occasion in a Gregorian year.
     */
    public function dateIn(int $year): CarbonImmutable
    {
        return (match ($this) {
            self::NewYear => CarbonImmutable::create($year, 1, 1),
            self::LunarNewYear => LunarCalendar::lunarNewYear($year),
            self::InternationalWomensDay => CarbonImmutable::create($year, 3, 8),
            self::WomensDay => CarbonImmutable::create($year, 10, 20),
            self::MensDay => CarbonImmutable::create($year, 11, 19),
            self::Christmas => CarbonImmutable::create($year, 12, 25),
        })->startOfDay();
    }

    /**
     * Emoji shown on the banner slide and in the admin list.
     */
    public function emoji(): string
    {
        return match ($this) {
            self::NewYear => '🎆',
            self::LunarNewYear => '🧧',
            self::InternationalWomensDay => '🌷',
            self::WomensDay => '🌹',
            self::MensDay => '🏆',
            self::Christmas => '🎄',
        };
    }
}
