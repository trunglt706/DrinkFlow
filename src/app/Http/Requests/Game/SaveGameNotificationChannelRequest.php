<?php

declare(strict_types=1);

namespace App\Http\Requests\Game;

use App\Models\GameNotificationChannel;
use App\Support\Security\OutboundUrlGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create / update a notification channel of the Utilities area. Credentials may be left blank on update to keep the
 * stored ones; the service checks that every key the driver needs ends up with a value.
 */
class SaveGameNotificationChannelRequest extends FormRequest
{
    /**
     * Signed-in global user; when updating, only the channel's owner.
     *
     * @return bool True when allowed.
     */
    public function authorize(): bool
    {
        $user = $this->user('web');
        $channel = $this->route('channel');

        return $user !== null && (! $channel instanceof GameNotificationChannel || $user->can('manage', $channel));
    }

    /**
     * @return array<string, mixed> Validation rules.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(GameNotificationChannel::TYPES)],
            'is_active' => ['boolean'],
            'config' => ['array'],
            'config.*' => ['nullable', 'string', 'max:2000'],
            'config.webhook_url' => [
                'nullable',
                'string',
                'max:2000',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! is_string($value) || trim($value) === '') {
                        return;
                    }
                    $guard = app(OutboundUrlGuard::class);
                    if ($this->input('type') === 'slack' && ! $guard->isSafe(trim($value), ['hooks.slack.com'])) {
                        $fail(__('games.channels.errors.slack_url'));

                        return;
                    }
                    if (! $guard->isSafe(trim($value))) {
                        $fail(__('games.channels.errors.unsafe_url'));
                    }
                },
            ],
            'config.bot_token' => ['nullable', 'string', 'max:2000', 'regex:/^\d+:[A-Za-z0-9_-]+$/'],
            'config.chat_id' => ['nullable', 'string', 'max:100', 'regex:/^-?\d+$|^@[A-Za-z0-9_]{5,}$/'],
            'config.room_id' => ['nullable', 'string', 'max:100', 'regex:/^\d+$/'],
        ];
    }

    /**
     * @return array<string, string> Attribute names.
     */
    public function attributes(): array
    {
        return [
            'name' => __('games.channels.name'),
            'type' => __('games.channels.type'),
            'config.webhook_url' => __('games.channels.fields.webhook_url'),
            'config.bot_token' => __('games.channels.fields.bot_token'),
            'config.chat_id' => __('games.channels.fields.chat_id'),
            'config.api_token' => __('games.channels.fields.api_token'),
            'config.room_id' => __('games.channels.fields.room_id'),
            'config.secret_token' => __('games.channels.fields.secret_token'),
        ];
    }

    /**
     * Validated data in the shape the service expects.
     *
     * @return array{name: string, type: string, is_active: bool, config: array<string, string|null>} Data.
     */
    public function channelData(): array
    {
        return [
            'name' => (string) $this->validated('name'),
            'type' => (string) $this->validated('type'),
            'is_active' => $this->boolean('is_active'),
            'config' => (array) ($this->validated('config') ?? []),
        ];
    }
}
