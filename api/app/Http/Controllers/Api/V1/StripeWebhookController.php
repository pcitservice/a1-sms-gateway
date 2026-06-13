<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Billing\InvoiceGenerator;
use App\Models\Team;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;

class StripeWebhookController extends CashierWebhookController
{
    public function handleInvoicePaid(array $payload)
    {
        $stripeInvoiceId = $payload['data']['object']['id'] ?? null;
        $stripeCustomer  = $payload['data']['object']['customer'] ?? null;
        if (! $stripeInvoiceId || ! $stripeCustomer) {
            return $this->successMethod();
        }
        $team = Team::query()->where('stripe_id', $stripeCustomer)->first();
        if ($team) {
            app(InvoiceGenerator::class)->fromStripe($team, $payload['data']['object']);
        }
        return $this->successMethod();
    }

    public function handleCustomerSubscriptionDeleted(array $payload)
    {
        $stripeCustomer = $payload['data']['object']['customer'] ?? null;
        $team = Team::query()->where('stripe_id', $stripeCustomer)->first();
        if ($team) {
            $team->update(['plan_id' => \App\Models\Plan::where('slug', 'free')->value('id')]);
        }
        return parent::handleCustomerSubscriptionDeleted($payload);
    }
}
