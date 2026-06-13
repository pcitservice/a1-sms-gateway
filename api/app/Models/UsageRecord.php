<?php

namespace App\Models;

use App\Domain\Sms\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Model;

class UsageRecord extends Model
{
    use BelongsToTeam;

    protected $fillable = [
        'team_id', 'period', 'messages_sent', 'messages_delivered',
        'messages_failed', 'messages_received', 'segments_billed',
        'reported_to_stripe',
    ];

    protected $casts = ['period' => 'date', 'reported_to_stripe' => 'boolean'];
}
