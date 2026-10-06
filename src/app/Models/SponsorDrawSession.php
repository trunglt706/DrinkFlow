<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SponsorDrawStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One sponsor draw of a campaign (see App\Services\SponsorDraw\SponsorDrawService).
 *
 * @property int $id
 * @property int $room_id
 * @property int|null $campaign_id
 * @property AppnumsSponsorDrawType $type
 * @property int|null $target_campaign_id
 * @property int $target_winner_count
 * @property SponsorDrawStatus $status
 * @property \Illuminate\Support\Carbon|null $link_expires_at
 * @property int $eligible_count
 * @property string $snapshot_hash
 * @property string $seed_commitment
 * @property int $last_sequence
 */
class SponsorDrawSession extends Model
{
    protected $fillable = [
        'room_id', 'type', 'campaign_id', 'target_campaign_id', 'target_winner_count', 'status', 'access_token_hash', 'access_token_encrypted',
        'link_expires_at', 'eligibility_snapshot_at', 'eligible_count', 'snapshot_hash', 'seed_encrypted', 'seed_commitment', 'random_method',
        'seed_revealed_at', 'last_sequence', 'created_by_admin_id', 'confirmed_by_admin_id', 'confirmed_at', 'cancelled_by_admin_id',
        'cancelled_at', 'cancel_reason', 'sponsor_assignment',
    ];

    /** The token and the seed never leave the model through serialization. */
    protected $hidden = ['access_token_hash', 'access_token_encrypted', 'seed_encrypted'];

    protected function casts(): array
    {
        return [
            'status' => SponsorDrawStatus::class,
            'type' => \App\Enums\SponsorDrawType::class,
            'target_winner_count' => 'integer',
            'eligible_count' => 'integer',
            'last_sequence' => 'integer',
            'access_token_encrypted' => 'encrypted',
            'seed_encrypted' => 'encrypted',
            'link_expires_at' => 'datetime',
            'eligibility_snapshot_at' => 'datetime',
            'seed_revealed_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'sponsor_assignment' => 'array',
        ];
    }

    /** Whether the access link exists and has not expired yet. */
    public function linkIsValid(): bool
    {
        return $this->access_token_hash !== null && $this->link_expires_at !== null && $this->link_expires_at->isFuture();
    }

    /** Timezone the session's times are shown in: the room's, else the application display timezone. */
    public function displayTimezone(): string
    {
        return (string) ($this->room?->timezone ?: config('app.display_timezone', config('app.timezone')));
    }

    /**
     * Format a stored (UTC) time of this session in its display timezone.
     *
     * @param  \DateTimeInterface|string|null  $time  Stored time; empty values render as an empty string.
     */
    public function localTime(\DateTimeInterface|string|null $time, string $format = 'H:i d/m/Y'): string
    {
        return $time ? \Illuminate\Support\Carbon::parse($time)->setTimezone($this->displayTimezone())->format($format) : '';
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function targetCampaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'target_campaign_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by_admin_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'confirmed_by_admin_id');
    }

    /** @return HasMany<SponsorDrawEligibleUser, $this> */
    public function eligibleUsers(): HasMany
    {
        return $this->hasMany(SponsorDrawEligibleUser::class)->orderBy('position');
    }

    /** @return HasMany<SponsorDrawResult, $this> */
    public function results(): HasMany
    {
        return $this->hasMany(SponsorDrawResult::class)->orderBy('draw_order');
    }

    /** @return HasMany<SponsorDrawNotification, $this> */
    public function notifications(): HasMany
    {
        return $this->hasMany(SponsorDrawNotification::class)->orderBy('id');
    }

    /** @return HasMany<SponsorDrawEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(SponsorDrawEvent::class)->orderBy('sequence');
    }
}
