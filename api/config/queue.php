<?php

return [
    'default' => env('QUEUE_CONNECTION', 'redis'),

    'connections' => [
        'sync'     => ['driver' => 'sync'],
        'database' => ['driver' => 'database', 'table' => 'jobs', 'queue' => 'default', 'retry_after' => 90],
        'redis'    => [
            'driver'      => 'redis',
            'connection'  => 'default',
            'queue'       => env('REDIS_QUEUE', 'default'),
            'retry_after' => 90,
            'block_for'   => null,
        ],
        'rabbitmq' => [
            'driver' => 'rabbitmq',
            'queue'  => 'default',
            'connection' => PhpAmqpLib\Connection\AMQPLazyConnection::class,
            'hosts' => [[
                'host'     => env('RABBITMQ_HOST', 'rabbitmq'),
                'port'     => env('RABBITMQ_PORT', 5672),
                'user'     => env('RABBITMQ_USER', 'a1sms'),
                'password' => env('RABBITMQ_PASSWORD', ''),
                'vhost'    => env('RABBITMQ_VHOST', '/'),
            ]],
            'options' => [
                'ssl_options'    => [],
                'queue' => [
                    'job' => VladimirYuldashev\LaravelQueueRabbitMQ\Queue\Jobs\RabbitMQJob::class,
                ],
            ],
            'worker' => env('RABBITMQ_WORKER', 'default'),
        ],
    ],

    'batching' => [
        'database' => env('DB_CONNECTION', 'pgsql'),
        'table'    => 'job_batches',
    ],

    'failed' => [
        'driver'   => 'database-uuids',
        'database' => env('DB_CONNECTION', 'pgsql'),
        'table'    => 'failed_jobs',
    ],
];
