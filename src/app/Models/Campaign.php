<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CampaignStatus;
use App\Enums\OrderStatus;
use App\Models\Concerns\BelongsToRoom;
use App\Models\Concerns\HasStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Campaign extends Model
{
    use HasStatus, BelongsToRoom;

    public const SPONSOR_TYPE_NONE = 'none';

    public const SPONSOR_TYPE_FULL = 'full';

    public const SPONSOR_TYPE_PER_ITEM = 'per_item';

    public const SPONSOR_TYPE_BUDGET = 'budget';

    /** Every sponsor type a campaign can have, in the order they are listed in filters. */
    public const SPONSOR_TYPES = [
        self::SPONSOR_TYPE_NONE,
        self::SPONSOR_TYPE_FULL,
        self::SPONSOR_TYPE_PER_ITEM,
        self::SPONSOR_TYPE_BUDGET,
    ];

    /** Nợ trả riêng lấy đúng đơn giá món, không cộng ship và không trừ giảm giá/chiết khấu chung của chiến dịch. */
    public const SELF_PAID_PRICE_BASIS_ORIGINAL = 'original';

    /** Nợ trả riêng được phân bổ theo tỷ lệ ship và giảm giá/chiết khấu chung của chiến dịch, như các món được tài trợ. */
    public const SELF_PAID_PRICE_BASIS_CAMPAIGN_PRORATED = 'campaign_prorated';

    /** Minutes an admin may add to an ordering deadline in one step. */
    public const EXTEND_DEADLINE_MINUTES = [10, 20, 30, 60];

    protected $fillable = ['room_id', 'code', 'name', 'restaurant', 'creator_admin_id', 'sponsor_name', 'sponsor_type', 'sponsor_description', 'sponsor_allocations', 'deadline', 'max_budget', 'flat_price', 'delivery_fee', 'discount', 'self_paid_price_basis', 'payment_account_id', 'description', 'status', 'started_at', 'closed_at'];

    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        static::creating(function (Campaign $campaign): void {
            if (empty($campaign->code)) {
                $campaign->code = \App\Services\Code\CodeGeneratorService::generateCampaignCode();
            }
        });
    }

    protected function casts(): array
    {
        return ['deadline' => 'datetime', 'started_at' => 'datetime', 'closed_at' => 'datetime', 'ordering_locked_at' => 'datetime', 'deadline_reminder_sent_for' => 'datetime', 'status' => CampaignStatus::class, 'sponsor_allocations' => 'array'];
    }

    /**
     * Determine whether members can currently place orders in this campaign.
     *
     * @return bool True when the campaign is live, its deadline has not passed and an admin has not locked ordering.
     */
    public function isOrderable(): bool
    {
        return $this->isOpenForOrders() && ! $this->isOrderingLocked();
    }

    /**
     * Determine whether the campaign is live and before its deadline, ignoring an admin ordering lock.
     *
     * Room admins may still place orders on a member's behalf while members are locked out.
     *
     * @return bool True when the campaign is active and its ordering deadline has not passed.
     */
    public function isOpenForOrders(): bool
    {
        return $this->status === CampaignStatus::Active
            && ($this->deadline === null || $this->deadline->isFuture());
    }

    /**
     * Determine whether an admin has locked ordering for members ("khóa chiến dịch").
     *
     * @return bool True when member ordering is locked.
     */
    public function isOrderingLocked(): bool
    {
        return $this->ordering_locked_at !== null;
    }

    /**
     * Message explaining why a member cannot order right now.
     *
     * @return string Translated "locked" or "ordering closed" message.
     */
    public function orderingClosedMessage(): string
    {
        return $this->isOpenForOrders() && $this->isOrderingLocked()
            ? __('room.campaign.ordering_locked')
            : __('room.campaign.ordering_closed');
    }

    /**
     * Determine whether this campaign is finalized and must no longer be edited.
     *
     * @return bool True when the campaign is closed or archived.
     */
    public function isLocked(): bool
    {
        return in_array($this->status, [CampaignStatus::Closed, CampaignStatus::Archived], true);
    }

    public function items(): HasMany
    {
        return $this->hasMany(CampaignItem::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Order lines of every order in this campaign (main items; toppings are stored per line).
     *
     * @return HasManyThrough<OrderItem, Order, $this>
     */
    public function orderItems(): HasManyThrough
    {
        return $this->hasManyThrough(OrderItem::class, Order::class);
    }

    /**
     * Gross total paid to the restaurant: order subtotals plus delivery fee minus discount (before sponsorship).
     *
     * @param int $grossSubtotal Sum of the non-cancelled orders' subtotals.
     * @return int Gross total in VND.
     */
    public function grossTotal(int $grossSubtotal): int
    {
        return max(0, $grossSubtotal + (int) ($this->delivery_fee ?? 0) - (int) ($this->discount ?? 0));
    }

    /**
     * Count the distinct members who currently have a (non-cancelled) order in this campaign.
     *
     * @return int Number of members who have ordered.
     */
    public function orderedMembersCount(): int
    {
        return $this->orders()
            ->where('status', '!=', OrderStatus::Cancelled->value)
            ->distinct()
            ->count('room_user_id');
    }

    /**
     * Members funding this campaign and their share (only full sponsorship keeps sponsor_allocations).
     *
     * @return array<int, int> Sponsorship percentage keyed by room_user_id; members with 0% are left out.
     */
    public function sponsorPercentages(): array
    {
        $percentages = [];
        foreach ($this->sponsor_allocations ?? [] as $allocation) {
            $roomUserId = is_array($allocation) ? (int) ($allocation['room_user_id'] ?? 0) : 0;
            $percentage = is_array($allocation) ? (int) ($allocation['percentage'] ?? 0) : 0;
            if ($roomUserId > 0 && $percentage > 0) {
                $percentages[$roomUserId] = $percentage;
            }
        }

        return $percentages;
    }

    public function debts(): HasMany
    {
        return $this->hasMany(Debt::class);
    }

    public function paymentAccount(): BelongsTo
    {
        return $this->belongsTo(PaymentAccount::class);
    }

    /**
     * Get the administrator who created the campaign.
     *
     * @return BelongsTo<Admin, $this> Campaign creator relationship.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'creator_admin_id');
    }
}
