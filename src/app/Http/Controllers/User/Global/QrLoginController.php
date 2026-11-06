<?php

declare(strict_types=1);

namespace App\Http\Controllers\User\Global;

use App\Http\Controllers\Controller;
use App\Models\GlobalUser;
use App\Services\Auth\QrLoginService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * QR codes shown on /me/devices to sign in on a phone: the page asks for a new code every
 * {@see QrLoginService::ROTATE_SECONDS} seconds while the QR panel is open.
 */
class QrLoginController extends Controller
{
    /**
     * Issue a new one-time sign-in link for the signed-in member.
     *
     * @param Request $request Current request (member set by the global.user middleware).
     * @param QrLoginService $service QR sign-in codes.
     * @return JsonResponse Link to put in the QR code and the seconds until the next code.
     */
    public function store(Request $request, QrLoginService $service): JsonResponse
    {
        /** @var GlobalUser $user */
        $user = $request->attributes->get('global_user') ?? $request->user('web');

        $issued = $service->issue($user, $request->ip());

        return response()
            ->json([
                'url' => route('auth.qr-login', ['token' => $issued['token']]),
                'expires_at' => $issued['expires_at']->toIso8601String(),
                'rotate_in' => QrLoginService::ROTATE_SECONDS,
            ])
            ->header('Cache-Control', 'no-store, private');
    }
}
