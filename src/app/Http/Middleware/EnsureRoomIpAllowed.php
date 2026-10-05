<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Room;
use App\Services\Room\RoomAccessPolicy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce a room's IP rules (alias "room.ip"): blocked IPs are always refused, and when an allow list is set only
 * those IPs may open the room. Applies to every member route that carries a {room} parameter.
 */
class EnsureRoomIpAllowed
{
    /**
     * Create the middleware.
     *
     * @param RoomAccessPolicy $policy Room access rules.
     */
    public function __construct(private readonly RoomAccessPolicy $policy)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request Incoming request.
     * @param Closure(Request): Response $next Next handler.
     * @return Response Response.
     * @throws \App\Exceptions\RoomAccessDeniedException When the client IP may not open the room.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $room = self::room($request);
        if ($room instanceof Room) {
            $this->policy->ensureIpAllowed($room, $request->ip());
        }

        return $next($request);
    }

    /**
     * Resolve the {room} route parameter (bound model, slug or id).
     *
     * @param Request $request Incoming request.
     * @return Room|null Room, or null when the route has none / it does not exist (the route then 404s itself).
     */
    public static function room(Request $request): ?Room
    {
        $cached = $request->attributes->get('room');
        if ($cached instanceof Room) {
            return $cached;
        }

        $room = $request->route('room');
        if ($room instanceof Room) {
            $request->attributes->set('room', $room);
            return $room;
        }
        if ($room === null || $room === '') {
            return null;
        }

        $resolved = Room::query()->where('slug', (string) $room)
            ->when(is_numeric($room), static fn ($query) => $query->orWhere('id', (int) $room))
            ->first();

        if ($resolved instanceof Room) {
            $request->attributes->set('room', $resolved);
        }

        return $resolved;
    }
}
