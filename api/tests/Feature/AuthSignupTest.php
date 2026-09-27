<?php

use App\Models\Plan;
use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    Plan::create([
        'slug' => 'free', 'name' => 'Free', 'price_ore' => 0, 'interval' => 'none',
        'sms_included' => 25, 'rate_per_minute' => 6,
    ]);
});

it('creates a user, team, and trial on signup', function () {
    $resp = $this->postJson('/api/v1/auth/signup', [
        'name'      => 'Anna Andersen',
        'email'     => 'anna@example.com',
        'password'  => 'a-very-strong-pw-9',
        'team_name' => 'Anna Co',
        'country'   => 'DK',
    ]);

    // Signup no longer returns a Sanctum token — the user must click the
    // verification link first. The response is 201 with a verification flag.
    $resp->assertCreated();
    $resp->assertJsonStructure(['user' => ['id', 'name', 'email'], 'verification_required']);
    $resp->assertJson(['verification_required' => true]);

    $user = User::where('email', 'anna@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->currentTeam->trial_ends_at->isFuture())->toBeTrue();
    expect($user->currentTeam->trial_sms_limit)->toBe(25);
    expect($user->hasVerifiedEmail())->toBeFalse();
});

it('rejects weak passwords', function () {
    $resp = $this->postJson('/api/v1/auth/signup', [
        'name' => 'A', 'email' => 'a@b.com', 'password' => 'short', 'country' => 'DK',
    ]);
    $resp->assertStatus(422);
});

it('rejects duplicate emails', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $resp = $this->postJson('/api/v1/auth/signup', [
        'name' => 'B', 'email' => 'taken@example.com', 'password' => 'a-very-strong-pw-9',
        'country' => 'DK',
    ]);
    $resp->assertStatus(422);
});

it('rejects signup from non-DK countries', function () {
    $resp = $this->postJson('/api/v1/auth/signup', [
        'name' => 'Bob', 'email' => 'bob@example.com', 'password' => 'a-very-strong-pw-9',
        'country' => 'US',
    ]);
    $resp->assertStatus(422);
});
