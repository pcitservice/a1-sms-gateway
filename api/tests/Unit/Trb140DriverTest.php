<?php

use App\Domain\Gateway\DTO\OutgoingMessage;
use App\Domain\Gateway\Drivers\Trb140Driver;
use App\Models\Gateway;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Psr\Log\NullLogger;

function makeDriver(array $responses): Trb140Driver
{
    $row = new Gateway([
        'id' => 1, 'kind' => 'trb140', 'host' => '192.0.2.1', 'port' => 80,
        'protocol' => 'http', 'username' => 'admin', 'password' => 'pw', 'modem_id' => '2-1',
    ]);
    $row->id = 1;
    $stack = HandlerStack::create(new MockHandler($responses));
    $http  = new Client(['handler' => $stack]);
    return new Trb140Driver($row, $http, new Repository(new ArrayStore), new NullLogger);
}

it('logs in then sends an SMS', function () {
    $driver = makeDriver([
        new Response(200, [], json_encode(['data' => ['ubus_rpc_session' => 'abc']])),
        new Response(200, [], json_encode(['success' => true, 'data' => ['sms_used' => 1]])),
    ]);
    $result = $driver->send(new OutgoingMessage(id: '01HW', to: '+4512345678', body: 'hi'));
    expect($result->ok)->toBeTrue();
    expect($result->providerId)->toStartWith('trb140-');
});

it('returns a failure DTO when the device rejects', function () {
    $driver = makeDriver([
        new Response(200, [], json_encode(['data' => ['ubus_rpc_session' => 'abc']])),
        new Response(200, [], json_encode([
            'success' => false,
            'errors'  => [['code' => 'invalid_number', 'detail' => 'bad msisdn']],
        ])),
    ]);
    $result = $driver->send(new OutgoingMessage(id: '01HW', to: 'no', body: 'hi'));
    expect($result->ok)->toBeFalse();
    expect($result->errorCode)->toBe('invalid_number');
});

it('parses health from /api/system/info, modem/status, sim/status, modem/connection', function () {
    $driver = makeDriver([
        new Response(200, [], json_encode(['data' => ['ubus_rpc_session' => 'abc']])),
        new Response(200, [], json_encode(['data' => ['uptime' => 3600]])),
        new Response(200, [], json_encode(['data' => ['signal' => -72, 'rsrp' => -95, 'band' => 'B3', 'imei' => 'IMEI']])),
        new Response(200, [], json_encode(['data' => ['state' => 'ready']])),
        new Response(200, [], json_encode(['data' => ['state' => 'connected', 'operator' => 'TDC']])),
    ]);
    $h = $driver->health();
    expect($h->reachable)->toBeTrue();
    expect($h->connectionState)->toBe('connected');
    expect($h->signalRssi)->toBe(-72);
    expect($h->operator)->toBe('TDC');
    expect($h->simStatus)->toBe('ready');
});
