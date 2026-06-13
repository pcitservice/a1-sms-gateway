<?php

namespace App\Domain\Sms\Listeners;

use App\Domain\Sms\Events\MessageDelivered;
use App\Domain\Sms\Events\MessageFailed;
use App\Domain\Sms\Events\MessageReceived;
use App\Domain\Sms\Events\MessageSent;
use App\Domain\Webhooks\WebhookDispatcher;

class DispatchWebhookForMessage
{
    public function __construct(protected WebhookDispatcher $dispatcher) {}

    public function handle(MessageSent|MessageDelivered|MessageFailed|MessageReceived $event): void
    {
        $eventName = match (true) {
            $event instanceof MessageSent      => 'message.sent',
            $event instanceof MessageDelivered => 'message.delivered',
            $event instanceof MessageFailed    => 'message.failed',
            $event instanceof MessageReceived  => 'message.received',
        };
        if (! $event->message->team_id) {
            return;
        }
        $this->dispatcher->dispatch(
            teamId:  $event->message->team_id,
            event:   $eventName,
            payload: [
                'message_id'   => $event->message->id,
                'direction'    => $event->message->direction,
                'from'         => $event->message->from,
                'to'           => $event->message->to,
                'body'         => $event->message->body,
                'status'       => $event->message->status,
                'error_code'   => $event->message->error_code,
                'sent_at'      => optional($event->message->sent_at)?->toIso8601String(),
                'delivered_at' => optional($event->message->delivered_at)?->toIso8601String(),
                'received_at'  => optional($event->message->received_at)?->toIso8601String(),
                'segments'     => $event->message->segments,
            ],
        );
    }
}
