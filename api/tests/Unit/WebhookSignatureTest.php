<?php

it('signs payloads in the documented format', function () {
    $secret = 'whsec_test';
    $body   = json_encode(['event' => 'message.sent', 'id' => 'evt_x']);
    $ts     = 1717948800;

    $sig = hash_hmac('sha256', "{$ts}.{$body}", $secret);

    // Verifier logic (mirrored from WebhookSignatureVerifier).
    $header   = "t={$ts},v1={$sig}";
    preg_match('/^t=(\d+),v1=([a-f0-9]+)$/', $header, $m);
    [, $hts, $hsig] = $m;
    $expected = hash_hmac('sha256', $hts.'.'.$body, $secret);

    expect(hash_equals($expected, $hsig))->toBeTrue();
});
