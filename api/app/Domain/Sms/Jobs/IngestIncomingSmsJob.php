<?php

namespace App\Domain\Sms\Jobs;

use App\Domain\Sms\Events\MessageReceived;
use App\Models\Contact;
use App\Models\Gateway;
use App\Models\SmsMessage;
use App\Models\SmsMessageEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class IngestIncomingSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int    $gatewayId,
        public string $providerId,
        public string $from,
        public string $to,
        public string $body,
        public string $receivedAtIso,
        public array  $metadata = [],
    ) {}

    public function handle(): void
    {
        $gateway = Gateway::query()->find($this->gatewayId);
        if (! $gateway) {
            return;
        }

        // Idempotency: ignore if we've already ingested this provider ID.
        $existing = SmsMessage::query()->withoutGlobalScopes()
            ->where('gateway_id', $this->gatewayId)
            ->where('provider_id', $this->providerId)
            ->first();
        if ($existing) {
            return;
        }

        $teamId = $gateway->team_id;
        // Auto-resolve to a contact (for thread building) but don't create.
        $contact = $teamId
            ? Contact::query()->withoutGlobalScopes()
                ->where('team_id', $teamId)->where('msisdn', $this->from)->first()
            : null;

        $message = SmsMessage::create([
            'id'           => (string) Str::ulid(),
            'team_id'      => $teamId,
            'gateway_id'   => $this->gatewayId,
            'direction'    => 'inbound',
            'from'         => $this->from,
            'to'           => $this->to,
            'body'         => $this->body,
            'status'       => 'received',
            'provider_id'  => $this->providerId,
            'metadata'     => array_merge($this->metadata, ['contact_id' => $contact?->id]),
            'received_at'  => $this->receivedAtIso,
        ]);

        SmsMessageEvent::create([
            'message_id'  => $message->id,
            'type'        => 'received',
            'payload'     => ['from' => $this->from],
            'occurred_at' => $this->receivedAtIso,
        ]);

        event(new MessageReceived($message));
    }
}
