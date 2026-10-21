<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\BankProvider;
use App\Http\Controllers\Controller;
use App\Models\RoomBankIntegration;
use App\Services\BankSync\BankReconciliationService;
use App\Services\BankSync\InvalidBankWebhook;
use App\Services\BankSync\SePayWebhookParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bank-feed notifications of a room (POST /webhooks/bank/{provider}/{token}).
 *
 * The secret token in the URL selects the room's connection and the provider's API key authenticates the call; only
 * then is the transaction stored and reconciled. Replays are harmless (unique provider transaction ID).
 */
class BankWebhookController extends Controller
{
    /**
     * Authenticate, store and reconcile one transaction.
     *
     * @param Request $request Incoming webhook.
     * @param string $provider Provider slug from the URL.
     * @param string $token Room connection token from the URL.
     * @param SePayWebhookParser $parser SePay notification parser.
     * @param BankReconciliationService $reconciliation Reconciliation service.
     * @return JsonResponse {"success": true} once stored (also for replays); 401 when not authenticated.
     */
    public function __invoke(Request $request, string $provider, string $token, SePayWebhookParser $parser, BankReconciliationService $reconciliation): JsonResponse
    {
        $integration = RoomBankIntegration::query()
            ->where('webhook_token', $token)
            ->where('provider', BankProvider::tryFrom($provider)?->value ?? '')
            ->where('is_active', true)
            ->first();
        if ($integration === null) {
            return response()->json(['success' => false], Response::HTTP_NOT_FOUND);
        }

        try {
            $data = $parser->parse($request, $integration);
        } catch (InvalidBankWebhook) {
            return response()->json(['success' => false], Response::HTTP_UNAUTHORIZED);
        }

        $transaction = $reconciliation->ingest($integration, $data);

        return response()->json(['success' => true, 'status' => $transaction->status->value]);
    }
}
