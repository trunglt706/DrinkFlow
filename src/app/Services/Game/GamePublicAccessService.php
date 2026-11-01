<?php

declare(strict_types=1);

namespace App\Services\Game;

use App\Enums\GameRoomStatus;
use App\Models\GamePlayer;
use App\Models\GameRoom;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Access through the public link of a game room: the token resolves the room, and the visitor's chosen player is
 * kept in their session (per room). That player is who the visitor records wins for on the public page. A picked
 * player is held by the visitor (claim token in the session) so other visitors cannot pick the same player.
 */
class GamePublicAccessService
{
    /** Session key of the visitor's claim token. */
    private const CLAIM_SESSION_KEY = 'game_rooms.claim';

    /**
     * Room of a public token.
     *
     * @param string $token Public token from the URL.
     * @return GameRoom|null Room, or null when the token is unknown or was revoked.
     */
    public function findRoom(string $token): ?GameRoom
    {
        return GameRoom::query()->with('game')->where('public_token', $token)->first();
    }

    /**
     * A closed room is no longer reachable through its public link: it is finished, or its game was turned off by a
     * Superadmin. The owner keeps the full history in /me.
     *
     * @param GameRoom $room Room.
     * @return bool True when the public link must refuse access.
     */
    public function isClosed(GameRoom $room): bool
    {
        return $room->status === GameRoomStatus::Finished || $room->game?->is_active !== true;
    }

    /**
     * Player the visitor picked for this room, as long as the visitor still holds its claim (an idle claim may have
     * expired and been taken by someone else).
     *
     * @param Session $session Visitor's session.
     * @param GameRoom $room Room.
     * @return GamePlayer|null Player of the room, or null when none is picked (or it no longer exists / is not held).
     */
    public function selectedPlayer(Session $session, GameRoom $room): ?GamePlayer
    {
        $playerId = $session->get($this->sessionKey($room));
        $claim = $session->get(self::CLAIM_SESSION_KEY);
        if (! is_int($playerId) || ! is_string($claim)) {
            return null;
        }

        return $room->players()->whereKey($playerId)->where('claimed_by', $claim)->first();
    }

    /**
     * Pick a player for the visitor and hold it, so no other visitor can pick it while the claim is alive.
     *
     * Race safety: claims of one room run one at a time (lock on the room row); the claim itself is a conditional
     * UPDATE, so two visitors racing for the same player cannot both win it; the visitor's previous player is
     * released first, and the unique (game_room_id, claimed_by) index guarantees one visitor never holds two players
     * (e.g. two tabs picking at once).
     *
     * @param Session $session Visitor's session.
     * @param GameRoom $room Room.
     * @param int $playerId Wanted player.
     * @return bool True when the player belongs to the room and is now held by the visitor; false when unknown or
     *              held by another visitor.
     */
    public function selectPlayer(Session $session, GameRoom $room, int $playerId): bool
    {
        $claim = $this->claimToken($session);

        $taken = DB::transaction(function () use ($room, $playerId, $claim): bool {
            GameRoom::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();
            $available = fn (Builder $query) => $query->whereKey($playerId)->where(function (Builder $query) use ($claim): void {
                $query->whereNull('claimed_by')
                    ->orWhere('claimed_by', $claim)
                    ->orWhereNull('claimed_at')
                    ->orWhere('claimed_at', '<', $this->claimExpiry());
            });
            if (! $available($room->players()->getQuery())->exists()) {
                return false;
            }
            $room->players()->where('claimed_by', $claim)->whereKeyNot($playerId)->toBase()->update(['claimed_by' => null, 'claimed_at' => null]);

            return $available($room->players()->getQuery())->toBase()->update(['claimed_by' => $claim, 'claimed_at' => now()]) === 1;
        });
        if (! $taken) {
            return false;
        }
        $session->put($this->sessionKey($room), $playerId);

        return true;
    }

    /**
     * Keep the visitor's claim alive (called on each visit of the page).
     *
     * @param Session $session Visitor's session.
     * @param GamePlayer $player Player currently held by the visitor.
     * @return void
     */
    public function refreshClaim(Session $session, GamePlayer $player): void
    {
        $claim = $session->get(self::CLAIM_SESSION_KEY);
        if (is_string($claim) && $player->claimed_by === $claim) {
            $player->newQuery()->whereKey($player->id)->where('claimed_by', $claim)->toBase()->update(['claimed_at' => now()]);
        }
    }

    /**
     * Forget the visitor's player for this room and release its claim.
     *
     * @param Session $session Visitor's session.
     * @param GameRoom $room Room.
     * @return void
     */
    public function clearPlayer(Session $session, GameRoom $room): void
    {
        $claim = $session->get(self::CLAIM_SESSION_KEY);
        if (is_string($claim)) {
            $room->players()->where('claimed_by', $claim)->toBase()->update(['claimed_by' => null, 'claimed_at' => null]);
        }
        $session->forget($this->sessionKey($room));
    }

    /**
     * Players of the room currently held by another visitor than this one.
     *
     * @param Session $session Visitor's session.
     * @param GameRoom $room Room.
     * @return list<int> Ids of the players other visitors hold.
     */
    public function claimedByOthers(Session $session, GameRoom $room): array
    {
        $claim = $session->get(self::CLAIM_SESSION_KEY);

        return $room->players()
            ->whereNotNull('claimed_by')
            ->where('claimed_at', '>=', $this->claimExpiry())
            ->when(is_string($claim), fn (Builder $query) => $query->where('claimed_by', '!=', $claim))
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    /**
     * Claim token of the visitor, if they ever picked a player (checked again inside the result transaction).
     *
     * @param Session $session Visitor's session.
     * @return string|null Claim token.
     */
    public function currentClaim(Session $session): ?string
    {
        $claim = $session->get(self::CLAIM_SESSION_KEY);

        return is_string($claim) && $claim !== '' ? $claim : null;
    }

    /**
     * Random token identifying the visitor's session in claims (created on first use).
     *
     * @param Session $session Visitor's session.
     * @return string Claim token.
     */
    private function claimToken(Session $session): string
    {
        $claim = $session->get(self::CLAIM_SESSION_KEY);
        if (! is_string($claim) || $claim === '') {
            $claim = Str::random(40);
            $session->put(self::CLAIM_SESSION_KEY, $claim);
        }

        return $claim;
    }

    /**
     * Claims idle since before this moment have expired (same lifetime as the visitor's session).
     *
     * @return CarbonInterface Expiry threshold.
     */
    private function claimExpiry(): CarbonInterface
    {
        return now()->subMinutes(max(1, (int) config('session.lifetime', 120)));
    }

    /**
     * @param GameRoom $room Room.
     * @return string Session key of the chosen player.
     */
    private function sessionKey(GameRoom $room): string
    {
        return 'game_rooms.'.$room->id.'.player';
    }
}
