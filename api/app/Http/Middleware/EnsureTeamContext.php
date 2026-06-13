<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class EnsureTeamContext
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (! $user) {
            throw new HttpException(401, 'Unauthenticated');
        }

        // API tokens carry the team_id they were issued under; session-based
        // requests fall back to the user's currentTeam.
        $teamId = null;
        if ($token = $user->currentAccessToken()) {
            $teamId = $token->team_id;
        }
        $team = $teamId
            ? \App\Models\Team::query()->find($teamId)
            : $user->currentTeam;

        if (! $team) {
            throw new HttpException(403, 'No team context for this request.');
        }
        if ($team->isSuspended()) {
            throw new HttpException(403, 'Team is suspended.');
        }

        app()->instance('current_team', $team);
        $request->attributes->set('current_team', $team);

        return $next($request);
    }
}
