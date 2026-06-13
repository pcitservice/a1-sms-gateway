<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('team.{teamId}', function ($user, int $teamId) {
    return (int) $user->current_team_id === (int) $teamId;
});
