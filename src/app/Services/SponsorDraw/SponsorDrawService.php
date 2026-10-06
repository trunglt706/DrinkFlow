<?php

declare(strict_types=1);

namespace App\Services\SponsorDraw;

use App\Actions\Campaign\UpdateCampaignAction;
use App\Enums\CampaignStatus;
use App\Enums\GlobalUserStatus;
use App\Enums\RoomUserStatus;
use App\Enums\SponsorDrawStatus;
use App\Jobs\SendSponsorDrawNotification;
use App\Models\Admin;
use App\Models\Campaign;
use App\Models\CampaignParticipant;
use App\Models\NotificationChannel;
use App\Models\Room;
use App\Models\RoomUser;
use App\Models\SponsorDrawEligibleUser;
use App\Models\SponsorDrawNotification;
use App\Models\SponsorDrawResult;
use App\Models\SponsorDrawSession;
use App\Services\Audit\AuditService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Sponsor draw of a campaign: an admin freezes the eligible members, publishes a time-limited link to the
 * campaign's landing page, draws the winners one turn at a time (the backend picks them), confirms the
 * result (optionally making the winners the sponsors of a next campaign) and sends it to the room's
 * notification channels.
 *
 * Eligible = active members of the campaign's room (member and global account both active) who did not
 * decline the campaign, frozen when the session is created; later changes never alter that snapshot.
 * Every state change runs in a transaction holding the session row lock, so concurrent admins can never
 * draw the same member twice or exceed the target; unique indexes back that up at database level.
 */
class SponsorDrawService
{
    public function __construct(
        private readonly SponsorDrawRandomizer $randomizer,
        private readonly SponsorDrawEventLog $events,
        private readonly AuditService $audit,
        private readonly UpdateCampaignAction $updateCampaign,
    ) {}

    /**
     * Members eligible for a draw over the campaign right now.
     *
     * @return Builder<RoomUser>
     */
    public function eligibleMembersQuery(Campaign $campaign): Builder
    {
        return RoomUser::query()
            ->where('room_id', $campaign->room_id)
            ->where('status', RoomUserStatus::Active->value)
            ->whereHas('globalUser', static fn (Builder $query) => $query->where('status', GlobalUserStatus::Active->value))
            ->whereNotIn('id', CampaignParticipant::query()->where('campaign_id', $campaign->id)
                ->where('status', CampaignParticipant::STATUS_DECLINED)->select('room_user_id'));
    }

    /**
     * Whether a campaign can receive the winners as sponsors (not finished, not cancelled).
     */
    public function canBeTarget(Campaign $campaign): bool
    {
        return in_array($campaign->status, [CampaignStatus::Draft, CampaignStatus::Scheduled, CampaignStatus::Active], true);
    }

