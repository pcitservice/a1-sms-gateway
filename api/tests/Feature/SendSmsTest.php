<?php

use App\Domain\Sms\Events\MessageSent;
use App\Models\Gateway;
use App\Models\SmsMessage;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    Gateway::factory()->create(['team_id' => null, 'kind' => 'mock', 'is_primary' => true]);
});

it('queues an SMS and persists a message row', function () {
    Event::fake([MessageSent::class]);
    [$user] = asUser();
    $token  = $user->createToken('t', ['sms:send'])->plainTextToken;

    $resp = $this->withToken($token)->postJson('/api/v1/send-sms', [
        'to' => '+4512345678', 'message' => 'Hello world',
    ]);

    $resp->assertStatus(202);
    $resp->assertJsonStructure(['id', 'status', 'estimated_cost', 'currency']);
    expect(SmsMessage::query()->withoutGlobalScopes()->count())->toBe(1);
    $msg = SmsMessage::query()->withoutGlobalScopes()->first();
    expect($msg->to)->toBe('+4512345678');
    expect($msg->team_id)->toBe($user->current_team_id);
});

it('rejects invalid recipients', function () {
    [$user] = asUser();
    $token  = $user->createToken('t', ['sms:send'])->plainTextToken;
    $resp = $this->withToken($token)->postJson('/api/v1/send-sms', [
        'to' => 'no', 'message' => 'x',
    ]);
    $resp->assertStatus(422);
});

it('blocks send when trial quota is exhausted', function () {
    [$user, $team] = asUser();
    $team->update(['trial_sms_used' => 50]); // limit reached
    $token = $user->createToken('t', ['sms:send'])->plainTextToken;

    $resp = $this->withToken($token)->postJson('/api/v1/send-sms', [
        'to' => '+4512345678', 'message' => 'over',
    ]);
    $resp->assertStatus(402);
});
