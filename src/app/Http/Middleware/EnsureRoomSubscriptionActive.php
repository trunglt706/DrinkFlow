<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Exceptions\RoomAccessDeniedException;
use App\Models\Room;
use App\Services\Subscription\AgentAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks room access when the owning Agent has no active subscription or is inactive.
 *
 * Runs after `admin.room`, which resolved the room and checked the Admin's own access.
 */
class EnsureRoomSubscriptionActive
{
    public function __construct(private readonly AgentAccessService $access) {}

    /**
     * Handle an incoming request.
     *
     * @param  Request  $request  Incoming request.
     * @param  Closure(Request): Response  $next  Next handler.
     * @return Response Downstream response, 403 JSON, or a redirect to the subscription page.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $room = $request->attributes->get('room') ?? $request->route('room');
        $reason = $room instanceof Room ? $this->access->roomBlockReason($room) : null;
        if ($reason === null) {
            return $next($request);
        }

        if ($request->routeIs('user.*')) {
            throw new RoomAccessDeniedException(__('room.access.subscription_blocked'));
        }

        $message = __('platform.subscriptions.blocked.'.$reason);
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], Response::HTTP_FORBIDDEN);
        }

        $isOwner = $room->isOwnedBy($request->user('admin'));

        return redirect()->route($isOwner ? 'admin.subscription.show' : 'admin.rooms.index')->withErrors(['subscription' => $message]);
    }
}
