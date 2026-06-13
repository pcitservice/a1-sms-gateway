<?php

namespace App\Models;

use App\Domain\Sms\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Model;

class Automation extends Model
{
    use BelongsToTeam;

    protected $fillable = [
        'team_id', 'name', 'is_active', 'trigger_type', 'trigger_config',
        'actions', 'execution_count', 'last_run_at',
    ];

    protected function casts(): array
    {
        return [
            'is_active'      => 'boolean',
            'trigger_config' => 'array',
            'actions'        => 'array',
            'last_run_at'    => 'datetime',
        ];
    }
}
