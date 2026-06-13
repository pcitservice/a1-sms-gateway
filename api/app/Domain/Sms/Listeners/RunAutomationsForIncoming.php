<?php

namespace App\Domain\Sms\Listeners;

use App\Domain\Automation\AutomationEngine;
use App\Domain\Sms\Events\MessageReceived;

class RunAutomationsForIncoming
{
    public function __construct(protected AutomationEngine $engine) {}

    public function handle(MessageReceived $event): void
    {
        $this->engine->runForIncoming($event->message);
    }
}
