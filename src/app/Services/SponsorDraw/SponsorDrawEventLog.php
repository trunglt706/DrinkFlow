<?php

declare(strict_types=1);

namespace App\Services\SponsorDraw;

use App\Models\Admin;
use App\Models\SponsorDrawEvent;
use App\Models\SponsorDrawSession;
use App\Services\Realtime\RealtimeEmitter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Append-only, hash-chained event log of a sponsor draw session, also used as the realtime replay source.
 *
 * Every event is written in the caller's transaction (with the session row locked) and gets the next
 * sequence; its event_hash covers the previous event's hash, so editing or removing a past event breaks
 * the chain ({@see verify()}). It is pushed to the session's realtime channel only after the commit, so a
 * viewer never sees a result that is not stored; a missed push is recovered by replaying from a sequence.
 */
class SponsorDrawEventLog
{
    /** Realtime event name carrying every draw event (realtime/server.js routes it to draw:{session}). */
    public const REALTIME_EVENT = 'sponsor_draw.event';

    public function __construct(private readonly RealtimeEmitter $emitter) {}

    /**
     * Append an event (call inside a transaction holding the session row lock).
     *
     * @param SponsorDrawSession $session Locked session.
     * @param string $type Event type, e.g. "draw.completed".
     * @param array<string, mixed> $data Public data (display names only, never personal data).
     * @param Admin|null $actor Admin who triggered it.
     * @return SponsorDrawEvent Stored event.
     */
    public function append(SponsorDrawSession $session, string $type, array $data, ?Admin $actor = null): SponsorDrawEvent
    {
        $previous = SponsorDrawEvent::query()->where('sponsor_draw_session_id', $session->id)->orderByDesc('sequence')->first();
        $sequence = ((int) ($previous?->sequence ?? 0)) + 1;
        $createdAt = now()->toImmutable()->startOfSecond();
        $attributes = [
            'sponsor_draw_session_id' => $session->id,
            'sequence' => $sequence,
            'event_uuid' => (string) Str::uuid(),
            'type' => $type,
            'payload' => $data + ($actor !== null ? ['actor' => $actor->name] : []),
            'actor_admin_id' => $actor?->id,
            'previous_hash' => $previous?->event_hash,
            'created_at' => $createdAt,
        ];
        $attributes['event_hash'] = $this->hash($attributes);
        $event = SponsorDrawEvent::create($attributes);
        $session->forceFill(['last_sequence' => $sequence])->save();

        $roomId = (int) $session->room_id;
        $publish = fn () => $this->emitter->emit(self::REALTIME_EVENT, $roomId, $this->publicPayload($event), 'draw:'.$session->id);
        DB::transactionLevel() > 0 ? DB::afterCommit($publish) : $publish();

        return $event;
    }

    /**
     * Shape sent to viewers (realtime and replay API): no internal IDs, no hashes of personal data.
     *
     * @return array{session_id: int, sequence: int, event_id: string, type: string, server_time: string, data: array<string, mixed>}
     */
    public function publicPayload(SponsorDrawEvent $event): array
    {
        return [
            'session_id' => (int) $event->sponsor_draw_session_id,
            'sequence' => (int) $event->sequence,
            'event_id' => (string) $event->event_uuid,
            'type' => (string) $event->type,
            'server_time' => $event->created_at->toIso8601String(),
            'data' => (array) $event->payload,
        ];
    }

    /**
     * Events after a sequence, for a viewer that reconnects or missed a push.
     *
     * @return list<array<string, mixed>>
     */
    public function after(SponsorDrawSession $session, int $sequence): array
    {
        return SponsorDrawEvent::query()->where('sponsor_draw_session_id', $session->id)->where('sequence', '>', $sequence)
            ->orderBy('sequence')->get()->map(fn (SponsorDrawEvent $event): array => $this->publicPayload($event))->all();
    }

    /**
     * Recompute the whole chain.
     *
     * @return array{valid: bool, events: int, broken_at: int|null} Broken sequence when invalid.
     */
    public function verify(SponsorDrawSession $session): array
    {
        $previousHash = null;
        $expected = 1;
        $events = SponsorDrawEvent::query()->where('sponsor_draw_session_id', $session->id)->orderBy('sequence')->get();
        foreach ($events as $event) {
            $attributes = [
                'sponsor_draw_session_id' => (int) $event->sponsor_draw_session_id,
                'sequence' => (int) $event->sequence,
                'event_uuid' => (string) $event->event_uuid,
                'type' => (string) $event->type,
                'payload' => (array) $event->payload,
                'actor_admin_id' => $event->actor_admin_id,
                'previous_hash' => $event->previous_hash,
                'created_at' => $event->created_at,
            ];
            if ((int) $event->sequence !== $expected || $event->previous_hash !== $previousHash || ! hash_equals((string) $event->event_hash, $this->hash($attributes))) {
                return ['valid' => false, 'events' => $events->count(), 'broken_at' => (int) $event->sequence];
            }
            $previousHash = $event->event_hash;
            $expected++;
        }

        return ['valid' => true, 'events' => $events->count(), 'broken_at' => null];
    }

    /**
     * SHA-256 over the canonical JSON of an event (its previous hash included).
     *
     * @param array<string, mixed> $attributes Event attributes.
     */
    private function hash(array $attributes): string
    {
        $payload = (array) $attributes['payload'];
        ksort($payload);

        return hash('sha256', json_encode([
            'session' => (int) $attributes['sponsor_draw_session_id'],
            'sequence' => (int) $attributes['sequence'],
            'id' => (string) $attributes['event_uuid'],
            'type' => (string) $attributes['type'],
            'payload' => $payload,
            'actor' => $attributes['actor_admin_id'] === null ? null : (int) $attributes['actor_admin_id'],
            'previous' => $attributes['previous_hash'],
            'at' => $attributes['created_at']->getTimestamp(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }
}