    /**
     * Create a session: freeze the snapshot, commit to a seed and issue the access link.
     *
     * @param Room $room Room of the campaign.
     * @param Admin $admin Admin creating it.
     * @param Campaign $campaign Source campaign.
     * @param int $winners Number of sponsors to draw.
     * @param Campaign|null $target Optional next campaign receiving the winners as sponsors.
     * @return array{session: SponsorDrawSession, token: string} Session and the plain link token (shown to the admin).
     * @throws ValidationException When the campaigns or the number of winners are invalid.
     */
    public function create(Room $room, Admin $admin, Campaign $campaign, int $winners, ?Campaign $target = null): array
    {
        if ((int) $campaign->room_id !== (int) $room->id) {
            throw ValidationException::withMessages(['campaign_id' => __('sponsor_draw.errors.campaign_not_in_room')]);
        }
        if ($target !== null && ((int) $target->room_id !== (int) $room->id || (int) $target->id === (int) $campaign->id || ! $this->canBeTarget($target))) {
            throw ValidationException::withMessages(['target_campaign_id' => __('sponsor_draw.errors.target_invalid')]);
        }

        return DB::transaction(function () use ($room, $admin, $campaign, $winners, $target): array {
            Campaign::query()->whereKey($campaign->id)->lockForUpdate()->first();
            $members = $this->eligibleMembersQuery($campaign)->with('globalUser:id,name')->orderBy('id')->get();
            if ($members->isEmpty()) {
                throw ValidationException::withMessages(['campaign_id' => __('sponsor_draw.errors.no_eligible')]);
            }
            if ($winners < 1 || $winners > $members->count()) {
                throw ValidationException::withMessages(['target_winner_count' => __('sponsor_draw.errors.too_many_winners', ['count' => $members->count()])]);
            }

            $seed = $this->randomizer->newSeed();
            $session = SponsorDrawSession::create([
                'room_id' => $room->id,
                'campaign_id' => $campaign->id,
                'target_campaign_id' => $target?->id,
                'target_winner_count' => $winners,
                'status' => SponsorDrawStatus::Ready->value,
                'eligibility_snapshot_at' => now(),
                'eligible_count' => $members->count(),
                'snapshot_hash' => str_repeat('0', 64),
                'seed_encrypted' => $seed,
                'seed_commitment' => str_repeat('0', 64),
                'random_method' => SponsorDrawRandomizer::METHOD,
                'created_by_admin_id' => $admin->id,
            ]);
            foreach ($members->values() as $position => $member) {
                SponsorDrawEligibleUser::create([
                    'sponsor_draw_session_id' => $session->id,
                    'room_user_id' => $member->id,
                    'position' => $position,
                    'display_name_snapshot' => $member->payerName() ?: __('sponsor_draw.anonymous'),
                ]);
            }
            $snapshotHash = $this->randomizer->snapshotHash($session->id, $members->pluck('id')->map(static fn ($id): int => (int) $id)->all());
            $session->update(['snapshot_hash' => $snapshotHash, 'seed_commitment' => $this->randomizer->commitment($seed, $session->id, $snapshotHash)]);
            $token = $this->assignLink($session);

            $this->events->append($session, 'eligibility.locked', [
                'eligible_count' => $session->eligible_count,
                'snapshot_hash' => $session->snapshot_hash,
                'seed_commitment' => $session->seed_commitment,
                'random_method' => $session->random_method,
            ], $admin);
            $this->events->append($session, 'session.ready', [
                'target_winner_count' => $winners,
                'link_expires_at' => $session->link_expires_at->toIso8601String(),
            ], $admin);
            $this->audit->record('sponsor_draw.created', 'sponsor_draw_session', $session->id, $room->id, [], [
                'campaign_id' => $campaign->id,
                'target_campaign_id' => $target?->id,
                'target_winner_count' => $winners,
                'eligible_count' => $session->eligible_count,
                'snapshot_hash' => $snapshotHash,
            ]);

            return ['session' => $session->refresh(), 'token' => $token];
        });
    }

    /**
     * Issue a new access link (the previous one stops working at once).
     *
     * @return string Plain token.
     * @throws ValidationException When the session was cancelled.
     */
    public function reissueLink(SponsorDrawSession $session, Admin $admin): string
    {
        return DB::transaction(function () use ($session, $admin): string {
            $session = $this->lock($session);
            if ($session->status === SponsorDrawStatus::Cancelled) {
                throw ValidationException::withMessages(['session' => __('sponsor_draw.errors.cancelled')]);
            }
            $token = $this->assignLink($session);
            $this->events->append($session, 'session.link_issued', ['link_expires_at' => $session->link_expires_at->toIso8601String()], $admin);
            $this->audit->record('sponsor_draw.link_issued', 'sponsor_draw_session', $session->id, $session->room_id, [], [
                'link_expires_at' => $session->link_expires_at->toIso8601String(),
            ]);

            return $token;
        });
    }

