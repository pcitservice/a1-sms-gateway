<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Gateway extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'team_id', 'name', 'kind', 'host', 'port', 'protocol',
        'username', 'password', 'modem_id', 'ssh_enabled', 'ssh_key_ref',
        'rate_per_minute', 'daily_cap', 'status', 'health', 'last_seen_at',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'password'      => 'encrypted',
            'ssh_enabled'   => 'boolean',
            'is_primary'    => 'boolean',
            'health'        => 'array',
            'last_seen_at'  => 'datetime',
        ];
    }

    public function team(): BelongsTo { return $this->belongsTo(Team::class); }
    public function sims(): HasMany   { return $this->hasMany(Sim::class); }
    public function messages(): HasMany { return $this->hasMany(SmsMessage::class); }

    public function isOnline(): bool { return $this->status === 'online'; }
    public function isOffline(): bool { return $this->status === 'offline'; }
}
