<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomUser;
use App\Models\SponsorDrawSession;
use App\Services\Realtime\SocketTokenService;
use App\Services\SponsorDraw\SponsorDrawEventLog;
use App\Services\SponsorDraw\SponsorDrawService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Sponsor draw landing page of a campaign (/rooms/{room}/sponsor-draws/{token}), read-only for members.
 *
 * Every request checks, in this order: the link exists and has not expired, it belongs to this room, and
 * the signed-in member belongs to the campaign (active member who did not decline it). An expired link
 * returns no session data at all; a non-member learns nothing about the session.
 */
class SponsorDrawController extends Controller
{
    public function __construct(private readonly SponsorDrawService $draws) {}

    /**
     * Landing page, or the "link expired" / "no access" page.
     */
    public function show(Request $request, Room $room, string $token): View|Response
    {
        $session = $this->authorizedSession($request, $room, $token);
        if (! $session instanceof SponsorDrawSession) {
            return response()->view('user.sponsor-draw.unavailable', ['room' => $room, 'reason' => $session] + $this->layoutData($request), $session === 'expired' ? 410 : 403);
        }

        return view('user.sponsor-draw.show', [
            'room' => $room,
            'token' => $token,
            'state' => $this->draws->state($session),
            'timezone' => $session->setRelation('room', $room)->displayTimezone(),
        ] + $this->layoutData($request));
    }

    /**
     * Current state and the events after `?after=`.
     */
    public function state(Request $request, Room $room, string $token, SponsorDrawEventLog $events): JsonResponse
    {
        $session = $this->authorizedSession($request, $room, $token);
        if (! $session instanceof SponsorDrawSession) {
            return $this->denied($session);
        }

        return response()->json(['data' => [
            'state' => $this->draws->state($session),
            'events' => $events->after($session, max(0, $request->integer('after'))),
        ]]);
    }

    /**
     * Realtime token of the session channel (never valid beyond the link expiry).
     */
    public function socketToken(Request $request, Room $room, string $token, SocketTokenService $tokens): JsonResponse
    {
        $session = $this->authorizedSession($request, $room, $token);
        if (! $session instanceof SponsorDrawSession) {
            return $this->denied($session);
        }

        return response()->json(['data' => ['token' => $tokens->issueForDrawViewer($session)]]);
    }

    /**
     * The session when the viewer may watch it, otherwise the reason: "expired" or "forbidden".
     */
    private function authorizedSession(Request $request, Room $room, string $token): SponsorDrawSession|string
    {
        $session = $this->draws->findByToken($token);
        if ($session === null || ! $session->linkIsValid()) {
            return 'expired';
        }
        /** @var RoomUser|null $member */
        $member = $request->attributes->get('room_user');
        if ((int) $session->room_id !== (int) $room->id || $member === null
            || ! $this->draws->eligibleMembersQuery($session->campaign)->whereKey($member->id)->exists()) {
            return 'forbidden';
        }

        return $session;
    }

    private function denied(string $reason): JsonResponse
    {
        return $reason === 'expired'
            ? response()->json(['message' => __('sponsor_draw.errors.link_expired'), 'reason' => 'expired'], 410)
            : response()->json(['message' => __('sponsor_draw.errors.forbidden'), 'reason' => 'forbidden'], 403);
    }

    /**
     * Data the room layout needs (same as the other room pages).
     *
     * @return array<string, mixed>
     */
    private function layoutData(Request $request): array
    {
        $member = $request->attributes->get('room_user');
        $user = $request->attributes->get('global_user') ?? $request->user('web');

        return [
            'roomUser' => $member,
            'userRooms' => $user?->rooms()->where('rooms.status', \App\Enums\RoomStatus::Active->value)->get() ?? collect(),
            'unreadNotificationsCount' => $user ? \Illuminate\Support\Facades\DB::table('user_notifications')->where('global_user_id', $user->id)->whereNull('read_at')->count() : 0,
        ];
    }
}
