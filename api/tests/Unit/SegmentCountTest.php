<?php

use App\Domain\Sms\Services\LinkShortener;
use App\Domain\Sms\Services\SmsBilling;
use App\Domain\Sms\Services\SmsDispatcher;

function dispatcher(): SmsDispatcher
{
    // segmentCount() is a pure static-ish method on the dispatcher; wiring the
    // dependencies via app() keeps the test insulated from constructor drift.
    return new SmsDispatcher(new SmsBilling, new LinkShortener);
}

it('segments GSM-7 messages by 160 and 153', function () {
    $d = dispatcher();
    expect($d->segmentCount(str_repeat('x', 160)))->toBe(1);
    expect($d->segmentCount(str_repeat('x', 161)))->toBe(2);
    expect($d->segmentCount(str_repeat('x', 306)))->toBe(2);
    expect($d->segmentCount(str_repeat('x', 307)))->toBe(3);
});

it('segments unicode messages by 70 and 67', function () {
    $d = dispatcher();
    $emoji = str_repeat('🎉', 70);
    expect($d->segmentCount($emoji))->toBe(1);
    expect($d->segmentCount(str_repeat('🎉', 71)))->toBe(2);
});
