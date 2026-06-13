<?php

namespace App\Http\Middleware;

use Closure;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

/**
 * Optional Cloudflare Turnstile verifier. Enabled only when TURNSTILE_SECRET
 * is set in .env. Frontend includes the widget on the signup form and submits
 * a `cf-turnstile-response` field.
 */
class VerifyTurnstile
{
    public function handle(Request $request, Closure $next)
    {
        $secret = config('services.turnstile.secret');
        if (! $secret) {
            return $next($request);
        }
        $token = $request->input('cf-turnstile-response');
        if (! $token) {
            return response()->json(['title' => 'Captcha missing', 'status' => 400], 400);
        }
        $resp = (new Client(['timeout' => 5]))->post(
            'https://challenges.cloudflare.com/turnstile/v0/siteverify',
            ['form_params' => ['secret' => $secret, 'response' => $token, 'remoteip' => $request->ip()]],
        );
        $body = json_decode((string) $resp->getBody(), true);
        if (! ($body['success'] ?? false)) {
            return response()->json(['title' => 'Captcha rejected', 'status' => 400], 400);
        }
        return $next($request);
    }
}
