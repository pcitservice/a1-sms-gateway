<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureTwoFactorVerified
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // No 2FA configured? Pass through.
        if (! $user || ! $user->hasTwoFactor()) {
            return $next($request);
        }

        // API tokens implicitly trust the holder — 2FA only gates browser sessions.
        if ($user->currentAccessToken() && get_class($user->currentAccessToken()) !== \Laravel\Sanctum\TransientToken::class) {
            return $next($request);
        }

        if (! $request->session()->get('2fa_verified_at')) {
            return response()->json([
                'type'   => 'https://sms.a1techflow.com/errors/2fa-required',
                'title'  => 'Two-factor verification required',
                'status' => 423,
            ], 423);
        }

        return $next($request);
    }
}
