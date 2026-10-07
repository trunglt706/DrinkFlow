<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CampaignItemOptionStatus;
use App\Enums\CampaignItemStatus;
use App\Models\Concerns\BelongsToRoom;
use App\Models\Concerns\HasNormalizedName;
use App\Models\Concerns\HasStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class CampaignItem extends Model
{
    use HasStatus, HasNormalizedName, BelongsToRoom;

    protected $fillable = [
        'campaign_id',
        'name',
        'normalized_name',
        'category',
        'description',
        'image_url',
        'base_price',
        'sponsor_amount',
        'status',
        'sort_order',
        'source_url',
        'source_item_key',
    ];

    protected function casts(): array
    {
        return [
            'status'     => CampaignItemStatus::class,
            'base_price' => 'decimal:0',
            'sponsor_amount' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Store categories trimmed with single spaces, and a blank one as null, so the same category typed or imported
     * with stray spaces is grouped once and a whitespace-only category never shows up as an empty menu tab.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function category(): Attribute
    {
        // Read the same way, so rows saved before this normalization still match their menu tab.
        return Attribute::make(
            get: static fn (?string $value): ?string => self::normalizeCategory($value),
            set: static fn (?string $value): ?string => self::normalizeCategory($value),
        );
    }

    /**
     * Trim a category name and collapse inner whitespace.
     *
     * @param string|null $value Raw category.
     * @return string|null Normalized category, or null when blank.
     */
    private static function normalizeCategory(?string $value): ?string
    {
        $normalized = trim((string) preg_replace('/\s+/u', ' ', (string) $value));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Distinct menu categories of the given items, in first-seen order; items without a category are skipped,
     * so a category is only listed when at least one of these items belongs to it.
     *
     * @param iterable<int, self> $items Items shown on the menu (already filtered, e.g. to active ones).
     * @return Collection<int, string>
     */
    public static function categoriesOf(iterable $items): Collection
    {
        return collect($items)
            ->map(static fn (self $item): ?string => $item->category)
            ->filter()
            ->unique()
            ->values();
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function toppings(): HasMany
    {
        return $this->hasMany(CampaignItemTopping::class);
    }

    /**
     * Eager-load constraints for the sizes and toppings a member may see and order: deleted (hidden) options
     * are left out, in menu order. Use as `->with(CampaignItem::orderableOptions())`.
     *
     * @return array<string, \Closure> Relation name => query constraint.
     */
    public static function orderableOptions(): array
    {
        $active = static fn ($query) => $query->where('status', CampaignItemOptionStatus::Active->value)->orderBy('sort_order');

        return ['sizes' => $active, 'toppings' => $active];
    }

    public function sizes(): HasMany
    {
        return $this->hasMany(CampaignItemSize::class);
    }
}