    /**
     * Session of a link token (the token is looked up by its hash).
     */
    public function findByToken(string $token): ?SponsorDrawSession
    {
        return preg_match('/^[A-Za-z0-9]{40,128}$/', $token) === 1
            ? SponsorDrawSession::query()->where('access_token_hash', hash('sha256', $token))->first()
            : null;
    }

    /**
     * Plain link token of a session, for the admin to copy while it is valid.
     */
    public function currentToken(SponsorDrawSession $session): ?string
    {
        return $session->linkIsValid() ? (string) $session->access_token_encrypted : null;
    }

    /**
     * Draw the next turn: the backend picks the winner from the frozen snapshot and the committed seed.
     *
     * @return SponsorDrawResult The new winner.
     * @throws ValidationException When the session cannot draw (status, expired link, target reached).
     */
    public function draw(SponsorDrawSession $session, Admin $admin): SponsorDrawResult
    {
        return DB::transaction(function () use ($session, $admin): SponsorDrawResult {
            $session = $this->lock($session);
            if (! $session->status->canDraw()) {
                throw ValidationException::withMessages(['session' => __('sponsor_draw.errors.cannot_draw')]);
            }
            if (! $session->linkIsValid()) {
                throw ValidationException::withMessages(['session' => __('sponsor_draw.errors.link_expired')]);
            }
            $drawn = SponsorDrawResult::query()->where('sponsor_draw_session_id', $session->id)->count();
            if ($drawn >= $session->target_winner_count) {
                throw ValidationException::withMessages(['session' => __('sponsor_draw.errors.target_reached')]);
            }

            $positions = $this->randomizer->winnerPositions((string) $session->seed_encrypted, $session->eligible_count, $session->target_winner_count);
            $winner = SponsorDrawEligibleUser::query()->where('sponsor_draw_session_id', $session->id)->where('position', $positions[$drawn])->firstOrFail();
            $order = $drawn + 1;
            $this->events->append($session, 'draw.started', ['draw_order' => $order], $admin);
            $result = SponsorDrawResult::create([
                'sponsor_draw_session_id' => $session->id,
                'room_user_id' => $winner->room_user_id,
                'snapshot_position' => $winner->position,
                'display_name_snapshot' => $winner->display_name_snapshot,
                'draw_order' => $order,
                'drawn_by_admin_id' => $admin->id,
                'drawn_at' => now(),
            ]);
            $winner->update(['is_winner' => true]);
            $completed = $order >= $session->target_winner_count;
            $session->update(['status' => ($completed ? SponsorDrawStatus::Completed : SponsorDrawStatus::Drawing)->value]);

            $this->events->append($session, 'draw.completed', [
                'draw_order' => $order,
                'winner' => $winner->display_name_snapshot,
                'position' => $winner->position,
                'drawn' => $order,
                'target' => $session->target_winner_count,
            ], $admin);
            $this->audit->record('sponsor_draw.drawn', 'sponsor_draw_session', $session->id, $session->room_id, [], [
                'draw_order' => $order,
                'room_user_id' => $winner->room_user_id,
                'snapshot_position' => $winner->position,
            ]);
            if ($completed) {
                $this->revealSeed($session);
                $this->events->append($session, 'session.completed', [
                    'drawn' => $order,
                    'seed' => (string) $session->seed_encrypted,
                ], $admin);
            }

            return $result;
        });
    }

