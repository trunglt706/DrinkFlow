<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PackageStatus;
use App\Services\Subscription\SubscriptionService;
use App\Support\Helpers\CaptchaHelper;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Public Agent (Admin) registration form at /admin/register.
 */
class RegisterAdminRequest extends FormRequest
{
    /**
     * Anyone may register; spam is limited by the captcha and the route throttle.
     *
     * @return bool Always true.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Normalize the email and trim free-text fields before validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => mb_strtolower(trim((string) $this->input('email'))),
            'phone' => trim((string) $this->input('phone')),
            'company' => trim((string) $this->input('company')) ?: null,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('admins', 'email')],
            'phone' => ['required', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 .\-]{6,28}$/'],
            'package_id' => ['required', 'integer', Rule::exists('packages', 'id')->where('status', PackageStatus::Active->value)],
            // Billing cycle of the first invoice and of the renewals: up to 24 months or 3 years.
            'period_unit' => ['required', 'string', Rule::in([SubscriptionService::CYCLE_UNIT_MONTH, SubscriptionService::CYCLE_UNIT_YEAR])],
            'period_quantity' => ['required', 'integer', 'min:1', $this->input('period_unit') === SubscriptionService::CYCLE_UNIT_YEAR ? 'max:3' : 'max:24'],
        ];

        // Same switch as the form (components/admin-auth/captcha.blade.php), so the two never disagree.
        $rules['captcha'] = CaptchaHelper::isCaptchaEnabled()
            ? ['required', 'string', 'captcha']
            : ['nullable', 'string', 'max:20'];

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
            'name' => __('platform.registration.field_name'),
            'company' => __('platform.registration.field_company'),
            'email' => __('validation.attributes.email'),
            'phone' => __('platform.registration.field_phone'),
            'package_id' => __('platform.registration.field_package'),
            'period_quantity' => __('platform.registration.field_cycle_quantity'),
            'period_unit' => __('platform.registration.field_cycle_unit'),
            'captcha' => __('validation.attributes.captcha'),
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
            'email.unique' => __('platform.registration.email_taken'),
            'phone.regex' => __('platform.registration.phone_format'),
            'package_id.exists' => __('platform.registration.package_unavailable'),
            'period_quantity.max' => __('platform.registration.cycle_max'),
        ];
    }
}
