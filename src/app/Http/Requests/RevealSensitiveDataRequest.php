<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesUserAndAdmin;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Reveal token of a masked value in the Superadmin console.
 */
class RevealSensitiveDataRequest extends FormRequest
{
    use AuthorizesUserAndAdmin;

    public function authorize(): bool
    {
        return $this->authorizeActiveSuperadmin();
    }

    /**
     * @return array<string, array<int, string>> Validation rules.
     */
    public function rules(): array
    {
        return ['token' => ['required', 'string', 'max:4096']];
    }
}
