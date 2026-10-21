<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\RateOrderItemRequest;
use App\Models\Room;
use App\Models\RoomUser;
use App\Services\Order\OrderItemRatingService;
use Illuminate\Http\JsonResponse;

/**
 * Member rates the items of their received orders (order page and room dashboard). The room middleware has checked
 * that the viewer is an active member of the room.
 */
class OrderItemRatingController extends Controller
{
    /**
     * Save the member's stars for an order item, or send their comment (which makes the rating final).
     *
     * @param RateOrderItemRequest $request Validated stars and comment.
     * @param Room $room Room of the order.
     * @param int $item Order item id.
     * @param OrderItemRatingService $ratings Ratings.
     * @return JsonResponse The rating and the dish average.
     */
    public function store(RateOrderItemRequest $request, Room $room, int $item, OrderItemRatingService $ratings): JsonResponse
    {
        $member = $request->attributes->get('room_user');
        abort_unless($member instanceof RoomUser, 403);
        $room = $request->attributes->get('room') ?? $room;
        $comment = $request->validated('comment');
        $comment = is_string($comment) ? $comment : null;

        return response()->json([
            'message' => __($comment !== null ? 'room.rating.comment_sent' : 'room.rating.saved'),
            'rating' => $ratings->rate($room, $member, $item, (int) $request->validated('stars'), $comment),
        ]);
    }
}
