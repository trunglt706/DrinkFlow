<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Subscription of an Agent (Admin) to a package.
 *
 * `price_snapshot` and `room_limit_snapshot` are copied from the package at activation and are
 * the only values billing and room quota read, so later package edits never change it silently.
 *
 * @property int $id
 * @property int $admin_id
 * @property int $package_id
 * @property SubscriptionStatus $status
 * @property int $price_snapshot
 * @property int $room_limit_snapshot
 * @property \Illuminate\Support\Carbon $starts_at
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $ended_at
 * @property int|null $approved_by_superadmin_id
 */
class AdminSubscription extends Model
{
    protected $fillable = [
        'admin_id', 'previous_subscription_id', 'package_id', 'scheduled_package_id', 'status', 'price_snapshot', 'period_months', 'proration_credit',
        'room_limit_snapshot', 'starts_at', 'expires_at', 'renewal_reminder_sent_for', 'cancel_at_period_end', 'ended_at', 'approved_by_superadmin_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'price_snapshot' => 'integer',
            'period_months' => 'integer',
            'proration_credit' => 'integer',
            'renewal_reminder_sent_for' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'room_limit_snapshot' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    /**
     * Current subscriptions.
     *
     * @param Builder<AdminSubscription> $query Subscription query.
     * @return Builder<AdminSubscription> Active subscriptions.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', SubscriptionStatus::Active->value);
    }

    /**
     * Subscription waiting for its first invoice to be paid (self-registered Agent).
     *
     * @param Builder<AdminSubscription> $query Subscription query.
     * @return Builder<AdminSubscription> Pending subscriptions.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', SubscriptionStatus::Pending->value);
    }

    /**
     * Length of one billing period in months (at least 1).
     */
    public function periodMonths(): int
    {
        return max(1, (int) ($this->period_months ?? 1));
    }

    /**
     * Price of one full billing period: the monthly price snapshot times the period length.
     *
     * @return int Amount in VND.
     */
    public function periodPrice(): int
    {
        return (int) $this->price_snapshot * $this->periodMonths();
    }

    /**
     * Subscribed Agent.
     *
     * @return BelongsTo<Admin, $this> Agent.
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    /**
     * Package the subscription was created on (its live values may differ from the snapshot).
     *
     * @return BelongsTo<Package, $this> Package.
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Package the subscription moves to at the end of the period (scheduled downgrade).
     *
     * @return BelongsTo<Package, $this> Scheduled package.
     */
    public function scheduledPackage(): BelongsTo
    {
        return $this->belongsTo(Package::class, 'scheduled_package_id');
    }

    /**
     * Subscription this one replaced (package change).
     *
     * @return BelongsTo<AdminSubscription, $this> Previous subscription.
     */
    public function previous(): BelongsTo
    {
        return $this->belongsTo(self::class, 'previous_subscription_id');
    }

    /**
     * Invoices of this subscription, one per period.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany<AdminInvoice, $this> Invoices.
     */
    public function invoices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AdminInvoice::class);
    }

    /**
     * Superadmin who approved/activated the subscription.
     *
     * @return BelongsTo<Superadmin, $this> Approver.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Superadmin::class, 'approved_by_superadmin_id');
    }
}
