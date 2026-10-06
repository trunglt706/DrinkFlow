<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesUserAndAdmin;
use App\Support\Security\OutboundUrlGuard;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SystemNotificationChannelRequest extends FormRequest
{
    use AuthorizesUserAndAdmin;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool True nếu là Superadmin đang active.
     */
    public function authorize(): bool
    {
        return $this->authorizeActiveSuperadmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(array_keys(\App\Models\SystemNotificationChannel::TYPES))],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(['active', 'disabled'])],
            'config' => ['sometimes', 'array'],
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
                    if ($this->input('type') === 'slack' && ! $guard->isSafe($value, ['hooks.slack.com'])) {
                        $fail(__('admin.slack_webhook_host_invalid'));

                        return;
                    }
                    if (! $guard->isSafe($value)) {
                        $fail(__('admin.webhook_url_unsafe'));
                    }
                },
            ],
            'config.bot_token' => ['nullable', 'string', 'max:2000', 'regex:/^\d+:[A-Za-z0-9_-]+$/'],
            'config.room_id' => ['nullable', 'string', 'max:2000', 'regex:/^\d+$/'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => __('validation.attributes.type'),
            'name' => __('validation.attributes.name'),
            'config' => __('validation.attributes.config'),
            'status' => __('validation.attributes.status'),
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => __('validation.required', ['attribute' => __('validation.attributes.type')]),
            'type.in' => __('validation.in', ['attribute' => __('validation.attributes.type')]),
            'name.required' => __('validation.required', ['attribute' => __('validation.attributes.name')]),
            'config.required' => __('validation.required', ['attribute' => __('validation.attributes.config')]),
        ];
    }
}
