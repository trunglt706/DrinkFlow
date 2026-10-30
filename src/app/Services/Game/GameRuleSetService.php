<?php

declare(strict_types=1);

namespace App\Services\Game;

use App\Models\Game;
use App\Models\GameRuleSet;
use App\Models\GameRuleVersion;
use App\Models\GlobalUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Scoring rule sets of an owner. A version is never edited: saving a rule set writes a new version with its items,
 * and rooms keep the version they apply until their owner applies the new one (GameRoomService::applyLatestRuleVersion).
 */
class GameRuleSetService
{
    /**
     * Rule sets of the owner for a game, with their current version and items.
     *
     * @param Game $game Game.
     * @param GlobalUser $owner Owner.
     * @return Collection<int, GameRuleSet> Rule sets, newest first.
     */
    public function forOwner(Game $game, GlobalUser $owner): Collection
    {
        return GameRuleSet::query()
            ->where('game_id', $game->id)
            ->where('owner_id', $owner->id)
            ->with('currentVersion.items')
            ->latest('id')
            ->get();
    }

    /**
     * Create a rule set with its first version.
     *
     * @param Game $game Game.
     * @param GlobalUser $owner Owner.
     * @param string $name Rule set name.
     * @param bool $allowNegativePoints Whether items may award negative points.
     * @param array<int, array{name: string, points: int, is_active: bool}> $items Items in display order.
     * @return GameRuleSet Created rule set (current version loaded).
     */
    public function create(Game $game, GlobalUser $owner, string $name, bool $allowNegativePoints, array $items): GameRuleSet
    {
        return DB::transaction(function () use ($game, $owner, $name, $allowNegativePoints, $items): GameRuleSet {
            $ruleSet = GameRuleSet::create([
                'game_id' => $game->id,
                'owner_id' => $owner->id,
                'name' => $name,
                'allow_negative_points' => $allowNegativePoints,
            ]);
            $version = $this->writeVersion($ruleSet, 1, $items);
            $ruleSet->update(['current_version_id' => $version->id]);

            return $ruleSet->load('currentVersion.items');
        });
    }

    /**
     * Save a rule set as a new version. Earlier versions and the results that snapshot them stay untouched.
     *
     * @param GameRuleSet $ruleSet Rule set.
     * @param string $name Rule set name.
     * @param bool $allowNegativePoints Whether items may award negative points.
     * @param array<int, array{name: string, points: int, is_active: bool}> $items Items in display order.
     * @return GameRuleVersion The new current version.
     */
    public function revise(GameRuleSet $ruleSet, string $name, bool $allowNegativePoints, array $items): GameRuleVersion
    {
        return DB::transaction(function () use ($ruleSet, $name, $allowNegativePoints, $items): GameRuleVersion {
            $locked = GameRuleSet::query()->whereKey($ruleSet->id)->lockForUpdate()->firstOrFail();
            $next = (int) GameRuleVersion::query()->where('game_rule_set_id', $locked->id)->max('version') + 1;
            $version = $this->writeVersion($locked, $next, $items);
            $locked->update([
                'name' => $name,
                'allow_negative_points' => $allowNegativePoints,
                'current_version_id' => $version->id,
            ]);
            $ruleSet->setRawAttributes($locked->getAttributes(), true);

            return $version;
        });
    }

    /**
     * Write a version and its items.
     *
     * @param GameRuleSet $ruleSet Rule set.
     * @param int $number Version number.
     * @param array<int, array{name: string, points: int, is_active: bool}> $items Items in display order.
     * @return GameRuleVersion Created version.
     */
    private function writeVersion(GameRuleSet $ruleSet, int $number, array $items): GameRuleVersion
    {
        $version = GameRuleVersion::create(['game_rule_set_id' => $ruleSet->id, 'version' => $number]);
        foreach (array_values($items) as $index => $item) {
            $version->items()->create([
                'name' => $item['name'],
                'points' => $item['points'],
                'sort_order' => $index + 1,
                'is_active' => $item['is_active'],
            ]);
        }

        return $version;
    }
}
