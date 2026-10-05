<?php

declare(strict_types=1);

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A room's state, subscription, or configured access rules refused the request.
 *
 * Rendered as HTTP 403; the 403 error page and JSON responses show its translated message.
 */
class RoomAccessDeniedException extends HttpException
{
    /**
     * @param  string  $message  Translated reason shown to the member.
     */
    public function __construct(string $message)
    {
        parent::__construct(403, $message);
    }
}
