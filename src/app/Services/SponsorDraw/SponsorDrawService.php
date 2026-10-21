<?php

declare(strict_types=1);

namespace App\Services\SponsorDraw;

use App\Actions\Campaign\UpdateCampaignAction;
use App\Enums\CampaignStatus;
use App\Enums\GlobalUserStatus;
use App\Enums\RoomUserStatus;
use App\Enums\SponsorDrawStatus;
use App\Enums\SponsorDrawType;
use App\Jobs\SendSponsorDrawNotification;
use App\Models\Admin;
use App\Models\Campaign;
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
 * Draw sessions of a room. An admin creates a draft, then saves its settings on the draw page, which freezes the
 * eligible list, commits to a seed and publishes a time-limited link; the admin draws one turn at a time (the
 * backend picks), confirms the result and it is sent to the room's notification channels.
 *
 * - Sponsors: eligible = active members of the room (member and global account both active); the winners may
 *   become the sponsors of a target campaign on confirmation.
 * - Shops: candidates = the shop names saved in the settings; one shop is drawn and only announced.
 *
 * The frozen snapshot never changes once a turn is drawn. Every state change runs in a transaction holding the
 * session row lock, so concurrent admins can never draw twice the same candidate or exceed the target; unique
 * indexes back that up at database level.
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
     * Members eligible for a sponsor draw of the room right now (also who may watch any draw of the room):
     * active members whose global account is active too.
     *
     * @return Builder<RoomUser>
     */
    public function eligibleMembersQuery(Room $room): Builder
    {
        return RoomUser::query()
            ->where('room_id', $room->id)
            ->where('status', RoomUserStatus::Active->value)
            ->whereHas('globalUser', static fn (Builder $query) => $query->where('status', GlobalUserStatus::Active->value));
    }

    /**
     * Shop names suggested for a shop draw: the distinct shops of the room's past campaigns, newest first.
     *
     * @return list<string>
     */
    public function shopSuggestions(Room $room, int $limit = 50): array
    {
        return Campaign::query()->where('room_id', $room->id)
            ->whereNotNull('restaurant')->where('restaurant', '!=', '')
            ->selectRaw('restaurant, MAX(id) as latest')->groupBy('restaurant')->orderByDesc('latest')
            ->limit($limit)->pluck('restaurant')
            ->map(static fn ($name): string => trim((string) $name))->filter()->unique(static fn (string $name): string => mb_strtolower($name))
            ->values()->all();
    }

    /**
     * Whether a campaign can receive the winners as sponsors (not finished, not cancelled).
     */
    public function canBeTarget(Campaign $campaign): bool
    {
        return in_array($campaign->status, [CampaignStatus::Draft, CampaignStatus::Scheduled, CampaignStatus::Active], true);
    }

    /**
     * Create a draft session from the list: no snapshot, seed or link yet; the draw page then asks for the settings.
     *
     * @param Room $room Room of the session.
     * @param Admin $admin Admin creating it.
     * @return SponsorDrawSession Draft session.
     */
    public function createDraft(Room $room, Admin $admin): SponsorDrawSession
    {
        return DB::transaction(function () use ($room, $admin): SponsorDrawSession {
            $session = SponsorDrawSession::create([
                'room_id' => $room->id,
                'type' => SponsorDrawType::Sponsors->value,
                'target_winner_count' => 1,
                'status' => SponsorDrawStatus::Draft->value,
                'eligible_count' => 0,
                'created_by_admin_id' => $admin->id,
            ]);
            $this->events->append($session, 'session.draft', [], $admin);
            $this->audit->record('sponsor_draw.created', 'sponsor_draw_session', $session->id, $room->id, [], ['status' => SponsorDrawStatus::Draft->value]);

            return $session->refresh();
        });
    }

    /**
     * Create a session with its settings at once (the "New session" dialog of the list): nothing is kept when the
     * settings are invalid.
     *
     * @param Room $room Room of the session.
     * @param Admin $admin Admin creating it.
     * @param SponsorDrawType $type What is drawn.
     * @param int $winners Number of sponsors (ignored for shops: always one).
     * @param Campaign|null $target Campaign receiving the sponsors (sponsors only).
     * @param list<string> $shops Shop names (shops only).
     * @return array{session: SponsorDrawSession, token: string|null} Ready session and the plain token of its link.
     * @throws ValidationException When the settings are invalid (no session is created).
     */
    public function create(Room $room, Admin $admin, SponsorDrawType $type, int $winners, ?Campaign $target = null, array $shops = []): array
    {
        return DB::transaction(fn (): array => $this->configure($this->createDraft($room, $admin), $admin, $type, $winners, $target, $shops));
    }

    /**
     * Save the settings: freeze the eligible list, commit to a new seed and make sure an access link exists.
     *
     * - Sponsors: every active member of the room is eligible; `$winners` sponsors are drawn and, when a target
     *   campaign is given, they become its sponsors on confirmation.
     * - Shops: the given shop names (trimmed, duplicates removed) are the candidates; one shop is drawn and the
     *   result is only announced.
     *
     * Allowed for a draft, or for a ready session before its first turn (the new snapshot and seed commitment are
     * appended to the event chain, so a reconfiguration stays visible).
     *
     * @param SponsorDrawSession $session Session to configure.
     * @param Admin $admin Admin saving the settings.
     * @param SponsorDrawType $type What is drawn.
     * @param int $winners Number of sponsors (ignored for shops: always one).
     * @param Campaign|null $target Campaign receiving the sponsors (sponsors only).
     * @param list<string> $shops Shop names (shops only).
     * @return array{session: SponsorDrawSession, token: string|null} Session and the plain token when a new link was issued.
     * @throws ValidationException When the settings are invalid or the session can no longer be configured.
     */
    public function configure(SponsorDrawSession $session, Admin $admin, SponsorDrawType $type, int $winners, ?Campaign $target = null, array $shops = []): array
    {
        return DB::transaction(function () use ($session, $admin, $type, $winners, $target, $shops): array {
            $session = $this->lock($session);
            $room = Room::query()->findOrFail($session->room_id);
            $alreadyDrawn = SponsorDrawResult::query()->where('sponsor_draw_session_id', $session->id)->exists();
            if (! $session->status->canConfigure() || $alreadyDrawn) {
                throw ValidationException::withMessages(['session' => __('sponsor_draw.errors.cannot_configure')]);
            }

            if ($type === SponsorDrawType::Shops) {
                $candidates = $this->normalizeShops($shops);
                $max = (int) config('sponsor_draw.max_shops', 100);
                if (count($candidates) < 2 || count($candidates) > $max) {
                    throw ValidationException::withMessages(['shops' => __('sponsor_draw.errors.shops_count', ['max' => $max])]);
                }
                $winners = 1;
                $target = null;
                $rows = array_map(static fn (string $name): array => ['room_user_id' => null, 'name' => $name], $candidates);
                $snapshotHash = fn (SponsorDrawSession $s): string => $this->randomizer->namesSnapshotHash($s->id, $candidates);
            } else {
                if ($target !== null && ((int) $target->room_id !== (int) $room->id || ! $this->canBeTarget($target))) {
                    throw ValidationException::withMessages(['target_campaign_id' => __('sponsor_draw.errors.target_invalid')]);
                }
                $members = $this->eligibleMembersQuery($room)->with('globalUser:id,name')->orderBy('id')->get();
                if ($members->isEmpty()) {
                    throw ValidationException::withMessages(['target_winner_count' => __('sponsor_draw.errors.no_eligible')]);
                }
                if ($winners < 1 || $winners > $members->count()) {
                    throw ValidationException::withMessages(['target_winner_count' => __('sponsor_draw.errors.too_many_winners', ['count' => $members->count()])]);
                }
                $rows = $members->values()->map(static fn (RoomUser $member): array => [
                    'room_user_id' => $member->id,
                    'name' => $member->payerName() ?: __('sponsor_draw.anonymous'),
                ])->all();
                $memberIds = $members->pluck('id')->map(static fn ($id): int => (int) $id)->all();
                $snapshotHash = fn (SponsorDrawSession $s): string => $this->randomizer->snapshotHash($s->id, $memberIds);
            }

            $wasDraft = $session->status === SponsorDrawStatus::Draft;
            SponsorDrawEligibleUser::query()->where('sponsor_draw_session_id', $session->id)->delete();
            foreach (array_values($rows) as $position => $row) {
                SponsorDrawEligibleUser::create([
                    'sponsor_draw_session_id' => $session->id,
                    'room_user_id' => $row['room_user_id'],
                    'position' => $position,
                    'display_name_snapshot' => $row['name'],
                ]);
            }
            $seed = $this->randomizer->newSeed();
            $hash = $snapshotHash($session);
            $session->update([
                'type' => $type->value,
                'target_campaign_id' => $target?->id,
                'target_winner_count' => $winners,
                'status' => SponsorDrawStatus::Ready->value,
                'eligibility_snapshot_at' => now(),
                'eligible_count' => count($rows),
                'snapshot_hash' => $hash,
                'seed_encrypted' => $seed,
                'seed_commitment' => $this->randomizer->commitment($seed, $session->id, $hash),
                'random_method' => SponsorDrawRandomizer::METHOD,
            ]);
            $token = $session->linkIsValid() ? null : $this->assignLink($session);

            $this->events->append($session, 'settings.saved', [
                'type' => $type->value,
                'target_winner_count' => $winners,
                'target_campaign' => $target?->name,
                'reconfigured' => ! $wasDraft,
            ], $admin);
            $this->events->append($session, 'eligibility.locked', [
                'eligible_count' => $session->eligible_count,
                'snapshot_hash' => $session->snapshot_hash,
                'seed_commitment' => $session->seed_commitment,
                'random_method' => $session->random_method,
            ], $admin);
            $this->events->append($session, 'session.ready', [
                'target_winner_count' => $winners,
                'link_expires_at' => $session->link_expires_at?->toIso8601String(),
            ], $admin);
            $this->audit->record('sponsor_draw.configured', 'sponsor_draw_session', $session->id, $room->id, [], [
                'type' => $type->value,
                'target_campaign_id' => $target?->id,
                'target_winner_count' => $winners,
                'eligible_count' => $session->eligible_count,
                'snapshot_hash' => $hash,
            ]);

            return ['session' => $session->refresh(), 'token' => $token];
        });
    }

    /**
     * Whether the frozen list may still be edited (remove / sync): configured, not cancelled, no turn drawn yet.
     */
    public function canEditEligible(SponsorDrawSession $session): bool
    {
        return $session->status === SponsorDrawStatus::Ready
            && ! SponsorDrawResult::query()->where('sponsor_draw_session_id', $session->id)->exists();
    }

    /**
     * Take one candidate out of the frozen list before the first turn; the list is frozen again with a new seed.
     *
     * @param SponsorDrawSession $session Session whose list changes.
     * @param Admin $admin Admin removing the candidate.
     * @param int $position Snapshot position of the candidate.
     * @return SponsorDrawSession Session with its new snapshot.
     * @throws ValidationException When the list can no longer change or would become too short.
     */
    public function removeEligible(SponsorDrawSession $session, Admin $admin, int $position): SponsorDrawSession
    {
        return DB::transaction(function () use ($session, $admin, $position): SponsorDrawSession {
            $session = $this->lockEditable($session);
            $rows = SponsorDrawEligibleUser::query()->where('sponsor_draw_session_id', $session->id)->orderBy('position')
                ->get(['position', 'room_user_id', 'display_name_snapshot']);
            $removed = $rows->firstWhere('position', $position);
            if ($removed === null) {
                throw ValidationException::withMessages(['position' => __('sponsor_draw.errors.eligible_not_found')]);
            }
            $kept = $rows->reject(static fn (SponsorDrawEligibleUser $row): bool => (int) $row->position === $position)
                ->map(static fn (SponsorDrawEligibleUser $row): array => ['room_user_id' => $row->room_user_id, 'name' => (string) $row->display_name_snapshot])
                ->values()->all();

            $this->refreezeSnapshot($session, $admin, $kept, 'eligibility.removed', ['name' => $removed->display_name_snapshot]);

            return $session->refresh();
        });
    }

    /**
     * Rebuild the list of a sponsor draw from the room's currently eligible members (before the first turn), e.g.
     * after someone joined, left or was removed by mistake.
     *
     * @throws ValidationException When the list can no longer change, the session draws shops, or too few members remain.
     */
    public function syncEligible(SponsorDrawSession $session, Admin $admin): SponsorDrawSession
    {
        return DB::transaction(function () use ($session, $admin): SponsorDrawSession {
            $session = $this->lockEditable($session);
            if ($session->type !== SponsorDrawType::Sponsors) {
                throw ValidationException::withMessages(['session' => __('sponsor_draw.errors.sync_sponsors_only')]);
            }
            $room = Room::query()->findOrFail($session->room_id);
            $rows = $this->eligibleMembersQuery($room)->with('globalUser:id,name')->orderBy('id')->get()
                ->map(static fn (RoomUser $member): array => [
                    'room_user_id' => $member->id,
                    'name' => $member->payerName() ?: __('sponsor_draw.anonymous'),
                ])->values()->all();

            $this->refreezeSnapshot($session, $admin, $rows, 'eligibility.synced', []);

            return $session->refresh();
        });
    }

    /**
     * Start the drawing over: every result is discarded and a new seed is committed for the same frozen list. Allowed
     * until the result is confirmed; the discarded winners and the revealed old seed stay in the event chain.
     *
     * @throws ValidationException When nothing was drawn yet, or the session is confirmed or cancelled.
     */
    public function reset(SponsorDrawSession $session, Admin $admin): SponsorDrawSession
    {
        return DB::transaction(function () use ($session, $admin): SponsorDrawSession {
            $session = $this->lock($session);
            $results = SponsorDrawResult::query()->where('sponsor_draw_session_id', $session->id)->orderBy('draw_order')->get();
            if (! in_array($session->status, [SponsorDrawStatus::Drawing, SponsorDrawStatus::Completed], true) || $results->isEmpty()) {
                throw ValidationException::withMessages(['session' => __('sponsor_draw.errors.cannot_reset')]);
            }

            $previousSeed = (string) $session->seed_encrypted;
            SponsorDrawResult::query()->where('sponsor_draw_session_id', $session->id)->delete();
            SponsorDrawEligibleUser::query()->where('sponsor_draw_session_id', $session->id)->update(['is_winner' => false]);
            $seed = $this->randomizer->newSeed();
            $session->update([
                'status' => SponsorDrawStatus::Ready->value,
                'seed_encrypted' => $seed,
                'seed_commitment' => $this->randomizer->commitment($seed, $session->id, (string) $session->snapshot_hash),
                'seed_revealed_at' => null,
            ]);

            // The old seed is published so the discarded turns stay verifiable.
            $this->events->append($session, 'session.reset', [
                'discarded_winners' => $results->pluck('display_name_snapshot')->all(),
                'previous_seed' => $previousSeed,
                'seed_commitment' => $session->seed_commitment,
            ], $admin);
            $this->audit->record('sponsor_draw.reset', 'sponsor_draw_session', $session->id, $session->room_id, [
                'winners' => $results->pluck('room_user_id')->all(),
            ], ['status' => SponsorDrawStatus::Ready->value]);

            return $session->refresh();
        });
    }

    /**
     * Lock a session whose list may still change.
     *
     * @throws ValidationException When a turn was drawn or the session is not ready.
     */
    private function lockEditable(SponsorDrawSession $session): SponsorDrawSession
    {
        $session = $this->lock($session);
        if (! $this->canEditEligible($session)) {
            throw ValidationException::withMessages(['session' => __('sponsor_draw.errors.cannot_edit_eligible')]);
        }

        return $session;
    }

    /**
     * Freeze a new candidate list (same order as given) and commit to a new seed, recording the change in the chain.
     *
     * @param list<array{room_user_id: int|null, name: string}> $rows New candidates.
     * @param string $event Event type describing the change.
     * @param array<string, mixed> $eventData Event details.
     * @throws ValidationException When too few candidates remain.
     */
    private function refreezeSnapshot(SponsorDrawSession $session, Admin $admin, array $rows, string $event, array $eventData): void
    {
        $isShops = $session->type === SponsorDrawType::Shops;
        $minimum = $isShops ? 2 : max(1, (int) $session->target_winner_count);
        if (count($rows) < $minimum) {
            throw ValidationException::withMessages(['position' => $isShops
                ? __('sponsor_draw.errors.shops_count', ['max' => (int) config('sponsor_draw.max_shops', 100)])
                : __('sponsor_draw.errors.too_few_eligible', ['count' => $minimum])]);
        }

        SponsorDrawEligibleUser::query()->where('sponsor_draw_session_id', $session->id)->delete();
        foreach (array_values($rows) as $position => $row) {
            SponsorDrawEligibleUser::create([
                'sponsor_draw_session_id' => $session->id,
                'room_user_id' => $row['room_user_id'],
                'position' => $position,
                'display_name_snapshot' => $row['name'],
            ]);
        }
        $hash = $isShops
            ? $this->randomizer->namesSnapshotHash($session->id, array_map(static fn (array $row): string => $row['name'], array_values($rows)))
            : $this->randomizer->snapshotHash($session->id, array_map(static fn (array $row): int => (int) $row['room_user_id'], array_values($rows)));
        $seed = $this->randomizer->newSeed();
        $session->update([
            'eligibility_snapshot_at' => now(),
            'eligible_count' => count($rows),
            'snapshot_hash' => $hash,
            'seed_encrypted' => $seed,
            'seed_commitment' => $this->randomizer->commitment($seed, $session->id, $hash),
        ]);

        $this->events->append($session, $event, $eventData + ['eligible_count' => count($rows)], $admin);
        $this->events->append($session, 'eligibility.locked', [
            'eligible_count' => $session->eligible_count,
            'snapshot_hash' => $session->snapshot_hash,
            'seed_commitment' => $session->seed_commitment,
            'random_method' => $session->random_method,
        ], $admin);
        $this->audit->record('sponsor_draw.'.str_replace('eligibility.', 'eligible_', $event), 'sponsor_draw_session', $session->id, $session->room_id, [], [
            'eligible_count' => count($rows),
            'snapshot_hash' => $hash,
        ] + $eventData);
    }

    /**
     * Clean a shop list: trimmed, inner spaces collapsed, empty and duplicate (case-insensitive) names removed.
     *
     * @param list<mixed> $shops Raw names.
     * @return list<string> Names in the given order.
     */
    private function normalizeShops(array $shops): array
    {
        $seen = [];
        $names = [];
        foreach ($shops as $shop) {
            $name = trim((string) preg_replace('/\s+/u', ' ', (string) $shop));
            $key = mb_strtolower($name);
            if ($name === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $names[] = mb_substr($name, 0, 120);
        }

        return $names;
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
            if ($session->status === SponsorDrawStatus::Draft) {
                throw ValidationException::withMessages(['session' => __('sponsor_draw.errors.not_configured')]);
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
            $this->events->append($session, 'session.cancelled', ['reason' => $reason, 'seed' => $session->seed_commitment !== null ? (string) $session->seed_encrypted : null], $admin);
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
            'type' => $session->type->value,
            'status' => $session->status->value,
            // Older sessions came from a campaign; newer ones are titled by their type.
            'campaign' => $session->campaign?->name ?? __('sponsor_draw.types.'.$session->type->value),
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
            $state['can_edit_eligible'] = $session->status === SponsorDrawStatus::Ready && $results->isEmpty();
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

        $snapshotRows = SponsorDrawEligibleUser::query()->where('sponsor_draw_session_id', $session->id)->orderBy('position')->get(['room_user_id', 'display_name_snapshot']);
        $expectedHash = $session->type === SponsorDrawType::Shops
            ? $this->randomizer->namesSnapshotHash($session->id, $snapshotRows->pluck('display_name_snapshot')->map(static fn ($name): string => (string) $name)->all())
            : $this->randomizer->snapshotHash($session->id, $snapshotRows->pluck('room_user_id')->map(static fn ($id): int => (int) $id)->all());

        return [
            'session_id' => $session->id,
            'type' => $session->type->value,
            'campaign' => $session->campaign?->name,
            'status' => $session->status->value,
            'random_method' => $session->random_method,
            'algorithm' => ($session->type === SponsorDrawType::Shops ? 'snapshot = shop names in the order saved; ' : 'snapshot ordered by room_user_id; ').'seed = 32 bytes CSPRNG; stream = HMAC-SHA256(seed, "drinkflow-sponsor-draw-v1:{counter}") as big-endian uint32; uniform(n) by rejection sampling; partial Fisher-Yates over positions',
            'snapshot' => SponsorDrawEligibleUser::query()->where('sponsor_draw_session_id', $session->id)->orderBy('position')
                ->get(['position', 'room_user_id', 'display_name_snapshot'])->map(static fn ($user): array => [
                    'position' => $user->position, 'room_user_id' => $user->room_user_id, 'name' => $user->display_name_snapshot,
                ])->all(),
            'snapshot_hash' => $session->snapshot_hash,
            'snapshot_hash_valid' => $session->snapshot_hash !== null && hash_equals($session->snapshot_hash, $expectedHash),
            'seed_commitment' => $session->seed_commitment,
            'seed' => $seedRevealed ? (string) $session->seed_encrypted : null,
            'seed_matches_commitment' => $seedRevealed && $session->seed_commitment !== null
                ? hash_equals($session->seed_commitment, $this->randomizer->commitment((string) $session->seed_encrypted, $session->id, (string) $session->snapshot_hash))
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
        $room = $session->room()->first(['id', 'name']);
        if ($session->type === SponsorDrawType::Shops) {
            return [
                'event' => 'sponsor_draw.confirmed',
                'title' => __('sponsor_draw.notify.shop_title', ['room' => $room?->name]),
                'message' => __('sponsor_draw.notify.shop_winner', ['name' => $winners[0] ?? '']),
            ];
        }

        // Older sessions were drawn among the members of a campaign; newer ones among the room's members.
        $lines = $session->campaign !== null ? [__('sponsor_draw.notify.campaign', ['name' => $session->campaign->name])] : [];
        foreach (array_values($winners) as $index => $name) {
            $lines[] = __('sponsor_draw.notify.winner', ['order' => $index + 1, 'name' => $name]);
        }
        if ($session->targetCampaign !== null) {
            $lines[] = __('sponsor_draw.notify.target', ['name' => $session->targetCampaign->name]);
        }

        return [
            'event' => 'sponsor_draw.confirmed',
            'title' => $session->campaign !== null
                ? __('sponsor_draw.notify.title', ['campaign' => $session->campaign->name])
                : __('sponsor_draw.notify.room_title', ['room' => $room?->name]),
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
        if ($session->seed_revealed_at === null && $session->seed_commitment !== null) {
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
