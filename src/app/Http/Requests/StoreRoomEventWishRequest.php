<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesUserAndAdmin;
use App\Services\Occasion\RoomEventWishWallService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Member writes a wish on the wishes wall of a room event page.
 */
class StoreRoomEventWishRequest extends FormRequest
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
     * Blank lines and surrounding spaces are not kept.
     */
    protected function prepareForValidation(): void
    {
        $message = $this->input('message');
        if (is_string($message)) {
            $this->merge(['message' => trim((string) preg_replace("/\n{3,}/", "\n\n", str_replace("\r\n", "\n", $message)))]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:'.RoomEventWishWallService::MAX_LENGTH],
            // Birthday wall: user code of the celebrant the wish is for (checked against the celebrants by the service).
            'recipient' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['message' => __('occasions.wall.message'), 'recipient' => __('occasions.wall.recipient')];
    }
}
