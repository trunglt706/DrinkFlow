<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\Room\SendRoomEventNotificationAction;
use App\Enums\EventMusicMode;
use App\Http\Requests\Concerns\AuthorizesUserAndAdmin;
use App\Services\Occasion\RoomEventSettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin sets the background music and sound effects of one room event page.
 */
class UpdateRoomEventSettingsRequest extends FormRequest
{
    use AuthorizesUserAndAdmin;

    public function authorize(): bool
    {
        return $this->authorizeActiveRoomAdmin();
    }

    /**
     * Only an https link is accepted (played by the browser of every member, never fetched by the server).
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(SendRoomEventNotificationAction::types())],
            'music_mode' => ['required', Rule::enum(EventMusicMode::class)],
            'music_url' => ['nullable', 'required_if:music_mode,'.EventMusicMode::Custom->value, 'string', 'max:2048', 'url:https',
                // A streaming site page (Zing MP3, YouTube...) is not an audio file: it could never play.
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    $site = is_string($value) ? RoomEventSettingsService::streamingPageHost($value) : null;
                    if ($site !== null) {
                        $fail(__('occasions.admin.music_url_page', ['site' => $site]));
                    }
                },
            ],
            'sound_effects' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['music_url' => __('occasions.admin.music_url')];
    }
}
