<?php

namespace App\Domain\Webhooks;

use App\Domain\Webhooks\Jobs\DeliverWebhookJob;
use App\Models\Webhook;
use App\Models\WebhookDelivery;

class WebhookDispatcher
{
    /**
     * Fan out a single event to every webhook subscribed to it on the team.
     */
    public function dispatch(int $teamId, string $event, array $payload): void
    {
        $hooks = Webhook::query()->where('team_id', $teamId)
            ->where('is_active', true)
            ->get()
            ->filter(fn (Webhook $h) => $h->subscribesTo($event));

        foreach ($hooks as $hook) {
            $delivery = WebhookDelivery::create([
                'webhook_id'   => $hook->id,
                'event'        => $event,
                'payload'      => $payload,
                'attempt'      => 1,
                'status'       => 'pending',
                'scheduled_at' => now(),
            ]);
            DeliverWebhookJob::dispatch($delivery->id)->onQueue('webhooks');
        }
    }
}
