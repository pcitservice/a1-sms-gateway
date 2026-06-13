<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sim extends Model
{
    use HasFactory;

    protected $fillable = [
        'gateway_id', 'iccid', 'msisdn', 'imsi', 'carrier', 'country',
        'is_active', 'balance_ore',
    ];

    protected $casts = ['is_active' => 'boolean'];

    public function gateway(): BelongsTo { return $this->belongsTo(Gateway::class); }
}
