<?php

namespace App\Models;

use App\Domain\Sms\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Webhook extends Model
{
    use BelongsToTeam;

    protected $fillable = ['team_id', 'url', 'events', 'secret', 'is_active'];
    protected $casts    = ['events' => 'array', 'is_active' => 'boolean'];
    protected $hidden   = ['secret'];

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    public function subscribesTo(string $event): bool
    {
        return in_array('*', $this->events ?? []) || in_array($event, $this->events ?? []);
    }
}
