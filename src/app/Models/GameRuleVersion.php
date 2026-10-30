<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Immutable snapshot of a rule set: its items as they were when the version was saved.
 */
class GameRuleVersion extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['game_rule_set_id', 'version'];

    protected function casts(): array
    {
        return ['version' => 'integer'];
    }

    public function ruleSet(): BelongsTo
    {
        return $this->belongsTo(GameRuleSet::class, 'game_rule_set_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(GameRuleItem::class)->orderBy('sort_order')->orderBy('id');
    }
}
