<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    /** Minimum length of a new admin password (also shown on the reset page's checklist). */
    public const MIN_LENGTH = 12;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<string>>
     */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string', 'confirmed', Password::min(self::MIN_LENGTH)],
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
            'password' => __('validation.attributes.password'),
            'password_confirmation' => __('validation.attributes.password_confirmation'),
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
            'password.required' => __('validation.required', ['attribute' => __('validation.attributes.password')]),
            'password.min' => __('validation.min.string', ['attribute' => __('validation.attributes.password'), 'min' => 12]),
            'password.confirmed' => __('validation.confirmed', ['attribute' => __('validation.attributes.password')]),
        ];
    }
}
