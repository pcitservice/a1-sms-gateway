<?php

return [
    'mailgun'   => ['domain' => env('MAILGUN_DOMAIN'), 'secret' => env('MAILGUN_SECRET'), 'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net')],
    'postmark'  => ['token'  => env('POSTMARK_TOKEN')],
    'ses'       => ['key' => env('AWS_ACCESS_KEY_ID'), 'secret' => env('AWS_SECRET_ACCESS_KEY'), 'region' => env('AWS_DEFAULT_REGION', 'eu-central-1')],
    'turnstile' => ['secret' => env('TURNSTILE_SECRET'), 'site_key' => env('NEXT_PUBLIC_TURNSTILE_SITE_KEY')],
    'stripe'    => ['model' => App\Models\Team::class, 'key' => env('STRIPE_KEY'), 'secret' => env('STRIPE_SECRET'), 'webhook' => ['secret' => env('STRIPE_WEBHOOK_SECRET')]],
];
