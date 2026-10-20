<?php

declare(strict_types=1);

namespace App\Support\Helpers;

use Carbon\CarbonImmutable;

/**
 * Vietnamese lunar calendar (Hồ Ngọc Đức's astronomical algorithm, UTC+7), used to place Tết Nguyên Đán.
 * Pure arithmetic on Julian day numbers: no table to keep up to date.
 */
final class LunarCalendar
{
    /** Time zone of the Vietnamese calendar, in hours. */
    private const TIME_ZONE = 7.0;

    /**
     * Solar date of the first day of the first lunar month (Tết Nguyên Đán) of a year.
     *
     * @param int $year Gregorian year in which that Tết falls (between late January and mid February).
     */
    public static function lunarNewYear(int $year): CarbonImmutable
    {
        return self::toSolar(1, 1, $year);
    }

    /**
     * Solar date of a (non-leap) lunar date.
     *
     * @param int $day Lunar day (1-30).
     * @param int $month Lunar month (1-12).
     * @param int $year Lunar year.
     */
    public static function toSolar(int $day, int $month, int $year): CarbonImmutable
    {
        [$a11, $b11] = $month < 11
            ? [self::lunarMonth11($year - 1), self::lunarMonth11($year)]
            : [self::lunarMonth11($year), self::lunarMonth11($year + 1)];
        $k = (int) floor(0.5 + ($a11 - 2415021.076998695) / 29.530588853);
        $offset = $month - 11;
        if ($offset < 0) {
            $offset += 12;
        }
        // A leap year has 13 months: every month after the leap one moves one new moon later.
        if ($b11 - $a11 > 365 && $offset >= self::leapMonthOffset($a11)) {
            $offset++;
        }

        return self::fromJulianDay(self::newMoonDay($k + $offset) + $day - 1);
    }

    private static function julianDay(int $day, int $month, int $year): int
    {
        $a = intdiv(14 - $month, 12);
        $y = $year + 4800 - $a;
        $m = $month + 12 * $a - 3;

        return $day + intdiv(153 * $m + 2, 5) + 365 * $y + intdiv($y, 4) - intdiv($y, 100) + intdiv($y, 400) - 32045;
    }

    private static function fromJulianDay(int $jd): CarbonImmutable
    {
        $a = $jd + 32044;
        $b = intdiv(4 * $a + 3, 146097);
        $c = $a - intdiv($b * 146097, 4);
        $d = intdiv(4 * $c + 3, 1461);
        $e = $c - intdiv(1461 * $d, 4);
        $m = intdiv(5 * $e + 2, 153);

        return CarbonImmutable::create(
            $b * 100 + $d - 4800 + intdiv($m, 10),
            $m + 3 - 12 * intdiv($m, 10),
            $e - intdiv(153 * $m + 2, 5) + 1,
        )->startOfDay();
    }

    /**
     * Julian day of the k-th new moon after 1 January 1900, in the calendar time zone.
     */
    private static function newMoonDay(int $k): int
    {
        $t = $k / 1236.85;
        $t2 = $t * $t;
        $t3 = $t2 * $t;
        $dr = M_PI / 180;
        $jd1 = 2415020.75933 + 29.53058868 * $k + 0.0001178 * $t2 - 0.000000155 * $t3
            + 0.00033 * sin((166.56 + 132.87 * $t - 0.009173 * $t2) * $dr);
        $m = 359.2242 + 29.10535608 * $k - 0.0000333 * $t2 - 0.00000347 * $t3;
        $mpr = 306.0253 + 385.81691806 * $k + 0.0107306 * $t2 + 0.00001236 * $t3;
        $f = 21.2964 + 390.67050646 * $k - 0.0016528 * $t2 - 0.00000239 * $t3;
        $c1 = (0.1734 - 0.000393 * $t) * sin($m * $dr) + 0.0021 * sin(2 * $dr * $m)
            - 0.4068 * sin($mpr * $dr) + 0.0161 * sin($dr * 2 * $mpr)
            - 0.0004 * sin($dr * 3 * $mpr)
            + 0.0104 * sin($dr * 2 * $f) - 0.0051 * sin($dr * ($m + $mpr))
            - 0.0074 * sin($dr * ($m - $mpr)) + 0.0004 * sin($dr * (2 * $f + $m))
            - 0.0004 * sin($dr * (2 * $f - $m)) - 0.0006 * sin($dr * (2 * $f + $mpr))
            + 0.0010 * sin($dr * (2 * $f - $mpr)) + 0.0005 * sin($dr * (2 * $mpr + $m));
        $deltaT = $t < -11
            ? 0.001 + 0.000839 * $t + 0.0002261 * $t2 - 0.00000845 * $t3 - 0.000000081 * $t * $t3
            : -0.000278 + 0.000265 * $t + 0.000262 * $t2;

        return (int) floor($jd1 + $c1 - $deltaT + 0.5 + self::TIME_ZONE / 24);
    }

    /**
     * Sector (0-11, 30° each) of the sun's longitude at the start of a Julian day.
     */
    private static function sunLongitudeSector(int $jdn): int
    {
        $t = ($jdn - 0.5 - self::TIME_ZONE / 24 - 2451545.0) / 36525;
        $t2 = $t * $t;
        $dr = M_PI / 180;
        $m = 357.52910 + 35999.05030 * $t - 0.0001559 * $t2 - 0.00000048 * $t * $t2;
        $l0 = 280.46645 + 36000.76983 * $t + 0.0003032 * $t2;
        $dl = (1.914600 - 0.004817 * $t - 0.000014 * $t2) * sin($dr * $m)
            + (0.019993 - 0.000101 * $t) * sin($dr * 2 * $m) + 0.000290 * sin($dr * 3 * $m);
        $l = ($l0 + $dl) * $dr;
        $l -= M_PI * 2 * floor($l / (M_PI * 2));

        return (int) floor($l / M_PI * 6);
    }

    /**
     * Julian day of the start of the 11th lunar month (the one containing the winter solstice) of a year.
     */
    private static function lunarMonth11(int $year): int
    {
        $k = (int) floor((self::julianDay(31, 12, $year) - 2415021) / 29.530588853);
        $newMoon = self::newMoonDay($k);

        return self::sunLongitudeSector($newMoon) >= 9 ? self::newMoonDay($k - 1) : $newMoon;
    }

    /**
     * Offset, from the 11th month starting at `$a11`, of the leap month (the first one without a solar term).
     */
    private static function leapMonthOffset(int $a11): int
    {
        $k = (int) floor(($a11 - 2415021.076998695) / 29.530588853 + 0.5);
        $i = 1;
        $arc = self::sunLongitudeSector(self::newMoonDay($k + $i));
        do {
            $last = $arc;
            $i++;
            $arc = self::sunLongitudeSector(self::newMoonDay($k + $i));
        } while ($arc !== $last && $i < 14);

        return $i - 1;
    }
}
