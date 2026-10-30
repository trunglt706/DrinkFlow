<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\GameRuleSet;
use App\Models\GlobalUser;

/**
 * Scoring rule sets are private to their owner.
 */
class GameRuleSetPolicy
{
    /**
     * Use or edit the rule set.
     *
     * @param GlobalUser $user Signed-in user.
     * @param GameRuleSet $ruleSet Rule set.
     * @return bool True for the owner.
     */
    public function manage(GlobalUser $user, GameRuleSet $ruleSet): bool
    {
        return (int) $ruleSet->owner_id === (int) $user->id;
    }
}
