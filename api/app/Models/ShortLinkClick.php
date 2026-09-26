<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShortLinkClick extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'short_link_id', 'ip', 'user_agent', 'referrer', 'clicked_at',
    ];

    protected $casts = [
        'clicked_at' => 'datetime',
    ];

    public function link(): BelongsTo
    {
        return $this->belongsTo(ShortLink::class, 'short_link_id');
    }
}
