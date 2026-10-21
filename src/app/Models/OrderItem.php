<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    protected $fillable = ['order_id', 'campaign_item_id', 'item_name', 'size_name', 'unit_price', 'quantity', 'ice_percent', 'sugar_percent', 'line_subtotal', 'note', 'is_self_paid'];

    protected function casts(): array
    {
        return ['is_self_paid' => 'boolean'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function toppings(): HasMany
    {
        return $this->hasMany(OrderItemTopping::class);
    }

    /**
     * The menu item this order item was ordered from, used to display its image.
     * May be null when the campaign item was later deleted.
     *
     * @return BelongsTo<CampaignItem, $this>
     */
    public function campaignItem(): BelongsTo
    {
        return $this->belongsTo(CampaignItem::class);
    }

    /**
     * Ratings given to this item (by the member who received it).
     *
     * @return HasMany<OrderItemRating, $this>
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(OrderItemRating::class);
    }
}
