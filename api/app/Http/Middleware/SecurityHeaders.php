<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers on every response. Cheap to add, hard to
 * forget later — done centrally so no controller can skip them.
 *
 * We do NOT set CSP here because the /docs and /join public pages load
 * scripts + fonts from CDNs and the value would drift; that's tracked
 * separately.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
            'X-Content-Type-Options'    => 'nosniff',
            'X-Frame-Options'           => 'DENY',
            'Referrer-Policy'           => 'strict-origin-when-cross-origin',
            'Permissions-Policy'        => 'camera=(), microphone=(), geolocation=(), payment=(self)',
        ];
        foreach ($headers as $k => $v) {
            if (! $response->headers->has($k)) {
                $response->headers->set($k, $v);
            }
        }
        return $response;
    }
}
