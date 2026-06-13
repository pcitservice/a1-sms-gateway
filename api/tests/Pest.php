<?php

use Tests\TestCase;

/*
| Pest binds TestCase to every feature test by default; Unit tests stay
| on PHPUnit\Framework\TestCase.
*/
pest()->extend(TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/**
 * Sign in a fresh user (with a fresh team + free plan trial) and return both.
 */
function asUser(array $userAttrs = [], array $teamAttrs = []): array
{
    $plan = \App\Models\Plan::firstOrCreate(
        ['slug' => 'free'],
        ['name' => 'Free Trial', 'price_ore' => 0, 'interval' => 'none', 'sms_included' => 50, 'rate_per_minute' => 6],
    );

    $user = \App\Models\User::factory()->create($userAttrs);
    $team = \App\Models\Team::factory()->create(array_merge([
        'owner_id'        => $user->id,
        'plan_id'         => $plan->id,
        'trial_ends_at'   => now()->addDays(14),
        'trial_sms_limit' => 50,
    ], $teamAttrs));
    $team->users()->attach($user->id, ['role' => 'owner']);
    $user->forceFill(['current_team_id' => $team->id])->save();

    return [$user->fresh(), $team->fresh()];
}
