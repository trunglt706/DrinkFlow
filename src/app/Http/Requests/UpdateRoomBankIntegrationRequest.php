<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesUserAndAdmin;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Room admin settings of the bank feed (on/off, API key, new webhook URL).
 */
class UpdateRoomBankIntegrationRequest extends FormRequest
{
    use AuthorizesUserAndAdmin;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool True when an active admin manages the room.
     */
    public function authorize(): bool
    {
        return $this->authorizeActiveRoomAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'is_active' => ['nullable', 'boolean'],
            'api_key' => ['nullable', 'string', 'min:8', 'max:255'],
            'regenerate_token' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['api_key' => __('bank_sync.api_key')];
    }
}
