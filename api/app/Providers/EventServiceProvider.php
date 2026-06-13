<?php

namespace App\Providers;

use App\Domain\Sms\Events\MessageDelivered;
use App\Domain\Sms\Events\MessageFailed;
use App\Domain\Sms\Events\MessageReceived;
use App\Domain\Sms\Events\MessageSent;
use App\Domain\Sms\Listeners\DispatchWebhookForMessage;
use App\Domain\Sms\Listeners\RecordMessageInAudit;
use App\Domain\Sms\Listeners\RunAutomationsForIncoming;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        MessageSent::class      => [DispatchWebhookForMessage::class, RecordMessageInAudit::class],
        MessageDelivered::class => [DispatchWebhookForMessage::class],
        MessageFailed::class    => [DispatchWebhookForMessage::class],
        MessageReceived::class  => [DispatchWebhookForMessage::class, RunAutomationsForIncoming::class],
    ];

    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
