<?php

namespace App\Models;

use App\Domain\Sms\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    use BelongsToTeam, HasUlids;

    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'team_id', 'user_id', 'name', 'status', 'body',
        'targets', 'timezone', 'scheduled_at', 'recurrence',
        'started_at', 'completed_at', 'total_recipients',
        'sent_count', 'failed_count',
    ];

    protected function casts(): array
    {
        return [
            'targets'      => 'array',
            'scheduled_at' => 'datetime',
            'started_at'   => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(CampaignMessage::class);
    }
}
