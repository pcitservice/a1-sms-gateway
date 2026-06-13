<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $r)
    {
        $q = User::query()->withoutGlobalScopes();
        if ($s = $r->string('q')->toString()) {
            $q->where(fn ($q) => $q->where('email', 'ilike', "%$s%")->orWhere('name', 'ilike', "%$s%"));
        }
        return response()->json($q->latest()->paginate(50));
    }

    public function show(User $user)
    {
        return response()->json($user->load('teams'));
    }

    public function suspend(Request $r, User $user)
    {
        $user->forceFill(['suspended_at' => now()])->save();
        $this->audit($r, 'user.suspended', $user);
        return response()->json($user);
    }

    public function activate(Request $r, User $user)
    {
        $user->forceFill(['suspended_at' => null])->save();
        $this->audit($r, 'user.activated', $user);
        return response()->json($user);
    }

    private function audit(Request $r, string $action, User $user): void
    {
        AuditLog::create([
            'team_id'      => null,
            'user_id'      => $r->user()->id,
            'action'       => $action,
            'subject_type' => User::class,
            'subject_id'   => $user->id,
            'payload'      => [],
            'ip_address'   => $r->ip(),
            'user_agent'   => $r->userAgent(),
            'occurred_at'  => now(),
        ]);
    }
}
