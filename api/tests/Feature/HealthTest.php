<?php

it('reports OK on health endpoint', function () {
    $resp = $this->getJson('/api/v1/health');
    $resp->assertOk();
    $resp->assertJsonStructure(['status', 'db', 'redis', 'time']);
});
