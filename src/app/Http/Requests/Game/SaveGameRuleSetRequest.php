<?php

declare(strict_types=1);

namespace App\Http\Requests\Game;

use App\Models\GameRuleSet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Owner creates a rule set or saves it as a new version: name, negative points switch and items (name, points,
 * active) in display order. At least one item must stay active.
 */
class SaveGameRuleSetRequest extends FormRequest
{
    /** Bounds of the points of an item. */
    public const MAX_POINTS = 1000;
    public const MAX_ITEMS = 30;

    /**
     * @return bool True for a signed-in user, and for the owner when editing an existing rule set.
     */
    public function authorize(): bool
    {
        $user = $this->user('web');
        $ruleSet = $this->route('ruleSet');

        return $user !== null && (! $ruleSet instanceof GameRuleSet || $user->can('manage', $ruleSet));
    }

    /**
     * Unchecked checkboxes are absent from the form: read them as false.
     */
    protected function prepareForValidation(): void
    {
        $items = $this->input('items');
        if (is_array($items)) {
            $this->merge(['items' => array_values(array_map(static fn (mixed $item): mixed => is_array($item)
                ? $item + ['is_active' => false]
                : $item, $items))]);
        }
        $this->merge(['allow_negative_points' => $this->boolean('allow_negative_points')]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'allow_negative_points' => ['boolean'],
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'items.*.name' => ['required', 'string', 'max:100'],
            'items.*.points' => ['required', 'integer', 'between:-'.self::MAX_POINTS.','.self::MAX_POINTS],
            'items.*.is_active' => ['boolean'],
        ];
    }

    /**
     * Negative points need the switch; at least one item stays selectable.
     *
     * @return array<int, \Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $items = (array) $this->input('items', []);
                if (! $this->boolean('allow_negative_points')) {
                    foreach ($items as $index => $item) {
                        if (is_array($item) && is_numeric($item['points'] ?? null) && (int) $item['points'] < 0) {
                            $validator->errors()->add("items.$index.points", __('games.rules.negative_not_allowed'));
                        }
                    }
                }
                $active = array_filter($items, static fn (mixed $item): bool => is_array($item) && filter_var($item['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN));
                if ($items !== [] && $active === []) {
                    $validator->errors()->add('items', __('games.rules.one_active_required'));
                }
            },
        ];
    }

    /**
     * Items ready for GameRuleSetService.
     *
     * @return array<int, array{name: string, points: int, is_active: bool}>
     */
    public function items(): array
    {
        return array_map(static fn (array $item): array => [
            'name' => trim((string) $item['name']),
            'points' => (int) $item['points'],
            'is_active' => filter_var($item['is_active'] ?? false, FILTER_VALIDATE_BOOLEAN),
        ], array_values((array) $this->validated('items')));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => __('games.rules.name'),
            'items' => __('games.rules.items'),
            'items.*.name' => __('games.rules.item_name'),
            'items.*.points' => __('games.rules.item_points'),
        ];
    }
}
