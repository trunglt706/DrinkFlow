<?php

declare(strict_types=1);

namespace App\Services\Occasion;

use App\Actions\Room\SendRoomEventNotificationAction;
use App\Enums\RoomOccasion;
use App\Models\Admin;
use App\Models\Room;
use App\Models\RoomEventWish;
use App\Models\RoomEventWishHeart;
use App\Models\RoomUser;
use App\Services\Birthday\RoomBirthdayService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Wishes wall of the room event pages: while an event is shown, members write short wishes (on the birthday page,
 * to one of the celebrants) and give hearts to the others' wishes; the room admin hides the unsuitable ones.
 * Each occurrence of an event has its own wall (the occasion's day, the celebrant's birthday that year).
 */
class RoomEventWishWallService
{
    /** Longest wish (characters), wishes a member may write per event occurrence, wishes shown on a wall. */
    public const MAX_LENGTH = 280;

    public const MAX_PER_MEMBER = 5;

    public const WALL_LIMIT = 100;

    public function __construct(
        private readonly RoomEventService $events,
        private readonly RoomBirthdayService $birthdays,
    ) {}

    /**
     * Occurrence dates whose wishes make up the current wall of an event, null when the event is not shown now.
     * Locally the wall stays open like the event pages, to preview them at any date.
     *
     * @param Room $room Room.
     * @param string $type `birthday` or a RoomOccasion value.
     * @return array{0: string, 1: string}|null First and last occurrence date (Y-m-d).
     */
    public function openRange(Room $room, string $type): ?array
    {
        $today = $this->events->today($room);
        if ($type === SendRoomEventNotificationAction::TYPE_BIRTHDAY) {
            if ($this->birthdays->celebrants($room) === [] && ! app()->isLocal()) {
                return null;
            }
            $window = RoomBirthdayService::WINDOW_DAYS;

            return [$today->subDays($window)->toDateString(), $today->addDays($window)->toDateString()];
        }

        $occasion = RoomOccasion::tryFrom($type);
        if ($occasion === null) {
            return null;
        }
        $occurrence = $this->events->occurrence($occasion, $today);
        if (! $occurrence['active'] && ! app()->isLocal()) {
            return null;
        }

        return [$occurrence['date']->toDateString(), $occurrence['date']->toDateString()];
    }

    /**
     * Visible wishes of the current wall, newest first, as shown to a member.
     *
     * @param Room $room Room.
     * @param string $type `birthday` or a RoomOccasion value.
     * @param RoomUser $viewer Member viewing the wall (hearts given, own wishes).
     * @return list<array{id: int, message: string, author: string, avatar_url: string, recipient: string|null, time: string, hearts: int, hearted: bool, mine: bool}>
     */
    public function wall(Room $room, string $type, RoomUser $viewer): array
    {
        $range = $this->openRange($room, $type);
        if ($range === null) {
            return [];
        }

        $wishes = $this->wallQuery($room, $type, $range)->whereNull('hidden_at')->limit(self::WALL_LIMIT)->get();
        $hearted = RoomEventWishHeart::query()
            ->where('room_user_id', $viewer->id)
            ->whereIn('room_event_wish_id', $wishes->modelKeys())
            ->pluck('room_event_wish_id')
            ->flip();

        return $wishes->map(fn (RoomEventWish $wish): array => $this->present($room, $wish) + [
            'hearted' => $hearted->has($wish->id),
            'mine' => $wish->room_user_id === $viewer->id,
        ])->values()->all();
    }

    /**
     * Wishes of the current walls of a room for its admin (hidden ones included), keyed by event type; events not
     * shown now have no entry.
     *
     * @param Room $room Room.
     * @return array<string, list<array{id: int, message: string, author: string, avatar_url: string, recipient: string|null, time: string, hearts: int, hidden: bool}>>
     */
    public function forAdmin(Room $room): array
    {
        $walls = [];
        foreach (SendRoomEventNotificationAction::types() as $type) {
            $range = $this->openRange($room, $type);
            if ($range === null) {
                continue;
            }
            $walls[$type] = $this->wallQuery($room, $type, $range)->limit(self::WALL_LIMIT)->get()
                ->map(fn (RoomEventWish $wish): array => $this->present($room, $wish) + ['hidden' => $wish->hidden_at !== null])
                ->values()->all();
        }

        return $walls;
    }

