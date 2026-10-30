<?php

declare(strict_types=1);

namespace App\Http\Requests\Game;

use App\Http\Requests\Concerns\AuthorizesUserAndAdmin;
use App\Models\Game;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Superadmin game form, used for create and update (the slug stays unique).
 */
class SaveGameRequest extends FormRequest
{
    use AuthorizesUserAndAdmin;

    /**
     * @return bool True for an active superadmin (the route checks `game.manage`).
     */
    public function authorize(): bool
    {
        return $this->authorizeActiveSuperadmin();
    }

    /**
     * Normalize the slug and icon; an unchecked "active" box is absent from the form.
     *
     * @return void
     */
    protected function prepareForValidation(): void
    {
        $icon = trim((string) $this->input('icon'));
        $this->merge([
            'slug' => mb_strtolower(trim((string) $this->input('slug'))),
            'icon' => $icon === '' ? null : mb_strtolower($icon),
            'is_active' => $this->boolean('is_active'),
            'sort_order' => $this->filled('sort_order') ? $this->input('sort_order') : 0,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $game = $this->route('game');

        return [
            'slug' => ['required', 'string', 'max:100', 'regex:/^[a-z0-9][a-z0-9-]*$/', Rule::unique('games', 'slug')->ignore($game instanceof Game ? $game->id : null)],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            // Material Symbols ligature name, e.g. sports_esports.
            'icon' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9_]+$/'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0', 'max:65535'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'slug' => __('games.admin.field_slug'),
            'name' => __('games.admin.field_name'),
            'description' => __('games.admin.field_description'),
            'icon' => __('games.admin.field_icon'),
            'sort_order' => __('games.admin.field_sort_order'),
        ];
    }
}
