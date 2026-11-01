<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A game room action refused by a business rule (room not playing, loser not in the room, ...). The message is
 * already translated: JSON callers get a 422 with it, page forms are sent back with it as a flash error.
 *
 * A "stale" refusal means the page the client acted on is out of date (another round opened, the room finished, the
 * visitor lost their player): the JSON answer then carries `reload: true` so the page reloads instead of letting the
 * user retry the same outdated action.
 */
class GameActionException extends \DomainException
{
    /** Whether the client's page is out of date and must reload. */
    private bool $stale = false;

    /**
     * Refusal caused by an out-of-date page.
     *
     * @param string $message Translated message.
     * @return self Exception telling JSON callers to reload.
     */
    public static function stale(string $message): self
    {
        $exception = new self($message);
        $exception->stale = true;

        return $exception;
    }

    /**
     * @return bool True when the client's page is out of date.
     */
    public function isStale(): bool
    {
        return $this->stale;
    }

    /**
     * Render the refusal for the caller.
     *
     * @param Request $request Current request.
     * @return JsonResponse|RedirectResponse 422 JSON or redirect back with the error.
     */
    public function render(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $this->getMessage()] + ($this->stale ? ['reload' => true] : []), 422);
        }

        return back()->withInput()->with('error', $this->getMessage());
    }
}
