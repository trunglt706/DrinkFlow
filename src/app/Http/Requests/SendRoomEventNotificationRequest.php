<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\Room\SendRoomEventNotificationAction;
use App\Http\Requests\Concerns\AuthorizesUserAndAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin sends the notification of a room event (today's birthdays or an occasion being shown) from the events page.
 */
class SendRoomEventNotificationRequest extends FormRequest
{
    use AuthorizesUserAndAdmin;

    public function authorize(): bool
    {
        return $this->authorizeActiveRoomAdmin();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return ['type' => ['required', 'string', Rule::in(SendRoomEventNotificationAction::types())]];
    }
}
