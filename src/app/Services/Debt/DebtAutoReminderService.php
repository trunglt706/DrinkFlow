<?php

declare(strict_types=1);

namespace App\Services\Debt;

use App\Enums\DebtStatus;
use App\Enums\GlobalUserStatus;
use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Enums\RoomStatus;
use App\Enums\RoomUserStatus;
use App\Models\Debt;
use App\Models\Room;
use App\Models\RoomSetting;
use App\Models\RoomUser;
use App\Services\Notification\UserNotificationService;
use App\Support\Helpers\FormatHelper;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tiered automatic debt reminders: an outstanding campaign debt is reminded 1, 3 and 7 days after it was created,
 * from SEND_HOUR in the room's timezone. Each notification links to the debts page, which opens the debt's VietQR.
 *
 * Every (debt, stage) is claimed in `debt_reminders` (unique index) before it is sent, so overlapping runs never send
 * it twice. A debt that is already past several stages (e.g. when the feature is turned on) only gets the latest one.
 * The debtor can snooze a debt's reminders SNOOZE_DAYS at a time, at most MAX_SNOOZES times per debt.
 */
class DebtAutoReminderService
{
    /** Room setting turning the automatic reminders on or off (on by default). */
    public const SETTING_KEY = 'auto_debt_reminders';

    /** Debt age, in days, at which each reminder stage is sent. */
    public const STAGES = [1, 3, 7];

    /** Local hour (room timezone) from which the day's reminders are sent. */
    public const SEND_HOUR = 9;

    /** Days a snooze postpones the reminders of a debt. */
    public const SNOOZE_DAYS = 3;

    /** Snoozes allowed per debt. */
    public const MAX_SNOOZES = 2;

    public function __construct(private readonly UserNotificationService $notifications)
    {
    }

    /**
     * Send the due reminders of every active room whose local time has reached SEND_HOUR.
     *
     * @param Carbon|null $now Reference time (defaults to now).
     * @return int Number of reminders sent by this run.
     */
    public function remindDueRooms(?Carbon $now = null): int
    {
        $now ??= now();
        $disabled = RoomSetting::query()->where('key', self::SETTING_KEY)->whereIn('value', ['0', 'false'])->pluck('room_id')->all();
        $sent = 0;

        Room::query()->where('status', RoomStatus::Active->value)->whereNotIn('id', $disabled)->orderBy('id')
            ->each(function (Room $room) use ($now, &$sent): void {
                $sent += $this->remindRoom($room, $now);
            });

        return $sent;
    }

    /**
     * Send a room's due reminders, unless it is before SEND_HOUR in the room's timezone.
     *
     * @param Room $room Room whose debts are reminded.
     * @param Carbon $now Reference time.
     * @return int Number of reminders sent.
     */
    public function remindRoom(Room $room, Carbon $now): int
    {
        $timezone = $this->timezone($room);
        $localNow = CarbonImmutable::instance($now)->setTimezone($timezone);
        if ($localNow->hour < self::SEND_HOUR) {
            return 0;
        }

        $today = $localNow->startOfDay();
        // Debts created after this instant are less than STAGES[0] days old in the room's calendar (timestamps are
        // stored in the application timezone).
        $youngest = $today->subDays(self::STAGES[0])->endOfDay()->setTimezone((string) config('app.timezone'));
        $sent = 0;

        $this->eligibleDebtsQuery($room, $now)
            ->where('debts.created_at', '<=', $youngest)
            ->with(['campaign', 'roomUser'])
            ->chunkById(200, function ($debts) use ($room, $today, $timezone, &$sent): void {
                $lastStages = DB::table('debt_reminders')->whereIn('debt_id', $debts->modelKeys())
                    ->groupBy('debt_id')->selectRaw('debt_id, MAX(stage) AS stage')->pluck('stage', 'debt_id');

                foreach ($debts as $debt) {
                    $stage = $this->dueStage($debt, $today, $timezone);
                    if ($stage === null || (int) ($lastStages[$debt->id] ?? 0) >= $stage) {
                        continue;
                    }
                    if ($this->send($room, $debt, $stage)) {
                        $sent++;
                    }
                }
            }, 'debts.id', 'id');

        return $sent;
    }

