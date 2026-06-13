<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Cashier\Billable;

class Team extends Model
{
    use Billable, HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'owner_id', 'plan_id', 'country', 'vat_number',
        'timezone', 'settings',
        'trial_ends_at', 'trial_sms_used', 'trial_sms_limit',
    ];

    protected function casts(): array
    {
        return [
            'settings'       => 'array',
            'trial_ends_at'  => 'datetime',
            'suspended_at'   => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function gateways(): HasMany
    {
        return $this->hasMany(Gateway::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SmsMessage::class);
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(Webhook::class);
    }

    public function automations(): HasMany
    {
        return $this->hasMany(Automation::class);
    }

    public function inTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function trialRemaining(): int
    {
        return max(0, $this->trial_sms_limit - $this->trial_sms_used);
    }

    public function isSuspended(): bool
    {
        return ! is_null($this->suspended_at);
    }
}
