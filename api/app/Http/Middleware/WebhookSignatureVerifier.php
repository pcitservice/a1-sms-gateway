<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Verifies inbound webhook signatures from devices/gateways we previously
 * registered for callbacks (e.g. TRB140 push). Expected header format:
 *
 *   X-A1Sms-Signature: t=<unix>,v1=<hmac_sha256_hex>
 *
 * where the HMAC is over "{t}.{rawBody}" with the gateway's signing secret.
 */
class WebhookSignatureVerifier
{
    public function handle(Request $request, Closure $next)
    {
        $secret = config('sms.webhook.signing_secret');
        if (! $secret) {
            throw new HttpException(503, 'Webhook signing secret not configured.');
        }
        $header = $request->header('X-A1Sms-Signature', '');
        if (! preg_match('/^t=(\d+),v1=([a-f0-9]+)$/', $header, $m)) {
            throw new HttpException(401, 'Missing or malformed webhook signature.');
        }
        [, $ts, $sig] = $m;
        if (abs(time() - (int) $ts) > (int) (config('sms.webhook.tolerance', 300))) {
            throw new HttpException(401, 'Webhook timestamp out of tolerance.');
        }
        $expected = hash_hmac('sha256', $ts.'.'.$request->getContent(), $secret);
        if (! hash_equals($expected, $sig)) {
            throw new HttpException(401, 'Webhook signature mismatch.');
        }
        return $next($request);
    }
}
