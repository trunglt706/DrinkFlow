<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A game room action refused by a business rule (room not playing, loser not in the room, ...). The message is
 * already translated: JSON callers get a 422 with it, page forms are sent back with it as a flash error.
 */
class GameActionException extends \DomainException
{
    /**
     * Render the refusal for the caller.
     *
     * @param Request $request Current request.
     * @return JsonResponse|RedirectResponse 422 JSON or redirect back with the error.
     */
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()], 422);
        }

        return back()->withInput()->with('error', $this->getMessage());
    }
}
