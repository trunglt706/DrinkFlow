<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CampaignStatus;
use App\Enums\DebtStatus;
use App\Enums\PaymentAccountStatus;
use App\Enums\RoomStatus;
use App\Enums\RoomUserStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\AdminLoginRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\SendResetOtpRequest;
use App\Http\Requests\VerifyOtpRequest;
use App\Models\Admin;
use App\Models\Debt;
use App\Models\Order;
use App\Models\Room;
use App\Services\Audit\AuditService;
use App\Services\Auth\AdminAuthService;
use App\Support\Auth\AreaIntendedRedirect;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class AuthController extends Controller
{
    /**
     * Display the Admin login page with captcha challenge.
     *
     * @param Request $request Incoming HTTP request.
     * @return View|RedirectResponse Login view or redirect if already authenticated.
     */
    public function loginPage(Request $request): View|RedirectResponse
    {
        if ($request->user('admin') && ! $request->session()->pull('admin_access_denied', false)) {
            return redirect()->route('admin.landing');
        }

        return view('admin.auth.login');
    }

    /**
     * Handle the admin landing dispatch (switch room or redirect to dashboard).
     *
     * @param Request $request Incoming HTTP request.
     * @return View|RedirectResponse Admin rooms list or direct room dashboard.
     */
    public function landing(Request $request): View|RedirectResponse
    {
        /** @var Admin $admin */
        $admin = $request->user('admin');

        // A self-registered Agent whose first invoice is unpaid has no package yet: billing is the next step.
        if ($admin->subscriptions()->pending()->exists() && ! $admin->subscriptions()->active()->exists()) {
            return redirect()->route('admin.billing.index');
        }

        $today = Carbon::today();

        $rooms = $admin->rooms()
            ->where('status', RoomStatus::Active)
            ->withCount([
                'roomUsers as active_members_count' => fn ($q) => $q->where('status', RoomUserStatus::Active),
            ])
            ->orderBy('name')
            ->get();

        if ($rooms->count() === 1) {
            return redirect()->route('admin.dashboard.page', $rooms->first());
        }

        $roomsData = $rooms->map(function (Room $room) use ($today) {
            $activeCampaign = $room->campaigns()
                ->where('status', CampaignStatus::Active)
                ->latest('started_at')
                ->first();

            $scheduledCampaign = $room->campaigns()
                ->where('status', CampaignStatus::Scheduled)
                ->where('deadline', '>=', now())
                ->orderBy('deadline')
                ->first();

            $todayOrdersCount = Order::query()
                ->where('room_id', $room->id)
                ->whereDate('created_at', $today)
                ->whereNotIn('status', ['cancelled'])
                ->count();

            $unpaidDebtsSum = (int) Debt::query()
                ->where('room_id', $room->id)
                ->whereIn('status', DebtStatus::outstandingValues())
                ->sum('remaining_amount');

            $unpaidDebtsCount = Debt::query()
                ->where('room_id', $room->id)
                ->whereIn('status', DebtStatus::outstandingValues())
                ->count();

            $paymentAccount = $room->paymentAccounts()
                ->where('status', PaymentAccountStatus::Active)
                ->first();

            $roomType = 'idle';
            if ($activeCampaign) {
                $roomType = 'live';
            } elseif ($scheduledCampaign) {
                $roomType = 'scheduled';
            } elseif ($unpaidDebtsSum > 0) {
                $roomType = 'debt';
            }

            return (object) [
                'model' => $room,
                'id' => $room->id,
                'slug' => $room->slug,
                'name' => $room->name,
                'description' => $room->description,
                'active_members_count' => $room->active_members_count ?? 0,
                'active_campaign' => $activeCampaign,
                'scheduled_campaign' => $scheduledCampaign,
                'today_orders_count' => $todayOrdersCount,
                'unpaid_debts_sum' => $unpaidDebtsSum,
                'unpaid_debts_count' => $unpaidDebtsCount,
                'payment_account' => $paymentAccount,
                'room_type' => $roomType,
                'updated_at' => $room->updated_at,
            ];
        });

        $liveCount = $roomsData->where('room_type', 'live')->count();
        $debtCount = $roomsData->where('room_type', 'debt')->count();
        $idleCount = $roomsData->whereIn('room_type', ['idle', 'scheduled'])->count();

        return view('admin.rooms', [
            'rooms' => $rooms,
            'roomsData' => $roomsData,
            'admin' => $admin,
            'liveCount' => $liveCount,
            'debtCount' => $debtCount,
            'idleCount' => $idleCount,
            'roomUsage' => app(\App\Services\Room\RoomQuotaService::class)->usage($admin),
        ]);
    }

    /**
     * Authenticate admin account credentials.
     *
     * @param AdminLoginRequest $request Validated admin login request.
     * @param AdminAuthService $authService Authentication service.
     * @param AuditService $audit Activity audit service.
     * @return JsonResponse|RedirectResponse Response containing admin payload or redirect.
     */
    public function login(AdminLoginRequest $request, AdminAuthService $authService, AuditService $audit): JsonResponse|RedirectResponse
    {
        $admin = $authService->login($request);

        if ($admin->two_factor_enabled) {
            auth('admin')->logout();
            $request->session()->put([
                'admin_google_2fa_admin_id' => $admin->id,
                'admin_google_2fa_remember' => $request->boolean('remember'),
            ]);

            if ($request->expectsJson()) {
                return response()->json(['two_factor_required' => true]);
            }

            return redirect()->route('admin.login.page')->with('status', __('admin.google_workspace_continue'));
        }

        $audit->record('admin.logged_in', 'admin', $admin->id, null, [], [], [
            'authentication_method' => 'password',
        ]);

        if ($request->expectsJson()) {
            return response()->json(['data' => $admin]);
        }

        return AreaIntendedRedirect::to($request, 'admin', route('admin.landing'));
    }

    /**
     * Cancel the pending Google Workspace second-factor challenge.
     *
     * @param Request $request Incoming HTTP request.
     * @return RedirectResponse Redirect to the manual Admin login form.
     */
    public function cancelTwoFactorLogin(Request $request): RedirectResponse
    {
        $request->session()->forget([
            'admin_google_2fa_admin_id',
            'admin_google_2fa_remember',
            'google_oauth_state',
            'google_oauth_login_source',
        ]);

        return redirect()->route('admin.login.page');
    }

    /**
     * Terminate the admin authenticated session.
     *
     * @param Request $request Incoming HTTP request.
     * @param AdminAuthService $authService Authentication service.
     * @return JsonResponse|RedirectResponse Logout response.
     */
    public function logout(Request $request, AdminAuthService $authService): JsonResponse|RedirectResponse
    {
        $authService->logout($request);

        if ($request->expectsJson()) {
            return response()->json(['message' => __('admin.logged_out')]);
        }

        return redirect()->route('admin.login.page');
    }

    /**
     * Display the forgot password request view.
     *
     * @param Request $request Incoming HTTP request.
     * @return View View instance.
     */
    public function forgotPasswordPage(Request $request): View
    {
        return view('admin.auth.forgot-password');
    }

    /**
     * Send password reset OTP challenge to admin email.
     *
     * @param SendResetOtpRequest $request Validated OTP dispatch request.
     * @param AdminAuthService $authService Authentication service.
     * @return JsonResponse|RedirectResponse Redirection to verify OTP view or JSON payload.
     */
    public function sendResetOtp(SendResetOtpRequest $request, AdminAuthService $authService): JsonResponse|RedirectResponse
    {
        $email = $authService->sendResetOtp((string) $request->input('email'), $request->ip() ?? '127.0.0.1');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('admin.otp_dispatched_success'),
                'email' => $email,
            ]);
        }

        return redirect()->route('admin.verify-otp.page')->with('status', __('admin.otp_dispatched'));
    }

    /**
     * Display the OTP verification view.
     *
     * @param Request $request Incoming HTTP request.
     * @return View|RedirectResponse Verify view or redirect back if no reset in progress.
     */
    public function verifyOtpPage(Request $request): View|RedirectResponse
    {
        $email = $request->session()->get('admin_reset_email');
        if (! $email) {
            return redirect()->route('admin.forgot-password.page');
        }

        return view('admin.auth.verify-otp', ['email' => $email]);
    }

    /**
     * Verify the submitted OTP code and redirect to 30-min signed reset password route.
     *
     * @param VerifyOtpRequest $request Validated OTP input request.
     * @param AdminAuthService $authService Authentication service.
     * @return JsonResponse|RedirectResponse Redirect to signed reset password URL or JSON.
     */
    public function verifyOtp(VerifyOtpRequest $request, AdminAuthService $authService): JsonResponse|RedirectResponse
    {
        $authService->verifyResetOtp((string) $request->input('otp'));
        $email = (string) $request->session()->get('admin_reset_email');

        $signedUrl = URL::temporarySignedRoute(
            'admin.reset-password.page',
            now()->addMinutes(30),
            ['email' => $email]
        );

        if ($request->expectsJson()) {
            return response()->json([
                'message' => __('admin.otp_verified_success'),
                'redirect_url' => $signedUrl,
            ]);
        }

        return redirect()->to($signedUrl);
    }

    /**
     * Display the new password reset input form if temporary signed signature is valid.
     *
     * @param Request $request Incoming HTTP request.
     * @return View|RedirectResponse Reset view or redirect if signature invalid or expired.
     */
    public function resetPasswordPage(Request $request): View|RedirectResponse
    {
        if (! $request->hasValidSignature() || ! $request->session()->get('admin_reset_verified')) {
            return redirect()->route('admin.forgot-password.page')->withErrors([
                'email' => __('admin.reset_token_invalid_or_expired'),
            ]);
        }

        return view('admin.auth.reset-password', [
            'email' => (string) ($request->query('email') ?: $request->session()->get('admin_reset_email')),
        ]);
    }

    /**
     * Update the admin account password after successful OTP verification and valid signature.
     *
     * @param ResetPasswordRequest $request Validated password reset request.
     * @param AdminAuthService $authService Authentication service.
     * @return JsonResponse|RedirectResponse Redirect to login page with success.
     */
    public function resetPassword(ResetPasswordRequest $request, AdminAuthService $authService): JsonResponse|RedirectResponse
    {
        if (! $request->hasValidSignature() || ! $request->session()->get('admin_reset_verified')) {
            return redirect()->route('admin.forgot-password.page')->withErrors([
                'email' => __('admin.reset_token_invalid_or_expired'),
            ]);
        }

        $authService->resetPassword((string) $request->input('password'), $request->ip() ?? '127.0.0.1');

        if ($request->expectsJson()) {
            return response()->json(['message' => __('admin.password_reset_successfully')]);
        }

        return redirect()->route('admin.login.page')->with('status', __('admin.password_reset_success'));
    }
}
