<?php

return [
    'key'    => env('STRIPE_KEY'),
    'secret' => env('STRIPE_SECRET'),
    'path'   => env('CASHIER_PATH', 'stripe'),
    'webhook' => [
        'secret'    => env('STRIPE_WEBHOOK_SECRET'),
        'tolerance' => env('CASHIER_WEBHOOK_TOLERANCE', 300),
    ],
    'currency'        => env('CASHIER_CURRENCY', 'dkk'),
    'currency_locale' => env('CASHIER_CURRENCY_LOCALE', 'da_DK'),
    'payment_notification' => env('CASHIER_PAYMENT_NOTIFICATION'),
    'model'   => env('CASHIER_MODEL', App\Models\Team::class),
    'logger'  => env('CASHIER_LOGGER'),
    'invoices' => [
        'metadata' => [
            'platform' => 'A1 SMS Gateway',
        ],
    ],
];
