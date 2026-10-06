<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FeedbackStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    use HasFactory;

    /** Minimum rating for an approved feedback to be shown on the /me/feedback page. */
    public const MIN_VISIBLE_RATING = 3;

    /** Subsystem codes a feedback can be about (public form and Superadmin correction). */
    public const SUBSYSTEMS = ['all', 'room', 'split_qr', 'socket', 'sponsor'];

    protected $table = 'feedbacks';

    protected $fillable = [
        'global_user_id',
        'rating',
        'subsystem',
        'content',
        'status',
        'user_display_name',
        'department_name',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'integer',
            'status' => FeedbackStatus::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Feedback shown on the /me/feedback page: approved by a superadmin and rated at least MIN_VISIBLE_RATING.
     *
     * @param Builder<Feedback> $query Feedback query.
     * @return Builder<Feedback> Query limited to publicly visible feedback.
     */
    public function scopeVisibleOnFeedbackPage(Builder $query): Builder
    {
        return $query
            ->where('status', FeedbackStatus::Active->value)
            ->where('rating', '>=', self::MIN_VISIBLE_RATING);
    }

    public function globalUser(): BelongsTo
    {
        return $this->belongsTo(GlobalUser::class, 'global_user_id');
    }

    /**
     * Map subsystem code to human-readable label.
     */
    public function getSubsystemLabelAttribute(): string
    {
        return match ($this->subsystem) {
            'room' => __('global.feedback.subsystem_room'),
            'split_qr' => __('global.feedback.subsystem_split_qr'),
            'socket' => __('global.feedback.subsystem_socket'),
            'sponsor' => __('global.feedback.subsystem_sponsor'),
            default => __('global.feedback.subsystem_all'),
        };
    }
}
