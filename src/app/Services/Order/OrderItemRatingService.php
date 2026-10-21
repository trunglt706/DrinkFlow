<?php

declare(strict_types=1);

namespace App\Services\Order;

use App\Enums\OrderStatus;
use App\Models\Campaign;
use App\Models\CampaignItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OrderItemRating;
use App\Models\Room;
use App\Models\RoomUser;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Ratings of the dishes: once an order is received (delivering or completed), its member rates each item with 1–5
 * stars and a short comment. The ratings of a dish (same restaurant and item name, normalized) are averaged across
 * the room's campaigns and shown on the ordering page; the room dashboard lists recently received items to rate.
 */
class OrderItemRatingService
{
    /** Stars range, longest comment (characters), days an order stays on the dashboard "to rate" list. */
    public const MIN_STARS = 1;

    public const MAX_STARS = 5;

    public const MAX_COMMENT = 200;

    public const PENDING_DAYS = 7;

    public const PENDING_LIMIT = 5;

    /**
     * Order statuses whose items have been received and can be rated.
     *
     * @return list<string>
     */
    public static function ratableStatuses(): array
    {
        return [OrderStatus::Delivering->value, OrderStatus::Completed->value];
    }

    /**
     * Rate an item of the member's own received order. The stars can be changed until the member sends a comment;
     * the comment is sent once and makes the rating final.
     *
     * @param Room $room Room of the order.
     * @param RoomUser $member Member rating; must own the order.
     * @param int $orderItemId Order item.
     * @param int $stars 1–5 (validated).
     * @param string|null $comment Comment being sent (validated, not blank), null when only the stars are given.
     * @return array{stars: int, comment: string|null, commented_at: string|null, locked: bool, average: float, count: int}
     *         The member's rating and the dish average.
     *
     * @throws NotFoundHttpException When the item is not on a received order of the member in the room.
     * @throws ValidationException When the rating is already final (comment sent).
     */
    public function rate(Room $room, RoomUser $member, int $orderItemId, int $stars, ?string $comment): array
    {
        $item = OrderItem::query()
            ->whereKey($orderItemId)
            ->whereHas('order', static fn ($query) => $query
                ->where('room_id', $room->id)
                ->where('room_user_id', $member->id)
                ->whereIn('status', self::ratableStatuses()))
            ->with('order.campaign:id,restaurant')
            ->first();
        if ($item === null) {
            throw new NotFoundHttpException;
        }

        $keys = $this->keys((string) $item->order?->campaign?->restaurant, (string) $item->item_name);
        $comment = $comment === null ? null : trim($comment);
        $rating = DB::transaction(function () use ($room, $member, $item, $keys, $stars, $comment): OrderItemRating {
            $rating = OrderItemRating::query()
                ->where('order_item_id', $item->id)
                ->where('room_user_id', $member->id)
                ->lockForUpdate()
                ->first() ?? new OrderItemRating(['order_item_id' => $item->id, 'room_user_id' => $member->id]);
            if ($rating->commented_at !== null) {
                throw ValidationException::withMessages(['comment' => __('room.rating.locked')]);
            }
            $rating->fill(['room_id' => $room->id, ...$keys, 'stars' => $stars]);
            if ($comment !== null && $comment !== '') {
                $rating->fill(['comment' => $comment, 'commented_at' => now()]);
            }
            $rating->save();

            return $rating;
        });
        $average = $this->averages($room->id, [$keys])[$this->dishKey($keys)] ?? ['average' => (float) $stars, 'count' => 1];

        return $this->state($room, $rating) + $average;
    }

    /**
     * The member's rating as shown by the rating widget.
     *
     * @return array{stars: int|null, comment: string|null, commented_at: string|null, locked: bool}
     */
    private function state(Room $room, ?OrderItemRating $rating): array
    {
        $timezone = (string) ($room->timezone ?: config('app.display_timezone', config('app.timezone')));

        return [
            'stars' => $rating?->stars,
            'comment' => $rating?->comment,
            'commented_at' => $rating?->commented_at?->copy()->timezone($timezone)->format('H:i d/m/Y'),
            'locked' => $rating?->commented_at !== null,
        ];
    }

