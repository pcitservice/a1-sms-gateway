<?php

use App\Models\Contact;

it('creates a contact via the API', function () {
    [$user] = asUser();
    $token = $user->createToken('t', ['*'])->plainTextToken;

    $resp = $this->withToken($token)->postJson('/api/v1/contacts', [
        'msisdn' => '+4512345678', 'first_name' => 'Anna',
    ]);
    $resp->assertCreated();
    expect(Contact::where('msisdn', '+4512345678')->count())->toBe(1);
});

it('returns only the caller-team\'s contacts', function () {
    [$userA] = asUser();
    [$userB] = asUser();
    Contact::factory()->create(['team_id' => $userA->current_team_id, 'msisdn' => '+1']);
    Contact::factory()->create(['team_id' => $userB->current_team_id, 'msisdn' => '+2']);

    $token = $userA->createToken('t', ['*'])->plainTextToken;
    $resp  = $this->withToken($token)->getJson('/api/v1/contacts');
    $resp->assertOk();
    $body = $resp->json();
    expect(collect($body['data'])->pluck('msisdn')->all())->toBe(['+1']);
});
