<?php

namespace App\Domain\Sms\Services;

use App\Models\Team;
use App\Models\UsageRecord;

class SmsBilling
{
    public function priceFor(Team $team, int $segments): int
    {
        return (int) config('sms.pricing.per_segment_ore') * $segments;
    }

    public function canAfford(Team $team, int $cost_ore): bool
    {
        if ($team->isSuspended()) {
            return false;
        }
        if ($team->inTrial()) {
            return $team->trialRemaining() >= max(1, intdiv($cost_ore, (int) config('sms.pricing.per_segment_ore')));
        }
        // For subscription-based teams, included quota is enforced at usage
        // reconciliation. The send path itself trusts the active subscription.
        $subscription = $team->subscription();
        return $subscription && $subscription->active();
    }

    public function record(Team $team, int $segments): void
    {
        $row = $this->todaysRow($team);
        $row->increment('messages_sent');
        $row->increment('segments_billed', $segments);

        if ($team->inTrial()) {
            $team->increment('trial_sms_used', 1);
        }
    }

    public function recordDelivery(Team $team, bool $delivered): void
    {
        $row = $this->todaysRow($team);
        $row->increment($delivered ? 'messages_delivered' : 'messages_failed');
    }

    public function recordIncoming(Team $team): void
    {
        $row = $this->todaysRow($team);
        $row->increment('messages_received');
    }

    /**
     * Get-or-create today's UsageRecord for the team. Done as two steps so
     * we can safely use Eloquent's `increment()` rather than a DB::raw
     * column self-reference inside an INSERT (which Postgres rejects).
     */
    private function todaysRow(Team $team): UsageRecord
    {
        return UsageRecord::query()->withoutGlobalScopes()->firstOrCreate(
            ['team_id' => $team->id, 'period' => now()->toDateString()],
            [
                'messages_sent'      => 0,
                'messages_delivered' => 0,
                'messages_failed'    => 0,
                'messages_received'  => 0,
                'segments_billed'    => 0,
            ],
        );
    }
}
