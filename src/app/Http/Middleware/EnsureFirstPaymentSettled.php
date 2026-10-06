<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Billing\PaymentRequirementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keep a self-registered Agent on its billing pages until the first invoice is paid.
 *
 * The package only starts with that payment, so every other page of the admin area (rooms, profile,
 * subscription, room dashboards…) sends the Agent back to the billing history, where it can view,
 * download and pay the invoice, or sign out. Runs in the web group after EnsureActiveAdmin.
 */
class EnsureFirstPaymentSettled
{
    /** Admin routes still reachable while the first invoice is unpaid. */
    public const ALLOWED_ROUTES = [
        'admin.billing.index',
        'admin.billing.show',
        'admin.billing.download',
        'admin.billing.pay',
        'admin.logout',
        // Sidebar avatar of the admin layout.
        'admin.profile.avatar.show',
    ];

    public function __construct(private readonly PaymentRequirementService $payments)
    {
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request Incoming request with its resolved route.
     * @param Closure(Request): Response $next Next middleware.
     * @return Response Redirect to the billing history, or the downstream response.
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException For blocked JSON requests (402).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $admin = $request->user('admin');
        if ($admin === null || ! $this->isAgentRoute($request) || in_array($request->route()?->getName(), self::ALLOWED_ROUTES, true)) {
            return $next($request);
        }
        if (! $this->payments->awaitingFirstPayment($admin)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            abort(Response::HTTP_PAYMENT_REQUIRED, __('platform.billing.first_payment_required'));
        }

        return redirect()->route('admin.billing.index');
    }

    /**
     * Whether the request targets an Agent page protected by the admin guard (not the superadmin console).
     *
     * @param Request $request Incoming request.
     * @return bool True for `auth:admin` routes under /admin.
     */
    private function isAgentRoute(Request $request): bool
    {
        return $request->is('admin', 'admin/*')
            && in_array('auth:admin', $request->route()?->gatherMiddleware() ?? [], true);
    }
}
