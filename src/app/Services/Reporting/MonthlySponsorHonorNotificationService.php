<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\GlobalUserStatus;
use App\Enums\NotificationType;
use App\Enums\RoomUserStatus;
use App\Models\Room;
use App\Models\RoomUser;
use App\Services\Notification\RoomNotificationChannelDispatcher;
use App\Services\Notification\UserNotificationService;
use App\Support\Helpers\FormatHelper;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Top 3 sponsors announcement: once a month's statistics are rebuilt, every active member of the room receives an
 * in-app notification (pushed live over the socket) listing the month's medal winners, and the room's notification
 * channel gateways (Telegram, Discord, Slack, webhooks...) get the same list. Once per room and month.
 */
class MonthlySponsorHonorNotificationService
{
    private const MEDALS = [1 => '🥇', 2 => '🥈', 3 => '🥉'];

    public function __construct(
        private readonly MonthlySponsorStatisticsService $statistics,
        private readonly UserNotificationService $userNotifications,
        private readonly RoomNotificationChannelDispatcher $channels,
    ) {}

    /**
     * Announce a room's top 3 sponsors of a month, at most once.
     *
     * @param Room $room Room whose statistics were just rebuilt.
     * @param CarbonImmutable $month Any day of the announced month.
     * @return bool False when the month has no sponsor or was already announced.
     */
    public function notifyRoom(Room $room, CarbonImmutable $month): bool
    {
        $month = $month->startOfMonth();
        $honors = $this->statistics->monthHonors($room, $month);
        if ($honors['sponsors'] === []) {
            return false;
        }

        // The unique index (room_id, month) lets only one run announce it.
        $claimed = DB::table('room_sponsor_honor_notifications')->insertOrIgnore([
            'room_id' => $room->id,
            'month' => $month->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]) === 1;
        if (! $claimed) {
            return false;
        }

        $type = NotificationType::SponsorHonors->value;
        $link = route('user.dashboard', $room);
        $title = __('sponsor_honors.notify_title', ['month' => $honors['month']]);
        $list = $this->podiumLines($honors['sponsors']);
        $data = ['room_id' => $room->id, 'month' => $month->format('Y-m'), 'sponsors' => $honors['sponsors']];

        $members = 0;
        RoomUser::query()->where('room_id', $room->id)->where('status', RoomUserStatus::Active->value)
            ->whereHas('globalUser', static fn ($query) => $query->where('status', GlobalUserStatus::Active->value))
            ->chunkById(100, function ($roomUsers) use ($type, $title, $list, $data, $link, $room, &$members): void {
                foreach ($roomUsers as $roomUser) {
                    $this->userNotifications->toRoomUser(
                        $roomUser,
                        $type,
                        $title,
                        __('sponsor_honors.notify_body', ['room' => $room->name])."\n".$list,
                        $data,
                        $link,
                    );
                    $members++;
                }
            });

        $channelCount = $this->channels->dispatch($room, [
            'event' => $type,
            'title' => $title,
            'message' => __('sponsor_honors.notify_channel', ['room' => $room->name, 'month' => $honors['month']])
                ."\n".$list."\n".__('sponsor_honors.notify_link', ['url' => $link]),
            'url' => $link,
        ]);

        DB::table('room_sponsor_honor_notifications')->where('room_id', $room->id)->where('month', $month->toDateString())
            ->update(['member_count' => $members, 'channel_count' => $channelCount, 'sent_at' => now(), 'updated_at' => now()]);

        return true;
    }

    /**
     * One line per medal: "🥇 TOP 1 · Name · 120.000đ".
     *
     * @param list<array{name: string, amount: int, campaigns: int}> $sponsors Podium, gold first.
     */
    private function podiumLines(array $sponsors): string
    {
        $lines = [];
        foreach (array_values($sponsors) as $index => $sponsor) {
            $lines[] = __('sponsor_honors.notify_line', [
                'medal' => self::MEDALS[$index + 1] ?? '',
                'rank' => $index + 1,
                'name' => $sponsor['name'],
                'amount' => FormatHelper::formatCurrency($sponsor['amount']),
            ]);
        }

        return implode("\n", $lines);
    }
}
