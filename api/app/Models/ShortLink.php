<?php

namespace App\Models;

use App\Domain\Sms\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShortLink extends Model
{
    use BelongsToTeam;

    protected $fillable = [
        'code', 'team_id', 'message_id', 'campaign_id', 'target_url',
        'click_count', 'last_clicked_at',
    ];

    protected $casts = [
        'last_clicked_at' => 'datetime',
    ];

    public function clicks(): HasMany
    {
        return $this->hasMany(ShortLinkClick::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(SmsMessage::class, 'message_id');
    }
}
