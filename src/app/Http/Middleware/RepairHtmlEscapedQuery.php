<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Repair an emailed link whose query separators arrived HTML-escaped (`?expires=…&amp;signature=…`).
 *
 * Some mail clients and copy/paste from the raw message keep the `&amp;` of the HTML source, so the
 * signature reaches the app as an `amp;signature` parameter and a signed route answers 403. Placed before
 * the `signed` middleware of the GET links sent by email, it redirects once to the same URL with plain `&`;
 * the signature is then checked as usual, so nothing is accepted that was not signed.
 */
class RepairHtmlEscapedQuery
{
    /** HTML-escaped query separator (`&amp;`, possibly escaped more than once: `&amp;amp;`). */
    private const ESCAPED_SEPARATOR = '/&(?:amp;)+/';

    /**
     * Handle an incoming request.
     *
     * @param Request $request Incoming request.
     * @param Closure(Request): Response $next Next middleware.
     * @return Response Redirect to the repaired URL, or the downstream response.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // The raw query string is what the `signed` middleware checks the signature against.
        $query = (string) $request->server('QUERY_STRING', '');
        if (! $request->isMethod('GET') || preg_match(self::ESCAPED_SEPARATOR, $query) !== 1) {
            return $next($request);
        }

        return redirect()->to($request->url().'?'.preg_replace(self::ESCAPED_SEPARATOR, '&', $query));
    }
}
