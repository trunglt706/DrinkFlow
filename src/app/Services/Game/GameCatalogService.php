<?php

declare(strict_types=1);

namespace App\Services\Game;

use App\Models\Game;
use App\Services\Audit\AuditService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Catalogue of the games shown under "Trò chơi" in the /me portal, managed by Superadmins.
 *
 * A game that already has rooms or rule sets cannot be deleted (their history would cascade away): it can be turned
 * off instead (is_active = false), which hides it from the catalogue and its pages.
 */
class GameCatalogService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * Every game with its usage, in display order.
     *
     * @return Collection<int, Game> Games with rooms_count and rule_sets_count.
     */
    public function list(): Collection
    {
        return Game::query()
            ->withCount(['gameRooms as rooms_count', 'ruleSets as rule_sets_count'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    /**
     * Create a game.
     *
     * @param array{slug: string, name: string, description?: string|null, icon?: string|null, is_active: bool, sort_order: int} $data Validated data.
     * @return Game Created game.
     */
    public function create(array $data): Game
    {
        $game = Game::create($data);
        $this->audit->record('game.created', 'game', (int) $game->id, null, [], $game->only(array_keys($data)));

        return $game;
    }

    /**
     * Update a game.
     *
     * @param Game $game Game.
     * @param array{slug: string, name: string, description?: string|null, icon?: string|null, is_active: bool, sort_order: int} $data Validated data.
     * @return void
     */
    public function update(Game $game, array $data): void
    {
        $before = $game->only(array_keys($data));
        $game->update($data);
        $this->audit->record('game.updated', 'game', (int) $game->id, null, $before, $game->only(array_keys($data)));
    }

    /**
     * Whether rooms or rule sets reference the game.
     *
     * @param Game $game Game.
     * @return bool True when the game is in use.
     */
    public function isInUse(Game $game): bool
    {
        return $game->gameRooms()->exists() || $game->ruleSets()->exists();
    }

    /**
     * Delete an unused game.
     *
     * @param Game $game Game.
     * @return void
     *
     * @throws ValidationException When the game has rooms or rule sets (turn it off instead).
     */
    public function delete(Game $game): void
    {
        if ($this->isInUse($game)) {
            throw ValidationException::withMessages(['game' => __('games.admin.in_use')]);
        }
        $snapshot = $game->only(['slug', 'name']);
        $game->delete();
        $this->audit->record('game.deleted', 'game', (int) $game->id, null, $snapshot);
    }
}
