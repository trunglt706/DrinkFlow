<?php

declare(strict_types=1);

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Visitor of the public link records a win of the player they picked: only the category and one loser are sent.
 * The winner comes from the session and the points from the rule item (GameResultService::recordFromPublicLink).
 */
class RecordPublicGameResultRequest extends FormRequest
{
    /**
     * @return bool Always true: the public token is the capability, checked by the controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'rule_item_id' => ['required', 'integer'],
            // From the public page a result names exactly one loser.
            'loser_id' => ['required', 'integer'],
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
            'loser_id' => __('games.record.losers'),
        ];
    }
}
