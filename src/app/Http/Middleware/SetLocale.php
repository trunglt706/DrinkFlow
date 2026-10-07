<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = session('locale', \App\Constants\AppLocale::DEFAULT);
        $locale = is_string($locale) ? $locale : \App\Constants\AppLocale::DEFAULT;

        // Public pages are also served under /en and /ja: the URL wins over the session so each language has its own address.
        $segment = $request->segment(1);
        if (is_string($segment) && in_array($segment, \App\Support\Helpers\LocaleUrl::prefixedLocales(), true)) {
            $locale = $segment;
            session(['locale' => $locale]);
        }

        if (!\App\Constants\AppLocale::isValid($locale)) {
            $locale = \App\Constants\AppLocale::DEFAULT;
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
