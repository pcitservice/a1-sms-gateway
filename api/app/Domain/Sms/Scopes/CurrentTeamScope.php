<?php

namespace App\Domain\Sms\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class CurrentTeamScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (! app()->bound('current_team')) {
            return;
        }
        $team = app('current_team');
        $builder->where($model->getTable().'.team_id', $team->id);
    }
}
