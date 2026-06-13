<?php

namespace App\Providers;

use App\Models\PersonalAccessToken;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);

        // Default API throttle: 60/min/token.
        RateLimiter::for('api', function (Request $request) {
            $key = $request->user()?->id
                ?? $request->ip();
            return Limit::perMinute(60)->by($key);
        });

        // SMS-specific throttle: per-token, per-team plan tier, configurable
        // via team->plan->rate_per_minute. Default 120 (Pro plan).
        RateLimiter::for('sms', function (Request $request) {
            $team = $request->user()?->currentTeam;
            $perMin = (int) ($team?->plan?->rate_per_minute ?? 30);
            return Limit::perMinute(max(1, $perMin))
                ->by($team?->id ?? $request->ip());
        });

        // Signup is harshly throttled per IP — 5/hr, with a 1/min burst limit
        // chained. This blunts credential-stuffing & sign-up spam without
        // tripping a real person who fat-fingers their password once.
        RateLimiter::for('signup', function (Request $request) {
            return [
                Limit::perMinute(2)->by('signup-burst:'.$request->ip()),
                Limit::perHour(5)->by('signup-hour:'.$request->ip()),
            ];
        });
    }
}
