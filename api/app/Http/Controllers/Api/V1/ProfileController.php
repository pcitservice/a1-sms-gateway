<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

class ProfileController extends Controller
{
    public function update(Request $r)
    {
        $user = $r->user();
        $data = $r->validate([
            'name'   => 'sometimes|string|max:120',
            'phone'  => 'nullable|string|max:32',
            'locale' => 'sometimes|string|size:2',
        ]);
        $user->update($data);
        return response()->json($user);
    }

    public function changePassword(Request $r)
    {
        $data = $r->validate([
            'current_password' => 'required|string',
            'new_password'     => ['required', 'confirmed', PasswordRule::min(10)->letters()->numbers()],
        ]);
        $user = $r->user();
        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json(['title' => 'Current password is incorrect', 'status' => 422], 422);
        }
        $user->forceFill(['password' => Hash::make($data['new_password'])])->save();
        // Revoke all other tokens; current request's token remains.
        $current = $user->currentAccessToken();
        $user->tokens()->where('id', '!=', $current?->id)->delete();
        return response()->noContent();
    }

    public function team(Request $r)
    {
        $team = $r->user()->currentTeam;
        $data = $r->validate([
            'name'       => 'sometimes|string|max:120',
            'country'    => 'sometimes|nullable|string|size:2',
            'vat_number' => 'sometimes|nullable|string|max:32',
            'timezone'   => 'sometimes|string|max:64',
        ]);
        $team->update($data);
        return response()->json($team);
    }

    public function export(Request $r)
    {
        // GDPR data export: everything we hold for this user/team.
        $user = $r->user()->load('teams');
        $team = $user->currentTeam;
        $payload = [
            'exported_at' => now()->toIso8601String(),
            'user'        => $user->only('id', 'name', 'email', 'phone', 'locale', 'created_at'),
            'teams'       => $user->teams->map->only('id', 'name', 'created_at'),
            'contacts'    => $team ? $team->contacts()->withoutGlobalScope(\App\Domain\Sms\Scopes\CurrentTeamScope::class)->get(['msisdn', 'first_name', 'last_name', 'email']) : [],
            'messages'    => $team ? $team->messages()->withoutGlobalScope(\App\Domain\Sms\Scopes\CurrentTeamScope::class)->latest()->limit(5000)->get(['id', 'direction', 'from', 'to', 'body', 'status', 'created_at']) : [],
        ];
        return response()->json($payload, 200, [
            'Content-Disposition' => 'attachment; filename="a1sms-export.json"',
        ]);
    }
}
