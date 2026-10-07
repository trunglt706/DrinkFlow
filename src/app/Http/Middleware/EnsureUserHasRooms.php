<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\GlobalUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRooms
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var GlobalUser|null $user */
        $user = $request->attributes->get('global_user') ?? $request->user('web');

        if (!$user) {
            if ($request->expectsJson() || $request->wantsJson()) {
                abort(401, __('errors.common.unauthenticated'));
            }
            return redirect()->guest(route('auth.google'));
        }

        $hasRooms = $user->roomUsers()->exists();

        if (!$hasRooms) {
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json([
                    'message' => __('global.rooms.no_rooms_access'),
                    'data' => [],
                ], 403);
            }

            return redirect()->route('user.me.dashboard')->with('status_info', __('global.rooms.join_first_prompt'));
        }

        return $next($request);
    }
}
