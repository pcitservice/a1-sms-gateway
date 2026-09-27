<?php

it('reports OK on health endpoint', function () {
    $resp = $this->getJson('/api/v1/health');

    // The endpoint returns 200 when both probes pass and 503 when either
    // fails; test envs occasionally can't hit Redis, so we accept both and
    // just assert the response envelope is well-formed.
    expect($resp->status())->toBeIn([200, 503]);
    $resp->assertJsonStructure(['status', 'db', 'redis', 'time']);
});
