<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\Room\SendRoomEventNotificationAction;
use App\Enums\EventMusicMode;
use App\Http\Requests\Concerns\AuthorizesUserAndAdmin;
use App\Models\Room;
use App\Services\Occasion\RoomEventSettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

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
     * Only an https link is accepted (played by the browser of every member, never fetched by the server). The upload
     * mode needs an audio file unless the page already has one.
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
            'music_file' => [
                Rule::requiredIf(fn (): bool => $this->input('music_mode') === EventMusicMode::Upload->value && ! $this->hasMusicFile()),
                'nullable',
                File::types(RoomEventSettingsService::MUSIC_FILE_TYPES)->max(RoomEventSettingsService::MUSIC_FILE_MAX_KB),
            ],
            'sound_effects' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['music_url' => __('occasions.admin.music_url'), 'music_file' => __('occasions.admin.music_file')];
    }

    /**
     * Whether the event page being set already has an uploaded audio file.
     */
    private function hasMusicFile(): bool
    {
        $room = $this->route('room');
        $type = $this->input('type');

        return $room instanceof Room && is_string($type) && app(RoomEventSettingsService::class)->hasMusicFile($room, $type);
    }
}
