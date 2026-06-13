<?php

use App\Domain\Sms\Services\SmsBilling;
use App\Domain\Sms\Services\SmsDispatcher;

it('segments GSM-7 messages by 160 and 153', function () {
    $d = new SmsDispatcher(new SmsBilling);
    expect($d->segmentCount(str_repeat('x', 160)))->toBe(1);
    expect($d->segmentCount(str_repeat('x', 161)))->toBe(2);
    expect($d->segmentCount(str_repeat('x', 306)))->toBe(2);
    expect($d->segmentCount(str_repeat('x', 307)))->toBe(3);
});

it('segments unicode messages by 70 and 67', function () {
    $d = new SmsDispatcher(new SmsBilling);
    $emoji = str_repeat('🎉', 70);
    expect($d->segmentCount($emoji))->toBe(1);
    expect($d->segmentCount(str_repeat('🎉', 71)))->toBe(2);
});
