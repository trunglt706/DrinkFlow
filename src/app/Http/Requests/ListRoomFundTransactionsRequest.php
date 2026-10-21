<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\RoomFundTransactionType;
use App\Http\Requests\Concerns\AuthorizesUserAndAdmin;
use App\Models\Room;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filters of the room fund ledger (page, AJAX reload and export): type, member and date range.
 */
class ListRoomFundTransactionsRequest extends FormRequest
{
    use AuthorizesUserAndAdmin;

    /**
     * @return bool True when an active admin manages the room.
     */
    public function authorize(): bool
    {
        return $this->authorizeActiveRoomAdmin();
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $room = $this->route('room');

        return [
            'type' => ['nullable', Rule::enum(RoomFundTransactionType::class)],
            'member' => ['nullable', 'integer', Rule::exists('room_users', 'id')->where('room_id', $room instanceof Room ? $room->id : 0)],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => __('room_fund.field_type'),
            'member' => __('room_fund.filter_member'),
            'date_from' => __('room_fund.filter_from'),
            'date_to' => __('room_fund.filter_to'),
        ];
    }

    /**
     * Validated filters as typed values (dates in the room timezone).
     *
     * @return array{type: RoomFundTransactionType|null, member: int|null, from: CarbonImmutable|null, to: CarbonImmutable|null}
     */
    public function filters(Room $room): array
    {
        $timezone = (string) ($room->timezone ?: config('app.display_timezone', config('app.timezone')));
        $date = fn (string $key): ?CarbonImmutable => filled($this->validated($key)) ? CarbonImmutable::createFromFormat('Y-m-d', (string) $this->validated($key), $timezone)->startOfDay() : null;

        return [
            'type' => RoomFundTransactionType::tryFrom((string) $this->validated('type')),
            'member' => filled($this->validated('member')) ? (int) $this->validated('member') : null,
            'from' => $date('date_from'),
            'to' => $date('date_to'),
        ];
    }
}
