<?php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $fillable = [
        'tokenable_id', 'tokenable_type', 'team_id',
        'name', 'token', 'abilities', 'expires_at',
        'last_used_ip',
    ];
}
