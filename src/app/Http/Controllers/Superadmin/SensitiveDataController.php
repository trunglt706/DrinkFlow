<?php

declare(strict_types=1);

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RevealSensitiveDataRequest;
use App\Models\Superadmin;
use App\Services\Audit\AuditService;
use App\Services\Security\SensitiveDataService;
use Illuminate\Http\JsonResponse;

/**
 * Reveal one masked email / phone number of the Superadmin console (the eye button next to it).
 */
class SensitiveDataController extends Controller
{
    /**
     * Return the real value of a reveal token issued to the signed-in Superadmin; every reveal is audited
     * (type and masked form only, never the value itself).
     *
     * @param RevealSensitiveDataRequest $request Validated token.
     * @param SensitiveDataService $sensitive Token reader.
     * @param AuditService $audit Audit trail.
     * @return JsonResponse The value, or 404 when the token is invalid / expired / someone else's.
     */
    public function reveal(RevealSensitiveDataRequest $request, SensitiveDataService $sensitive, AuditService $audit): JsonResponse
    {
        /** @var Superadmin $viewer */
        $viewer = $request->user('superadmin');
        $revealed = $sensitive->reveal((string) $request->validated('token'), $viewer);
        abort_if($revealed === null, 404, __('superadmin.sensitive.expired'));

        $audit->record('sensitive.revealed', 'sensitive', 0, null, [], [], [
            'type' => $revealed['type'],
            'masked' => $sensitive->mask($revealed['value'], $revealed['type']),
        ]);

        return response()->json(['data' => ['value' => $revealed['value']]]);
    }
}
