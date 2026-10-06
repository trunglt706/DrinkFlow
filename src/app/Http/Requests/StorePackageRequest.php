<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PackageStatus;
use App\Http\Requests\Concerns\AuthorizesUserAndAdmin;
use App\Http\Requests\Concerns\NormalizesGroupedNumbers;
use App\Models\Package;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Package form, used for create and update (the code stays unique).
 */
class StorePackageRequest extends FormRequest
{
    use AuthorizesUserAndAdmin, NormalizesGroupedNumbers;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool True for an active superadmin (the route checks `package.manage`).
     */
    public function authorize(): bool
    {
        return $this->authorizeActiveSuperadmin();
    }

    /**
     * Normalize the code, the grouped numbers and the optional sort order before validation.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => mb_strtolower(trim((string) $this->input('code'))),
            // The form groups the digits (1.000.000): keep only the digits, so the integer rules apply.
            'monthly_price' => $this->withoutGroupSeparators('monthly_price'),
            'room_limit' => $this->withoutGroupSeparators('room_limit'),
            'sort_order' => $this->filled('sort_order') ? $this->input('sort_order') : 0,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $package = $this->route('package');

        return [
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9][a-z0-9_-]*$/', Rule::unique('packages', 'code')->ignore($package instanceof Package ? $package->id : null)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'monthly_price' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'room_limit' => ['required', 'integer', 'min:1', 'max:10000'],
            'status' => ['required', Rule::enum(PackageStatus::class)],
            'sort_order' => ['integer', 'min:0', 'max:65535'],
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
            'code' => __('platform.packages.field_code'),
            'name' => __('platform.packages.field_name'),
            'description' => __('platform.packages.field_description'),
            'monthly_price' => __('platform.packages.field_monthly_price'),
            'room_limit' => __('platform.packages.field_room_limit'),
            'status' => __('validation.attributes.status'),
            'sort_order' => __('platform.packages.field_sort_order'),
        ];
    }

    /**
     * Get custom validation messages.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['code.regex' => __('platform.packages.code_format')];
    }
}
