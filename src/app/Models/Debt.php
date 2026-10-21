<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DebtStatus;
use App\Models\Concerns\BelongsToRoom;
use App\Models\Concerns\HasStatus;
use App\Models\Scopes\CampaignDebtScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

/**
 * A room member's debt for one campaign, or a consolidated payment request.
 *
 * Campaign debts have a `campaign_id`. A consolidated payment request is a parent row without a
 * campaign; the campaign debts bundled into it point to it through `parent_id`. Parent rows are
 * hidden from normal queries by CampaignDebtScope and are reached through paymentRequests().
 */
class Debt extends Model
{
    use HasStatus, BelongsToRoom;

    /**
     * Columns that change what a campaign debt is worth; frozen while its payment request is open.
     *
     * @var array<int, string>
     */
    public const BALANCE_COLUMNS = ['original_amount', 'sponsor_amount', 'adjustment_amount', 'paid_amount', 'remaining_amount', 'status'];

    /** Set while an approved payment request settles its children, the only write allowed on them. */
    private static bool $settlingPaymentRequest = false;

    protected $fillable = ['room_id', 'code', 'campaign_id', 'room_user_id', 'original_amount', 'sponsor_amount', 'sponsor_type', 'sponsor_description', 'adjustment_amount', 'paid_amount', 'remaining_amount', 'status', 'payment_requested_at', 'payment_content', 'reviewed_at', 'reviewed_by_admin_id', 'review_reason', 'note', 'reminder_snoozed_until', 'reminder_snooze_count'];

    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        static::addGlobalScope(new CampaignDebtScope());

        static::creating(function (Debt $debt): void {
            if (empty($debt->code)) {
                $debt->code = \App\Services\Code\CodeGeneratorService::generateDebtCode($debt->room_id !== null ? (int) $debt->room_id : null);
            }
        });

        static::updating(function (Debt $debt): void {
            // A bundled debt keeps its link for traceability; only re-bundling after a rejection (a bulk
            // update in SubmitDebtPaymentRequestAction, which bypasses this hook) may move it.
            if ($debt->isDirty('parent_id') && $debt->getOriginal('parent_id') !== null) {
                throw self::lockedException();
            }
            if (! self::$settlingPaymentRequest && $debt->isDirty(self::BALANCE_COLUMNS) && $debt->isLockedByPaymentRequest()) {
                throw self::lockedException();
            }
        });

        static::deleting(function (Debt $debt): void {
            if ($debt->parent_id !== null || $debt->campaign_id === null) {
                throw self::lockedException();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'status' => DebtStatus::class,
            'payment_requested_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'reminder_snoozed_until' => 'datetime',
            'reminder_snooze_count' => 'integer',
        ];
    }

    /**
     * Query consolidated payment requests (parent rows) instead of campaign debts.
     *
     * @return Builder<Debt> Payment request query.
     */
    public static function paymentRequests(): Builder
    {
        return static::query()->withoutGlobalScope(CampaignDebtScope::class)->whereNull('campaign_id');
    }

    /**
     * Run the settlement of an approved payment request, which may update its frozen children.
     *
     * @template TResult
     * @param callable(): TResult $callback Settlement work.
     * @return TResult Callback result.
     */
    public static function settlingPaymentRequest(callable $callback): mixed
    {
        $previous = self::$settlingPaymentRequest;
        self::$settlingPaymentRequest = true;
        try {
            return $callback();
        } finally {
            self::$settlingPaymentRequest = $previous;
        }
    }

    /**
     * Whether this campaign debt belongs to a payment request that is pending or already approved.
     *
     * Children of a rejected request stay linked but can be paid again through the single-debt flows.
     *
     * @return bool True when its balance must not change.
     */
    public function isLockedByPaymentRequest(): bool
    {
        $parentId = $this->getOriginal('parent_id') ?? $this->parent_id;
        if ($parentId === null) {
            return false;
        }

        return self::paymentRequests()
            ->whereKey($parentId)
            ->whereIn('status', [DebtStatus::Pending->value, DebtStatus::Approved->value])
            ->exists();
    }

    /**
     * Keep only campaign debts that are not frozen by a pending or approved payment request.
     *
     * Bulk admin/campaign settlements use it so they skip bundled debts instead of failing midway.
     *
     * @param Builder<Debt> $query Campaign debt query.
     * @return Builder<Debt> Filtered query.
     */
    public function scopeNotLockedByPaymentRequest(Builder $query): Builder
    {
        return $query->where(static function (Builder $query): void {
            $query->whereNull('parent_id')->orWhereIn('parent_id', self::paymentRequests()
                ->where('status', DebtStatus::Rejected->value)
                ->select('id'));
        });
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function roomUser(): BelongsTo
    {
        return $this->belongsTo(RoomUser::class);
    }

    /**
     * Payment request this campaign debt was bundled into.
     *
     * @return BelongsTo<Debt, Debt> Parent request relation.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id')->withoutGlobalScope(CampaignDebtScope::class);
    }

    /**
     * Campaign debts bundled into this payment request.
     *
     * @return HasMany<Debt> Child debt relation.
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Admin who approved or rejected this payment request.
     *
     * @return BelongsTo<Admin, Debt> Reviewer relation.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_admin_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(DebtAdjustment::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(DebtPayment::class);
    }

    /**
     * Build the error raised when a write touches a debt frozen by a payment request.
     *
     * @return ValidationException Localized validation error.
     */
    private static function lockedException(): ValidationException
    {
        return ValidationException::withMessages(['debt' => __('room.debts.request_locked')]);
    }
}