    /**
     * Write a wish on the wall of an event being shown.
     *
     * @param Room $room Room.
     * @param RoomUser $author Member writing.
     * @param string $type `birthday` or a RoomOccasion value.
     * @param string $message Wish text (validated length).
     * @param string|null $recipientCode Birthday only: user code of the celebrant the wish is for.
     * @return RoomEventWish Created wish.
     *
     * @throws ValidationException When the event is not shown, the celebrant is not celebrated or the member reached
     *                             the limit of wishes for this occurrence.
     */
    public function post(Room $room, RoomUser $author, string $type, string $message, ?string $recipientCode): RoomEventWish
    {
        if ($this->openRange($room, $type) === null) {
            throw ValidationException::withMessages(['message' => __('occasions.wall.closed')]);
        }

        $recipientId = null;
        if ($type === SendRoomEventNotificationAction::TYPE_BIRTHDAY) {
            [$recipientId, $occurrence] = $this->celebrantOccurrence($room, (string) $recipientCode);
        } else {
            $occurrence = $this->events->occurrence(RoomOccasion::from($type), $this->events->today($room))['date'];
        }

        return DB::transaction(function () use ($room, $author, $type, $message, $recipientId, $occurrence): RoomEventWish {
            // Lock the member row so two quick posts cannot both pass the limit.
            RoomUser::query()->whereKey($author->id)->lockForUpdate()->first();
            $written = RoomEventWish::query()
                ->where('room_user_id', $author->id)
                ->where('event_type', $type)
                ->whereDate('occurrence', $occurrence->toDateString())
                ->count();
            if ($written >= self::MAX_PER_MEMBER) {
                throw ValidationException::withMessages(['message' => __('occasions.wall.limit', ['max' => self::MAX_PER_MEMBER])]);
            }

            return RoomEventWish::query()->create([
                'room_id' => $room->id,
                'event_type' => $type,
                'occurrence' => $occurrence->toDateString(),
                'room_user_id' => $author->id,
                'recipient_room_user_id' => $recipientId,
                'message' => $message,
            ]);
        });
    }

    /**
     * Give or take back a heart on a visible wish of a current wall.
     *
     * @param Room $room Room.
     * @param RoomUser $member Member giving the heart.
     * @param int $wishId Wish.
     * @return array{hearted: bool, hearts: int} State after the toggle.
     *
     * @throws NotFoundHttpException When the wish is not on a current wall of the room or is hidden.
     */
    public function toggleHeart(Room $room, RoomUser $member, int $wishId): array
    {
        return DB::transaction(function () use ($room, $member, $wishId): array {
            $wish = $this->currentWish($room, $wishId, lock: true);
            $heart = RoomEventWishHeart::query()->where('room_event_wish_id', $wish->id)->where('room_user_id', $member->id)->first();
            if ($heart !== null) {
                $heart->delete();
                $wish->decrement('hearts_count');
            } else {
                RoomEventWishHeart::query()->create(['room_event_wish_id' => $wish->id, 'room_user_id' => $member->id]);
                $wish->increment('hearts_count');
            }

            return ['hearted' => $heart === null, 'hearts' => max(0, (int) $wish->hearts_count)];
        });
    }

    /**
     * Delete one of the member's own wishes.
     *
     * @param Room $room Room.
     * @param RoomUser $member Author.
     * @param int $wishId Wish.
     *
     * @throws NotFoundHttpException When the wish is not the member's own wish in the room.
     */
    public function delete(Room $room, RoomUser $member, int $wishId): void
    {
        $deleted = RoomEventWish::query()->where('room_id', $room->id)->where('room_user_id', $member->id)->whereKey($wishId)->delete();
        if ($deleted === 0) {
            throw new NotFoundHttpException;
        }
    }

