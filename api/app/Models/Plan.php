<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug', 'name', 'price_ore', 'currency', 'interval',
        'stripe_price_id', 'sms_included', 'rate_per_minute',
        'features', 'is_public', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'features'  => 'array',
            'is_public' => 'boolean',
        ];
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function priceDkk(): float
    {
        return round($this->price_ore / 100, 2);
    }
}
