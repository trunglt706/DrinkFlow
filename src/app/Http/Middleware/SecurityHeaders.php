<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Attach baseline browser-side protections (anti-clickjacking, MIME sniffing, referrer leakage) to every web response.
     *
     * script-src/style-src are pinned to an explicit allowlist (self + the CDNs Blade layouts actually load) instead
     * of being left unset: an unset directive is not restricted at all, so any XSS payload could previously pull in
     * and run a script from an arbitrary attacker-controlled domain. 'unsafe-inline' and 'unsafe-eval' stay in place
     * because the Blade views still rely on inline event handlers and both Alpine.js and the Tailwind CDN JIT
     * compiler evaluate code at runtime; removing them needs those to be migrated first (see AGENTS.md follow-up).
     *
     * @param Request $request Incoming HTTP request.
     * @param Closure(Request): Response $next Next middleware handler.
     * @return Response Response carrying the security headers.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Frame-Options' => 'SAMEORIGIN',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            'Content-Security-Policy' => $this->buildContentSecurityPolicy(),
        ];

        if ($request->is('admin', 'admin/*', 'superadmin', 'superadmin/*', 'me', 'me/*', 'rooms', 'rooms/*', 'auth/*', 'check-order/*', 'lang/*', 'logout', 'dev/*')) {
            $headers['X-Robots-Tag'] = 'noindex, nofollow, noarchive';
        }

        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }

    /**
     * Build the Content-Security-Policy header value, including the realtime notification
     * server's origin (its address is environment-specific, so it cannot be hardcoded).
     *
     * @return string Full CSP directive string.
     */
    private function buildContentSecurityPolicy(): string
    {
        [$realtimeOrigin, $realtimeWsOrigin] = $this->realtimeOrigins();
        $viteDevOrigins = [];
        $viteDevWsOrigins = [];
        if (Vite::isRunningHot()) {
            $viteUrl = parse_url(trim((string) file_get_contents(Vite::hotFile())));
            if (is_array($viteUrl)
                && in_array($viteUrl['scheme'] ?? null, ['http', 'https'], true)
                && in_array($viteUrl['host'] ?? null, ['localhost', '127.0.0.1'], true)
                && isset($viteUrl['port'])) {
                $origin = $viteUrl['scheme'].'://'.$viteUrl['host'].':'.$viteUrl['port'];
                $viteDevOrigins[] = $origin;
                $viteDevWsOrigins[] = ($viteUrl['scheme'] === 'https' ? 'wss' : 'ws').'://'.$viteUrl['host'].':'.$viteUrl['port'];
            }
        }

        $scriptSrc = array_filter([
            "'self'", "'unsafe-inline'", "'unsafe-eval'",
            'https://cdn.jsdelivr.net', 'https://cdn.tailwindcss.com',
            // Cloudflare Web Analytics beacon, injected by Cloudflare in front of production.
            'https://static.cloudflareinsights.com',
            $realtimeOrigin,
            ...$viteDevOrigins,
        ]);
        // Locally, let DevTools fetch source maps (*.js.map) of the CDN scripts above. Kept out of other
        // environments: jsDelivr serves arbitrary npm/GitHub content, so it must not be a connect target there.
        $devToolsOrigins = app()->isLocal() ? ['https://cdn.jsdelivr.net'] : [];
        // cloudflareinsights.com receives the Web Analytics beacon's measurements.
        $connectSrc = array_filter(["'self'", $realtimeOrigin, $realtimeWsOrigin, 'https://cloudflareinsights.com', ...$viteDevOrigins, ...$viteDevWsOrigins, ...$devToolsOrigins]);
        $styleSrc = array_filter(["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com', 'https://cdn.tailwindcss.com', ...$viteDevOrigins]);
        $fontSrc = ["'self'", 'https://fonts.gstatic.com', 'data:'];
        $imgSrc = ["'self'", 'data:', 'https:'];

        return implode('; ', [
            "default-src 'self'",
            'script-src '.implode(' ', $scriptSrc),
            'style-src '.implode(' ', $styleSrc),
            'font-src '.implode(' ', $fontSrc),
            'img-src '.implode(' ', $imgSrc),
            'connect-src '.implode(' ', $connectSrc),
            // Background music of the room event pages may be an https audio link chosen by the room admin; blob: lets
            // the admin events page preview an audio file picked for upload before it is sent.
            "media-src 'self' https: blob:",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ]);
    }

    /**
     * Derive the realtime server's HTTP(S) and WS(S) origins from its configured public URL.
     *
     * @return array{0: string|null, 1: string|null} [http(s) origin, ws(s) origin], both null when unconfigured.
     */
    private function realtimeOrigins(): array
    {
        $parts = parse_url((string) config('services.realtime.public_url', ''));
        if (! isset($parts['scheme'], $parts['host'])) {
            return [null, null];
        }

        $authority = $parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        $wsScheme = $parts['scheme'] === 'https' ? 'wss' : 'ws';

        return [$parts['scheme'].'://'.$authority, $wsScheme.'://'.$authority];
    }
}
