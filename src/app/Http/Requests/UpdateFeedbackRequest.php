<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesUserAndAdmin;
use App\Models\Feedback;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Superadmin correction of a feedback entry (typos, offensive wording, wrong rating or subsystem).
 * Same limits as the public form ({@see StoreFeedbackRequest}).
 */
class UpdateFeedbackRequest extends FormRequest
{
    use AuthorizesUserAndAdmin;

    public function authorize(): bool
    {
        return $this->authorizeActiveSuperadmin();
    }

    /**
     * Trim free-text fields before validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'content' => trim((string) $this->input('content', '')),
            'user_display_name' => trim((string) $this->input('user_display_name', '')) ?: null,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>> Validation rules.
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'subsystem' => ['required', 'string', Rule::in(Feedback::SUBSYSTEMS)],
            'content' => ['required', 'string', 'min:3', 'max:1000'],
            'user_display_name' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string> Attribute labels.
     */
    public function attributes(): array
    {
        return [
            'rating' => __('global.feedback.rating_label'),
            'subsystem' => __('global.feedback.subsystem_label'),
            'content' => __('global.feedback.content_label'),
            'user_display_name' => __('superadmin.feedbacks.display_name'),
        ];
    }
}
