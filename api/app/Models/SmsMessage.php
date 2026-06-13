<?php

namespace App\Models;

use App\Domain\Sms\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmsMessage extends Model
{
    use BelongsToTeam, HasFactory, HasUlids;

    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id', 'team_id', 'user_id', 'gateway_id', 'sim_id',
        'batch_id', 'campaign_id',
        'direction', 'from', 'to', 'body', 'segments', 'encoding',
        'status', 'provider_id', 'error_code', 'error_message',
        'metadata', 'cost_ore',
        'queued_at', 'sent_at', 'delivered_at', 'failed_at', 'received_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata'     => 'array',
            'queued_at'    => 'datetime',
            'sent_at'      => 'datetime',
            'delivered_at' => 'datetime',
            'failed_at'    => 'datetime',
            'received_at'  => 'datetime',
        ];
    }

    public function team(): BelongsTo    { return $this->belongsTo(Team::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function gateway(): BelongsTo { return $this->belongsTo(Gateway::class); }
    public function sim(): BelongsTo     { return $this->belongsTo(Sim::class); }

    public function events(): HasMany
    {
        return $this->hasMany(SmsMessageEvent::class, 'message_id')->orderBy('occurred_at');
    }
}
