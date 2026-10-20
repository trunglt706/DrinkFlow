<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesUserAndAdmin;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Room admin assignment of an unmatched bank transfer to a debt (by debt code).
 */
class AssignBankTransactionRequest extends FormRequest
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
            'debt_code' => ['required', 'string', 'max:40', 'regex:/^[A-Za-z0-9-]+$/'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['debt_code' => __('bank_sync.debt_code')];
    }
}
