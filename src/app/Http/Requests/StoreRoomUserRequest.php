<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesUserAndAdmin;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoomUserRequest extends FormRequest
{
    use AuthorizesUserAndAdmin;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->authorizeActiveRoomAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $email = strtolower(trim((string) $value));
                    $domain = str_contains($email, '@') ? substr($email, strrpos($email, '@') + 1) : '';
                    $allowed = (array) config('services.google.allowed_domains', []);

                    if ($allowed !== [] && !in_array($domain, $allowed, true)) {
                        $fail(__('admin.error_domain_unsupported', ['domain' => $domain ?: 'unknown']));
                    }
                },
            ],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'desk_location' => ['nullable', 'string', 'max:255'],
            'birthday' => ['nullable', 'date_format:Y-m-d', 'after:1900-01-01', 'before:today'],
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
            'email' => __('admin.th_email'),
            'name' => __('admin.th_user_member'),
            'phone' => __('admin.phone'),
            'desk_location' => __('admin.desk_location'),
            'birthday' => __('admin.user_birthday_label'),
        ];
    }
}
