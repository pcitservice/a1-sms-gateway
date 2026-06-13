<?php

namespace App\Domain\Sms\Jobs;

use App\Domain\Gateway\DTO\OutgoingMessage;
use App\Domain\Gateway\GatewayManager;
use App\Domain\Sms\Events\MessageFailed;
use App\Domain\Sms\Events\MessageSent;
use App\Models\SmsMessage;
use App\Models\SmsMessageEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 8;
    public int $timeout = 60;

    public function __construct(public string $messageId) {}

    public function backoff(): array
    {
        return [5, 15, 30, 60, 120, 300, 600, 1800];
    }

    public function handle(GatewayManager $manager): void
    {
        $message = SmsMessage::query()->withoutGlobalScopes()->findOrFail($this->messageId);

        if (in_array($message->status, ['sent', 'delivered'], true)) {
            return;
        }

        $row = $message->gateway_id
            ? \App\Models\Gateway::query()->findOrFail($message->gateway_id)
            : (function () use ($message, $manager) {
                // Resolve via team — bind it for the manager's primary lookup.
                app()->instance('current_team', $message->team);
                return $manager->primaryForCurrentTeam();
            })();

        $driver = $row instanceof \App\Domain\Gateway\Contracts\SmsGateway
            ? $row
            : $manager->driver($row);

        $message->forceFill([
            'gateway_id' => $driver->id(),
            'status'     => 'sending',
        ])->save();

        SmsMessageEvent::create([
            'message_id'  => $message->id,
            'type'        => 'attempted',
            'payload'     => ['attempt' => $this->attempts()],
            'occurred_at' => now(),
        ]);

        $result = $driver->send(new OutgoingMessage(
            id:       $message->id,
            to:       $message->to,
            body:     $message->body,
            from:     $message->from,
            modemId:  $message->metadata['modem_id'] ?? null,
            metadata: $message->metadata ?? [],
        ));

        if ($result->ok) {
            $message->forceFill([
                'status'      => 'sent',
                'provider_id' => $result->providerId,
                'segments'    => $result->segments,
                'sent_at'     => now(),
            ])->save();

            SmsMessageEvent::create([
                'message_id'  => $message->id,
                'type'        => 'sent',
                'payload'     => ['provider_id' => $result->providerId],
                'occurred_at' => now(),
            ]);

            event(new MessageSent($message->fresh()));
            return;
        }

        // Transient vs. terminal: anything tagged 'transport' or 'unknown'
        // is retried; everything else is dead-lettered.
        $transient = in_array($result->errorCode, ['transport', 'unknown', 'temporary'], true);
        if ($transient && $this->attempts() < $this->tries) {
            $message->forceFill([
                'status'        => 'queued',
                'error_code'    => $result->errorCode,
                'error_message' => $result->errorMessage,
            ])->save();
            $this->release($this->backoff()[min($this->attempts() - 1, count($this->backoff()) - 1)]);
            return;
        }

        $message->forceFill([
            'status'        => 'failed',
            'error_code'    => $result->errorCode,
            'error_message' => $result->errorMessage,
            'failed_at'     => now(),
        ])->save();

        SmsMessageEvent::create([
            'message_id'  => $message->id,
            'type'        => 'failed',
            'payload'     => ['code' => $result->errorCode, 'message' => $result->errorMessage],
            'occurred_at' => now(),
        ]);

        event(new MessageFailed($message->fresh()));
    }
}
