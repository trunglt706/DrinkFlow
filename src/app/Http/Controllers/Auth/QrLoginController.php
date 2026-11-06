<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Audit\AuditService;
use App\Services\Auth\QrLoginService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Link opened on the phone by scanning the QR code of /me/devices: signs the phone in to the member who showed the
 * code, like a Google sign-in (remembered session, audited).
 */
class QrLoginController extends Controller
{
    /**
     * Sign in with a scanned one-time code.
     *
     * @param Request $request Current request.
     * @param string $token Plain code from the QR link.
     * @param QrLoginService $service QR sign-in codes.
     * @param AuditService $audit Audit trail.
     * @return RedirectResponse Dashboard when signed in, home page with an error otherwise.
     */
    public function __invoke(Request $request, string $token, QrLoginService $service, AuditService $audit): RedirectResponse
    {
        $user = $service->consume($token, $request->ip());
        if ($user === null) {
            return redirect()->to(url('/'))
                ->with('login_error', __('global.qr_login.invalid'))
                ->header('Referrer-Policy', 'no-referrer');
        }

        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();
        $audit->record('user.logged_in', 'global_user', $user->id, null, [], [], [
            'authentication_method' => 'qr_code',
        ]);

        return redirect()->route('user.me.dashboard')
            ->withoutCookie('drinkflow_device_uuid')
            ->withoutCookie('drinkflow_trusted_token')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
