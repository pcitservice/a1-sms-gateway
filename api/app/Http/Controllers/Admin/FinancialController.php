<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

class FinancialController extends Controller
{
    public function dashboard()
    {
        return response()->json([
            'mrr_ore'      => $this->mrrOre(),
            'arr_ore'      => $this->mrrOre() * 12,
            'active_subs'  => Subscription::query()->where('stripe_status', 'active')->count(),
            'active_trials'=> Team::query()->whereNotNull('trial_ends_at')->where('trial_ends_at', '>', now())->count(),
            'churn_30d'    => $this->churn30Day(),
        ]);
    }

    public function mrr()
    {
        return response()->json(['mrr_ore' => $this->mrrOre()]);
    }

    public function churn()
    {
        return response()->json(['churn_30d' => $this->churn30Day()]);
    }

    private function mrrOre(): int
    {
        // Sum included monthly plan prices across teams whose Cashier subscription is active.
        return (int) DB::table('teams')
            ->join('plans', 'plans.id', '=', 'teams.plan_id')
            ->join('subscriptions', 'subscriptions.team_id', '=', 'teams.id')
            ->where('subscriptions.stripe_status', 'active')
            ->where('plans.interval', 'month')
            ->sum('plans.price_ore');
    }

    private function churn30Day(): float
    {
        $end = DB::table('subscriptions')->where('stripe_status', 'canceled')
            ->where('ends_at', '>=', now()->subDays(30))->count();
        $base = DB::table('subscriptions')->where('stripe_status', 'active')->count();
        return $base > 0 ? round($end / max(1, $base + $end) * 100, 2) : 0.0;
    }
}
