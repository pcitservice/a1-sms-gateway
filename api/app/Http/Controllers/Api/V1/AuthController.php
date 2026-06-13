<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    public function signup(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:120',
            'email'    => 'required|email|max:160|unique:users,email',
            'password' => ['required', PasswordRule::min(10)->letters()->numbers()],
            'team_name' => 'nullable|string|max:120',
            'country'  => 'nullable|string|size:2',
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name'     => $data['name'],
                'email'    => $data['email'],
                'password' => $data['password'],
            ]);

            $freePlan = Plan::where('slug', 'free')->first();
            $teamName = $data['team_name'] ?? $data['name']."'s Workspace";
            $team = Team::create([
                'name'            => $teamName,
                'slug'            => Str::slug($teamName).'-'.Str::lower(Str::random(6)),
                'owner_id'        => $user->id,
                'plan_id'         => $freePlan?->id,
                'country'         => $data['country'] ?? null,
                'trial_ends_at'   => now()->addDays((int) config('sms.trial.days')),
                'trial_sms_used'  => 0,
                'trial_sms_limit' => (int) config('sms.trial.sms_limit'),
            ]);

            $team->users()->attach($user->id, ['role' => 'owner']);
            $user->forceFill(['current_team_id' => $team->id])->save();

            return $user->fresh();
        });

        event(new Registered($user));

        return response()->json([
            'user'  => $user->only('id', 'name', 'email'),
            'team'  => $user->currentTeam,
            'token' => $user->createToken('signup', ['*'])->plainTextToken,
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:80',
        ]);

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['title' => 'Invalid credentials', 'status' => 401], 401);
        }
        if ($user->isSuspended()) {
            return response()->json(['title' => 'Account suspended', 'status' => 403], 403);
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $token = $user->createToken(
            $data['device_name'] ?? 'web',
            ['*'],
        );
        // Stamp the token with the team it operates under.
        $token->accessToken->forceFill(['team_id' => $user->current_team_id])->save();

        return response()->json([
            'user'  => $user->only('id', 'name', 'email', 'current_team_id', 'is_admin'),
            'token' => $token->plainTextToken,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->noContent();
    }

    public function me(Request $request)
    {
        $user = $request->user()->load('currentTeam.plan');
        return response()->json($user);
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate(['email' => 'required|email']);
        $status = Password::sendResetLink($data);
        return response()->json(['status' => __($status)]);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'token'    => 'required|string',
            'password' => ['required', 'confirmed', PasswordRule::min(10)],
        ]);
        $status = Password::reset($data, function ($user, $password) {
            $user->forceFill(['password' => Hash::make($password)])->save();
        });
        return response()->json(['status' => __($status)]);
    }

    public function verifyEmail(Request $request, int $id, string $hash)
    {
        $user = User::findOrFail($id);
        if (! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            abort(403);
        }
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
        }
        return response()->json(['status' => 'verified']);
    }

    public function listTokens(Request $request)
    {
        return response()->json($request->user()->tokens()->latest()->get());
    }

    public function issueToken(Request $request)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:80',
            'abilities' => 'array',
            'expires_in_days' => 'nullable|integer|min:1|max:730',
        ]);
        $abilities = $data['abilities'] ?? ['sms:send', 'sms:read', 'contacts:read'];
        $expires   = isset($data['expires_in_days']) ? now()->addDays($data['expires_in_days']) : null;

        $token = $request->user()->createToken($data['name'], $abilities, $expires);
        $token->accessToken->forceFill(['team_id' => app('current_team')->id])->save();

        return response()->json([
            'id'    => $token->accessToken->id,
            'name'  => $token->accessToken->name,
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at,
        ], 201);
    }

    public function revokeToken(Request $request, int $id)
    {
        $request->user()->tokens()->where('id', $id)->delete();
        return response()->noContent();
    }
}
