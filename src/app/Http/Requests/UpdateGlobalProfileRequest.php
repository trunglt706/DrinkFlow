<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesUserAndAdmin;
use Illuminate\Foundation\Http\FormRequest;

class UpdateGlobalProfileRequest extends FormRequest
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
        return [
            'phone' => ['nullable', 'string', 'max:30'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp,gif', 'max:5120'],
            'avatar_url' => ['nullable', 'url', 'max:1000'],
            'desk_location' => ['nullable', 'string', 'max:100'],
            'delivery_location' => ['nullable', 'string', 'max:255'],
            'birthday' => ['nullable', 'date_format:Y-m-d', 'after:1900-01-01', 'before:today'],
            // Markdown self-description: the word limit is the rule, the character cap only guards the column.
            'bio' => ['nullable', 'string', 'max:5000', new \App\Rules\MaxWords(\App\Models\GlobalUser::BIO_MAX_WORDS)],
            'sugar' => ['nullable', 'string', 'max:10'],
            'ice' => ['nullable', 'string', 'max:20'],
            'toppings' => ['nullable', 'array'],
            'toppings.*' => ['string', 'max:100'],
            'note' => ['nullable', 'string', 'max:500'],
            'notify_campaign' => ['nullable', 'boolean'],
            'notify_sound' => ['nullable', 'boolean'],
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
            'phone' => __('validation.attributes.phone'),
            'desk_location' => __('validation.attributes.desk_location'),
            'delivery_location' => __('validation.attributes.delivery_location'),
            'bio' => __('validation.attributes.bio'),
            'birthday' => __('validation.attributes.birthday'),
            'sugar' => __('validation.attributes.sugar'),
            'ice' => __('validation.attributes.ice'),
            'toppings' => __('validation.attributes.toppings'),
            'note' => __('validation.attributes.note'),
            'notify_campaign' => __('validation.attributes.notify_campaign'),
            'notify_sound' => __('validation.attributes.notify_sound'),
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
            'phone.max' => __('validation.max.string', ['attribute' => __('validation.attributes.phone'), 'max' => 30]),
            'desk_location.max' => __('validation.max.string', ['attribute' => __('validation.attributes.desk_location'), 'max' => 100]),
            'delivery_location.max' => __('validation.max.string', ['attribute' => __('validation.attributes.delivery_location'), 'max' => 255]),
            'note.max' => __('validation.max.string', ['attribute' => __('validation.attributes.note'), 'max' => 500]),
        ];
    }
}
