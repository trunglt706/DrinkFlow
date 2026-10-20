<?php

declare(strict_types=1);

namespace App\Services\Occasion;

use App\Enums\RoomOccasion;
use App\Models\Room;
use App\Services\Birthday\RoomBirthdayService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Room events: members' birthdays (shown 3 days before and after) and yearly occasions (shown on the day itself,
 * Tết Nguyên Đán 7 days before and after). Feeds the events banner of room pages, gates the occasion pages and lists
 * the upcoming events with their notification status on the admin events page. Dates are in the room's timezone.
 */
class RoomEventService
{
    /** Days ahead covered by the admin list of upcoming birthdays. */
    public const UPCOMING_BIRTHDAY_DAYS = 30;

    public function __construct(private readonly RoomBirthdayService $birthdays) {}

    /**
     * Today at midnight in the room's timezone.
     */
    public function today(Room $room): CarbonImmutable
    {
        return $this->birthdays->today($room);
    }

    /**
     * Current or next occurrence of an occasion: the one being shown, otherwise the next one.
     *
     * @param RoomOccasion $occasion Occasion.
     * @param CarbonImmutable $today Today, at the start of the day.
     * @return array{date: CarbonImmutable, offset: int, active: bool} `offset`: days from today (negative once passed).
     */
    public function occurrence(RoomOccasion $occasion, CarbonImmutable $today): array
    {
        $window = $occasion->windowDays();
        foreach ([$today->year - 1, $today->year, $today->year + 1] as $year) {
            $date = CarbonImmutable::parse($occasion->dateIn($year)->toDateString(), $today->getTimezone());
            $offset = self::daysBetween($today, $date);
            if ($offset >= -$window) {
                return ['date' => $date, 'offset' => $offset, 'active' => $offset <= $window];
            }
        }

        // Unreachable: the occasion of next year is always ahead.
        $date = CarbonImmutable::parse($occasion->dateIn($today->year + 1)->toDateString(), $today->getTimezone());

        return ['date' => $date, 'offset' => self::daysBetween($today, $date), 'active' => false];
    }

    /**
     * Calendar days from one date to another, whatever their timezones (negative when `$to` is earlier).
     */
    public static function daysBetween(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return (int) CarbonImmutable::parse($from->toDateString(), 'UTC')->diffInDays(CarbonImmutable::parse($to->toDateString(), 'UTC'), false);
    }

    /**
     * Whether an occasion is shown today in the room.
     */
    public function isActive(Room $room, RoomOccasion $occasion): bool
    {
        return $this->occurrence($occasion, $this->today($room))['active'];
    }

    /**
     * Events shown in the banner of the room pages right now: occasions first, then birthdays (closest first).
     *
     * @param Room $room Room whose events are listed.
     * @return list<array{type: string, occasion?: RoomOccasion, date?: CarbonImmutable, offset: int, celebrant?: array<string, mixed>}>
     */
    public function bannerEvents(Room $room): array
    {
        $today = $this->today($room);
        $events = [];
        foreach (RoomOccasion::cases() as $occasion) {
            $occurrence = $this->occurrence($occasion, $today);
            if ($occurrence['active']) {
                $events[] = ['type' => $occasion->value, 'occasion' => $occasion, 'date' => $occurrence['date'], 'offset' => $occurrence['offset']];
            }
        }
        foreach ($this->birthdays->celebrants($room) as $celebrant) {
            $events[] = ['type' => 'birthday', 'offset' => $celebrant['offset'], 'celebrant' => $celebrant];
        }

        return $events;
    }

    /**
     * Upcoming (and currently shown) events of the room for the admin events page, by date: the current or next
     * occurrence of every occasion, and the birthdays from 3 days ago up to UPCOMING_BIRTHDAY_DAYS ahead (one row per
     * day). Each row tells whether its notification was sent and whether the admin can send it (again) now.
     *
     * @param Room $room Room whose events are listed.
     * @return list<array{type: string, occasion: RoomOccasion|null, date: CarbonImmutable, offset: int, active: bool, names: list<string>, sent_at: string|null, channel_count: int, can_send: bool}>
     */
    public function upcoming(Room $room): array
    {
        $today = $this->today($room);
        $rows = [];

        $occasionRows = DB::table('room_occasion_notifications')->where('room_id', $room->id)
            ->get(['occasion', 'year', 'sent_at', 'channel_count'])
            ->keyBy(static fn (object $row): string => $row->occasion.':'.$row->year);
        foreach (RoomOccasion::cases() as $occasion) {
            $occurrence = $this->occurrence($occasion, $today);
            $notification = $occasionRows->get($occasion->value.':'.$occurrence['date']->year);
            $sentAt = $notification?->sent_at;
            $rows[] = [
                'type' => $occasion->value,
                'occasion' => $occasion,
                'date' => $occurrence['date'],
                'offset' => $occurrence['offset'],
                'active' => $occurrence['active'],
                'names' => [],
                'sent_at' => $sentAt,
                'channel_count' => (int) ($notification?->channel_count ?? 0),
                // Sent (or sent again) from the admin page while the occasion is shown.
                'can_send' => $occurrence['active'],
            ];
        }

        $birthdaysByDay = [];
        foreach ($this->birthdays->upcoming($room, self::UPCOMING_BIRTHDAY_DAYS) as $birthday) {
            $birthdaysByDay[$birthday['offset']][] = $birthday['name'];
        }
        if ($birthdaysByDay !== []) {
            $dates = array_map(static fn (int $offset): string => $today->addDays($offset)->toDateString(), array_keys($birthdaysByDay));
            $birthdayRows = DB::table('room_birthday_notifications')->where('room_id', $room->id)
                ->whereIn('celebrated_on', $dates)->get(['celebrated_on', 'sent_at', 'channel_count'])
                ->keyBy(static fn (object $row): string => substr((string) $row->celebrated_on, 0, 10));
            foreach ($birthdaysByDay as $offset => $names) {
                $date = $today->addDays($offset);
                $notification = $birthdayRows->get($date->toDateString());
                $sentAt = $notification?->sent_at;
                $rows[] = [
                    'type' => 'birthday',
                    'occasion' => null,
                    'date' => $date,
                    'offset' => $offset,
                    'active' => abs($offset) <= RoomBirthdayService::WINDOW_DAYS,
                    'names' => $names,
                    'sent_at' => $sentAt,
                    'channel_count' => (int) ($notification?->channel_count ?? 0),
                    // The birthday notification announces "today's" birthdays: only on the day itself.
                    'can_send' => $offset === 0,
                ];
            }
        }

        usort($rows, static fn (array $a, array $b): int => [$a['offset'], $a['type']] <=> [$b['offset'], $b['type']]);

        return $rows;
    }
}
