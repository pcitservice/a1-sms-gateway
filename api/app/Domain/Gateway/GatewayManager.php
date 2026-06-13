<?php

namespace App\Domain\Gateway;

use App\Domain\Gateway\Contracts\SmsGateway;
use App\Domain\Gateway\Exceptions\GatewayException;
use App\Models\Gateway;
use Closure;
use Illuminate\Contracts\Container\Container;

class GatewayManager
{
    /** @var array<string, Closure> */
    protected array $factories = [];

    /** @var array<int, SmsGateway> */
    protected array $instances = [];

    public function __construct(protected Container $app) {}

    public function extend(string $kind, Closure $factory): void
    {
        $this->factories[$kind] = $factory;
    }

    public function driver(Gateway $row): SmsGateway
    {
        if (isset($this->instances[$row->id])) {
            return $this->instances[$row->id];
        }
        $factory = $this->factories[$row->kind] ?? null;
        if (! $factory) {
            throw new GatewayException("No driver registered for kind '{$row->kind}'");
        }
        return $this->instances[$row->id] = $factory($row);
    }

    /**
     * Pick the gateway that should send the next outbound message for the
     * current team. Prefers the team's primary, falls back to the platform
     * pool (team_id NULL), and skips offline rows.
     */
    public function primaryForCurrentTeam(): SmsGateway
    {
        $teamId = optional(app()->bound('current_team') ? app('current_team') : null)->id;

        $query = Gateway::query()
            ->where('status', 'online')
            ->orderByDesc('is_primary');

        if ($teamId) {
            $query->where(function ($q) use ($teamId) {
                $q->where('team_id', $teamId)->orWhereNull('team_id');
            })->orderByRaw('CASE WHEN team_id IS NULL THEN 1 ELSE 0 END');
        } else {
            $query->whereNull('team_id');
        }

        $row = $query->first();
        if (! $row) {
            throw new GatewayException('No gateway available to route this message.');
        }
        return $this->driver($row);
    }

    /** @return iterable<Gateway> */
    public function activeRows(): iterable
    {
        return Gateway::query()->whereNotNull('host')->orWhere('kind', 'mock')->get();
    }

    public function forgetCached(int $gatewayId): void
    {
        unset($this->instances[$gatewayId]);
    }
}
