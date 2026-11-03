<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\Notification\RoomNotificationChannelDispatcher;
use Illuminate\Validation\Rule;

/**
 * Values of the "connect a new bot" form sent to test a channel before it is saved.
 *
 * Same driver/credential rules as {@see NotificationChannelRequest}; the name is optional because nothing is stored,
 * and `channel_id` (the channel being edited) lets blank credentials fall back to the stored ones.
 */
class TestNotificationChannelDraftRequest extends NotificationChannelRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_replace(parent::rules(), [
            'name' => ['nullable', 'string', 'max:120'],
            'channel_id' => ['nullable', 'integer', 'min:1'],
            'template' => ['nullable', 'string', Rule::in(RoomNotificationChannelDispatcher::TEST_TEMPLATES)],
        ]);
    }
}
