<?php

declare(strict_types=1);

namespace App\Http\Requests\Game;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Owner adds a player or renames / (de)activates one.
 */
class SaveGamePlayerRequest extends FormRequest
{
    /**
     * @return bool True for the owner of the room.
     */
    public function authorize(): bool
    {
        return $this->user('web')?->can('manage', $this->route('gameRoom')) === true;
    }

    /**
     * Trim the name; an unchecked "active" box is absent from the form.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'display_name' => trim((string) $this->input('display_name')),
            'is_active' => $this->boolean('is_active'),
        ]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:50'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['display_name' => __('games.create.player_name')];
    }
}
