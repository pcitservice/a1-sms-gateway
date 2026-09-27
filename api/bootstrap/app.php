<?php

use App\Http\Middleware\EnsureTeamContext;
use App\Http\Middleware\ForceJsonAccept;
use App\Http\Middleware\WebhookSignatureVerifier;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web:      __DIR__.'/../routes/web.php',
        api:      __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        // We serve /api/v1/health from HealthController with a rich JSON body
        // (db + redis probes + version). Laravel 11's built-in health path
        // would return an HTML "Application up" page and shadow our route,
        // so we register it at /up instead to keep both usable.
        health:    '/up',
        apiPrefix: 'api',
        then: function () {
            Illuminate\Support\Facades\Route::middleware('web')
                ->group(__DIR__.'/../routes/admin.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Trust CyberPanel/OLS + docker nginx that proxy this app; without
        // this, Laravel sees http:// instead of https:// and signed URL
        // verification fails.
        $middleware->trustProxies(at: '*', headers:
            \Illuminate\Http\Request::HEADER_X_FORWARDED_FOR |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT |
            \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO
        );

        // We use bearer tokens (Sanctum personal access tokens), not cookie
        // sessions, so the stateful SPA middleware is intentionally not
        // applied. Adding it would require a CSRF token on every browser
        // request and break the API for cross-origin SPA callers.

        $middleware->api(prepend: [
            ForceJsonAccept::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        $middleware->alias([
            'team'              => EnsureTeamContext::class,
            'webhook.signed'    => WebhookSignatureVerifier::class,
            'admin'             => \App\Http\Middleware\EnsureAdmin::class,
            'verified.2fa'      => \App\Http\Middleware\EnsureTwoFactorVerified::class,
            'turnstile'         => \App\Http\Middleware\VerifyTurnstile::class,
        ]);

        $middleware->throttleApi();
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (\App\Domain\Billing\Exceptions\InsufficientBalance $e, $request) {
            return response()->json([
                'type'   => 'https://sms.a1techflow.com/errors/insufficient-balance',
                'title'  => 'Insufficient balance',
                'status' => 402,
                'detail' => $e->getMessage(),
            ], 402);
        });
        $exceptions->render(function (\App\Domain\Gateway\Exceptions\GatewayException $e, $request) {
            return response()->json([
                'type'   => 'https://sms.a1techflow.com/errors/gateway',
                'title'  => 'Gateway error',
                'status' => 502,
                'detail' => $e->getMessage(),
            ], 502);
        });
    })
    ->create();
