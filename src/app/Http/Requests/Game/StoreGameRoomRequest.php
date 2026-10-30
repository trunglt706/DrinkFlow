<?php

declare(strict_types=1);

namespace App\Http\Requests\Game;

use App\Services\Game\GameRoomService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Owner creates a game room: name, players (blank names are numbered) and the rule set to apply.
 */
class StoreGameRoomRequest extends FormRequest
{
    /**
     * @return bool True when a user is signed in (the rule set ownership is checked by the controller).
     */
    public function authorize(): bool
    {
        return $this->user('web') !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'players' => ['required', 'array', 'min:'.GameRoomService::MIN_PLAYERS, 'max:'.GameRoomService::MAX_PLAYERS],
            'players.*' => ['nullable', 'string', 'max:50'],
            'rule_set_id' => ['required', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('games.create.room_name'),
            'players' => __('games.create.players'),
            'players.*' => __('games.create.player_name'),
            'rule_set_id' => __('games.create.rule_set'),
        ];
    }
}
