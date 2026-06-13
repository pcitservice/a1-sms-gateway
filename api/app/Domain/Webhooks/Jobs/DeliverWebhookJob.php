<?php

namespace App\Domain\Webhooks\Jobs;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeliverWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 8;
    public int $timeout = 30;

    public function __construct(public int $deliveryId) {}

    public function backoff(): array
    {
        return config('sms.webhook.backoff');
    }

    public function handle(Client $http): void
    {
        $delivery = WebhookDelivery::findOrFail($this->deliveryId);
        $hook     = Webhook::find($delivery->webhook_id);
        if (! $hook || ! $hook->is_active) {
            $delivery->update(['status' => 'giving_up', 'response_excerpt' => 'webhook deactivated']);
            return;
        }

        $body  = json_encode([
            'id'      => 'evt_'.bin2hex(random_bytes(8)),
            'event'   => $delivery->event,
            'created' => now()->toIso8601String(),
            'data'    => $delivery->payload,
        ], JSON_UNESCAPED_SLASHES);

        $ts    = time();
        $sig   = hash_hmac('sha256', "{$ts}.{$body}", $hook->secret);

        try {
            $resp = $http->post($hook->url, [
                'headers' => [
                    'Content-Type'        => 'application/json',
                    'User-Agent'          => 'A1SMS-Webhook/1.0',
                    'X-A1Sms-Event'       => $delivery->event,
                    'X-A1Sms-Signature'   => "t={$ts},v1={$sig}",
                ],
                'body'    => $body,
                'timeout' => (int) config('sms.webhook.timeout'),
                'http_errors' => false,
            ]);
            $code = $resp->getStatusCode();
            $ok   = $code >= 200 && $code < 300;

            $delivery->update([
                'http_status'      => $code,
                'response_excerpt' => substr((string) $resp->getBody(), 0, 400),
                'status'           => $ok ? 'success' : 'failed',
                'delivered_at'     => $ok ? now() : null,
            ]);

            if (! $ok) {
                $this->maybeRetry($delivery, $hook);
            } else {
                $hook->update(['failure_count' => 0]);
            }
        } catch (\Throwable $e) {
            $delivery->update([
                'status'           => 'failed',
                'response_excerpt' => substr($e->getMessage(), 0, 400),
            ]);
            $this->maybeRetry($delivery, $hook);
        }
    }

    private function maybeRetry(WebhookDelivery $delivery, Webhook $hook): void
    {
        $attempt = $delivery->attempt + 1;
        $max     = (int) config('sms.webhook.attempts');
        if ($attempt > $max) {
            $delivery->update(['status' => 'giving_up']);
            $hook->increment('failure_count');
            if ($hook->failure_count >= 25) {
                $hook->update(['is_active' => false, 'disabled_at' => now()]);
            }
            return;
        }
        $next = WebhookDelivery::create([
            'webhook_id'   => $hook->id,
            'event'        => $delivery->event,
            'payload'      => $delivery->payload,
            'attempt'      => $attempt,
            'status'       => 'pending',
            'scheduled_at' => now()->addSeconds($this->backoff()[$attempt - 1] ?? 3600),
        ]);
        self::dispatch($next->id)->delay($next->scheduled_at)->onQueue('webhooks');
    }
}