    /**
     * Hide a wish from the members (or show it again).
     *
     * @param Room $room Room.
     * @param Admin $admin Room admin moderating.
     * @param int $wishId Wish.
     * @param bool $hidden True to hide.
     *
     * @throws NotFoundHttpException When the wish is not in the room.
     */
    public function setHidden(Room $room, Admin $admin, int $wishId, bool $hidden): void
    {
        $wish = RoomEventWish::query()->where('room_id', $room->id)->findOr($wishId, static fn () => throw new NotFoundHttpException);
        $wish->update($hidden
            ? ['hidden_at' => $wish->hidden_at ?? now(), 'hidden_by_admin_id' => $wish->hidden_by_admin_id ?? $admin->id]
            : ['hidden_at' => null, 'hidden_by_admin_id' => null]);
    }

    /**
     * Wishes of the given occurrence range, newest first, with their author and recipient.
     *
     * @param array{0: string, 1: string} $range First and last occurrence date.
     * @return \Illuminate\Database\Eloquent\Builder<RoomEventWish>
     */
    private function wallQuery(Room $room, string $type, array $range): \Illuminate\Database\Eloquent\Builder
    {
        return RoomEventWish::query()
            ->where('room_id', $room->id)
            ->where('event_type', $type)
            ->whereDate('occurrence', '>=', $range[0])
            ->whereDate('occurrence', '<=', $range[1])
            ->with(['author.globalUser:id,name,avatar_url', 'recipient.globalUser:id,name'])
            ->latest('id');
    }

    /**
     * Visible wish of a current wall of the room.
     *
     * @throws NotFoundHttpException When there is none.
     */
    private function currentWish(Room $room, int $wishId, bool $lock = false): RoomEventWish
    {
        $query = RoomEventWish::query()->where('room_id', $room->id)->whereNull('hidden_at')->whereKey($wishId);
        $wish = ($lock ? $query->lockForUpdate() : $query)->first();
        $range = $wish === null ? null : $this->openRange($room, $wish->event_type);
        $date = $wish?->occurrence?->toDateString();
        if ($range === null || $date < $range[0] || $date > $range[1]) {
            throw new NotFoundHttpException;
        }

        return $wish;
    }

    /**
     * Member id and birthday date of a celebrant being celebrated.
     *
     * @return array{0: int, 1: CarbonImmutable}
     *
     * @throws ValidationException When the code is not one of the current celebrants.
     */
    private function celebrantOccurrence(Room $room, string $code): array
    {
        foreach ($this->birthdays->celebrants($room) as $celebrant) {
            if ($code !== '' && hash_equals($celebrant['user_code'], $code)) {
                $member = RoomUser::query()->where('room_id', $room->id)->where('user_code', $code)->first();
                if ($member !== null) {
                    return [$member->id, $this->events->today($room)->addDays($celebrant['offset'])];
                }
            }
        }

        throw ValidationException::withMessages(['recipient' => __('occasions.wall.recipient_invalid')]);
    }

    /**
     * Fields of a wish shown on a wall.
     *
     * @return array{id: int, message: string, author: string, avatar_url: string, recipient: string|null, time: string, hearts: int}
     */
    private function present(Room $room, RoomEventWish $wish): array
    {
        $timezone = $this->events->today($room)->getTimezone();

        return [
            'id' => $wish->id,
            'message' => $wish->message,
            'author' => $wish->author?->payerName() ?: __('global.common.user'),
            'avatar_url' => (string) ($wish->author?->globalUser?->avatar_url ?? ''),
            'recipient' => $wish->recipient?->payerName(),
            'time' => $wish->created_at?->timezone($timezone)->format('H:i d/m') ?? '',
            'hearts' => max(0, (int) $wish->hearts_count),
        ];
    }
}
