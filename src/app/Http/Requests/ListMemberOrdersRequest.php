<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use App\Http\Requests\Concerns\AuthorizesUserAndAdmin;
use App\Services\Order\MemberOrderPageService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters of the member's order history on "My orders": order code, date range, self / proxy orders and status.
 */
class ListMemberOrdersRequest extends FormRequest
{
    use AuthorizesUserAndAdmin;

    /**
     * @return bool True when an active room member is signed in.
     */
    public function authorize(): bool
    {
        return $this->authorizeActiveRoomUser();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'code' => ['nullable', 'string', 'max:64'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'type' => ['nullable', Rule::in(MemberOrderPageService::HISTORY_TYPES)],
            'status' => ['nullable', Rule::enum(OrderStatus::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => __('room.orders.filter_code'),
            'date_from' => __('room.orders.filter_from'),
            'date_to' => __('room.orders.filter_to'),
            'type' => __('room.orders.filter_type'),
            'status' => __('room.orders.filter_status'),
        ];
    }

    /**
     * Valid filters only, blank ones left out.
     *
     * @return array{code?: string, date_from?: string, date_to?: string, type?: string, status?: string}
     */
    public function filters(): array
    {
        return array_filter(
            array_map(static fn (mixed $value): string => trim((string) $value), $this->safe()->only(['code', 'date_from', 'date_to', 'type', 'status'])),
            static fn (string $value): bool => $value !== '',
        );
    }
}
