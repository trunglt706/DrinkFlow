<?php

declare(strict_types=1);

namespace App\Services\Birthday;

use App\Enums\GlobalUserStatus;
use App\Enums\RoomUserStatus;
use App\Models\GlobalUser;
use App\Models\Room;
use App\Models\RoomUser;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use App\Support\Helpers\MarkdownHelper;
use Illuminate\Support\Facades\Cache;

/**
 * Birthdays of a room's members around today (room timezone): they drive the banner on every room page and the
 * celebration page. Only the name, day and month leave this service, never the birth year.
 */
class RoomBirthdayService
{
    /** Days before and after a birthday during which it is celebrated. */
    public const WINDOW_DAYS = 3;

    /** Seconds the celebrants of a room are cached (the list only changes with the date or a profile edit). */
    private const CACHE_SECONDS = 300;

    /**
     * Members of the room whose birthday is within WINDOW_DAYS of today, closest first.
     *
     * @param Room $room Room whose members are checked.
     * @return list<array{user_code: string, name: string, avatar_url: string, day: int, month: int, offset: int, joined_on: string|null, bio_html: string}>
     *         `offset` is the number of days from today to the birthday (negative once it has passed).
     */
    public function celebrants(Room $room): array
    {
        $today = $this->today($room);

        return Cache::remember(
            "room:{$room->id}:birthdays:{$today->toDateString()}",
            self::CACHE_SECONDS,
            fn (): array => $this->computeCelebrants($room, $today),
        );
    }

    /**
     * Signed number of days from `$today` to the closest yearly occurrence of a birthday (29 February is celebrated
     * on 28 February in non-leap years).
     *
     * @param CarbonImmutable $birthday Birth date (the year is ignored).
     * @param CarbonImmutable $today Today, at the start of the day.
     * @return int Days until the birthday, negative when it has passed.
     */
    public function offsetFromToday(CarbonImmutable $birthday, CarbonImmutable $today): int
    {
        $best = null;
        foreach ([$today->year - 1, $today->year, $today->year + 1] as $year) {
            $day = min($birthday->day, CarbonImmutable::create($year, $birthday->month, 1)->daysInMonth);
            $occurrence = CarbonImmutable::create($year, $birthday->month, $day)->startOfDay();
            $offset = (int) $today->diffInDays($occurrence, false);
            if ($best === null || abs($offset) < abs($best)) {
                $best = $offset;
            }
        }

        return (int) $best;
    }

    /**
     * Number of active members of the room born in the current month (room timezone), for the admin menu badge.
     */
    public function monthCount(Room $room): int
    {
        return RoomUser::query()
            ->where('room_id', $room->id)
            ->where('status', RoomUserStatus::Active->value)
            ->whereHas('globalUser', fn (Builder $query) => $query
                ->where('status', GlobalUserStatus::Active->value)
                ->whereMonth('birthday', $this->today($room)->month))
            ->count();
    }

    /**
     * Whether a birthday falls in the room's current month.
     *
     * @param Room $room Room giving the timezone.
     * @param \DateTimeInterface|null $birthday Birth date, if any.
     */
    public function isInCurrentMonth(Room $room, ?\DateTimeInterface $birthday): bool
    {
        return $birthday !== null && (int) $birthday->format('n') === $this->today($room)->month;
    }

    /**
     * Drop the cached celebrants of every room of a member (their birthday or name just changed).
     *
     * @param GlobalUser $user Member whose rooms are refreshed.
     */
    public function forgetForUser(GlobalUser $user): void
    {
        Room::query()->whereIn('id', $user->roomUsers()->select('room_id'))->get(['id', 'timezone'])
            ->each(fn (Room $room) => Cache::forget("room:{$room->id}:birthdays:{$this->today($room)->toDateString()}"));
    }

