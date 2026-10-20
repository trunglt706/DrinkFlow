<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AuthorizesUserAndAdmin;
use App\Services\Room\RoomAccessPolicy;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRoomSettingsRequest extends FormRequest
{
    use AuthorizesUserAndAdmin;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool True nếu là Admin đang active và có quyền quản trị phòng.
     */
    public function authorize(): bool
    {
        return $this->authorizeActiveRoomAdmin();
    }

    /**
     * Normalize the access-rule tag lists before validation: trim, lower-case domains, drop a leading "@".
     */
    protected function prepareForValidation(): void
    {
        $lists = [];
        foreach (RoomAccessPolicy::LIST_KEYS as $key) {
            if ($this->has($key) && is_array($this->input($key))) {
                $lists[$key] = array_values(array_filter(array_map(
                    static fn (mixed $value): string => is_scalar($value) ? trim((string) $value) : '',
                    $this->input($key)
                ), static fn (string $value): bool => $value !== ''));
            }
        }
        if (isset($lists[RoomAccessPolicy::ALLOWED_EMAIL_DOMAINS])) {
            $lists[RoomAccessPolicy::ALLOWED_EMAIL_DOMAINS] = RoomAccessPolicy::normalizeDomains($lists[RoomAccessPolicy::ALLOWED_EMAIL_DOMAINS]);
        }

        $this->merge($lists);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:160'],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'avatar_url' => ['sometimes', 'nullable', 'url', 'max:1000'],
            'timezone' => ['sometimes', 'required', 'timezone'],
            'language' => ['sometimes', 'required', 'in:vi,en,ja'],
            'default_sponsor' => ['sometimes', 'nullable', 'string', 'max:160'],
            'default_payment_account_id' => ['sometimes', 'nullable', 'integer'],
            'campaign_title_template' => ['sometimes', 'nullable', 'string', 'max:255'],
            'max_campaign_budget' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'personal_debt_ceiling' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'auto_lock_on_debt_limit' => ['sometimes', 'nullable', 'boolean'],
            'auto_debt_reminders' => ['sometimes', 'nullable', 'boolean'],
            'is_public' => ['sometimes', 'boolean'],
            // Access rules: empty lists mean "no restriction".
            RoomAccessPolicy::ALLOWED_EMAIL_DOMAINS => ['sometimes', 'present', 'array', 'max:'.RoomAccessPolicy::MAX_ENTRIES],
            RoomAccessPolicy::ALLOWED_EMAIL_DOMAINS.'.*' => ['string', 'max:253', 'regex:'.RoomAccessPolicy::DOMAIN_PATTERN],
            RoomAccessPolicy::ALLOWED_IPS => ['sometimes', 'present', 'array', 'max:'.RoomAccessPolicy::MAX_ENTRIES],
            RoomAccessPolicy::ALLOWED_IPS.'.*' => ['string', 'ip'],
            RoomAccessPolicy::BLOCKED_IPS => ['sometimes', 'present', 'array', 'max:'.RoomAccessPolicy::MAX_ENTRIES],
            RoomAccessPolicy::BLOCKED_IPS.'.*' => ['string', 'ip'],
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
            'name' => __('validation.attributes.name'),
            'description' => __('validation.attributes.description'),
            'avatar_url' => __('validation.attributes.items.*.image_url'),
            'timezone' => __('validation.attributes.timezone'),
            'language' => __('validation.attributes.language'),
            'default_sponsor' => __('validation.attributes.default_sponsor'),
            'default_payment_account_id' => __('validation.attributes.default_payment_account_id'),
            RoomAccessPolicy::ALLOWED_EMAIL_DOMAINS => __('admin.room_allowed_email_domains'),
            RoomAccessPolicy::ALLOWED_IPS => __('admin.room_allowed_ips'),
            RoomAccessPolicy::BLOCKED_IPS => __('admin.room_blocked_ips'),
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
            'name.required' => __('validation.required', ['attribute' => __('validation.attributes.name')]),
            'name.max' => __('validation.max.string', ['attribute' => __('validation.attributes.name'), 'max' => 160]),
            'avatar_url.url' => __('validation.url', ['attribute' => __('validation.attributes.items.*.image_url')]),
            'language.in' => __('validation.in', ['attribute' => __('validation.attributes.language')]),
            RoomAccessPolicy::ALLOWED_EMAIL_DOMAINS.'.*.regex' => __('admin.room_access_invalid_domain', ['value' => ':input']),
            RoomAccessPolicy::ALLOWED_EMAIL_DOMAINS.'.*.max' => __('admin.room_access_invalid_domain', ['value' => ':input']),
            RoomAccessPolicy::ALLOWED_IPS.'.*.ip' => __('admin.room_access_invalid_ip', ['value' => ':input']),
            RoomAccessPolicy::BLOCKED_IPS.'.*.ip' => __('admin.room_access_invalid_ip', ['value' => ':input']),
            RoomAccessPolicy::ALLOWED_EMAIL_DOMAINS.'.max' => __('admin.room_access_too_many', ['max' => RoomAccessPolicy::MAX_ENTRIES]),
            RoomAccessPolicy::ALLOWED_IPS.'.max' => __('admin.room_access_too_many', ['max' => RoomAccessPolicy::MAX_ENTRIES]),
            RoomAccessPolicy::BLOCKED_IPS.'.max' => __('admin.room_access_too_many', ['max' => RoomAccessPolicy::MAX_ENTRIES]),
        ];
    }
}
