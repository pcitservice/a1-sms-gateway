<?php

namespace App\Domain\Gateway\DTO;

final class GatewayHealth
{
    public function __construct(
        public readonly bool    $reachable,
        public readonly ?string $connectionState = null,   // connected | connecting | disconnected
        public readonly ?int    $signalRssi      = null,
        public readonly ?int    $signalRsrp      = null,
        public readonly ?string $operator        = null,
        public readonly ?string $lteBand         = null,
        public readonly ?string $simStatus       = null,   // ready | absent | pin | error
        public readonly ?string $imei            = null,
        public readonly ?int    $uptimeSeconds   = null,
        public readonly array   $raw             = [],
    ) {}

    public function toArray(): array
    {
        return [
            'reachable'        => $this->reachable,
            'connection_state' => $this->connectionState,
            'signal_rssi'      => $this->signalRssi,
            'signal_rsrp'      => $this->signalRsrp,
            'operator'         => $this->operator,
            'lte_band'         => $this->lteBand,
            'sim_status'       => $this->simStatus,
            'imei'             => $this->imei,
            'uptime_seconds'   => $this->uptimeSeconds,
        ];
    }
}
