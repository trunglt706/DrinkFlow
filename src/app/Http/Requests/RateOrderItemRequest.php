<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesUserAndAdmin;
use App\Services\Order\OrderItemRatingService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Member rates an item of one of their received orders (1–5 stars, optional short comment).
 */
class RateOrderItemRequest extends FormRequest
{
    use AuthorizesUserAndAdmin;

    /**
     * @return bool True when an active room member is signed in.
     */
    public function authorize(): bool
    {
        return $this->authorizeActiveRoomUser();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'stars' => ['required', 'integer', 'between:'.OrderItemRatingService::MIN_STARS.','.OrderItemRatingService::MAX_STARS],
            // Sent only with the comment button (stars alone leave it out); it cannot be blank.
            'comment' => ['sometimes', 'required', 'string', 'max:'.OrderItemRatingService::MAX_COMMENT],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['stars' => __('room.rating.stars'), 'comment' => __('room.rating.comment')];
    }
}
