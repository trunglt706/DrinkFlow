<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\RegisterAdminAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterAdminRequest;
use App\Http\Requests\ResendAdminVerificationRequest;
use App\Models\Admin;
use App\Models\Package;
use App\Services\Admin\AdminEmailVerificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Public Agent registration (/admin/register): form, email verification and pending notice.
 */
class RegistrationController extends Controller
{
    /**
     * Registration form with the packages currently offered.
     *
     * @param Request $request Incoming request.
     * @return View|RedirectResponse Form, or the admin area for a signed-in Admin.
     */
    public function create(Request $request): View|RedirectResponse
    {
        if ($request->user('admin') !== null) {
            return redirect()->route('admin.landing');
        }

        return view('admin.auth.register', [
            'packages' => Package::query()->selectable()->get(),
        ]);
    }

    /**
     * Register a pending Agent and send the verification email.
     *
     * @param RegisterAdminRequest $request Validated registration.
     * @param RegisterAdminAction $action Registration action.
     * @return RedirectResponse Pending notice.
     */
    public function store(RegisterAdminRequest $request, RegisterAdminAction $action): RedirectResponse
    {
        $admin = $action->register($request->validated());

        return redirect()->route('admin.register.pending')->with('registered_email', $admin->email);
    }

    /**
     * "Check your inbox / waiting for approval" notice, with the resend form.
     *
     * Only self-service registrations reach this page: an Agent invited by a Superadmin receives its
     * activation link by email and never passes through the registration flow.
     *
     * @param Request $request Incoming request.
     * @return View Notice.
     */
    public function pending(Request $request): View
    {
        return view('admin.auth.register-pending', [
            'email' => (string) $request->session()->get('registered_email', ''),
        ]);
    }

    /**
     * Confirm the email address from the signed link.
     *
     * @param Admin $admin Registering Agent.
     * @param string $hash Email hash from the link.
     * @param AdminEmailVerificationService $verification Verification service.
     * @return RedirectResponse Login page with the outcome.
     */
    public function verify(Admin $admin, string $hash, AdminEmailVerificationService $verification): RedirectResponse
    {
        abort_unless($verification->matches($admin, $hash), 403);
        $verification->verify($admin);

        return redirect()->route('admin.login.page')->with('status', $admin->isActive()
            ? __('platform.registration.email_verified_active')
            : __('platform.registration.email_verified'));
    }

    /**
     * Resend the activation email (same answer whether or not the email is registered).
     *
     * Registrations made before the pay-to-activate flow (no subscription yet) still get the old
     * verification link.
     *
     * @param ResendAdminVerificationRequest $request Validated email.
     * @param RegisterAdminAction $registration Registration action (activation email).
     * @param AdminEmailVerificationService $verification Legacy verification service.
     * @return RedirectResponse Pending notice.
     */
    public function resend(ResendAdminVerificationRequest $request, RegisterAdminAction $registration, AdminEmailVerificationService $verification): RedirectResponse
    {
        if (! $registration->resendActivation((string) $request->validated('email'))) {
            $verification->resend((string) $request->validated('email'));
        }

        return redirect()->route('admin.register.pending')
            ->with('registered_email', (string) $request->validated('email'))
            ->with('status', __('platform.registration.verification_resent'));
    }
}