    /**
     * The member's own ratings of order items, keyed by order item id, with whether each item can be rated.
     *
     * @param Collection<int, OrderItem> $items Items of the order(s) shown (with their order loaded).
     * @param RoomUser $member Viewer.
     * @return array<int, array{can_rate: bool, url: string, stars: int|null, comment: string|null, commented_at: string|null, locked: bool}>
     */
    public function forItems(Room $room, Collection $items, RoomUser $member): array
    {
        $ratings = OrderItemRating::query()
            ->where('room_user_id', $member->id)
            ->whereIn('order_item_id', $items->modelKeys())
            ->get()
            ->keyBy('order_item_id');

        $states = [];
        foreach ($items as $item) {
            /** @var OrderItem $item */
            $order = $item->order;
            $canRate = $order instanceof Order
                && $order->room_user_id === $member->id
                && in_array($order->status instanceof OrderStatus ? $order->status->value : (string) $order->status, self::ratableStatuses(), true);
            $states[$item->id] = ['can_rate' => $canRate, 'url' => route('user.order-items.rating', [$room, $item->id])]
                + $this->state($room, $ratings->get($item->id));
        }

        return $states;
    }

    /**
     * Average rating of each item of a campaign menu (ratings of the same dish in earlier campaigns of the room).
     *
     * @param Campaign $campaign Campaign whose menu is shown.
     * @param iterable<CampaignItem> $items Menu items.
     * @return array<int, array{average: float, count: int}> Keyed by campaign item id; unrated items have no entry.
     */
    public function averagesForMenu(Campaign $campaign, iterable $items): array
    {
        $keysById = [];
        foreach ($items as $item) {
            $keysById[$item->id] = $this->keys((string) $campaign->restaurant, (string) $item->name);
        }
        if ($keysById === []) {
            return [];
        }

        $averages = $this->averages((int) $campaign->room_id, array_values($keysById));
        $result = [];
        foreach ($keysById as $id => $keys) {
            if (isset($averages[$this->dishKey($keys)])) {
                $result[$id] = $averages[$this->dishKey($keys)];
            }
        }

        return $result;
    }

    /**
     * Items of the member's recently received orders that they have not rated yet (room dashboard).
     *
     * @param Room $room Room.
     * @param RoomUser $member Member.
     * @return list<array{id: int, name: string, restaurant: string, image_url: string|null, url: string}>
     */
    public function pending(Room $room, RoomUser $member): array
    {
        return OrderItem::query()
            ->whereHas('order', static fn ($query) => $query
                ->where('room_id', $room->id)
                ->where('room_user_id', $member->id)
                ->whereIn('status', self::ratableStatuses())
                ->where('created_at', '>=', now()->subDays(self::PENDING_DAYS)))
            ->whereDoesntHave('ratings', static fn ($query) => $query->where('room_user_id', $member->id))
            ->with(['order.campaign:id,restaurant', 'campaignItem:id,image_url'])
            ->latest('id')
            ->limit(self::PENDING_LIMIT)
            ->get()
            ->map(static fn (OrderItem $item): array => [
                'id' => $item->id,
                'name' => (string) $item->item_name,
                'restaurant' => (string) $item->order?->campaign?->restaurant,
                'image_url' => $item->campaignItem?->image_url,
                'url' => route('user.order-items.rating', [$room, $item->id]),
            ])
            ->all();
    }

    /**
     * Average and count of the given dishes in a room.
     *
     * @param int $roomId Room.
     * @param list<array{restaurant_key: string, item_key: string}> $dishes Dishes.
     * @return array<string, array{average: float, count: int}> Keyed by dishKey().
     */
    private function averages(int $roomId, array $dishes): array
    {
        $rows = OrderItemRating::query()
            ->where('room_id', $roomId)
            ->whereIn('restaurant_key', array_unique(array_column($dishes, 'restaurant_key')))
            ->whereIn('item_key', array_unique(array_column($dishes, 'item_key')))
            ->groupBy('restaurant_key', 'item_key')
            ->select(['restaurant_key', 'item_key', DB::raw('AVG(stars) as average'), DB::raw('COUNT(*) as total')])
            ->get();

        $averages = [];
        foreach ($rows as $row) {
            $averages[$this->dishKey(['restaurant_key' => $row->restaurant_key, 'item_key' => $row->item_key])] = [
                'average' => round((float) $row->average, 1),
                'count' => (int) $row->total,
            ];
        }

        return $averages;
    }

    /**
     * Normalized restaurant and item names identifying a dish.
     *
     * @return array{restaurant_key: string, item_key: string}
     */
    private function keys(string $restaurant, string $item): array
    {
        return [
            'restaurant_key' => mb_substr(CampaignItem::normalizeString($restaurant), 0, 191),
            'item_key' => mb_substr(CampaignItem::normalizeString($item), 0, 191),
        ];
    }

    /**
     * @param array{restaurant_key: string, item_key: string} $keys
     */
    private function dishKey(array $keys): string
    {
        return $keys['restaurant_key']."\n".$keys['item_key'];
    }
}
