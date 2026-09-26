<?php

// Overrides Laravel's default `mail` config only for the `smtp` mailer, so we
// can talk to the host's Postfix over the docker bridge (172.19.0.1:25). That
// Postfix advertises STARTTLS with the CyberPanel-issued self-signed
// dovecot.pem cert, which Symfony's transport otherwise refuses. The stream
// options below skip peer verification for this internal hop only. Do NOT copy
// these options into a mailer that talks to a public MTA.

return [

    'default' => env('MAIL_MAILER', 'smtp'),

    'mailers' => [

        'smtp' => [
            'transport'    => 'smtp',
            'scheme'       => env('MAIL_SCHEME'),
            'url'          => env('MAIL_URL'),
            'host'         => env('MAIL_HOST', '127.0.0.1'),
            'port'         => env('MAIL_PORT', 25),
            'username'     => env('MAIL_USERNAME'),
            'password'     => env('MAIL_PASSWORD'),
            'encryption'   => env('MAIL_ENCRYPTION'),
            'timeout'      => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', 'sms.a1techflow.com'),
            'stream'       => [
                'ssl' => [
                    'verify_peer'       => false,
                    'verify_peer_name'  => false,
                    'allow_self_signed' => true,
                ],
            ],
        ],

        'log' => [
            'transport' => 'log',
            'channel'   => env('MAIL_LOG_CHANNEL'),
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers'   => ['smtp', 'log'],
        ],
    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'no-reply@sms.a1techflow.com'),
        'name'    => env('MAIL_FROM_NAME',    'PCIT SMS Gateway'),
    ],

    'markdown' => [
        'theme' => 'default',
        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],
];
