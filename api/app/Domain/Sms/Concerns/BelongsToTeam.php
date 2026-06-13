<?php

namespace App\Domain\Sms\Concerns;

use App\Domain\Sms\Scopes\CurrentTeamScope;
use Illuminate\Database\Eloquent\Model;

/**
 * Adds a global scope that filters queries to the current team set by the
 * `EnsureTeamContext` middleware. The scope is intentionally inert when no
 * current team is bound (e.g. CLI commands and admin tools).
 */
trait BelongsToTeam
{
    protected static function bootBelongsToTeam(): void
    {
        static::addGlobalScope(new CurrentTeamScope);

        static::creating(function (Model $model) {
            if (empty($model->team_id) && $team = app()->bound('current_team') ? app('current_team') : null) {
                $model->team_id = $team->id;
            }
        });
    }
}
