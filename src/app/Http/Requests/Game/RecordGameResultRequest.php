<?php

declare(strict_types=1);

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Owner records a result: rule item, winner, losers and the idempotency key of the submission. Points are never
 * accepted from the client; GameResultService reads them from the rule item.
 */
class RecordGameResultRequest extends FormRequest
{
    /**
     * @return bool True for the owner of the room.
     */
    public function authorize(): bool
    {
        return $this->user('web')?->can('manage', $this->route('gameRoom')) === true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'rule_item_id' => ['required', 'integer'],
            'winner_id' => ['required', 'integer'],
            'loser_ids' => ['required', 'array', 'min:1'],
            'loser_ids.*' => ['integer', 'distinct', 'different:winner_id'],
            'idempotency_key' => ['required', 'string', 'uuid'],
            // Round shown to the client: a result is refused once another round has opened (no silent move).
            'round' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'rule_item_id' => __('games.record.category'),
            'winner_id' => __('games.record.winner'),
            'loser_ids' => __('games.record.losers'),
            'loser_ids.*' => __('games.record.losers'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['loser_ids.*.different' => __('games.errors.winner_is_loser')];
    }
}