    /**
     * Today at midnight in the room's timezone.
     */
    public function today(Room $room): CarbonImmutable
    {
        $timezone = (string) ($room->timezone ?: config('app.display_timezone', config('app.timezone')));

        return CarbonImmutable::now($timezone)->startOfDay();
    }

    /**
     * Placeholder celebrant (birthday today) used to preview the celebration page locally when nobody is celebrated.
     *
     * @param Room $room Room whose page is previewed.
     * @return array{user_code: string, name: string, avatar_url: string, day: int, month: int, offset: int, joined_on: null, bio_html: string}
     */
    public function demoCelebrant(Room $room): array
    {
        $today = $this->today($room);

        return ['user_code' => 'DEMO', 'name' => 'Demo', 'avatar_url' => '', 'day' => $today->day, 'month' => $today->month, 'offset' => 0, 'joined_on' => null, 'bio_html' => ''];
    }

    /**
     * Members of the room whose birthday is from WINDOW_DAYS ago up to `$daysAhead` days from now, for the admin
     * events page. Only names, day and month, never the birth year.
     *
     * @param Room $room Room whose members are checked.
     * @param int $daysAhead Last day (from today) included.
     * @return list<array{name: string, day: int, month: int, offset: int}> Closest date first.
     */
    public function upcoming(Room $room, int $daysAhead): array
    {
        $today = $this->today($room);
        $birthdays = [];
        foreach ($this->membersWithBirthday($room) as $member) {
            $birthday = CarbonImmutable::parse($member->globalUser->birthday->toDateString());
            $offset = $this->offsetFromToday($birthday, $today);
            if ($offset < -self::WINDOW_DAYS || $offset > $daysAhead) {
                continue;
            }
            $birthdays[] = [
                'name' => (string) ($member->payerName() ?: $member->globalUser->name),
                'day' => $birthday->day,
                'month' => $birthday->month,
                'offset' => $offset,
            ];
        }
        usort($birthdays, static fn (array $a, array $b): int => [$a['offset'], $a['name']] <=> [$b['offset'], $b['name']]);

        return $birthdays;
    }

    /**
     * Active members of the room (active accounts) who entered their birthday.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, RoomUser>
     */
    private function membersWithBirthday(Room $room): \Illuminate\Database\Eloquent\Collection
    {
        return RoomUser::query()
            ->where('room_id', $room->id)
            ->where('status', RoomUserStatus::Active->value)
            ->whereHas('globalUser', static fn (Builder $query) => $query
                ->where('status', GlobalUserStatus::Active->value)
                ->whereNotNull('birthday'))
            ->with('globalUser:id,name,avatar_url,birthday,bio')
            ->get();
    }

    /**
     * @return list<array{user_code: string, name: string, avatar_url: string, day: int, month: int, offset: int}>
     */
    private function computeCelebrants(Room $room, CarbonImmutable $today): array
    {
        $celebrants = [];
        foreach ($this->membersWithBirthday($room) as $member) {
            $birthday = CarbonImmutable::parse($member->globalUser->birthday->toDateString());
            $offset = $this->offsetFromToday($birthday, $today);
            if (abs($offset) > self::WINDOW_DAYS) {
                continue;
            }
            $celebrants[] = [
                'user_code' => (string) $member->user_code,
                'name' => (string) ($member->payerName() ?: $member->globalUser->name),
                'avatar_url' => (string) $member->globalUser->avatar_url,
                'day' => $birthday->day,
                'month' => $birthday->month,
                'offset' => $offset,
                // Shown on the celebration page card: since when they are in the room, and their bio (escaped Markdown).
                'joined_on' => ($member->joined_at ?? $member->created_at)?->toDateString(),
                'bio_html' => MarkdownHelper::toSafeHtml((string) ($member->globalUser->bio ?? '')),
            ];
        }

        usort($celebrants, static fn (array $a, array $b): int => [abs($a['offset']), $a['name']] <=> [abs($b['offset']), $b['name']]);

        return $celebrants;
    }
}
