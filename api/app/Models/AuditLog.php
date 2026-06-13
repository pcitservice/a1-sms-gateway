<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'team_id', 'user_id', 'action', 'subject_type', 'subject_id',
        'payload', 'ip_address', 'user_agent', 'occurred_at',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array', 'occurred_at' => 'datetime'];
    }
}
