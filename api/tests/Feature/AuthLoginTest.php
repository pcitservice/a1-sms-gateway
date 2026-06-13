<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('issues a Sanctum token on valid credentials', function () {
    [$user] = asUser(['password' => Hash::make('correct-horse-battery-9')]);
    $resp = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email, 'password' => 'correct-horse-battery-9',
    ]);
    $resp->assertOk();
    $resp->assertJsonStructure(['token', 'user' => ['id', 'email']]);
    expect($user->fresh()->tokens()->count())->toBe(1);
});

it('rejects bad credentials', function () {
    [$user] = asUser(['password' => Hash::make('correct')]);
    $resp = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email, 'password' => 'wrong',
    ]);
    $resp->assertStatus(401);
});

it('refuses login for suspended users', function () {
    [$user] = asUser(['suspended_at' => now()->subDay(), 'password' => Hash::make('pw')]);
    $resp = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email, 'password' => 'pw',
    ]);
    $resp->assertStatus(403);
});
