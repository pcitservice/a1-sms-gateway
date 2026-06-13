<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmsMessageEvent extends Model
{
    protected $fillable = ['message_id', 'type', 'payload', 'occurred_at'];

    protected function casts(): array
    {
        return [
            'payload'     => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(SmsMessage::class, 'message_id');
    }
}
