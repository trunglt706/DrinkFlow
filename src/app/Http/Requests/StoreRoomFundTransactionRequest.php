<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\RoomFundTransactionType;
use App\Enums\RoomUserStatus;
use App\Http\Requests\Concerns\AuthorizesUserAndAdmin;
use App\Models\Room;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Room admin entry on the fund page: a deposit, an expense or an adjustment (signed).
 */
class StoreRoomFundTransactionRequest extends FormRequest
{
    use AuthorizesUserAndAdmin;

    /** Largest amount of a single entry, in VND. */
    public const MAX_AMOUNT = 1_000_000_000;

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
     * The amount field is typed with thousands separators ("1.500.000", "-20.000"): only the digits and a leading
     * minus sign are kept.
     */
    protected function prepareForValidation(): void
    {
        $amount = $this->input('amount');
        if (is_string($amount) && trim($amount) !== '') {
            $digits = preg_replace('/\D+/', '', $amount);
            if ($digits !== '') {
                $this->merge(['amount' => (str_starts_with(ltrim($amount), '-') ? '-' : '').$digits]);
            }
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $room = $this->route('room');
        $roomId = $room instanceof Room ? $room->id : 0;
        $isAdjustment = $this->input('type') === RoomFundTransactionType::Adjustment->value;

        return [
            'type' => ['required', 'string', Rule::in(RoomFundTransactionType::manualValues())],
            'amount' => ['required', 'integer', 'not_in:0', 'min:'.($isAdjustment ? -self::MAX_AMOUNT : 1), 'max:'.self::MAX_AMOUNT],
            'room_user_id' => ['nullable', 'integer', Rule::exists('room_users', 'id')->where('room_id', $roomId)->where('status', RoomUserStatus::Active->value)],
            // Spending and corrections must say why; a deposit may just name the member.
            'note' => [Rule::requiredIf($this->input('type') !== RoomFundTransactionType::Deposit->value), 'nullable', 'string', 'max:255'],
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
            'type' => __('room_fund.field_type'),
            'amount' => __('room_fund.field_amount'),
            'room_user_id' => __('room_fund.field_member'),
            'note' => __('room_fund.field_note'),
        ];
    }
}
