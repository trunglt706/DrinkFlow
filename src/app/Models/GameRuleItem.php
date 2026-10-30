<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A scoring category of a rule version (e.g. "Thắng thường" = 1 point). Inactive items are archived: kept in the
 * version for reference but no longer offered when recording a result.
 */
class GameRuleItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['game_rule_version_id', 'name', 'points', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['points' => 'integer', 'sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(GameRuleVersion::class, 'game_rule_version_id');
    }
}
