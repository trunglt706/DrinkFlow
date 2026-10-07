<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\GlobalUserStatus;
use App\Models\Room;
use App\Services\Auth\DeviceTrustService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveGlobalUser
{
    /**
     * Xác định GlobalUser từ session hoặc trusted device cookie và bảo vệ route.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): \Symfony\Component\HttpFoundation\Response  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('web');
        $deviceUuid = (string) $request->cookie('drinkflow_device_uuid', '');
        $token = (string) $request->cookie('drinkflow_trusted_token', '');
        $clearTrustedDeviceCookies = false;

        // A revoked trusted-device token must also invalidate an otherwise
        // still-present Laravel session. Without this check, a revoked device
        // can retain its web session or have it restored on the next refresh.
        if ($user && ($deviceUuid !== '' || $token !== '')) {
            $device = app(DeviceTrustService::class)->resolve($deviceUuid, $token);
            if (! $device || $device->roomUser?->global_user_id !== $user->id) {
                auth('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();
                $user = null;
                $clearTrustedDeviceCookies = true;
            }
        }

        // A trusted device is sufficient to restore the global session. The
        // token is always checked against its hash; device_uuid alone is not
        // treated as an authentication credential.
        if (! $user) {
            $clearTrustedDeviceCookies = $deviceUuid !== '' || $token !== '';
            $room       = $request->route('room');
            $roomId     = is_object($room) ? $room->id : (is_numeric($room) ? (int) $room : null);
            $device     = app(DeviceTrustService::class)->resolve($deviceUuid, $token, $roomId);
            if ($device?->roomUser?->globalUser) {
                $user = $device->roomUser->globalUser;
                auth('web')->login($user, true);
                // New authenticated session: never keep a session id that existed before login (fixation).
                $request->session()->regenerate();
            }
        }

        if (! $user) {
            if ($request->expectsJson()) {
                abort(401, __('errors.common.unauthenticated'));
            }
            // Room links (e.g. campaign announcements) send guests home with the login modal open; after
            // login they come back to the link, which sends non-members through the room join page.
            if (($room = $request->route('room')) !== null) {
                if ($request->isMethod('GET')) {
                    $request->session()->put('url.intended', $request->fullUrl());
                }
                $roomName = $room instanceof Room ? $room->name : Room::query()->where('slug', (string) $room)->value('name');
                $response = redirect()->to('/')->with('auth_notice', $roomName !== null
                    ? __('public.auth_modal.require_login_room', ['room' => $roomName])
                    : __('public.auth_modal.require_register'));

                return $clearTrustedDeviceCookies ? $this->forgetTrustedDeviceCookies($response) : $response;
            }

            $referer = $request->headers->get('referer') ?: url()->previous();
            if ($this->isSafeInternalUrl($request, $referer)
                && $referer !== $request->fullUrl()
                && $referer !== $request->url()
                && $referer !== url('/')) {
                $response = redirect()->to($referer);
                return $clearTrustedDeviceCookies ? $this->forgetTrustedDeviceCookies($response) : $response;
            }

            $response = redirect()->to('/');
            return $clearTrustedDeviceCookies ? $this->forgetTrustedDeviceCookies($response) : $response;
        }

        // Status is cast to GlobalUserStatus enum via model casts.
        $status = $user->status instanceof GlobalUserStatus
            ? $user->status
            : GlobalUserStatus::from((string) ($user->status?->value ?? $user->status ?? ''));

        if ($status === GlobalUserStatus::Blocked) {
            $request->attributes->set('global_user', $user);
            if ($request->routeIs('user.blocked', 'user.blocked.appeal', 'logout')) {
                return $next($request);
            }
            if ($request->expectsJson()) {
                abort(403, __('errors.common.global_account_blocked'));
            }

            return redirect()->route('user.blocked');
        }

        abort_unless($status === GlobalUserStatus::Active, 403);
        $request->attributes->set('global_user', $user);

        return $next($request);
    }

    /**
     * Determine whether a redirect target belongs to the current application.
     *
     * @param Request $request Current HTTP request.
     * @param string|null $url Candidate redirect URL.
     * @return bool True when the URL is relative or matches the current host.
     */
    private function isSafeInternalUrl(Request $request, ?string $url): bool
    {
        if ($url === null || $url === '' || str_starts_with($url, '//')) {
            return false;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return false;
        }

        if (! isset($parts['host'])) {
            return str_starts_with($url, '/');
        }

        $host = strtolower((string) $parts['host']);
        $requestHost = strtolower($request->getHost());
        return $host === $requestHost
            || ($host === 'localhost' && $requestHost === '127.0.0.1')
            || ($host === '127.0.0.1' && $requestHost === 'localhost');
    }

    /**
     * Remove revoked trusted-device cookies so a subsequent OAuth login can establish a fresh session.
     *
     * @param Response $response Redirect response to decorate.
     * @return Response Response without stale trusted-device cookies.
     */
    private function forgetTrustedDeviceCookies(Response $response): Response
    {
        return $response
            ->withoutCookie('drinkflow_device_uuid')
            ->withoutCookie('drinkflow_trusted_token');
    }
}
