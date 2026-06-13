<?php

namespace App\Models;

use App\Domain\Sms\Concerns\BelongsToTeam;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'current_team_id',
        'locale', 'avatar_url', 'is_admin',
    ];

    protected $hidden = [
        'password', 'remember_token',
        'two_factor_recovery_codes', 'two_factor_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'        => 'datetime',
            'two_factor_confirmed_at'  => 'datetime',
            'suspended_at'             => 'datetime',
            'last_login_at'            => 'datetime',
            'is_admin'                 => 'boolean',
            'password'                 => 'hashed',
            'two_factor_secret'        => 'encrypted',
            'two_factor_recovery_codes'=> 'encrypted',
        ];
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)->withPivot('role')->withTimestamps();
    }

    public function currentTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'current_team_id');
    }

    public function ownedTeams()
    {
        return $this->hasMany(Team::class, 'owner_id');
    }

    public function isSuspended(): bool
    {
        return ! is_null($this->suspended_at);
    }

    public function hasTwoFactor(): bool
    {
        return ! is_null($this->two_factor_confirmed_at);
    }

    public function ownsTeam(Team $team): bool
    {
        return $this->id === $team->owner_id;
    }
}
