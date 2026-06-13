<?php

namespace App\Policies;

use App\Models\Gateway;
use App\Models\User;

class GatewayPolicy
{
    public function view(User $user, Gateway $gateway): bool
    {
        if ($user->is_admin) return true;
        return $gateway->team_id === $user->current_team_id;
    }

    public function manage(User $user, Gateway $gateway): bool
    {
        if ($user->is_admin) return true;
        return $gateway->team_id === $user->current_team_id && $user->ownsTeam($user->currentTeam);
    }
}