    /**
     * Cancel a session that is not confirmed yet (its results stay stored; the seed is revealed).
     *
     * @throws ValidationException When it can no longer be cancelled.
     */
    public function cancel(SponsorDrawSession $session, Admin $admin, string $reason): SponsorDrawSession
    {
        return DB::transaction(function () use ($session, $admin, $reason): SponsorDrawSession {
            $session = $this->lock($session);
            if (! $session->status->canCancel()) {
                throw ValidationException::withMessages(['session' => __('sponsor_draw.errors.cannot_cancel')]);
            }
            $before = $session->status->value;
            $session->update([
                'status' => SponsorDrawStatus::Cancelled->value,
                'cancelled_by_admin_id' => $admin->id,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);
            $this->revealSeed($session);
            $this->events->append($session, 'session.cancelled', ['reason' => $reason, 'seed' => (string) $session->seed_encrypted], $admin);
            $this->audit->record('sponsor_draw.cancelled', 'sponsor_draw_session', $session->id, $session->room_id, ['status' => $before], [
                'status' => SponsorDrawStatus::Cancelled->value,
                'reason' => $reason,
            ]);

            return $session;
        });
    }

    /**
     * Confirm the result: optionally make the winners the sponsors of the next campaign, then queue one
     * notification per enabled room channel. Idempotent: confirming again changes nothing.
     *
     * @throws ValidationException When the draw is not completed.
     */
    public function confirm(SponsorDrawSession $session, Admin $admin): SponsorDrawSession
    {
        $session = DB::transaction(function () use ($session, $admin): SponsorDrawSession {
            $session = $this->lock($session);
            if ($session->status->isConfirmed()) {
                return $session;
            }
            if ($session->status !== SponsorDrawStatus::Completed) {
                throw ValidationException::withMessages(['session' => __('sponsor_draw.errors.not_completed')]);
            }

            $results = SponsorDrawResult::query()->where('sponsor_draw_session_id', $session->id)->orderBy('draw_order')->get();
            $assignment = $this->assignSponsors($session, $results->pluck('room_user_id')->map(static fn ($id): int => (int) $id)->all(), $admin);
            $payload = $this->notificationPayload($session, $results->pluck('display_name_snapshot')->all());
            $channels = NotificationChannel::query()->where('room_id', $session->room_id)
                ->where('status', NotificationChannel::STATUS_ENABLED)->orderBy('id')->get();
            foreach ($channels as $channel) {
                SponsorDrawNotification::firstOrCreate(
                    ['sponsor_draw_session_id' => $session->id, 'notification_channel_id' => $channel->id],
                    ['channel_type' => $channel->type, 'channel_name' => $channel->name, 'status' => SponsorDrawNotification::STATUS_PENDING, 'payload_snapshot' => $payload],
                );
            }

            $session->update([
                'status' => ($channels->isEmpty() ? SponsorDrawStatus::Confirmed : SponsorDrawStatus::NotificationPending)->value,
                'confirmed_by_admin_id' => $admin->id,
                'confirmed_at' => now(),
                'sponsor_assignment' => $assignment,
            ]);
            $this->events->append($session, 'result.confirmed', [
                'winners' => $results->pluck('display_name_snapshot')->all(),
                'target_campaign' => $session->targetCampaign?->name,
                'sponsor_assignment' => $assignment['status'] ?? null,
                'channels' => $channels->count(),
            ], $admin);
            $this->audit->record('sponsor_draw.confirmed', 'sponsor_draw_session', $session->id, $session->room_id, [], [
                'winners' => $results->pluck('room_user_id')->all(),
                'sponsor_assignment' => $assignment,
                'channels' => $channels->pluck('id')->all(),
            ]);
            DB::afterCommit(fn () => $this->queuePending($session));

            return $session;
        });

        return $session->refresh();
    }

    /**
     * Send again the channels whose delivery failed (successful channels are never sent twice).
     *
     * @return int Number of channels queued again.
     */
    public function resendFailed(SponsorDrawSession $session, Admin $admin): int
    {
        return DB::transaction(function () use ($session, $admin): int {
            $session = $this->lock($session);
            if (! $session->status->isConfirmed()) {
                throw ValidationException::withMessages(['session' => __('sponsor_draw.errors.not_confirmed')]);
            }
            $failed = SponsorDrawNotification::query()->where('sponsor_draw_session_id', $session->id)
                ->where('status', SponsorDrawNotification::STATUS_FAILED)->lockForUpdate()->get();
            if ($failed->isEmpty()) {
                return 0;
            }
            SponsorDrawNotification::query()->whereKey($failed->pluck('id'))->update(['status' => SponsorDrawNotification::STATUS_PENDING]);
            $session->update(['status' => SponsorDrawStatus::NotificationPending->value]);
            $this->events->append($session, 'notification.updated', ['channels' => $this->channelStatuses($session)], $admin);
            $this->audit->record('sponsor_draw.notification_resent', 'sponsor_draw_session', $session->id, $session->room_id, [], [
                'notification_ids' => $failed->pluck('id')->all(),
            ]);
            DB::afterCommit(fn () => $this->queuePending($session));

            return $failed->count();
        });
    }

    /**
     * Record the outcome of one channel delivery and refresh the session's notification status.
     *
     * @param SponsorDrawNotification $notification Delivery row (locked by the caller's transaction).
     * @param string|null $error Error message, null on success.
     */
    public function recordDelivery(SponsorDrawNotification $notification, ?string $error): void
    {
        DB::transaction(function () use ($notification, $error): void {
            $session = $this->lock($notification->session);
            $notification->update($error === null
                ? ['status' => SponsorDrawNotification::STATUS_SENT, 'sent_at' => now(), 'last_error' => null]
                : ['status' => SponsorDrawNotification::STATUS_FAILED, 'last_error' => Str::limit($error, 500)]);

            $statuses = SponsorDrawNotification::query()->where('sponsor_draw_session_id', $session->id)->pluck('status');
            $status = match (true) {
                $statuses->contains(SponsorDrawNotification::STATUS_PENDING) => SponsorDrawStatus::NotificationPending,
                $statuses->contains(SponsorDrawNotification::STATUS_FAILED) => SponsorDrawStatus::NotificationFailed,
                default => SponsorDrawStatus::Notified,
            };
            $session->update(['status' => $status->value]);
            $this->events->append($session, 'notification.updated', ['channels' => $this->channelStatuses($session)]);
        });
    }

    /**
     * State shown on the landing page and the admin page (names only for members).
     *
     * @param bool $forAdmin Include delivery details and the link (admin page only).
     * @return array<string, mixed>
     */
    public function state(SponsorDrawSession $session, bool $forAdmin = false): array
    {
        $session->loadMissing(['campaign:id,name,code', 'targetCampaign:id,name,code']);
        $results = SponsorDrawResult::query()->with('drawnBy:id,name')->where('sponsor_draw_session_id', $session->id)->orderBy('draw_order')->get();
        $seedRevealed = $session->seed_revealed_at !== null;

        $state = [
            'session_id' => $session->id,
            'status' => $session->status->value,
            'campaign' => $session->campaign?->name,
            'target_campaign' => $session->targetCampaign?->name,
            'link_expires_at' => $session->link_expires_at?->toIso8601String(),
            'link_valid' => $session->linkIsValid(),
            'eligible_count' => $session->eligible_count,
            'target_winner_count' => $session->target_winner_count,
            'drawn_count' => $results->count(),
            'remaining_count' => max(0, $session->target_winner_count - $results->count()),
            'eligible' => SponsorDrawEligibleUser::query()->where('sponsor_draw_session_id', $session->id)->orderBy('position')
                ->get(['position', 'display_name_snapshot', 'is_winner'])
                ->map(static fn (SponsorDrawEligibleUser $user): array => ['position' => $user->position, 'name' => $user->display_name_snapshot, 'is_winner' => $user->is_winner])
                ->all(),
            'results' => $results->map(static fn (SponsorDrawResult $result): array => [
                'draw_order' => $result->draw_order,
                'name' => $result->display_name_snapshot,
                'position' => $result->snapshot_position,
                'drawn_at' => $result->drawn_at->toIso8601String(),
                'drawn_by' => $result->drawnBy?->name,
            ])->all(),
            'proof' => [
                'random_method' => $session->random_method,
                'snapshot_hash' => $session->snapshot_hash,
                'seed_commitment' => $session->seed_commitment,
                'seed' => $seedRevealed ? (string) $session->seed_encrypted : null,
            ],
            'last_sequence' => $session->last_sequence,
            'server_time' => now()->toIso8601String(),
        ];
        if ($forAdmin) {
            $state['notifications'] = $this->channelStatuses($session, true);
            $state['sponsor_assignment'] = $session->sponsor_assignment;
            $state['cancel_reason'] = $session->cancel_reason;
        }

        return $state;
    }

    /**
     * Full verification record for the admin (snapshot with internal IDs, seed, results, event chain).
     *
     * @return array<string, mixed>
     */
    public function proof(SponsorDrawSession $session): array
    {
        $seedRevealed = $session->seed_revealed_at !== null;

        return [
            'session_id' => $session->id,
            'campaign' => $session->campaign?->name,
            'status' => $session->status->value,
            'random_method' => $session->random_method,
            'algorithm' => 'snapshot ordered by room_user_id; seed = 32 bytes CSPRNG; stream = HMAC-SHA256(seed, "drinkflow-sponsor-draw-v1:{counter}") as big-endian uint32; uniform(n) by rejection sampling; partial Fisher-Yates over positions',
            'snapshot' => SponsorDrawEligibleUser::query()->where('sponsor_draw_session_id', $session->id)->orderBy('position')
                ->get(['position', 'room_user_id', 'display_name_snapshot'])->map(static fn ($user): array => [
                    'position' => $user->position, 'room_user_id' => $user->room_user_id, 'name' => $user->display_name_snapshot,
                ])->all(),
            'snapshot_hash' => $session->snapshot_hash,
            'snapshot_hash_valid' => hash_equals($session->snapshot_hash, $this->randomizer->snapshotHash(
                $session->id,
                SponsorDrawEligibleUser::query()->where('sponsor_draw_session_id', $session->id)->orderBy('position')->pluck('room_user_id')->map(static fn ($id): int => (int) $id)->all(),
            )),
            'seed_commitment' => $session->seed_commitment,
            'seed' => $seedRevealed ? (string) $session->seed_encrypted : null,
            'seed_matches_commitment' => $seedRevealed
                ? hash_equals($session->seed_commitment, $this->randomizer->commitment((string) $session->seed_encrypted, $session->id, $session->snapshot_hash))
                : null,
            'results' => SponsorDrawResult::query()->where('sponsor_draw_session_id', $session->id)->orderBy('draw_order')
                ->get(['draw_order', 'snapshot_position', 'room_user_id', 'display_name_snapshot', 'drawn_at'])->toArray(),
            'event_chain' => $this->events->verify($session),
            'events' => \App\Models\SponsorDrawEvent::query()->where('sponsor_draw_session_id', $session->id)->orderBy('sequence')
                ->get(['sequence', 'event_uuid', 'type', 'payload', 'previous_hash', 'event_hash', 'created_at'])->toArray(),
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Queue the pending deliveries of a session.
     */
    private function queuePending(SponsorDrawSession $session): void
    {
        SponsorDrawNotification::query()->where('sponsor_draw_session_id', $session->id)
            ->where('status', SponsorDrawNotification::STATUS_PENDING)->pluck('id')
            ->each(static fn ($id) => SendSponsorDrawNotification::dispatch((int) $id));
    }

    /**
     * Make the winners the sponsors of the target campaign (equal shares); skipped, never fatal.
     *
     * @param list<int> $roomUserIds Winners in draw order.
     * @return array{status: string, campaign_id?: int, allocations?: list<array{room_user_id: int, percentage: int}>, reason?: string}
     */
    private function assignSponsors(SponsorDrawSession $session, array $roomUserIds, Admin $admin): array
    {
        $target = $session->target_campaign_id !== null ? Campaign::query()->lockForUpdate()->find($session->target_campaign_id) : null;
        if ($target === null) {
            return ['status' => 'none'];
        }
        if (! $this->canBeTarget($target)) {
            return ['status' => 'skipped', 'campaign_id' => $target->id, 'reason' => __('sponsor_draw.errors.target_no_longer_open')];
        }

        $count = count($roomUserIds);
        $share = intdiv(100, $count);
        $remainder = 100 - $share * $count;
        $allocations = [];
        foreach (array_values($roomUserIds) as $index => $roomUserId) {
            // Integer shares adding up to exactly 100%: the first winners take the remainder.
            $allocations[] = ['room_user_id' => $roomUserId, 'percentage' => $share + ($index < $remainder ? 1 : 0)];
        }
        try {
            $this->updateCampaign->execute($target, ['sponsor_type' => Campaign::SPONSOR_TYPE_FULL, 'sponsor_allocations' => $allocations], $admin->id, false);
        } catch (ValidationException $exception) {
            return ['status' => 'skipped', 'campaign_id' => $target->id, 'reason' => collect($exception->errors())->flatten()->first() ?? $exception->getMessage()];
        }

        return ['status' => 'assigned', 'campaign_id' => $target->id, 'allocations' => $allocations];
    }

    /**
     * Driver-neutral gateway payload of a confirmed result (names only).
     *
     * @param list<string> $winners Winner names in draw order.
     * @return array<string, mixed>
     */
    private function notificationPayload(SponsorDrawSession $session, array $winners): array
    {
        $lines = [__('sponsor_draw.notify.campaign', ['name' => $session->campaign?->name])];
        foreach (array_values($winners) as $index => $name) {
            $lines[] = __('sponsor_draw.notify.winner', ['order' => $index + 1, 'name' => $name]);
        }
        if ($session->targetCampaign !== null) {
            $lines[] = __('sponsor_draw.notify.target', ['name' => $session->targetCampaign->name]);
        }

        return [
            'event' => 'sponsor_draw.confirmed',
            'title' => __('sponsor_draw.notify.title', ['campaign' => $session->campaign?->name]),
            'message' => implode("\n", $lines),
        ];
    }

    /**
     * Delivery status of every channel (type / name / status; errors for admins only).
     *
     * @return list<array<string, mixed>>
     */
    private function channelStatuses(SponsorDrawSession $session, bool $withDetails = false): array
    {
        return SponsorDrawNotification::query()->where('sponsor_draw_session_id', $session->id)->orderBy('id')->get()
            ->map(static fn (SponsorDrawNotification $row): array => [
                'type' => $row->channel_type,
                'status' => $row->status,
            ] + ($withDetails ? [
                'id' => $row->id,
                'name' => $row->channel_name,
                'attempts' => $row->attempt_count,
                'last_error' => $row->last_error,
                'sent_at' => $row->sent_at?->toIso8601String(),
            ] : []))->all();
    }

    /**
     * Give the session a fresh random link token (stored hashed + encrypted) and expiry.
     *
     * @return string Plain token.
     */
    private function assignLink(SponsorDrawSession $session): string
    {
        $token = Str::random(64);
        $session->update([
            'access_token_hash' => hash('sha256', $token),
            'access_token_encrypted' => $token,
            'link_expires_at' => now()->addMinutes(max(1, (int) config('sponsor_draw.link_ttl_minutes', 60))),
        ]);

        return $token;
    }

    /**
     * Mark the seed as revealed (drawing finished or cancelled).
     */
    private function revealSeed(SponsorDrawSession $session): void
    {
        if ($session->seed_revealed_at === null) {
            $session->update(['seed_revealed_at' => now()]);
        }
    }

    /**
     * Lock the session row for the rest of the transaction.
     */
    private function lock(SponsorDrawSession $session): SponsorDrawSession
    {
        return SponsorDrawSession::query()->lockForUpdate()->findOrFail($session->id);
    }
}
