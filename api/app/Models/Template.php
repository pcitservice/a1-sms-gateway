<?php

namespace App\Models;

use App\Domain\Sms\Concerns\BelongsToTeam;
use Illuminate\Database\Eloquent\Model;

class Template extends Model
{
    use BelongsToTeam;

    protected $fillable = ['team_id', 'name', 'body', 'variables'];
    protected $casts    = ['variables' => 'array'];
}