    /**
     * Whether the automatic reminders are turned on for a room.
     *
     * @param Room $room Room to check.
     * @return bool True unless the room admin turned them off.
     */
    public function isEnabled(Room $room): bool
    {
        $value = RoomSetting::query()->where('room_id', $room->id)->where('key', self::SETTING_KEY)->value('value');

        return $value === null || filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Postpone the automatic reminders of one of the member's outstanding debts by SNOOZE_DAYS.
     *
     * @param Room $room Current room.
     * @param RoomUser $roomUser Debtor.
     * @param int $debtId Debt to snooze.
     * @return Debt Snoozed debt.
     *
     * @throws ValidationException When the debt is not an outstanding debt of the member or no snooze is left.
     */
    public function snooze(Room $room, RoomUser $roomUser, int $debtId): Debt
    {
        return DB::transaction(function () use ($room, $roomUser, $debtId): Debt {
            $debt = Debt::query()
                ->where('room_id', $room->id)
                ->where('room_user_id', $roomUser->id)
                ->whereIn('status', [DebtStatus::Unpaid->value, DebtStatus::Partial->value])
                ->where('remaining_amount', '>', 0)
                ->whereKey($debtId)
                ->lockForUpdate()
                ->first();
            if ($debt === null) {
                throw ValidationException::withMessages(['debt_id' => __('debt_reminder.snooze_not_allowed')]);
            }
            if ((int) $debt->reminder_snooze_count >= self::MAX_SNOOZES) {
                throw ValidationException::withMessages(['debt_id' => __('debt_reminder.snooze_limit_reached', ['max' => self::MAX_SNOOZES])]);
            }

            $debt->forceFill([
                'reminder_snoozed_until' => now()->addDays(self::SNOOZE_DAYS),
                'reminder_snooze_count' => (int) $debt->reminder_snooze_count + 1,
            ])->save();

            return $debt;
        });
    }

    /**
     * Snooze state of each debt, for the debts page: the date reminders resume (when snoozed) and the snoozes left.
     *
     * @param iterable<Debt> $debts Debts shown to the member.
     * @param Room $room Current room.
     * @return array<int, array{until: string|null, remaining: int}> Keyed by debt ID; empty when reminders are off.
     */
    public function snoozeStates(iterable $debts, Room $room): array
    {
        if (! $this->isEnabled($room)) {
            return [];
        }

        $timezone = $this->timezone($room);
        $states = [];
        foreach ($debts as $debt) {
            if (! in_array($debt->status, [DebtStatus::Unpaid, DebtStatus::Partial], true) || (int) $debt->remaining_amount <= 0) {
                continue;
            }
            $until = $debt->reminder_snoozed_until;
            $states[$debt->id] = [
                'until' => $until !== null && $until->isFuture() ? $until->copy()->setTimezone($timezone)->format('d/m/Y H:i') : null,
                'remaining' => max(0, self::MAX_SNOOZES - (int) $debt->reminder_snooze_count),
            ];
        }

        return $states;
    }

    /**
     * Outstanding campaign debts of active members that may be reminded now.
     *
     * Same visibility rule as the member's debts page: a debt backed only by cancelled orders is never reminded.
     *
     * @param Room $room Room whose debts are queried.
     * @param Carbon $now Reference time (snoozes ending before it no longer apply).
     * @return Builder<Debt>
     */
    private function eligibleDebtsQuery(Room $room, Carbon $now): Builder
    {
        return Debt::query()
            ->where('debts.room_id', $room->id)
            ->whereIn('debts.status', [DebtStatus::Unpaid->value, DebtStatus::Partial->value])
            ->where('debts.remaining_amount', '>', 0)
            ->notLockedByPaymentRequest()
            ->where(static fn (Builder $query) => $query->whereNull('debts.reminder_snoozed_until')->orWhere('debts.reminder_snoozed_until', '<=', $now))
            ->whereHas('roomUser', static fn (Builder $query) => $query
                ->where('status', RoomUserStatus::Active->value)
                ->whereHas('globalUser', static fn (Builder $query) => $query->where('status', GlobalUserStatus::Active->value)))
            ->where(static function (Builder $query): void {
                $query
                    ->whereExists(static function (\Illuminate\Database\Query\Builder $orders): void {
                        $orders->select(DB::raw(1))->from('orders')
                            ->whereColumn('orders.campaign_id', 'debts.campaign_id')
                            ->whereColumn('orders.room_user_id', 'debts.room_user_id')
                            ->where('orders.status', '!=', OrderStatus::Cancelled->value);
                    })
                    ->orWhereNotExists(static function (\Illuminate\Database\Query\Builder $orders): void {
                        $orders->select(DB::raw(1))->from('orders')
                            ->whereColumn('orders.campaign_id', 'debts.campaign_id')
                            ->whereColumn('orders.room_user_id', 'debts.room_user_id');
                    });
            });
    }

    /**
     * Highest stage a debt has reached today, counted in calendar days of the room's timezone.
     *
     * @return int|null Stage (days), or null when the debt is younger than the first stage.
     */
    private function dueStage(Debt $debt, CarbonImmutable $today, string $timezone): ?int
    {
        $created = CarbonImmutable::instance($debt->created_at)->setTimezone($timezone)->startOfDay();
        $age = (int) $created->diffInDays($today);
        $due = null;
        foreach (self::STAGES as $stage) {
            if ($age >= $stage) {
                $due = $stage;
            }
        }

        return $due;
    }

    /**
     * Claim the (debt, stage) row, then notify the debtor.
     *
     * @return bool False when another run already claimed this stage.
     */
    private function send(Room $room, Debt $debt, int $stage): bool
    {
        $claimed = DB::table('debt_reminders')->insertOrIgnore([
            'debt_id' => $debt->id,
            'room_id' => $room->id,
            'stage' => $stage,
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($claimed !== 1) {
            return false;
        }

        $replace = [
            'campaign' => $debt->campaign?->name ?? $debt->campaign?->code ?? '',
            'amount' => FormatHelper::formatCurrency((int) $debt->remaining_amount),
            'days' => $stage,
        ];
        $this->notifications->toRoomUser(
            $debt->roomUser,
            NotificationType::DebtReminder->value,
            __('debt_reminder.stage_'.$stage.'_title', $replace),
            __('debt_reminder.stage_'.$stage.'_body', $replace),
            [
                'debt_id' => $debt->id,
                'debt_code' => $debt->code,
                'campaign_id' => $debt->campaign_id,
                'campaign_code' => $debt->campaign?->code,
                'remaining_amount' => (int) $debt->remaining_amount,
                'stage' => $stage,
                'automatic' => true,
            ],
            // The debts page opens the VietQR of the debt named in ?debt=.
            route('user.debts.index', ['room' => $room, 'debt' => $debt->code]),
        );

        return true;
    }

    private function timezone(Room $room): string
    {
        return (string) ($room->timezone ?: config('app.display_timezone', config('app.timezone')));
    }
}
