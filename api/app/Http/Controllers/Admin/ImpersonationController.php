<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function start(Request $r, User $user)
    {
        $admin = $r->user();
        if ($admin->id === $user->id) {
            return response()->json(['title' => 'Cannot impersonate self', 'status' => 422], 422);
        }
        $token = $user->createToken('impersonation:by:'.$admin->id, ['*'], now()->addHour());
        AuditLog::create([
            'user_id' => $admin->id, 'action' => 'user.impersonation.started',
            'subject_type' => User::class, 'subject_id' => $user->id,
            'occurred_at' => now(), 'ip_address' => $r->ip(),
            'payload' => ['target_email' => $user->email],
        ]);
        return response()->json(['token' => $token->plainTextToken, 'expires_at' => $token->accessToken->expires_at]);
    }

    public function stop(Request $r)
    {
        $r->user()->currentAccessToken()?->delete();
        AuditLog::create([
            'user_id' => $r->user()->id, 'action' => 'user.impersonation.stopped',
            'occurred_at' => now(), 'ip_address' => $r->ip(), 'payload' => [],
        ]);
        return response()->noContent();
    }
}
