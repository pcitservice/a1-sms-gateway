<?php

use App\Models\Plan;
use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    Plan::create([
        'slug' => 'free', 'name' => 'Free', 'price_ore' => 0, 'interval' => 'none',
        'sms_included' => 50, 'rate_per_minute' => 6,
    ]);
});

it('creates a user, team, and trial on signup', function () {
    $resp = $this->postJson('/api/v1/auth/signup', [
        'name'      => 'Anna Andersen',
        'email'     => 'anna@example.com',
        'password'  => 'a-very-strong-pw-9',
        'team_name' => 'Anna Co',
    ]);

    $resp->assertCreated();
    $resp->assertJsonStructure(['user' => ['id', 'name', 'email'], 'team' => ['id', 'name'], 'token']);

    $user = User::where('email', 'anna@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->currentTeam->trial_ends_at->isFuture())->toBeTrue();
    expect($user->currentTeam->trial_sms_limit)->toBe(50);
});

it('rejects weak passwords', function () {
    $resp = $this->postJson('/api/v1/auth/signup', [
        'name' => 'A', 'email' => 'a@b.com', 'password' => 'short',
    ]);
    $resp->assertStatus(422);
});

it('rejects duplicate emails', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $resp = $this->postJson('/api/v1/auth/signup', [
        'name' => 'B', 'email' => 'taken@example.com', 'password' => 'a-very-strong-pw-9',
    ]);
    $resp->assertStatus(422);
});
