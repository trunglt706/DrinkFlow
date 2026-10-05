<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\RoomStatus;
use App\Enums\RoomUserStatus;
use App\Exceptions\RoomAccessDeniedException;
use App\Models\Room;
use App\Services\Auth\DeviceTrustService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveRoomUser
{
    /** Session key holding the room page a non-member opened, so joining the room returns them there. */
    public const JOIN_REDIRECT_SESSION_KEY = 'room_join_redirect';

    /**
     * Xác định RoomUser từ request và bảo vệ route khỏi truy cập trái phép.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $room = $request->attributes->get('room') ?? $request->route('room');
        if (! ($room instanceof Room)) {
            $room = Room::where('slug', $room)->orWhere('id', is_numeric($room) ? (int) $room : 0)->firstOrFail();
            $request->attributes->set('room', $room);
        }

        if ($room->status !== RoomStatus::Active) {
            $translationKey = $room->status === RoomStatus::Archived
                ? 'room.access.room_archived'
                : 'room.access.room_inactive';

            throw new RoomAccessDeniedException(__($translationKey));
        }

        $globalUser = $request->attributes->get('global_user') ?? $request->user('web');

        if (! $globalUser) {
            if ($request->expectsJson()) {
                abort(401, __('errors.common.unauthenticated'));
            }
            $referer = $request->headers->get('referer') ?: url()->previous();
            if (
                $referer && parse_url($referer, PHP_URL_HOST) === $request->getHost()
                && $referer !== $request->fullUrl() && $referer !== $request->url() && $referer !== url('/')
            ) {
                return redirect()->to($referer);
            }

            return redirect()->to('/');
        }

        $roomUser = $globalUser->roomUsers()->where('room_id', $room->id)->first();

        if (! $roomUser) {
            if ($request->expectsJson()) {
                abort(403, __('errors.common.room_membership_required'));
            }

            if ($request->isMethod('GET')) {
                $request->session()->put(self::JOIN_REDIRECT_SESSION_KEY, ['room_id' => $room->id, 'url' => $request->fullUrl()]);
            }

            return redirect()->route('user.rooms.join.show', $room->slug);
        }

        // Status is already cast to RoomUserStatus enum via model casts.
        $roomUserStatus = $roomUser->status instanceof RoomUserStatus
            ? $roomUser->status
            : RoomUserStatus::from((string) $roomUser->status);

        if ($roomUserStatus === RoomUserStatus::Blocked) {
            if ($request->expectsJson()) {
                abort(403, __('errors.common.room_blocked'));
            }

            return response()->view('user.blocked-room', [
                'room' => $room,
                'roomUser' => $roomUser,
            ], 403);
        }

        if ($roomUserStatus === RoomUserStatus::Removed) {
            if ($request->expectsJson()) {
                abort(403, __('errors.common.room_membership_removed'));
            }

            return redirect()->route('user.me.dashboard');
        }

        abort_unless($roomUserStatus === RoomUserStatus::Active, 403);

        $deviceUuid = (string) $request->cookie('drinkflow_device_uuid', '');
        $token = (string) $request->cookie('drinkflow_trusted_token', '');
        if ($deviceUuid !== '' || $token !== '') {
            $device = app(DeviceTrustService::class)->resolve($deviceUuid, $token, $room->id);
            if ($device && $device->room_user_id !== $roomUser->id) {
                abort(403, __('errors.common.device_binding_mismatch'));
            }
        }

        $lastActive = $roomUser->last_active_at;
        if ($lastActive === null || $lastActive->lte(now()->subMinutes(5))) {
            $roomUser->update(['last_active_at' => now()]);
        }
        $request->attributes->set('room', $room);
        $request->attributes->set('room_user', $roomUser);

        return $next($request);
    }
}
