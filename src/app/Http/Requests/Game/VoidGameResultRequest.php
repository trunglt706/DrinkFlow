<?php

declare(strict_types=1);

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Owner voids a wrong result; the reason is kept with the result.
 */
class VoidGameResultRequest extends FormRequest
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
        return ['reason' => ['required', 'string', 'max:255']];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['reason' => __('games.void.reason')];
    }
}
