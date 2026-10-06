<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Superadmin;
use App\Services\Security\SensitiveDataService;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Superadmin JSON APIs never return a raw email or phone number: every such key is replaced by its masked
 * text plus a reveal token ({@see SensitiveDataService}). The console pages reveal one value on demand.
 */
class MaskSuperadminSensitiveData
{
    public function __construct(private readonly SensitiveDataService $sensitive) {}

    /**
     * @param Request $request Incoming request.
     * @param Closure(Request): Response $next Next handler.
     * @return Response Response with masked JSON.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $viewer = $request->user('superadmin');
        if ($response instanceof JsonResponse && $viewer instanceof Superadmin && $response->isSuccessful()) {
            $response->setData($this->sensitive->protectPayload($response->getData(true), $viewer));
        }

        return $response;
    }
}
