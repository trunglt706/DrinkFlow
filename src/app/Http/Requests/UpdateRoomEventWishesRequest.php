<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\Room\SendRoomEventNotificationAction;
use App\Http\Requests\Concerns\AuthorizesUserAndAdmin;
use App\Services\Occasion\RoomEventSettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin sets the wishes of one room event page (its own list, or back to the default sample wishes).
 */
class UpdateRoomEventWishesRequest extends FormRequest
{
    use AuthorizesUserAndAdmin;

    public function authorize(): bool
    {
        return $this->authorizeActiveRoomAdmin();
    }

    /**
     * Blank lines are dropped (the editor may leave an empty wish).
     */
    protected function prepareForValidation(): void
    {
        $wishes = $this->input('wishes');
        if (is_array($wishes)) {
            $wishes = array_map(static fn (mixed $wish): mixed => is_string($wish) ? trim($wish) : $wish, $wishes);
            $this->merge(['wishes' => array_values(array_filter($wishes, static fn (mixed $wish): bool => $wish !== null && $wish !== ''))]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(SendRoomEventNotificationAction::types())],
            'use_default' => ['nullable', 'boolean'],
            'wishes' => ['exclude_if:use_default,true,1', 'required', 'array', 'min:1', 'max:'.RoomEventSettingsService::MAX_WISHES],
            'wishes.*' => ['exclude_if:use_default,true,1', 'required', 'string', 'max:'.RoomEventSettingsService::MAX_WISH_LENGTH, 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['wishes' => __('occasions.admin.wishes'), 'wishes.*' => __('occasions.admin.wish')];
    }
}
