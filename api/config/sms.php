<?php

return [
    'trial' => [
        'days'      => (int) env('TRIAL_DAYS', 14),
        'sms_limit' => (int) env('TRIAL_SMS_LIMIT', 50),
    ],

    'default_gateway_kind' => env('DEFAULT_GATEWAY_KIND', 'mock'),

    // Cost per segment in DKK øre (i.e. 18 = 0.18 DKK).
    'pricing' => [
        'currency' => 'DKK',
        'per_segment_ore' => (int) env('SMS_PER_SEGMENT_ORE', 18),
        'overage_multiplier' => (float) env('SMS_OVERAGE_MULTIPLIER', 1.25),
    ],

    'plans' => [
        'free'       => ['sms_included' => 50,    'price_id' => null,                                       'price_dkk' => 0],
        'starter'    => ['sms_included' => 500,   'price_id' => env('PLAN_STARTER_PRICE_ID'),              'price_dkk' => 99],
        'business'   => ['sms_included' => 3000,  'price_id' => env('PLAN_BUSINESS_PRICE_ID'),             'price_dkk' => 299],
        'pro'        => ['sms_included' => 15000, 'price_id' => env('PLAN_PRO_PRICE_ID'),                  'price_dkk' => 999],
        'enterprise' => ['sms_included' => null,  'price_id' => null,                                       'price_dkk' => null],
    ],

    'trb140' => [
        'host'            => env('TRB140_HOST', '192.168.1.1'),
        'port'            => (int) env('TRB140_PORT', 80),
        'protocol'        => env('TRB140_PROTOCOL', 'http'),
        'username'        => env('TRB140_USERNAME', 'admin'),
        'password'        => env('TRB140_PASSWORD', ''),
        'ssh_port'        => (int) env('TRB140_SSH_PORT', 22),
        'ssh_key_path'    => env('TRB140_SSH_KEY_PATH'),
        'modem_id'        => env('TRB140_MODEM_ID', '2-1'),
        'inbox_max_age'   => 60 * 60 * 24, // delete read inbox messages older than 24h
    ],

    'enable_mock' => filter_var(env('GATEWAY_ENABLE_MOCK', false), FILTER_VALIDATE_BOOL),

    'webhook' => [
        'signing_secret' => env('WEBHOOK_SIGNING_SECRET'),
        'attempts'       => 8,
        'backoff'        => [5, 15, 60, 300, 900, 3600, 7200, 21600],
        'timeout'        => 10,
    ],

    'automation' => [
        'keywords' => [
            'STOP'  => 'unsubscribe',
            'START' => 'subscribe',
            'HELP'  => 'help',
            'INFO'  => 'info',
        ],
    ],
];
