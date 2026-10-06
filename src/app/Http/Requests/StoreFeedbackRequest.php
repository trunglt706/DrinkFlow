<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesUserAndAdmin;
use App\Models\Feedback;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFeedbackRequest extends FormRequest
{
    use AuthorizesUserAndAdmin;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool True nếu là Global User đang active.
     */
    public function authorize(): bool
    {
        return $this->authorizeActiveGlobalUser();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'subsystem' => ['required', 'string', Rule::in(Feedback::SUBSYSTEMS)],
            'content' => ['required', 'string', 'min:3', 'max:1000'],
        ];

        if (!config('captcha.disable', false) && extension_loaded('gd') && function_exists('gd_info')) {
            $rules['captcha'] = ['required', 'captcha'];
        }

        return $rules;
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'rating' => __('global.feedback.rating_label'),
            'subsystem' => __('global.feedback.subsystem_label'),
            'content' => __('global.feedback.content_label'),
            'captcha' => __('global.feedback.captcha_label'),
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
            'rating.required' => __('validation.required', ['attribute' => __('global.feedback.rating_label')]),
            'rating.integer' => __('validation.integer', ['attribute' => __('global.feedback.rating_label')]),
            'rating.min' => __('validation.min.numeric', ['attribute' => __('global.feedback.rating_label'), 'min' => 1]),
            'rating.max' => __('validation.max.numeric', ['attribute' => __('global.feedback.rating_label'), 'max' => 5]),
            'subsystem.required' => __('validation.required', ['attribute' => __('global.feedback.subsystem_label')]),
            'content.required' => __('validation.required', ['attribute' => __('global.feedback.content_label')]),
            'content.min' => __('validation.min.string', ['attribute' => __('global.feedback.content_label'), 'min' => 3]),
            'content.max' => __('validation.max.string', ['attribute' => __('global.feedback.content_label'), 'max' => 1000]),
            'captcha.required' => __('validation.required', ['attribute' => __('global.feedback.captcha_label')]),
            'captcha.captcha' => __('validation.custom.captcha.invalid'),
        ];
    }
}
