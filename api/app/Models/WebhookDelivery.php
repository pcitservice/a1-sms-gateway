<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookDelivery extends Model
{
    protected $fillable = [
        'webhook_id', 'event', 'payload', 'attempt',
        'http_status', 'response_excerpt', 'status',
        'scheduled_at', 'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'payload'      => 'array',
            'scheduled_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }
}
