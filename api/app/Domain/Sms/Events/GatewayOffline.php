<?php

namespace App\Domain\Sms\Events;

use App\Models\Gateway;
use Illuminate\Foundation\Events\Dispatchable;

class GatewayOffline
{
    use Dispatchable;

    public function __construct(public Gateway $gateway) {}
}
