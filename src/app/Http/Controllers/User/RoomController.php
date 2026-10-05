<?php

declare(strict_types=1);

namespace App\Http\Controllers\User;

use App\Actions\User\JoinRoomAction;
use App\Enums\GlobalUserStatus;
use App\Enums\RoomStatus;
use App\Enums\RoomUserStatus;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveRoomUser;
use App\Http\Resources\RoomResource;
use App\Http\Resources\RoomUserResource;
use App\Models\GlobalUser;
use App\Models\Room;
use App\Services\Audit\AuditService;
use App\Services\Auth\DeviceTrustService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class RoomController extends Controller
{
    /**
     * Show the room entry point, handling auth, membership check, and blocked states.
     *
     * @param Request $request Current HTTP request.
     * @param Room $room Target room model resolved by slug/id.
     * @param DeviceTrustService $devices Device trust verification service.
     * @param AuditService $audit Activity audit service.
     * @return JsonResponse|RedirectResponse|View|Response Rendered view, redirect, or JSON payload.
     */
    public function show(Request $request, Room $room, DeviceTrustService $devices, AuditService $audit): JsonResponse|RedirectResponse|View|Response
    {
        $roomStatus = $room->status instanceof \BackedEnum ? $room->status->value : (string) $room->status;
        abort_unless($roomStatus === RoomStatus::Active->value, 404);

        /** @var GlobalUser|null $user */
        $user = $request->user('web');
        if (! $user) {
            $device = $devices->resolve(
                (string) $request->cookie('drinkflow_device_uuid', ''),
                (string) $request->cookie('drinkflow_trusted_token', ''),
                $room->id,
            );
            $user = $device?->roomUser?->globalUser;
            if ($user) {
                Auth::guard('web')->login($user, true);
                $audit->record('user.logged_in', 'global_user', $user->id, $room->id, [], [], [
                    'authentication_method' => 'trusted_device',
                ]);
            }
        }

        if (! $user) {
            $request->session()->put('url.intended', url()->current());
            if ($request->expectsJson()) {
                return response()->json(['requires_authentication' => true, 'redirect' => route('landing')], 401);
            }
            return redirect()->to('/')->with('auth_notice', __('public.auth_modal.require_login_room', ['room' => $room->name]));
        }

        $userStatus = $user->status instanceof \BackedEnum ? $user->status->value : (string) ($user->status ?? GlobalUserStatus::Active->value);
        abort_unless($userStatus === GlobalUserStatus::Active->value, 403);

        $membership = $user->roomUsers()->where('room_id', $room->id)->first();
        if (! $membership) {
            if ($request->expectsJson()) {
                return response()->json([
                    'requires_confirmation' => true,
                    'profile' => $user->only(['name', 'email', 'avatar_url']),
                    'room' => $room->only(['id', 'name', 'slug', 'description', 'avatar_url']),
                ]);
            }
            return view('user.join-room', [
                'room' => $room,
                'user' => $user,
                'adminUser' => $room->admins()->first(),
            ]);
        }

        $memStatus = $membership->status instanceof \BackedEnum ? $membership->status->value : (string) $membership->status;
        if ($memStatus === RoomUserStatus::Blocked->value) {
            if ($request->expectsJson()) {
                abort(403, __('global.blocked.account_blocked'));
            }
            return response()->view('user.blocked-room', [
                'room' => $room,
                'roomUser' => $membership,
            ], 403);
        }

        abort_unless($memStatus === RoomUserStatus::Active->value, 403);

        if (! $request->expectsJson()) {
            return redirect()->route('user.dashboard', $room->slug);
        }

        $room->loadCount(['campaigns']);

        return response()->json([
            'data' => (new RoomResource($room))->resolve($request),
            'room_user' => (new RoomUserResource($membership))->resolve($request),
        ]);
    }

    /**
     * Join the current user to the specified room.
     *
     * @param Request $request Current HTTP request.
     * @param Room $room Target room model.
     * @param JoinRoomAction $action Join room domain action.
     * @param DeviceTrustService $devices Device trust issuance service.
     * @return JsonResponse|RedirectResponse Redirect to dashboard or JSON response.
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException When the room or global user is inactive.
     */
    public function join(Request $request, Room $room, JoinRoomAction $action, DeviceTrustService $devices): JsonResponse|RedirectResponse
    {
        $user = $request->user('web');
        if (! $user instanceof GlobalUser) {
            return redirect()->route('landing');
        }

        $roomStatus = $room->status instanceof \BackedEnum ? $room->status->value : (string) $room->status;
        abort_unless($roomStatus === RoomStatus::Active->value, 404);

        abort_unless($user->status === GlobalUserStatus::Active, 403);

        $deviceUuid = (string) ($request->cookie('drinkflow_device_uuid') ?: Str::uuid());
        $roomUser = $action->execute($user, $room, $deviceUuid, hash('sha256', Str::random(64)));
        $token = $devices->issue($roomUser, $deviceUuid);

        if (! $request->expectsJson()) {
            return redirect()->to($this->pullJoinRedirect($request, $room) ?? route('user.dashboard', $room->slug))
                ->withCookie(cookie('drinkflow_device_uuid', $deviceUuid, 60 * 24 * 365, '/', null, $request->isSecure(), true, false, 'lax'))
                ->withCookie(cookie('drinkflow_trusted_token', $token, 60 * 24 * 30, '/', null, $request->isSecure(), true, false, 'lax'));
        }

        $roomUser->load('room');

        return response()->json([
            'data' => (new RoomUserResource($roomUser))->resolve($request),
            'redirect' => route('user.campaigns.index', $room->slug),
        ])
            ->withCookie(cookie('drinkflow_device_uuid', $deviceUuid, 60 * 24 * 365, '/', null, $request->isSecure(), true, false, 'lax'))
            ->withCookie(cookie('drinkflow_trusted_token', $token, 60 * 24 * 30, '/', null, $request->isSecure(), true, false, 'lax'));
    }

    /**
     * Take the room page a member opened before joining (e.g. a campaign order link), if it belongs to this room.
     *
     * @param Request $request Current HTTP request.
     * @param Room $room Room that was just joined.
     * @return string|null Same-room URL to continue to, or null to fall back to the dashboard.
     */
    private function pullJoinRedirect(Request $request, Room $room): ?string
    {
        $redirect = $request->session()->pull(ResolveRoomUser::JOIN_REDIRECT_SESSION_KEY);
        if (! is_array($redirect) || (int) ($redirect['room_id'] ?? 0) !== $room->id) {
            return null;
        }

        $url = (string) ($redirect['url'] ?? '');
        $roomPrefix = route('user.rooms.show', $room->slug).'/';

        return str_starts_with($url, $roomPrefix) ? $url : null;
    }
}
